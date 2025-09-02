<?php

namespace MauticPlugin\MauticDoiBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicControllerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    /**
     * Test successful email verification with redirect URL.
     */
    public function testVerifyEmailActionWithSuccessRedirectUrl(): void
    {
        $form = $this->createForm('Test DOI Form');
        $this->createDoiConfig($form, 'https://example.com/success', 'https://example.com/error');

        // Submit the form:
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_testdoiform]');
        $formElement = $formCrawler->form();
        $formElement->setValues([
            'mauticform[email]' => 'lead@example.com',
        ]);
        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Ensure the submission was created properly.
        $submissions = $this->em->getRepository(Submission::class)->findAll();
        Assert::assertCount(1, $submissions);

        $clientResponse = $this->client->getResponse();
        $this->assertSame(Response::HTTP_OK, $clientResponse->getStatusCode());

        // Find the DOI submission that should have been created
        $doiSubmissions = $this->em->getRepository(FormDoiSubmission::class)->findAll();
        Assert::assertCount(1, $doiSubmissions);

        $doiSubmission = $doiSubmissions[0];
        Assert::assertSame('pending', $doiSubmission->getStatus());
        Assert::assertSame('lead@example.com', $doiSubmission->getEmail());

        // Create the token: base64("{formId}:{hash}")
        $formId    = $form->getId();
        $hash      = $doiSubmission->getHash();
        $tokenData = "{$formId}:{$hash}";
        $token     = base64_encode($tokenData);

        // Call the verification endpoint with the encoded token
        $verificationResponse = $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");

        // Verify redirect to success URL
        $this->assertSame('https://example.com/success', $verificationResponse->getUri());

        // Refresh the entity and verify it's now confirmed
        $this->em->refresh($doiSubmission);
        Assert::assertSame('confirmed', $doiSubmission->getStatus());
        Assert::assertNotNull($doiSubmission->getDateConfirmed());
    }

    private function createForm(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
            'description' => 'Form created via submission test',
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

        // Create the form
        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
    }

    private function createDoiConfig(Form $form, ?string $successUrl, ?string $errorUrl): FormDoiConfig
    {
        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setEnabled(true);
        $config->setSuccessRedirectUrl($successUrl);
        $config->setErrorRedirectUrl($errorUrl);
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }
}
