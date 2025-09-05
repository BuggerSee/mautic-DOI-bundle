<?php

namespace MauticPlugin\MauticDoiBundle\Tests\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use PHPUnit\Framework\Assert;

class SendFollowUpCommandFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    public function testCommandWithDefaultLimit(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form);
        
        $oldSubmission = $this->createDoiSubmission($form, 'old@example.com', new \DateTime('-25 hours'));
        $recentSubmission = $this->createDoiSubmission($form, 'recent@example.com', new \DateTime('-1 hour'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $output = $commandTester->getDisplay();
        Assert::assertStringContainsString('Processed: 1 | Sent: 1', $output);

        $this->em->refresh($oldSubmission);
        $this->em->refresh($recentSubmission);
        
        Assert::assertNotNull($oldSubmission->getDateFollowupSent());
        Assert::assertSame('pending', $recentSubmission->getStatus());
        Assert::assertNull($recentSubmission->getDateFollowupSent());
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
        $readySubmission = $this->createDoiSubmission($form, 'ready@example.com', new \DateTime('-25 hours'));

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:send-followup');

        $this->em->refresh($notReadySubmission);
        $this->em->refresh($readySubmission);
        
        Assert::assertSame('pending', $notReadySubmission->getStatus());
        Assert::assertNull($notReadySubmission->getDateFollowupSent());
        Assert::assertNotNull($readySubmission->getDateFollowupSent());
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
        $formId = $response['form']['id'];

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
        $doiSubmission->setHash(hash('sha256', $email . time()));
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
            'customHtml'   => '<p>Please confirm your email by clicking the link.</p>',
        ];

        $this->client->request('POST', '/api/emails/new', $emailPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $emailId = $response['email']['id'];

        return $this->em->getRepository(Email::class)->find($emailId);
    }

    private function createLead(string $email): Lead
    {
        $leadPayload = [
            'email' => $email,
        ];

        $this->client->request('POST', '/api/contacts/new', $leadPayload);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $leadId = $response['contact']['id'];

        return $this->em->getRepository(Lead::class)->find($leadId);
    }
}