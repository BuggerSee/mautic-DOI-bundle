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
}
