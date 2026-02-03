<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\FormBundle\Entity\Submission;
use Mautic\FormBundle\Helper\FormUploader;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfigRepository;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmissionRepository;
use Psr\Log\LoggerInterface;

class SubmissionCleanupService
{
    /**
     * Cache for table existence checks to avoid repeated schema queries.
     *
     * @var array<string, bool>
     */
    private array $tableExistsCache = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormDoiConfigRepository $configRepository,
        private FormDoiSubmissionRepository $submissionRepository,
        private FormUploader $formUploader,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get the DBAL connection from the EntityManager to ensure transactional consistency.
     */
    private function getConnection(): Connection
    {
        return $this->entityManager->getConnection();
    }

    /**
     * Get all DOI configs that have cleanup enabled.
     *
     * @return list<FormDoiConfig>
     */
    public function getConfigsWithCleanupEnabled(): array
    {
        return $this->configRepository->findConfigsWithCleanupEnabled();
    }

    /**
     * Calculate expiry threshold for a given config.
     */
    public function getExpiryThreshold(FormDoiConfig $config): \DateTimeImmutable
    {
        $days = $config->getDeleteAfterTimeoutDays();
        if (null === $days) {
            throw new \InvalidArgumentException('Config does not have deleteAfterTimeoutDays set');
        }

        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $nowUtc->modify(sprintf('-%d days', $days));
    }

    /**
     * Find expired pending submissions for a given config.
     *
     * @return list<FormDoiSubmission>
     */
    public function findExpiredSubmissions(FormDoiConfig $config, int $limit): array
    {
        $form = $config->getForm();
        if (null === $form) {
            return [];
        }

        $threshold = $this->getExpiryThreshold($config);

        return $this->submissionRepository->findExpiredPendingSubmissions(
            $form->getId(),
            $threshold,
            $limit
        );
    }

    /**
     * Delete a single DOI submission and all related data.
     *
     * If the contact was created by this submission (new contact), the contact
     * is also deleted. If the contact existed before the submission (previously known),
     * only the submission is deleted and the contact remains.
     *
     * @return bool True if deleted, false if skipped
     */
    public function deleteSubmission(FormDoiSubmission $doiSubmission): bool
    {
        $coreSubmission = $doiSubmission->getFormSubmission();
        if (null === $coreSubmission) {
            $this->logger->warning('DOI submission has no linked core submission', [
                'doiSubmissionId' => $doiSubmission->getId(),
            ]);

            return false;
        }

        $form = $doiSubmission->getForm();
        if (null === $form) {
            $this->logger->warning('DOI submission has no linked form', [
                'doiSubmissionId' => $doiSubmission->getId(),
            ]);

            return false;
        }

        $formId    = $form->getId();
        $formAlias = $form->getAlias();
        $conn      = $this->getConnection();

        // Determine if contact should be deleted (new contact created by this submission)
        $lead                = $doiSubmission->getLead();
        $shouldDeleteContact = $this->shouldDeleteContact($lead, $doiSubmission);

        try {
            $conn->beginTransaction();

            // delete from form_results table (DBAL - no ORM entity)
            $this->deleteFormResultsRow($formId, $formAlias, $coreSubmission);

            // Detach/Remove the DOI Submission explicitly first to avoid "new entity found" cascade errors
            // when flushing the core submission removal.
            $this->entityManager->remove($doiSubmission);

            // delete core Submission entity (cascades to FormDoiSubmission via FK)
            $this->entityManager->remove($coreSubmission);

            // Delete contact if it was created by this submission
            if ($shouldDeleteContact && null !== $lead) {
                $this->entityManager->remove($lead);
            }

            $this->entityManager->flush();

            $conn->commit();

            // Delete uploaded files AFTER successful DB commit to avoid data inconsistency
            // (orphaned files are preferable to deleted files with remaining DB records)
            $this->deleteUploadedFiles($coreSubmission);

            return true;
        } catch (\Throwable $e) {
            try {
                if ($conn->isTransactionActive()) {
                    $conn->rollBack();
                }
            } catch (\Throwable $rollbackError) {
                $this->logger->error('Rollback failed', [
                    'error' => $rollbackError->getMessage(),
                ]);
            }

            $this->logger->error('Failed to delete DOI submission', [
                'doiSubmissionId'  => $doiSubmission->getId(),
                'coreSubmissionId' => $coreSubmission->getId(),
                'error'            => $e->getMessage(),
            ]);

            // Clear EntityManager to prevent inconsistent state
            if ($this->entityManager->isOpen()) {
                $this->entityManager->clear();
            }

            return false;
        }
    }

    /**
     * Determine if a contact should be deleted along with the submission.
     *
     * A contact is considered "new" (created by this submission) if the contact's
     * dateIdentified matches the submission's dateCreated. If the contact existed before
     * the submission was created, it is considered "previously known" and should not be deleted.
     *
     * Additionally, if the contact has other pending DOI submissions, it should not be deleted.
     */
    private function shouldDeleteContact(?Lead $lead, FormDoiSubmission $doiSubmission): bool
    {
        if (null === $lead) {
            return false;
        }

        $leadId       = $lead->getId();
        $submissionId = $doiSubmission->getId();

        if (null === $leadId || null === $submissionId) {
            return false;
        }

        // Don't delete if contact has other pending DOI submissions
        if ($this->submissionRepository->hasOtherPendingSubmissionsForLead($leadId, $submissionId)) {
            return false;
        }

        $contactDateIdentified = $lead->getDateIdentified();
        $submissionDateCreated = $doiSubmission->getDateCreated();

        if (null === $contactDateIdentified) {
            return false;
        }

        // Contact is considered "new" if it was identified at the same time as the submission
        return $contactDateIdentified->getTimestamp() === $submissionDateCreated->getTimestamp();
    }

    /**
     * Clear the EntityManager to free memory during batch processing.
     */
    public function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }

    private function deleteFormResultsRow(int $formId, string $formAlias, Submission $submission): void
    {
        $submissionId = $submission->getId();

        // Guard against null submission ID to prevent unintended deletions
        if ($submissionId < 1) {
            $this->logger->error('Core submission has no ID; skipping form_results deletion', [
                'formId'    => $formId,
                'formAlias' => $formAlias,
            ]);

            return;
        }

        // Validate form alias contains only safe characters (alphanumeric and underscore)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $formAlias)) {
            $this->logger->error('Invalid form alias detected, skipping form_results deletion', [
                'formId'    => $formId,
                'formAlias' => $formAlias,
            ]);

            return;
        }

        $tableName = MAUTIC_TABLE_PREFIX.'form_results_'.$formId.'_'.$formAlias;
        $conn      = $this->getConnection();

        // Cache table existence check to avoid repeated schema queries
        if (!isset($this->tableExistsCache[$tableName])) {
            $schemaManager                      = $conn->createSchemaManager();
            $this->tableExistsCache[$tableName] = $schemaManager->tablesExist([$tableName]);
        }

        if (!$this->tableExistsCache[$tableName]) {
            return;
        }

        $conn->delete($tableName, ['submission_id' => $submissionId]);
    }

    private function deleteUploadedFiles(Submission $submission): void
    {
        try {
            $this->formUploader->deleteUploadedFiles($submission);
        } catch (\Throwable $e) {
            // Log but don't fail the whole operation if file deletion fails
            $this->logger->warning('Failed to delete uploaded files for submission', [
                'submissionId' => $submission->getId(),
                'error'        => $e->getMessage(),
            ]);
        }
    }
}
