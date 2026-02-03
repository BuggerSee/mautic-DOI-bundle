<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Tests\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\Submission;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\FormFixtureHelper;
use MauticPlugin\LeuchtfeuerDoiBundle\Tests\Fixtures\PluginFixtureHelper;
use PHPUnit\Framework\Assert;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupSubmissionsCommandFunctionalTest extends MauticMysqlTestCase
{
    protected $useCleanupRollback = false;

    private PluginFixtureHelper $pluginFixtureHelper;
    private FormFixtureHelper $formFixtureHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pluginFixtureHelper = new PluginFixtureHelper($this->em);
        $this->pluginFixtureHelper->createAndEnablePlugin();
        $this->formFixtureHelper = new FormFixtureHelper($this->em, $this->client);
    }

    // =========================================================================
    // Test Case: Cleanup enabled with deleteAfterTimeoutDays set
    // =========================================================================

    public function testExpiredSubmissionIsDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an expired submission (8 days old)
        $expiredSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'expired@example.com',
            new \DateTime('-8 days')
        );
        $doiSubmissionId  = $expiredSubmission->getId();
        $coreSubmissionId = $expiredSubmission->getFormSubmission()->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 1 submission', $output);

        // Verify DOI submission is deleted
        $this->em->clear();
        $deletedDoiSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNull($deletedDoiSubmission, 'DOI submission should be deleted');

        // Verify core submission is deleted
        $deletedCoreSubmission = $this->em->getRepository(Submission::class)->find($coreSubmissionId);
        Assert::assertNull($deletedCoreSubmission, 'Core submission should be deleted');
    }

    public function testRecentSubmissionIsNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create a recent submission (6 days old - not yet expired)
        $recentSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'recent@example.com',
            new \DateTime('-6 days')
        );
        $doiSubmissionId = $recentSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 0 submission', $output);

        // Verify submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Recent submission should not be deleted');
    }

    public function testConfirmedSubmissionIsNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an expired but confirmed submission
        $confirmedSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'confirmed@example.com',
            new \DateTime('-8 days')
        );
        $confirmedSubmission->confirm();
        $this->em->persist($confirmedSubmission);
        $this->em->flush();
        $doiSubmissionId = $confirmedSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 0 submission', $output);

        // Verify confirmed submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Confirmed submission should not be deleted');
        Assert::assertTrue($existingSubmission->isConfirmed(), 'Submission should remain confirmed');
    }

    // =========================================================================
    // Test Case: deleteAfterTimeoutDays = null (never delete)
    // =========================================================================

    public function testSubmissionRemainsWhenCleanupNotEnabled(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('No Cleanup Form');
        // Create DOI config WITHOUT cleanup enabled (deleteAfterTimeoutDays = null)
        $this->createDoiConfigWithoutCleanup($form);

        // Create a very old pending submission (30 days)
        $oldSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'old@example.com',
            new \DateTime('-30 days')
        );
        $doiSubmissionId = $oldSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('No forms have cleanup enabled', $output);

        // Verify submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Submission should remain when cleanup is not enabled');
        Assert::assertSame('pending', $existingSubmission->getStatus(), 'Submission status should remain pending');
    }

    /**
     * If contact was created by the form submission and is not known
     * to the system before this submission, the entire contact should be deleted.
     */
    public function testNewContactIsDeletedWithSubmission(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create submission with a NEW contact (contact created at same time as submission)
        $expiredSubmission = $this->createDoiSubmissionWithNewContact(
            $form,
            'newcontact@example.com',
            new \DateTime('-8 days')
        );
        $contactId       = $expiredSubmission->getLead()->getId();
        $doiSubmissionId = $expiredSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 1 submission', $output);

        // Verify submission is deleted
        $this->em->clear();
        $deletedSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNull($deletedSubmission, 'DOI submission should be deleted');

        // Verify NEW contact is also deleted
        $deletedContact = $this->em->getRepository(Lead::class)->find($contactId);
        Assert::assertNull($deletedContact?->getId(), 'New contact should be deleted with the submission');
    }

    /**
     * If contact was already known to the system before this submission,
     * only the submission is deleted; the contact remains.
     */
    public function testPreviouslyKnownContactRemainsAfterSubmissionDeletion(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an existing contact FIRST (1 month ago)
        $existingContact = $this->formFixtureHelper->createContact('knowncontact@example.com');
        // Backdate contact creation to 1 month ago
        $existingContact->setDateAdded(new \DateTime('-30 days'));
        $this->em->persist($existingContact);
        $this->em->flush();
        $contactId = $existingContact->getId();

        // Create an expired submission linked to this EXISTING contact
        $expiredSubmission = $this->createDoiSubmissionForExistingContact(
            $form,
            $existingContact,
            new \DateTime('-8 days')
        );
        $doiSubmissionId = $expiredSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 1 submission', $output);

        // Verify submission is deleted
        $this->em->clear();
        $deletedSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNull($deletedSubmission, 'DOI submission should be deleted');

        // Verify KNOWN contact still exists
        $existingContactReloaded = $this->em->getRepository(Lead::class)->find($contactId);
        Assert::assertNotNull($existingContactReloaded, 'Previously known contact should NOT be deleted');
        Assert::assertSame('knowncontact@example.com', $existingContactReloaded->getEmail());
    }

    // =========================================================================
    // Command options tests
    // =========================================================================

    public function testLimitOption(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Limit Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create 3 expired submissions with unique emails
        $sub1 = $this->formFixtureHelper->createDoiSubmission($form, 'limitopt1@example.com', new \DateTime('-10 days'));
        $sub2 = $this->formFixtureHelper->createDoiSubmission($form, 'limitopt2@example.com', new \DateTime('-11 days'));
        $sub3 = $this->formFixtureHelper->createDoiSubmission($form, 'limitopt3@example.com', new \DateTime('-12 days'));

        // Verify all 3 submissions exist before running command
        $this->em->clear();
        $allPendingCount = $this->em->getRepository(FormDoiSubmission::class)->count([
            'form'   => $form->getId(),
            'status' => FormDoiSubmission::STATUS_PENDING,
        ]);
        Assert::assertSame(3, $allPendingCount, 'Should have 3 pending submissions before cleanup');

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--limit' => '2']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Submissions deleted: 2', $output);

        // Verify only 1 submission remains
        $this->em->clear();
        $remainingCount = $this->em->getRepository(FormDoiSubmission::class)->count([
            'form'   => $form->getId(),
            'status' => FormDoiSubmission::STATUS_PENDING,
        ]);
        Assert::assertSame(1, $remainingCount, 'Should have 1 pending submission after cleanup with limit 2');
    }

    public function testBatchOption(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Batch Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create 5 expired submissions
        for ($i = 1; $i <= 5; ++$i) {
            $this->formFixtureHelper->createDoiSubmission(
                $form,
                "batch{$i}@example.com",
                new \DateTime('-'.(7 + $i).' days')
            );
        }

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--batch' => '2']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 5 submission', $output);
    }

    public function testFormIdFilterOption(): void
    {
        $form1 = $this->formFixtureHelper->createFormViaApi('Form One');
        $form2 = $this->formFixtureHelper->createFormViaApi('Form Two');

        $this->createDoiConfigWithCleanup($form1, 7);
        $this->createDoiConfigWithCleanup($form2, 7);

        $submission1 = $this->formFixtureHelper->createDoiSubmission($form1, 'form1@example.com', new \DateTime('-10 days'));
        $submission2 = $this->formFixtureHelper->createDoiSubmission($form2, 'form2@example.com', new \DateTime('-10 days'));

        $submission1Id = $submission1->getId();
        $submission2Id = $submission2->getId();

        // Only cleanup form1
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--form-id' => $form1->getId()]);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 1 submissions', $output);

        // Verify only form1 submission is deleted
        $this->em->clear();
        $deletedSubmission1  = $this->em->getRepository(FormDoiSubmission::class)->find($submission1Id);
        $existingSubmission2 = $this->em->getRepository(FormDoiSubmission::class)->find($submission2Id);

        Assert::assertNull($deletedSubmission1, 'Form1 submission should be deleted');
        Assert::assertNotNull($existingSubmission2, 'Form2 submission should remain');
    }

    public function testInvalidFormIdOption(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Valid Form');
        $this->createDoiConfigWithCleanup($form, 7);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--form-id' => '99999']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('does not have DOI cleanup enabled', $output);
    }

    public function testCommandFailsWhenPluginDisabled(): void
    {
        $this->pluginFixtureHelper->disablePlugin();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('DOI plugin is disabled', $output);
    }

    // =========================================================================
    // Verbose output tests
    // =========================================================================

    public function testVerboseOutputShowsDeletedIds(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Verbose Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        $submission1 = $this->formFixtureHelper->createDoiSubmission($form, 'verbose1@example.com', new \DateTime('-10 days'));
        $submission2 = $this->formFixtureHelper->createDoiSubmission($form, 'verbose2@example.com', new \DateTime('-11 days'));

        $id1 = $submission1->getId();
        $id2 = $submission2->getId();

        $kernel        = static::getContainer()->get('kernel');
        $application   = new Application($kernel);
        $command       = $application->find('leuchtfeuer:doi:cleanup-submissions');
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $output = $commandTester->getDisplay();

        Assert::assertStringContainsString((string) $id1, $output);
        Assert::assertStringContainsString((string) $id2, $output);
    }

    public function testVeryVerboseOutputShowsPerSubmissionDetails(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Very Verbose Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        $submission   = $this->formFixtureHelper->createDoiSubmission($form, 'veryverbose@example.com', new \DateTime('-10 days'));
        $submissionId = $submission->getId();

        $kernel        = static::getContainer()->get('kernel');
        $application   = new Application($kernel);
        $command       = $application->find('leuchtfeuer:doi:cleanup-submissions');
        $commandTester = new CommandTester($command);
        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE]);
        $output = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted DOI submission ID', $output);
        Assert::assertStringContainsString((string) $submissionId, $output);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testMultipleFormsWithDifferentTimeouts(): void
    {
        $form7days  = $this->formFixtureHelper->createFormViaApi('7 Day Timeout Form');
        $form14days = $this->formFixtureHelper->createFormViaApi('14 Day Timeout Form');

        $this->createDoiConfigWithCleanup($form7days, 7);
        $this->createDoiConfigWithCleanup($form14days, 14);

        // 10-day old submissions: expired for 7-day form, not expired for 14-day form
        $submission7days  = $this->formFixtureHelper->createDoiSubmission($form7days, 'form7@example.com', new \DateTime('-10 days'));
        $submission14days = $this->formFixtureHelper->createDoiSubmission($form14days, 'form14@example.com', new \DateTime('-10 days'));

        $id7days  = $submission7days->getId();
        $id14days = $submission14days->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 1 submissions', $output);

        // Verify only the 7-day form submission is deleted
        $this->em->clear();
        $deleted7days   = $this->em->getRepository(FormDoiSubmission::class)->find($id7days);
        $existing14days = $this->em->getRepository(FormDoiSubmission::class)->find($id14days);

        Assert::assertNull($deleted7days, '7-day form submission should be deleted');
        Assert::assertNotNull($existing14days, '14-day form submission should remain');
    }

    public function testSkippedSubmissionsAreNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Skipped Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an expired but skipped submission
        $skippedSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'skipped@example.com',
            new \DateTime('-8 days')
        );
        $skippedSubmission->skip(FormDoiSubmission::SKIP_REASON_COOKIE_MATCH);
        $this->em->persist($skippedSubmission);
        $this->em->flush();
        $doiSubmissionId = $skippedSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 0 submission', $output);

        // Verify skipped submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Skipped submission should not be deleted');
        Assert::assertSame(FormDoiSubmission::STATUS_SKIPPED, $existingSubmission->getStatus());
    }

    public function testNoFormsWithCleanupEnabled(): void
    {
        // Don't create any forms with cleanup enabled
        $form = $this->formFixtureHelper->createFormViaApi('No Cleanup Form');
        $this->createDoiConfigWithoutCleanup($form);

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('No forms have cleanup enabled', $output);
    }

    // =========================================================================
    // Validation tests
    // =========================================================================

    public function testInvalidBatchSizeReturnsError(): void
    {
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--batch' => '0']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Batch size must be a positive integer', $output);
    }

    public function testInvalidLimitReturnsError(): void
    {
        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--limit' => '-1']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Limit must be a positive integer', $output);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function createDoiConfigWithCleanup(Form $form, int $deleteAfterDays): FormDoiConfig
    {
        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setEnabled(true);
        $config->setDeleteAfterTimeoutDays($deleteAfterDays);
        $config->setSuccessRedirectUrl('https://example.com/success');
        $config->setErrorRedirectUrl('https://example.com/error');
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    private function createDoiConfigWithoutCleanup(Form $form): FormDoiConfig
    {
        $config = new FormDoiConfig();
        $config->setForm($form);
        $config->setEnabled(true);
        $config->setDeleteAfterTimeoutDays(null); // Cleanup NOT enabled
        $config->setSuccessRedirectUrl('https://example.com/success');
        $config->setErrorRedirectUrl('https://example.com/error');
        $this->em->persist($config);
        $this->em->flush();

        return $config;
    }

    /**
     * Create a DOI submission for a NEW contact (contact created at same time as submission).
     * This simulates a contact that was created by the form submission.
     */
    private function createDoiSubmissionWithNewContact(
        Form $form,
        string $email,
        \DateTime $dateSubmitted
    ): FormDoiSubmission {
        // Create contact with same dateAdded as submission dateSubmitted
        $contact = new Lead();
        $contact->setEmail($email);
        $contact->setDateAdded($dateSubmitted);
        $contact->setDateIdentified($dateSubmitted);
        $this->em->persist($contact);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateSubmitted);
        $submission->setReferer('https://example.com/');
        $submission->setLead($contact);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($contact);
        $doiSubmission->setEmail($email);
        $doiSubmission->setStatus(FormDoiSubmission::STATUS_PENDING);
        $doiSubmission->setHash(hash('sha256', $email.time().random_int(0, 100000)));
        $doiSubmission->setDateCreated($dateSubmitted);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }

    /**
     * Create a DOI submission for an EXISTING contact (contact created before submission).
     * This simulates a contact that was already known to the system.
     */
    private function createDoiSubmissionForExistingContact(
        Form $form,
        Lead $existingContact,
        \DateTime $dateSubmitted
    ): FormDoiSubmission {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateSubmitted);
        $submission->setReferer('https://example.com/');
        $submission->setLead($existingContact);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($existingContact);
        $doiSubmission->setEmail($existingContact->getEmail());
        $doiSubmission->setStatus(FormDoiSubmission::STATUS_PENDING);
        $doiSubmission->setHash(hash('sha256', $existingContact->getEmail().time().random_int(0, 100000)));
        $doiSubmission->setDateCreated($dateSubmitted);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }
}
