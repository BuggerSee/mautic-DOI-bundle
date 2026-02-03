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
     * Find expired pending submissions for a specific form.
     *
     * @return list<FormDoiSubmission>
     */
    public function findExpiredPendingSubmissions(
        int $formId,
        \DateTimeInterface $threshold,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->innerJoin('s.form', 'f')
            ->innerJoin('s.formSubmission', 'fs')
            ->where('s.status = :pending')
            ->andWhere('s.dateCreated < :threshold')
            ->andWhere('f.id = :formId')
            ->setParameter('pending', FormDoiSubmission::STATUS_PENDING)
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
     * Check if a lead has other pending DOI submissions besides the given one.
     */
    public function hasOtherPendingSubmissionsForLead(int $leadId, int $excludeSubmissionId): bool
    {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.lead = :leadId')
            ->andWhere('s.status = :pending')
            ->andWhere('s.id != :excludeId')
            ->setParameter('leadId', $leadId)
            ->setParameter('pending', FormDoiSubmission::STATUS_PENDING)
            ->setParameter('excludeId', $excludeSubmissionId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
