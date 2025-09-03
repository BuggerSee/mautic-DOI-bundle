<?php

namespace MauticPlugin\MauticDoiBundle\Tests\Service;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\PointsChangeLog;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DoiActionsDispatcherFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    public function testLeadPointsChangeActionExecutesAfterDoiVerification(): void
    {
        $form = $this->createForm('DOI Points Test Form');
        $this->createDoiConfig($form);
        $this->createDoiAction($form, 'Test Points Change Action', 'lead.pointschange', [
            'operator' => 'plus',
            'points'   => 10,
        ]);

        // Submit the form to create initial submission and DOI entry
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_doipointstestform]');
        $formElement = $formCrawler->form();
        $formElement->setValues([
            'mauticform[email]' => 'test@example.com',
        ]);
        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify submission was created
        $submissions = $this->em->getRepository(Submission::class)->findAll();
        Assert::assertCount(1, $submissions);
        $submission = $submissions[0];

        // Verify DOI submission was created
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findAll();
        Assert::assertCount(1, $doiSubmissions);
        $doiSubmission = $doiSubmissions[0];
        Assert::assertSame('pending', $doiSubmission->getStatus());

        // Get the contact that was created
        $contact = $submission->getLead();
        Assert::assertNotNull($contact);
        $initialPoints = $contact->getPoints();

        // Simulate DOI verification click
        $formId    = $form->getId();
        $hash      = $doiSubmission->getHash();
        $tokenData = "{$formId}:{$hash}";
        $token     = base64_encode($tokenData);

        $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");  // This still returns the Crawler for content parsing
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Refresh entities to get updated data
        $this->em->refresh($doiSubmission);
        $this->em->refresh($contact);

        // Verify DOI submission is now confirmed
        Assert::assertSame('confirmed', $doiSubmission->getStatus());

        // Verify points were added to the contact
        $finalPoints = $contact->getPoints();
        Assert::assertSame($initialPoints + 10, $finalPoints);

        // Verify points change log was created
        $pointsChangeLogs = $this->em->getRepository(PointsChangeLog::class)->findBy(['lead' => $contact]);
        Assert::assertCount(1, $pointsChangeLogs);

        $pointsChangeLog = $pointsChangeLogs[0];
        Assert::assertSame(10, $pointsChangeLog->getDelta());
        Assert::assertSame('form', $pointsChangeLog->getType());
        Assert::assertStringContainsString($form->getName(), $pointsChangeLog->getEventName());
    }

    public function testLeadScoreContactsCompaniesActionExecutesAfterDoiVerification(): void
    {
        $form = $this->createForm('DOI Score Companies Test Form');
        $this->createDoiConfig($form);
        $this->createDoiAction($form, 'Test Score Companies Action', 'lead.scorecontactscompanies', [
            'score' => 25,
        ]);

        $company = $this->createCompany('Test Company', 10);

        // Submit the form to create initial submission and DOI entry
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_doiscorecompaniestestform]');
        $formElement = $formCrawler->form();
        $formElement->setValues([
            'mauticform[email]'   => 'test@example.com',
            'mauticform[company]' => 'Test Company',
        ]);
        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify submission was created
        $submissions = $this->em->getRepository(Submission::class)->findAll();
        Assert::assertCount(1, $submissions);
        $submission = $submissions[0];

        // Verify DOI submission was created
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findAll();
        Assert::assertCount(1, $doiSubmissions);
        $doiSubmission = $doiSubmissions[0];
        Assert::assertSame('pending', $doiSubmission->getStatus());

        // Get the contact that was created
        $contact = $submission->getLead();
        Assert::assertNotNull($contact);

        // Simulate DOI verification click
        $formId    = $form->getId();
        $hash      = $doiSubmission->getHash();
        $tokenData = "{$formId}:{$hash}";
        $token     = base64_encode($tokenData);

        $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");
        $response = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Refresh entities to get updated data
        $this->em->refresh($doiSubmission);
        $this->em->refresh($company);

        // Verify DOI submission is now confirmed
        Assert::assertSame('confirmed', $doiSubmission->getStatus());

        Assert::assertSame(35, $company->getScore());
    }

    private function createForm(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
            'description' => 'Form created for DOI action testing',
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
                    'label'        => 'Company',
                    'type'         => 'text',
                    'alias'        => 'company',
                    'leadField'    => 'companyname',
                    'mappedField'  => 'companyname',
                    'mappedObject' => 'company',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction' => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
    }

    private function createDoiConfig(Form $form): FormDoiConfig
    {
        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setEnabled(true);
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    /**
     * @param array<string,mixed> $properties
     */
    private function createDoiAction(Form $form, string $name, string $type, array $properties): FormDoiAction
    {
        $action = new FormDoiAction();
        $action->setForm($form);
        $action->setName($name);
        $action->setType($type);
        $action->setProperties($properties);
        $action->setOrder(1);

        $this->em->persist($action);
        $this->em->flush();

        return $action;
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
