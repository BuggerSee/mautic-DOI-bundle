<?php

namespace MauticPlugin\MauticDoiBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use MauticPlugin\MauticDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use Symfony\Component\DomCrawler\Crawler;

class FormDoiControllerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private DoiConfigManager $doiConfigManager;
    private PluginFixtureHelper $pluginFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $this->pluginFixtureHelper->createAndEnablePlugin();
        $this->doiConfigManager = $this->getContainer()->get(DoiConfigManager::class);
    }

    /**
     * Test that DOI config can be saved when enabled.
     */
    public function testSaveDoiConfigWhenEnabled(): void
    {
        $form              = $this->createForm('Test DOI Form', 'test_doi_form');
        $verificationEmail = $this->createEmail('DOI Verification Email');
        $followUpEmail     = $this->createEmail('DOI Follow-up Email');

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
            'mauticform[doiConfig][followUpEmailId]'     => $followUpEmail->getId(),
            'mauticform[doiConfig][successRedirectUrl]'  => 'https://example.com/success',
            'mauticform[doiConfig][errorRedirectUrl]'    => 'https://example.com/error',
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify DOI config was saved
        $savedDoiConfig = $this->doiConfigManager->getFormDoiConfig($form);
        $this->assertNotNull($savedDoiConfig);
        $this->assertTrue($savedDoiConfig->isEnabled());
        $this->assertEquals($verificationEmail->getId(), $savedDoiConfig->getVerificationEmail()->getId());
        $this->assertEquals($followUpEmail->getId(), $savedDoiConfig->getFollowUpEmail()->getId());
        $this->assertEquals('https://example.com/success', $savedDoiConfig->getSuccessRedirectUrl());
        $this->assertEquals('https://example.com/error', $savedDoiConfig->getErrorRedirectUrl());
    }

    /**
     * Test that DOI config can be saved when disabled.
     */
    public function testSaveDoiConfigWhenDisabled(): void
    {
        $form = $this->createForm('Test DOI Form Disabled', 'test_doi_form_disabled');

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]' => '0',
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify DOI config was saved as disabled
        $savedDoiConfig = $this->doiConfigManager->getFormDoiConfig($form);
        $this->assertNotNull($savedDoiConfig);
        $this->assertFalse($savedDoiConfig->isEnabled());
    }

    /**
     * Test that validation fails when DOI is enabled but verification email is not provided.
     */
    public function testValidationFailsWhenEnabledWithoutVerificationEmail(): void
    {
        $form = $this->createForm('Test DOI Validation', 'test_doi_validation');

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]' => '1',
            // No verification email provided
        ]);

        $crawler  = $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk());

        // Check that validation error is displayed
        $validationText = 'Verification email is required when DOI is enabled';
        $pageContent    = $crawler->filter('body')->text();
        $this->assertStringContainsString($validationText, $pageContent, 'Validation text not found on the page');
    }

    /**
     * Test that validation passes when DOI is disabled even without verification email.
     */
    public function testValidationPassesWhenDisabledWithoutVerificationEmail(): void
    {
        $form = $this->createForm('Test DOI Disabled Validation', 'test_doi_disabled_validation');

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]' => '0',
            // No verification email provided, but that's OK when disabled
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify DOI config was saved as disabled
        $savedDoiConfig = $this->doiConfigManager->getFormDoiConfig($form);
        $this->assertNotNull($savedDoiConfig);
        $this->assertFalse($savedDoiConfig->isEnabled());
    }

    /**
     * Test that existing DOI config is loaded correctly in the form.
     */
    public function testExistingDoiConfigIsLoadedInForm(): void
    {
        $form              = $this->createForm('Test Existing DOI Config', 'test_existing_doi_config');
        $verificationEmail = $this->createEmail('Existing Verification Email');

        // Create DOI config directly
        $doiConfig = new FormDoiConfig();
        $doiConfig->setForm($form);
        $doiConfig->setEnabled(true);
        $doiConfig->setVerificationEmail($verificationEmail);
        $doiConfig->setSuccessRedirectUrl('https://example.com/existing-success');
        $this->em->persist($doiConfig);
        $this->em->flush();

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify that existing values are loaded
        $enabledField = $crawler->filter('input[name="mauticform[doiConfig][enabled]"]:checked');
        $this->assertEquals('1', $enabledField->attr('value'));

        $verificationEmailField = $crawler->filter('select[name="mauticform[doiConfig][verificationEmailId]"] option:selected');
        $this->assertEquals($verificationEmail->getId(), $verificationEmailField->attr('value'));

        $successUrlField = $crawler->filter('input[name="mauticform[doiConfig][successRedirectUrl]"]');
        $this->assertEquals('https://example.com/existing-success', $successUrlField->attr('value'));
    }

    /**
     * Test that an existing DOI config can be updated.
     */
    public function testUpdateExistingDoiConfig(): void
    {
        // Create initial form and emails
        $form                     = $this->createForm('Test DOI Update Form', 'test_doi_update_form');
        $initialVerificationEmail = $this->createEmail('Initial Verification Email');
        $initialFollowUpEmail     = $this->createEmail('Initial Follow-up Email');

        // Create initial DOI config
        $initialDoiConfig = new FormDoiConfig();
        $initialDoiConfig->setForm($form);
        $initialDoiConfig->setEnabled(true);
        $initialDoiConfig->setVerificationEmail($initialVerificationEmail);
        $initialDoiConfig->setFollowUpEmail($initialFollowUpEmail);
        $initialDoiConfig->setSuccessRedirectUrl('https://example.com/initial-success');
        $initialDoiConfig->setErrorRedirectUrl('https://example.com/initial-error');
        $this->em->persist($initialDoiConfig);
        $this->em->flush();

        // Create new emails for update
        $newVerificationEmail = $this->createEmail('New Verification Email');
        $newFollowUpEmail     = $this->createEmail('New Follow-up Email');

        // Request the edit form
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        // Update the form with new values
        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $newVerificationEmail->getId(),
            'mauticform[doiConfig][followUpEmailId]'     => $newFollowUpEmail->getId(),
            'mauticform[doiConfig][successRedirectUrl]'  => 'https://example.com/updated-success',
            'mauticform[doiConfig][errorRedirectUrl]'    => 'https://example.com/updated-error',
        ]);

        // Submit the updated form
        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Refresh the entity manager to ensure we're getting the latest data
        $this->em->clear();

        // Retrieve the updated DOI config
        $updatedDoiConfig = $this->doiConfigManager->getFormDoiConfig($form);

        // Assert that the config was updated correctly
        $this->assertNotNull($updatedDoiConfig);
        $this->assertTrue($updatedDoiConfig->isEnabled());
        $this->assertEquals($newVerificationEmail->getId(), $updatedDoiConfig->getVerificationEmail()->getId());
        $this->assertEquals($newFollowUpEmail->getId(), $updatedDoiConfig->getFollowUpEmail()->getId());
        $this->assertEquals('https://example.com/updated-success', $updatedDoiConfig->getSuccessRedirectUrl());
        $this->assertEquals('https://example.com/updated-error', $updatedDoiConfig->getErrorRedirectUrl());

        // Assert that the config is the same entity as the initial one (updated, not new)
        $this->assertEquals($initialDoiConfig->getId(), $updatedDoiConfig->getId());
    }

    /**
     * Ensure DOI form fields are not available when the plugin is disabled.
     */
    public function testDoiFieldsAreHiddenWhenPluginDisabled(): void
    {
        $this->pluginFixtureHelper->disablePlugin();

        $form = $this->createForm('Test DOI Disabled - Fields Hidden', 'test_doi_fields_hidden_when_disabled');

        // Load the form edit page
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        // Assert DOI fields are not present
        $this->assertSame(0, $crawler->filter('input[name="mauticform[doiConfig][enabled]"]')->count(), 'Enabled field should not be present');
        $this->assertSame(0, $crawler->filter('select[name="mauticform[doiConfig][verificationEmailId]"]')->count(), 'Verification email field should not be present');
        $this->assertSame(0, $crawler->filter('select[name="mauticform[doiConfig][followUpEmailId]"]')->count(), 'Follow-up email field should not be present');
        $this->assertSame(0, $crawler->filter('input[name="mauticform[doiConfig][successRedirectUrl]"]')->count(), 'Success redirect URL field should not be present');
        $this->assertSame(0, $crawler->filter('input[name="mauticform[doiConfig][errorRedirectUrl]"]')->count(), 'Error redirect URL field should not be present');
    }

    private function createForm(string $name, string $alias): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setAlias($alias);
        $form->setPostActionProperty('Success');
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    public function testSaveFormWithDoiActions(): void
    {
        $form              = $this->createForm('Test DOI Form with Actions', 'test_doi_form_with_actions');
        $verificationEmail = $this->createEmail('DOI Verification Email');
        $sessionId         = (string) $form->getId();

        $this->submitNewDoiActionForm($sessionId);
        $this->assertDoiActionInSession($sessionId);

        $sessionData = $this->storeSessionData();

        // Get the form edit page
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $this->restoreSessionData($sessionData);

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();

        // Now submit the main form with DOI config, including sessionId
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
            'mauticform[sessionId]'                      => $sessionId,
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify DOI action is persisted in database
        $doiActionRepository = $this->em->getRepository(FormDoiAction::class);
        $savedActions        = $doiActionRepository->findBy(['form' => $form]);
        $this->assertCount(1, $savedActions);

        $savedAction = $savedActions[0];
        $this->assertEquals('form.email', $savedAction->getType());
        $this->assertEquals('Test email subject', $savedAction->getProperties()['subject']);
        $this->assertEquals('Test email message', $savedAction->getProperties()['message']);
    }

    public function testEditFormDoiAction(): void
    {
        $form              = $this->createForm('Test DOI Form with Actions', 'test_doi_form_with_actions');
        $verificationEmail = $this->createEmail('DOI Verification Email');
        $sessionId         = (string) $form->getId();

        // Create initial action
        $this->submitNewDoiActionForm($sessionId);
        $this->assertDoiActionInSession($sessionId);

        $sessionData = $this->storeSessionData();

        // Get the form edit page
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $this->restoreSessionData($sessionData);

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();

        // Now submit the main form with DOI config, including sessionId
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        $doiActionRepository = $this->em->getRepository(FormDoiAction::class);
        $savedActions        = $doiActionRepository->findBy(['form' => $form]);
        $this->assertCount(1, $savedActions);
        $doiAction = $savedActions[0];

        $this->submitEditDoiActionForm($sessionId, $doiAction->getId());

        $this->assertDoiActionInSession($sessionId);

        $sessionData = $this->storeSessionData();

        // Get the form edit page
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $this->restoreSessionData($sessionData);

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();

        // Now submit the main form with DOI config, including sessionId
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify the action was updated
        $updatedAction = $doiActionRepository->find($doiAction->getId());
        $this->assertEquals('Updated DOI Email Action', $updatedAction->getName());
        $this->assertEquals('Updated action description', $updatedAction->getDescription());
        $this->assertEquals('Updated email subject', $updatedAction->getProperties()['subject']);
        $this->assertEquals('Updated email message', $updatedAction->getProperties()['message']);
    }

    public function testRemoveFormDoiAction(): void
    {
        $form              = $this->createForm('Test DOI Form with Actions', 'test_doi_form_with_actions');
        $verificationEmail = $this->createEmail('DOI Verification Email');
        $sessionId         = (string) $form->getId();

        // Create initial action
        $this->submitNewDoiActionForm($sessionId);
        $this->assertDoiActionInSession($sessionId);

        $sessionData = $this->storeSessionData();

        // Get the form edit page
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $this->restoreSessionData($sessionData);

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();

        // Submit the main form with DOI config, including sessionId
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
            'mauticform[sessionId]'                      => $sessionId,
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify DOI action is persisted in database
        $doiActionRepository = $this->em->getRepository(FormDoiAction::class);
        $savedActions        = $doiActionRepository->findBy(['form' => $form]);
        $this->assertCount(1, $savedActions);
        $doiAction = $savedActions[0];

        // Remove the DOI action
        $this->client->request(
            'POST',
            sprintf('/s/forms-doi/action/delete/%d?formId=%d', $doiAction->getId(), $form->getId()),
            [],
            [],
            $this->createAjaxHeaders()
        );
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify the action was removed from the session
        $this->assertDoiActionNotInSession($sessionId);
        $sessionData = $this->storeSessionData();

        // Save the form again to persist changes
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());
        $this->restoreSessionData($sessionData);

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]'             => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
            'mauticform[sessionId]'                      => $sessionId,
        ]);

        $this->client->submit($formElement);
        $this->assertTrue($this->client->getResponse()->isOk());

        // Verify the action was removed from the database
        $remainingActions = $doiActionRepository->findBy(['form' => $form]);
        $this->assertCount(0, $remainingActions, 'DOI action should be removed from the database');
    }

    private function submitNewDoiActionForm(string $sessionId): void
    {
        $this->client->request(
            'GET',
            '/s/forms-doi/action/new',
            [
                'formId' => $sessionId,
                'type'   => 'form.email',
            ],
            [],
            $this->createAjaxHeaders()
        );
        $this->assertTrue($this->client->getResponse()->isOk());

        $content    = json_decode($this->client->getResponse()->getContent())->newContent;
        $crawler    = new Crawler($content, $this->client->getInternalRequest()->getUri());
        $actionForm = $crawler->filter('form')->form();

        $actionForm->setValues([
            'formaction[properties][subject]' => 'Test email subject',
            'formaction[properties][message]' => 'Test email message',
            'formaction[name]'                => 'Test DOI Email Action',
            'formaction[description]'         => 'Test action description',
            'formaction[type]'                => 'form.email',
            'formaction[formId]'              => $sessionId,
        ]);
        $this->client->submit($actionForm, [], $this->createAjaxHeaders());
        $this->assertTrue($this->client->getResponse()->isOk());
    }

    private function submitEditDoiActionForm(string $sessionId, int $doiActionId): void
    {
        $this->client->request(
            'GET',
            sprintf('/s/forms-doi/action/edit/%d', $doiActionId),
            [
                'formId' => $sessionId,
                'type'   => 'form.email',
            ],
            [],
            $this->createAjaxHeaders()
        );
        $this->assertTrue($this->client->getResponse()->isOk());

        $content    = json_decode($this->client->getResponse()->getContent())->newContent;
        $crawler    = new Crawler($content, $this->client->getInternalRequest()->getUri());
        $actionForm = $crawler->filter('form')->form();

        $actionForm->setValues([
            'formaction[properties][subject]' => 'Updated email subject',
            'formaction[properties][message]' => 'Updated email message',
            'formaction[name]'                => 'Updated DOI Email Action',
            'formaction[description]'         => 'Updated action description',
            'formaction[type]'                => 'form.email',
            'formaction[formId]'              => $sessionId,
        ]);
        $this->client->submit($actionForm, [], $this->createAjaxHeaders());
        $this->assertTrue($this->client->getResponse()->isOk());
    }

    private function assertDoiActionNotInSession(string $sessionId): void
    {
        $sessionManager   = $this->getContainer()->get('MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager');
        $actionsInSession = $sessionManager->getActionsFromSession($sessionId);
        $this->assertEmpty($actionsInSession, 'Actions should not be in session');
    }

    private function assertDoiActionInSession(string $sessionId): void
    {
        $sessionManager   = $this->getContainer()->get('MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager');
        $actionsInSession = $sessionManager->getActionsFromSession($sessionId);
        $this->assertNotEmpty($actionsInSession, 'Actions should be in session');
    }

    /**
     * @return array<string, mixed>
     */
    private function storeSessionData(): array
    {
        return $this->client->getRequest()->getSession()->all();
    }

    /**
     * @param array<string, mixed> $sessionData
     */
    private function restoreSessionData(array $sessionData): void
    {
        foreach ($sessionData as $key => $value) {
            $this->client->getRequest()->getSession()->set($key, $value);
        }
    }

    private function createEmail(string $name): Email
    {
        $email = new Email();
        $email->setName($name);
        $email->setSubject('Test Subject');
        $email->setCustomHtml('<p>Test content</p>');
        $email->setEmailType('template');
        $this->em->persist($email);
        $this->em->flush();

        return $email;
    }
}
