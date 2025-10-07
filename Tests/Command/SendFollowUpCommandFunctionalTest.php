<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerDoiBundle\DTO\DoiTokenData;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\DoiTokenParser;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;

class SendFollowUpCommandFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private PluginFixtureHelper $pluginFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $this->pluginFixtureHelper->createAndEnablePlugin();
    }

    public function testCommandWithoutLimit(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        $oldSubmission    = $this->createDoiSubmission($form, 'old@example.com', new \DateTime('-25 hours'));
        $recentSubmission = $this->createDoiSubmission($form, 'recent@example.com', new \DateTime('-1 hour'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 1 | Sent: 1', $output);

        $this->em->refresh($oldSubmission);
        $this->em->refresh($recentSubmission);

        Assert::assertNotNull($oldSubmission->getDateFollowupSent());
        Assert::assertSame('pending', $recentSubmission->getStatus());
        Assert::assertNull($recentSubmission->getDateFollowupSent());

        // Verify email was sent with correct DOI token
        $messages = $this->getMailerMessagesByToAddress('old@example.com');
        Assert::assertCount(1, $messages, 'Should have exactly one follow-up email sent');
        $message  = $messages[0];
        $htmlBody = $message->getHtmlBody();

        $doiLinkPattern = '/https?:\/\/[^\/]+\/email\/verify\/([A-Za-z0-9+\/=]+)/';
        preg_match($doiLinkPattern, $htmlBody, $matches);
        Assert::assertNotEmpty($matches, 'DOI link should be present in follow-up email');
        Assert::assertNotEmpty($matches[1], 'DOI token should be present');

        // Use DoiTokenParser to decode and verify the token
        $tokenParser = static::getContainer()->get(DoiTokenParser::class);
        assert($tokenParser instanceof DoiTokenParser);
        $token       = $matches[1];
        $decodedData = $tokenParser->decode($token);

        Assert::assertInstanceOf(DoiTokenData::class, $decodedData, 'DOI token should be decodable');
        $tokenFormId = $decodedData->formId;
        $tokenHash   = $decodedData->hash;
        Assert::assertSame($form->getId(), $tokenFormId, 'Form ID in token should match expected');
        Assert::assertSame($oldSubmission->getHash(), $tokenHash, 'DOI hash in token should match submission hash');
    }

    public function testDifferentContactsGetIndividualLinks(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        $submission1 = $this->createDoiSubmission($form, 'contact1@example.com', new \DateTime('-25 hours'));
        $submission2 = $this->createDoiSubmission($form, 'contact2@example.com', new \DateTime('-26 hours'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 2 | Sent: 2', $output);

        $this->em->refresh($submission1);
        $this->em->refresh($submission2);

        Assert::assertNotNull($submission1->getDateFollowupSent());
        Assert::assertNotNull($submission2->getDateFollowupSent());

        // Verify both contacts received emails
        $messages1 = $this->getMailerMessagesByToAddress('contact1@example.com');
        $messages2 = $this->getMailerMessagesByToAddress('contact2@example.com');
        Assert::assertCount(1, $messages1, 'Contact 1 should have exactly one follow-up email');
        Assert::assertCount(1, $messages2, 'Contact 2 should have exactly one follow-up email');

        // Extract DOI tokens from both emails
        $doiLinkPattern = '/https?:\/\/[^\/]+\/email\/verify\/([A-Za-z0-9+\/=]+)/';

        $htmlBody1 = $messages1[0]->getHtmlBody();
        preg_match($doiLinkPattern, $htmlBody1, $matches1);
        Assert::assertNotEmpty($matches1, 'DOI link should be present in contact 1 email');
        $token1 = $matches1[1];

        $htmlBody2 = $messages2[0]->getHtmlBody();
        preg_match($doiLinkPattern, $htmlBody2, $matches2);
        Assert::assertNotEmpty($matches2, 'DOI link should be present in contact 2 email');
        $token2 = $matches2[1];

        // Verify tokens are different
        Assert::assertNotSame($token1, $token2, 'Each contact should have a unique DOI token');

        // Verify each token contains the correct submission hash
        $tokenParser = static::getContainer()->get(DoiTokenParser::class);
        assert($tokenParser instanceof DoiTokenParser);

        $decodedData1 = $tokenParser->decode($token1);
        Assert::assertInstanceOf(DoiTokenData::class, $decodedData1, 'Contact 1 DOI token should be decodable');
        $tokenFormId1 = $decodedData1->formId;
        $tokenHash1   = $decodedData1->hash;

        Assert::assertSame($form->getId(), $tokenFormId1, 'Form ID in token 1 should match expected');
        Assert::assertSame($submission1->getHash(), $tokenHash1, 'DOI hash in token 1 should match submission 1 hash');

        $decodedData2 = $tokenParser->decode($token2);
        Assert::assertInstanceOf(DoiTokenData::class, $decodedData2, 'Contact 2 DOI token should be decodable');
        $tokenFormId2 = $decodedData2->formId;
        $tokenHash2   = $decodedData2->hash;
        Assert::assertSame($form->getId(), $tokenFormId2, 'Form ID in token 2 should match expected');
        Assert::assertSame($submission2->getHash(), $tokenHash2, 'DOI hash in token 2 should match submission 2 hash');

        // Verify the hashes are different (ensuring individual links)
        Assert::assertNotSame($tokenHash1, $tokenHash2, 'Each contact should have a unique submission hash');
    }

    public function testCommandWithCustomLimit(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        $this->createDoiSubmission($form, 'test1@example.com', new \DateTime('-25 hours'));
        $this->createDoiSubmission($form, 'test2@example.com', new \DateTime('-26 hours'));
        $this->createDoiSubmission($form, 'test3@example.com', new \DateTime('-27 hours'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup', ['--limit' => '2']);

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 2 | Sent: 2', $output);
    }

    public function testCommandWithNoPendingSubmissions(): void
    {
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 0 | Sent: 0', $output);
    }

    public function testCommandRespectsWaitTime(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        $notReadySubmission = $this->createDoiSubmission($form, 'notready@example.com', new \DateTime('-23 hours'));
        $readySubmission    = $this->createDoiSubmission($form, 'ready@example.com', new \DateTime('-25 hours'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $this->em->refresh($notReadySubmission);
        $this->em->refresh($readySubmission);

        Assert::assertSame('pending', $notReadySubmission->getStatus());
        Assert::assertNull($notReadySubmission->getDateFollowupSent());
        Assert::assertNotNull($readySubmission->getDateFollowupSent());

        // Verify only the ready submission got an email
        $readyMessages    = $this->getMailerMessagesByToAddress('ready@example.com');
        $notReadyMessages = $this->getMailerMessagesByToAddress('notready@example.com');
        Assert::assertCount(1, $readyMessages, 'Ready submission should have received follow-up email');
        Assert::assertCount(0, $notReadyMessages, 'Not ready submission should not have received follow-up email');
    }

    public function testCommandWithBatchSize(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        // Create 5 submissions that are due for follow-up
        $this->createDoiSubmission($form, 'test1@example.com', new \DateTime('-25 hours'));
        $this->createDoiSubmission($form, 'test2@example.com', new \DateTime('-26 hours'));
        $this->createDoiSubmission($form, 'test3@example.com', new \DateTime('-27 hours'));
        $this->createDoiSubmission($form, 'test4@example.com', new \DateTime('-28 hours'));
        $this->createDoiSubmission($form, 'test5@example.com', new \DateTime('-29 hours'));

        // Process with batch size of 2 - should process all 5 in 3 batches (2+2+1)
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup', ['--batch' => '2']);

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 5 | Sent: 5', $output);

        // Verify all emails were sent
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com', 'test4@example.com', 'test5@example.com'];
        foreach ($emails as $email) {
            $messages = $this->getMailerMessagesByToAddress($email);
            Assert::assertCount(1, $messages, "Should have exactly one follow-up email sent to {$email}");
        }
    }

    public function testCommandWithBatchSizeAndLimit(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        // Create 7 submissions that are due for follow-up
        $this->createDoiSubmission($form, 'test1@example.com', new \DateTime('-25 hours'));
        $this->createDoiSubmission($form, 'test2@example.com', new \DateTime('-26 hours'));
        $this->createDoiSubmission($form, 'test3@example.com', new \DateTime('-27 hours'));
        $this->createDoiSubmission($form, 'test4@example.com', new \DateTime('-28 hours'));
        $this->createDoiSubmission($form, 'test5@example.com', new \DateTime('-29 hours'));
        $this->createDoiSubmission($form, 'test6@example.com', new \DateTime('-30 hours'));
        $this->createDoiSubmission($form, 'test7@example.com', new \DateTime('-31 hours'));

        // Process with batch size of 3 and limit of 5 - should process 5 total (3+2)
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup', [
            '--batch' => '3',
            '--limit' => '5',
        ]);

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 5 | Sent: 5', $output);

        // Count total emails sent - should be exactly 5
        $totalEmailsSent = 0;
        for ($i = 1; $i <= 7; ++$i) {
            $messages = $this->getMailerMessagesByToAddress("test{$i}@example.com");
            $totalEmailsSent += count($messages);
        }
        Assert::assertSame(5, $totalEmailsSent, 'Should have sent exactly 5 emails due to limit');
    }

    public function testCommandDisplaysMessageWhenPluginDisabled(): void
    {
        $this->pluginFixtureHelper->disablePlugin();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('DOI plugin is disabled', $output);
    }

    public function testCommandRespectsModifiedWaitTime(): void
    {
        // Modify the followup wait time to 12 hours instead of the default 24 hours
        $this->pluginFixtureHelper->modifyFollowupWaitTime(12);

        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);

        // Create a submission that should be ready with 12h wait time but not with 24h wait time
        $readySubmission = $this->createDoiSubmission($form, 'ready@example.com', new \DateTime('-13 hours'));
        // Create a submission that should not be ready even with 12h wait time
        $notReadySubmission = $this->createDoiSubmission($form, 'notready@example.com', new \DateTime('-11 hours'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 1 | Sent: 1', $output);

        $this->em->refresh($readySubmission);
        $this->em->refresh($notReadySubmission);

        // The 13-hour-old submission should have been processed
        Assert::assertNotNull($readySubmission->getDateFollowupSent());
        // The 11-hour-old submission should still be pending
        Assert::assertSame('pending', $notReadySubmission->getStatus());
        Assert::assertNull($notReadySubmission->getDateFollowupSent());

        // Verify only the ready submission got an email
        $readyMessages    = $this->getMailerMessagesByToAddress('ready@example.com');
        $notReadyMessages = $this->getMailerMessagesByToAddress('notready@example.com');
        Assert::assertCount(1, $readyMessages, 'Ready submission should have received follow-up email');
        Assert::assertCount(0, $notReadyMessages, 'Not ready submission should not have received follow-up email');
    }

    private function createForm(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
            'description' => 'Form created via command test',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction'  => 'return',
        ];

        $this->client->request('POST', '/api/forms/new', $formPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $formId   = $response['form']['id'];

        return $this->em->getRepository(Form::class)->find($formId);
    }

    private function createDoiConfig(Form $form): FormDoiConfig
    {
        $followUpEmail = $this->createEmail('Follow-up Email');

        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setEnabled(true);
        $config->setFollowUpEmail($followUpEmail);
        $config->setSuccessRedirectUrl('https://example.com/success');
        $config->setErrorRedirectUrl('https://example.com/error');
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    private function createDoiSubmission(Form $form, string $email, \DateTime $dateSubmitted): FormDoiSubmission
    {
        $lead = $this->createLead($email);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateSubmitted);
        $submission->setReferer('https://example.com/');
        $submission->setLead($lead);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($lead);
        $doiSubmission->setEmail($email);
        $doiSubmission->setStatus('pending');
        $doiSubmission->setHash(hash('sha256', $email.time()));
        $doiSubmission->setDateCreated($dateSubmitted);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }

    private function createEmail(string $name): Email
    {
        $emailPayload = [
            'name'         => $name,
            'subject'      => 'Test Follow-up Email',
            'emailType'    => 'template',
            'isPublished'  => true,
            'customHtml'   => '<!DOCTYPE html><html><body><p>Please confirm your email by clicking the link:</p><a href="{doi_link}">Verify Email</a></body></html>',
        ];

        $this->client->request('POST', '/api/emails/new', $emailPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $emailId  = $response['email']['id'];

        return $this->em->getRepository(Email::class)->find($emailId);
    }

    private function createLead(string $email): Lead
    {
        $leadPayload = [
            'email' => $email,
        ];

        $this->client->request('POST', '/api/contacts/new', $leadPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $leadId   = $response['contact']['id'];

        return $this->em->getRepository(Lead::class)->find($leadId);
    }
}
