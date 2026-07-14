<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Functional\Service;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\ListLead;
use Mautic\LeadBundle\Entity\PointsChangeLog;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\FormFixtureHelper;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoiActionsDispatcherFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private FormFixtureHelper $formFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $pluginFixtureHelper->createAndEnablePlugin();
        $this->formFixtureHelper = new FormFixtureHelper($this->em, $this->client);
    }

    public function testLeadPointsChangeActionExecutesAfterDoiVerification(): void
    {
        $form = $this->createFormWithDoiAction('DOI Points Test Form', 'Test Points Change Action', 'lead.pointschange', [
            'operator' => 'plus',
            'points'   => 10,
        ]);

        $submission    = $this->submitForm($form, ['mauticform[email]' => 'test@example.com']);
        $doiSubmission = $this->assertDoiSubmissionCreated();
        $contact       = $submission->getLead();
        $initialPoints = $contact->getPoints();

        $this->verifyDoiToken($form, $doiSubmission);

        $this->em->refresh($doiSubmission);
        $this->em->refresh($contact);

        Assert::assertSame('confirmed', $doiSubmission->getStatus());
        Assert::assertSame($initialPoints + 10, $contact->getPoints());

        $pointsChangeLogs = $this->em->getRepository(PointsChangeLog::class)->findBy(['lead' => $contact]);
        Assert::assertCount(1, $pointsChangeLogs);

        $pointsChangeLog = $pointsChangeLogs[0];
        Assert::assertSame(10, $pointsChangeLog->getDelta());
        Assert::assertSame('form', $pointsChangeLog->getType());
        Assert::assertStringContainsString($form->getName(), $pointsChangeLog->getEventName());
    }

    public function testLeadScoreContactsCompaniesActionExecutesAfterDoiVerification(): void
    {
        $form = $this->createFormWithDoiAction('DOI Score Companies Test Form', 'Test Score Companies Action', 'lead.scorecontactscompanies', [
            'score' => 25,
        ]);

        $company = $this->createCompany('Test Company', 10);

        $this->submitForm($form, [
            'mauticform[email]'   => 'test@example.com',
            'mauticform[company]' => 'Test Company',
        ]);
        $doiSubmission = $this->assertDoiSubmissionCreated();

        $this->verifyDoiToken($form, $doiSubmission);

        $this->em->refresh($doiSubmission);
        $this->em->refresh($company);

        Assert::assertSame('confirmed', $doiSubmission->getStatus());
        Assert::assertSame(35, $company->getScore());
    }

    public function testLeadChangeListActionExecutesAfterDoiVerification(): void
    {
        $addToSegment      = $this->formFixtureHelper->createSegment('Add To Segment', 'add-to-segment');
        $removeFromSegment = $this->formFixtureHelper->createSegment('Remove From Segment', 'remove-from-segment');

        $form = $this->createFormWithDoiAction('DOI Change List Test Form', 'Test Change List Action', 'lead.changelist', [
            'addToLists'      => [$addToSegment->getId()],
            'removeFromLists' => [$removeFromSegment->getId()],
        ]);

        $submission    = $this->submitForm($form, ['mauticform[email]' => 'test@example.com']);
        $doiSubmission = $this->assertDoiSubmissionCreated();
        $contact       = $submission->getLead();

        // Add contact to the "remove from" segment initially
        $listLead = new ListLead();
        $listLead->setLead($contact);
        $listLead->setList($removeFromSegment);
        $listLead->setDateAdded(new \DateTime());
        $this->em->persist($listLead);
        $this->em->flush();

        $this->verifyDoiToken($form, $doiSubmission);

        $this->em->refresh($doiSubmission);
        $this->em->refresh($contact);

        Assert::assertSame('confirmed', $doiSubmission->getStatus());

        // Check that contact was added to the "add to" segment
        $addToListLeads = $this->em->getRepository(ListLead::class)->findBy([
            'lead' => $contact,
            'list' => $addToSegment,
        ]);
        Assert::assertCount(1, $addToListLeads);

        // Check that contact was removed from the "remove from" segment
        $removeFromListLeads = $this->em->getRepository(ListLead::class)->findBy([
            'lead' => $contact,
            'list' => $removeFromSegment,
        ]);
        Assert::assertCount(1, $removeFromListLeads);
        Assert::assertTrue($removeFromListLeads[0]->getManuallyRemoved());
    }

    public function testEmailSendLeadActionExecutesAfterDoiVerification(): void
    {
        $email = $this->formFixtureHelper->createEmail('Test Email for Lead', 'Test email content for lead');

        $form = $this->createFormWithDoiAction('DOI Email Send Lead Test Form', 'Test Email Send Lead Action', 'email.send.lead', [
            'email' => $email->getId(),
        ]);

        $this->submitForm($form, ['mauticform[email]' => 'test@example.com']);
        $doiSubmission = $this->assertDoiSubmissionCreated();

        $this->verifyDoiToken($form, $doiSubmission);

        $this->em->refresh($doiSubmission);

        Assert::assertSame('confirmed', $doiSubmission->getStatus());

        $messages = $this->getMailerMessagesByToAddress('test@example.com');
        Assert::assertCount(1, $messages);
        Assert::assertStringContainsString('Test Email for Lead', $messages[0]->getSubject());
    }

    /**
     * @param array<string,string> $formData
     */
    private function submitForm(Form $form, array $formData): Submission
    {
        $formNameForId = strtolower(str_replace(' ', '', $form->getName()));

        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter("form[id=mauticform_{$formNameForId}]");
        $formElement = $formCrawler->form();
        $formElement->setValues($formData);
        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        $submissions = $this->em->getRepository(Submission::class)->findAll();
        Assert::assertCount(1, $submissions);

        return $submissions[0];
    }

    private function assertDoiSubmissionCreated(): FormDoiSubmission
    {
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findAll();
        Assert::assertCount(1, $doiSubmissions);
        $doiSubmission = $doiSubmissions[0];
        Assert::assertSame('pending', $doiSubmission->getStatus());

        return $doiSubmission;
    }

    private function verifyDoiToken(Form $form, FormDoiSubmission $doiSubmission): void
    {
        $formId    = $form->getId();
        $hash      = $doiSubmission->getHash();
        $tokenData = "{$formId}:{$hash}";
        $token     = base64_encode($tokenData);

        $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param array<string,mixed> $properties
     */
    private function createFormWithDoiAction(string $formName, string $actionName, string $actionType, array $properties): Form
    {
        $form = $this->formFixtureHelper->createFormWithCompanyViaApi($formName);
        $this->formFixtureHelper->createDoiConfig($form);
        $this->formFixtureHelper->createDoiAction($form, $actionName, $actionType, $properties);

        return $form;
    }

    private function createCompany(string $name, int $initialScore = 0): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setScore($initialScore);

        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
