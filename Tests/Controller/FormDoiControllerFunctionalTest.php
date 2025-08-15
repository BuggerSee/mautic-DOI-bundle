<?php

namespace MauticPlugin\MauticDoiBundle\Tests\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;

class FormDoiControllerFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private DoiConfigManager $doiConfigManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->doiConfigManager = $this->getContainer()->get('mautic.plugin.doi.model.doi_config_manager');
    }

    /**
     * Test that DOI config can be saved when enabled.
     */
    public function testSaveDoiConfigWhenEnabled(): void
    {
        $form = $this->createForm('Test DOI Form', 'test_doi_form');
        $verificationEmail = $this->createEmail('DOI Verification Email', 'verification_email');
        $followUpEmail = $this->createEmail('DOI Follow-up Email', 'followup_email');

        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]' => '1',
            'mauticform[doiConfig][verificationEmailId]' => $verificationEmail->getId(),
            'mauticform[doiConfig][followUpEmailId]' => $followUpEmail->getId(),
            'mauticform[doiConfig][successRedirectUrl]' => 'https://example.com/success',
            'mauticform[doiConfig][errorRedirectUrl]' => 'https://example.com/error',
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

        $crawler = $this->client->submit($formElement);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isOk());

        // Check that validation error is displayed
        $validationText = 'Verification email is required when DOI is enabled';
        $pageContent = $crawler->filter('body')->text();
        $this->assertStringContainsString($validationText, $pageContent, "Validation text not found on the page");
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
        $form = $this->createForm('Test Existing DOI Config', 'test_existing_doi_config');
        $verificationEmail = $this->createEmail('Existing Verification Email', 'existing_verification');
        
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
        $form = $this->createForm('Test DOI Update Form', 'test_doi_update_form');
        $initialVerificationEmail = $this->createEmail('Initial Verification Email', 'initial_verification');
        $initialFollowUpEmail = $this->createEmail('Initial Follow-up Email', 'initial_followup');

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
        $newVerificationEmail = $this->createEmail('New Verification Email', 'new_verification');
        $newFollowUpEmail = $this->createEmail('New Follow-up Email', 'new_followup');

        // Request the edit form
        $crawler = $this->client->request('GET', sprintf('/s/forms/edit/%d', $form->getId()));
        $this->assertTrue($this->client->getResponse()->isOk());

        // Update the form with new values
        $formElement = $crawler->filterXPath('//form[@name="mauticform"]')->form();
        $formElement->setValues([
            'mauticform[doiConfig][enabled]' => '1',
            'mauticform[doiConfig][verificationEmailId]' => $newVerificationEmail->getId(),
            'mauticform[doiConfig][followUpEmailId]' => $newFollowUpEmail->getId(),
            'mauticform[doiConfig][successRedirectUrl]' => 'https://example.com/updated-success',
            'mauticform[doiConfig][errorRedirectUrl]' => 'https://example.com/updated-error',
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