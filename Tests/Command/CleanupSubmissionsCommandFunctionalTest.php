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
    // Submissions are deleted X days after their dateTimeout, not dateCreated
    // =========================================================================

    public function testTimedOutSubmissionIsDeletedAfterTimeoutPeriod(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create a submission that timed out 8 days ago (past the 7-day cleanup threshold)
        $timedOutSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'timedout@example.com',
            new \DateTime('-30 days') // Created 30 days ago
        );
        // Mark as timed out 8 days ago
        $timedOutSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $timedOutSubmission->setDateTimeout(new \DateTime('-8 days'));
        $this->em->persist($timedOutSubmission);
        $this->em->flush();

        $doiSubmissionId  = $timedOutSubmission->getId();
        $coreSubmissionId = $timedOutSubmission->getFormSubmission()->getId();

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

    public function testRecentlyTimedOutSubmissionIsNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create a submission that timed out only 6 days ago (not yet past 7-day cleanup threshold)
        $recentTimeoutSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'recenttimeout@example.com',
            new \DateTime('-30 days') // Created 30 days ago
        );
        // Mark as timed out only 6 days ago
        $recentTimeoutSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $recentTimeoutSubmission->setDateTimeout(new \DateTime('-6 days'));
        $this->em->persist($recentTimeoutSubmission);
        $this->em->flush();

        $doiSubmissionId = $recentTimeoutSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 0 submission', $output);

        // Verify submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Recently timed out submission should not be deleted');
    }

    public function testPendingSubmissionIsNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an old pending submission (never timed out)
        $pendingSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'pending@example.com',
            new \DateTime('-30 days') // Created 30 days ago but still pending
        );
        $doiSubmissionId = $pendingSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Deleted: 0 submission', $output);

        // Verify pending submission still exists (only timed-out submissions are cleaned up)
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Pending submission should not be deleted');
        Assert::assertTrue($existingSubmission->isPending(), 'Submission should remain pending');
    }

    public function testConfirmedSubmissionIsNotDeleted(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create an old but confirmed submission
        $confirmedSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'confirmed@example.com',
            new \DateTime('-30 days')
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

    public function testTimedOutSubmissionRemainsWhenCleanupNotEnabled(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('No Cleanup Form');
        // Create DOI config WITHOUT cleanup enabled (deleteAfterTimeoutDays = null)
        $this->createDoiConfigWithoutCleanup($form);

        // Create a timed-out submission (even very old ones should not be deleted)
        $oldSubmission = $this->formFixtureHelper->createDoiSubmission(
            $form,
            'old@example.com',
            new \DateTime('-60 days')
        );
        $oldSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $oldSubmission->setDateTimeout(new \DateTime('-30 days'));
        $this->em->persist($oldSubmission);
        $this->em->flush();

        $doiSubmissionId = $oldSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('No forms have cleanup enabled', $output);

        // Verify submission still exists
        $this->em->clear();
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($doiSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Submission should remain when cleanup is not enabled');
        Assert::assertSame(FormDoiSubmission::STATUS_TIMEOUT, $existingSubmission->getStatus());
    }

    /**
     * If contact was created by the form submission and is not known
     * to the system before this submission, the entire contact should be deleted.
     */
    public function testNewContactIsDeletedWithSubmission(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Cleanup Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create submission with a NEW contact that timed out 8 days ago
        $timedOutSubmission = $this->createTimedOutDoiSubmissionWithNewContact(
            $form,
            'newcontact@example.com',
            new \DateTime('-30 days'), // Created 30 days ago
            new \DateTime('-8 days')   // Timed out 8 days ago
        );
        $contactId       = $timedOutSubmission->getLead()->getId();
        $doiSubmissionId = $timedOutSubmission->getId();

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

        // Create an existing contact FIRST (2 months ago)
        $existingContact = $this->formFixtureHelper->createContact('knowncontact@example.com');
        // Backdate contact creation to 2 months ago
        $existingContact->setDateAdded(new \DateTime('-60 days'));
        $this->em->persist($existingContact);
        $this->em->flush();
        $contactId = $existingContact->getId();

        // Create a timed-out submission linked to this EXISTING contact
        $timedOutSubmission = $this->createTimedOutDoiSubmissionForExistingContact(
            $form,
            $existingContact,
            new \DateTime('-30 days'), // Created 30 days ago
            new \DateTime('-8 days')   // Timed out 8 days ago
        );
        $doiSubmissionId = $timedOutSubmission->getId();

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

    /**
     * If contact has other pending DOI submissions, the contact should NOT be deleted
     * even if it was created by this submission.
     */
    public function testContactWithOtherPendingSubmissionsIsNotDeleted(): void
    {
        $form1 = $this->formFixtureHelper->createFormViaApi('Form One');
        $form2 = $this->formFixtureHelper->createFormViaApi('Form Two');

        $this->createDoiConfigWithCleanup($form1, 7);
        $this->createDoiConfigWithCleanup($form2, 7);

        // Create a NEW contact with a timed-out submission on form1
        $timedOutSubmission = $this->createTimedOutDoiSubmissionWithNewContact(
            $form1,
            'multisubmit@example.com',
            new \DateTime('-30 days'), // Created 30 days ago
            new \DateTime('-8 days')   // Timed out 8 days ago
        );
        $contact              = $timedOutSubmission->getLead();
        $contactId            = $contact->getId();
        $timedOutSubmissionId = $timedOutSubmission->getId();

        // Create a second PENDING submission on form2 for the same contact (not timed out)
        $pendingSubmission = $this->createDoiSubmissionForExistingContact(
            $form2,
            $contact,
            new \DateTime('-5 days')
        );
        $pendingSubmissionId = $pendingSubmission->getId();

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions');
        $output        = $commandTester->getDisplay();

        // Only the timed-out submission should be deleted
        Assert::assertStringContainsString('Submissions deleted: 1', $output);

        // Verify timed-out submission is deleted
        $this->em->clear();
        $deletedSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($timedOutSubmissionId);
        Assert::assertNull($deletedSubmission, 'Timed-out DOI submission should be deleted');

        // Verify pending submission still exists
        $existingSubmission = $this->em->getRepository(FormDoiSubmission::class)->find($pendingSubmissionId);
        Assert::assertNotNull($existingSubmission, 'Pending submission should still exist');

        // Verify contact is NOT deleted (has other pending submission)
        $existingContact = $this->em->getRepository(Lead::class)->find($contactId);
        Assert::assertNotNull($existingContact, 'Contact with other pending submissions should NOT be deleted');
        Assert::assertSame('multisubmit@example.com', $existingContact->getEmail());
    }

    // =========================================================================
    // Command options tests
    // =========================================================================

    public function testLimitOption(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Limit Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create 3 timed-out submissions with unique emails (all timed out 10+ days ago)
        $sub1 = $this->createTimedOutDoiSubmission($form, 'limitopt1@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));
        $sub2 = $this->createTimedOutDoiSubmission($form, 'limitopt2@example.com', new \DateTime('-31 days'), new \DateTime('-11 days'));
        $sub3 = $this->createTimedOutDoiSubmission($form, 'limitopt3@example.com', new \DateTime('-32 days'), new \DateTime('-12 days'));

        // Verify all 3 submissions exist before running command
        $this->em->clear();
        $allTimedOutCount = $this->em->getRepository(FormDoiSubmission::class)->count([
            'form'   => $form->getId(),
            'status' => FormDoiSubmission::STATUS_TIMEOUT,
        ]);
        Assert::assertSame(3, $allTimedOutCount, 'Should have 3 timed-out submissions before cleanup');

        $commandTester = $this->testSymfonyCommand('leuchtfeuer:doi:cleanup-submissions', ['--limit' => '2']);
        $output        = $commandTester->getDisplay();

        Assert::assertStringContainsString('Submissions deleted: 2', $output);

        // Verify only 1 submission remains
        $this->em->clear();
        $remainingCount = $this->em->getRepository(FormDoiSubmission::class)->count([
            'form'   => $form->getId(),
            'status' => FormDoiSubmission::STATUS_TIMEOUT,
        ]);
        Assert::assertSame(1, $remainingCount, 'Should have 1 timed-out submission after cleanup with limit 2');
    }

    public function testBatchOption(): void
    {
        $form = $this->formFixtureHelper->createFormViaApi('Batch Test Form');
        $this->createDoiConfigWithCleanup($form, 7);

        // Create 5 timed-out submissions (all past cleanup threshold)
        for ($i = 1; $i <= 5; ++$i) {
            $this->createTimedOutDoiSubmission(
                $form,
                "batch{$i}@example.com",
                new \DateTime('-'.(30 + $i).' days'), // Created 30+ days ago
                new \DateTime('-'.(7 + $i).' days')   // Timed out 8-12 days ago
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

        $submission1 = $this->createTimedOutDoiSubmission($form1, 'form1@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));
        $submission2 = $this->createTimedOutDoiSubmission($form2, 'form2@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));

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

        $submission1 = $this->createTimedOutDoiSubmission($form, 'verbose1@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));
        $submission2 = $this->createTimedOutDoiSubmission($form, 'verbose2@example.com', new \DateTime('-31 days'), new \DateTime('-11 days'));

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

        $submission   = $this->createTimedOutDoiSubmission($form, 'veryverbose@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));
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

        // Both timed out 10 days ago: past 7-day cleanup threshold, but not past 14-day threshold
        $submission7days  = $this->createTimedOutDoiSubmission($form7days, 'form7@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));
        $submission14days = $this->createTimedOutDoiSubmission($form14days, 'form14@example.com', new \DateTime('-30 days'), new \DateTime('-10 days'));

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

    /**
     * Create a timed-out DOI submission.
     * Cleanup is based on dateTimeout, not dateCreated.
     */
    private function createTimedOutDoiSubmission(
        Form $form,
        string $email,
        \DateTime $dateCreated,
        \DateTime $dateTimeout
    ): FormDoiSubmission {
        $doiSubmission = $this->formFixtureHelper->createDoiSubmission($form, $email, $dateCreated);
        $doiSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $doiSubmission->setDateTimeout($dateTimeout);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }

    /**
     * Create a timed-out DOI submission for a NEW contact (contact created at same time as submission).
     * This simulates a contact that was created by the form submission and then timed out.
     */
    private function createTimedOutDoiSubmissionWithNewContact(
        Form $form,
        string $email,
        \DateTime $dateCreated,
        \DateTime $dateTimeout
    ): FormDoiSubmission {
        // Create contact with same dateAdded as submission dateCreated
        $contact = new Lead();
        $contact->setEmail($email);
        $contact->setDateAdded($dateCreated);
        $contact->setDateIdentified($dateCreated);
        $this->em->persist($contact);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateCreated);
        $submission->setReferer('https://example.com/');
        $submission->setLead($contact);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($contact);
        $doiSubmission->setEmail($email);
        $doiSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $doiSubmission->setHash(hash('sha256', $email.time().random_int(0, 100000)));
        $doiSubmission->setDateCreated($dateCreated);
        $doiSubmission->setDateTimeout($dateTimeout);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }

    /**
     * Create a timed-out DOI submission for an EXISTING contact.
     * This simulates a contact that was already known and whose submission timed out.
     */
    private function createTimedOutDoiSubmissionForExistingContact(
        Form $form,
        Lead $existingContact,
        \DateTime $dateCreated,
        \DateTime $dateTimeout
    ): FormDoiSubmission {
        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDateSubmitted($dateCreated);
        $submission->setReferer('https://example.com/');
        $submission->setLead($existingContact);
        $this->em->persist($submission);

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission->setForm($form);
        $doiSubmission->setFormSubmission($submission);
        $doiSubmission->setLead($existingContact);
        $doiSubmission->setEmail($existingContact->getEmail());
        $doiSubmission->setStatus(FormDoiSubmission::STATUS_TIMEOUT);
        $doiSubmission->setHash(hash('sha256', $existingContact->getEmail().time().random_int(0, 100000)));
        $doiSubmission->setDateCreated($dateCreated);
        $doiSubmission->setDateTimeout($dateTimeout);
        $this->em->persist($doiSubmission);
        $this->em->flush();

        return $doiSubmission;
    }
}
