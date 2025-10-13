<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\FormFixtureHelper;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

/**
 * Functional tests for the custom post-action feature when DOI is skipped for a known contact.
 */
class DoiSkippedSubmitActionFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;
    private FormFixtureHelper $formFixtureHelper;
    private const CUSTOM_REDIRECT_URL      = '/custom-redirect-success';
    private const STANDARD_REDIRECT_URL    = '/standard-redirect';
    private const CUSTOM_MESSAGE           = 'This is the custom skip message.';
    private const STANDARD_MESSAGE         = 'This is the standard message.';

    protected function setUp(): void
    {
        parent::setUp();

        $pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $pluginFixtureHelper->createAndEnablePlugin();
        $this->formFixtureHelper = new FormFixtureHelper($this->em, $this->client);
    }

    /**
     * Verifies that the custom "skip action" redirect overrides the standard form redirect
     * when a known contact (with a valid cookie) submits the form.
     */
    public function testSkipActionRedirectsToCustomUrlWhenVerificationIsSkipped(): void
    {
        // 1. Arrange
        $email = 'redirect-test@example.com';

        /** @var Form $form */
        $form = $this->formFixtureHelper->createFormViaApi('Test Skip Redirect Form');
        $form->setIsPublished(true);
        $form->setPostAction('redirect');
        $form->setPostActionProperty(self::STANDARD_REDIRECT_URL);
        $this->em->flush();

        $this->formFixtureHelper->createDoiConfig(
            form: $form,
            skipOnCookie: true,
            skipPostAction: 'redirect',
            skipPostActionProperty: self::CUSTOM_REDIRECT_URL
        );

        // Phase 1: Go through the DOI process once to get the cookie.
        $this->performInitialSubmissionAndVerification($form, $email);

        // 2. Act (Phase 2): Submit the form again with the cookie.
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_testskipredirectform]');
        $formElement = $formCrawler->form();
        $formElement->setValues(['mauticform[email]' => $email]);
        $this->client->submit($formElement);

        // 3. Assert
        Assert::assertSame(self::CUSTOM_REDIRECT_URL, $this->client->getRequest()->getPathInfo(), 'Should have been redirected to the custom skip action URL.');
    }

    /**
     * Verifies that the custom "skip action" message overrides the standard form message
     * when submitting via AJAX with a valid cookie.
     */
    public function testSkipActionDisplaysCustomMessageWhenSkipped(): void
    {
        // 1. Arrange
        $email = 'message-test@example.com';

        /** @var Form $form */
        $form = $this->formFixtureHelper->createFormViaApi('Test Skip Message Form');
        $form->setIsPublished(true);
        $form->setPostAction('message');
        $form->setPostActionProperty(self::STANDARD_MESSAGE);
        $this->em->flush();

        $this->formFixtureHelper->createDoiConfig(
            form: $form,
            skipOnCookie: true, // Required to enable skip logic
            skipPostAction: 'message',
            skipPostActionProperty: self::CUSTOM_MESSAGE
        );

        // Phase 1: Get the cookie.
        $this->performInitialSubmissionAndVerification($form, $email);

        // 2. Act (Phase 2): Submit the form
        $payload = [
            'mauticform' => [
                'email'     => $email,
                'formId'    => $form->getId(),
                'formName'  => $form->getAlias(),
                'messenger' => 1,
            ],
        ];
        $this->client->request(Request::METHOD_POST, "/form/submit?formId={$form->getId()}", $payload);
        $response = $this->client->getResponse();
        $content  = $response->getContent();

        // 3. Assert
        Assert::assertTrue($response->isOk(), 'Response should be successful.');

        // Decode Unicode escapes (e.g., \u0022 -> ")
        $decodedContent = str_replace('\u0020', ' ', $content);
        Assert::assertStringContainsString(self::CUSTOM_MESSAGE, $decodedContent);
        Assert::assertStringNotContainsString(self::STANDARD_MESSAGE, $decodedContent);
    }

    /**
     * Verifies that the standard form action is used as a fallback when the user has a valid cookie
     * but the custom skip action itself is not configured.
     */
    public function testStandardFormActionIsUsedWhenSkipActionIsDisabled(): void
    {
        // 1. Arrange
        $email = 'fallback-test@example.com';

        /** @var Form $form */
        $form = $this->formFixtureHelper->createFormViaApi('Test Fallback Form');
        $form->setIsPublished(true);
        $form->setPostAction('redirect');
        $form->setPostActionProperty(self::STANDARD_REDIRECT_URL);
        $this->em->flush();

        // Note: skipPostAction is NOT set, enabling the fallback.
        $this->formFixtureHelper->createDoiConfig(form: $form, skipOnCookie: true);

        // Phase 1: Get the cookie. This request populates the state in the controller.
        $this->performInitialSubmissionAndVerification($form, $email);

        // 2. Act (Phase 2): Submit the form again with the cookie
        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter('form[id=mauticform_testfallbackform]');
        $formElement = $formCrawler->form();
        $formElement->setValues(['mauticform[email]' => $email]);
        $this->client->submit($formElement);

        // 3. Assert
        Assert::assertSame(self::STANDARD_REDIRECT_URL, $this->client->getRequest()->getPathInfo(), 'Should have been redirected to the standard form action URL.');
    }

    /**
     * Helper method to perform the initial submission and verification to get the browser proof cookie.
     */
    private function performInitialSubmissionAndVerification(Form $form, string $email): void
    {
        $doiSubmission = $this->formFixtureHelper->createDoiSubmission($form, $email, new \DateTime());

        // 2. Simulate the email verification click
        $hash  = $doiSubmission->getHash();
        $token = base64_encode("{$form->getId()}:{$hash}");
        $this->client->request(Request::METHOD_GET, "/email/verify/{$token}");

        // 3. Verify cookie is now set on the client
        Assert::assertNotNull(
            $this->client->getCookieJar()->get('mautic_doi_receipt'),
            'The mautic_doi_receipt cookie was not set after verification.'
        );
    }
}
