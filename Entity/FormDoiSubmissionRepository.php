<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Entity;

use Doctrine\Common\Collections\Criteria;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<FormDoiSubmission>
 */
class FormDoiSubmissionRepository extends CommonRepository
{
    /**
     * Find timed-out submissions eligible for cleanup for a specific form.
     *
     * Only submissions with STATUS_TIMEOUT and dateTimeout older than the threshold are returned.
     *
     * @return list<FormDoiSubmission>
     */
    public function findTimedOutSubmissionsForCleanup(
        int $formId,
        \DateTimeInterface $threshold,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.form', 'f')
            ->innerJoin('s.formSubmission', 'fs')
            ->where('s.status = :timeout')
            ->andWhere('s.dateTimeout IS NOT NULL')
            ->andWhere('s.dateTimeout < :threshold')
            ->andWhere('f.id = :formId')
            ->setParameter('timeout', FormDoiSubmission::STATUS_TIMEOUT)
            ->setParameter('threshold', $threshold)
            ->setParameter('formId', $formId)
            ->orderBy('s.id', Criteria::ASC);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<FormDoiSubmission>
     */
    public function findPendingDueForFollowup(
        \DateTimeInterface $threshold,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.form', 'f')
            ->innerJoin(FormDoiConfig::class, 'dc', 'WITH', 'dc.form = f')
            ->andWhere('s.status = :pending')
            ->andWhere('s.dateConfirmed IS NULL')
            ->andWhere('s.dateCreated <= :threshold')
            ->andWhere('s.dateFollowupSent IS NULL')
            ->andWhere('dc.followUpEmail IS NOT NULL')
            ->setParameter('pending', FormDoiSubmission::STATUS_PENDING)
            ->setParameter('threshold', $threshold)
            ->orderBy('s.id', Criteria::DESC);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find pending submissions that have exceeded the expiration threshold.
     *
     * @return list<FormDoiSubmission>
     */
    public function findPendingExpired(
        \DateTimeInterface $expirationThreshold,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.status = :pending')
            ->andWhere('s.dateCreated < :threshold')
            ->setParameter('pending', FormDoiSubmission::STATUS_PENDING)
            ->setParameter('threshold', $expirationThreshold)
            ->orderBy('s.id', Criteria::ASC);

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if a lead has other active DOI submissions (pending or confirmed) besides the given one.
     */
    public function hasOtherActiveSubmissionsForLead(int $leadId, int $excludeSubmissionId): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.lead = :leadId')
            ->andWhere('s.status IN (:activeStatuses)')
            ->andWhere('s.id != :excludeId')
            ->setParameter('leadId', $leadId)
            ->setParameter('activeStatuses', [
                FormDoiSubmission::STATUS_PENDING,
                FormDoiSubmission::STATUS_CONFIRMED,
            ])
            ->setParameter('excludeId', $excludeSubmissionId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
