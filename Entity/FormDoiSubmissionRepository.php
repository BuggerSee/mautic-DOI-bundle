<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<FormDoiSubmission>
 */
class FormDoiSubmissionRepository extends CommonRepository
{
    /**
     * @return FormDoiSubmission[]
     */
    public function findPendingDueForFollowup(
        \DateTimeInterface $threshold,
        int $limit = 500
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.status = :pending')
            ->andWhere('s.dateConfirmed IS NULL')
            ->andWhere('s.dateCreated <= :threshold')
            ->andWhere('s.dateFollowupSent IS NULL')
            ->setParameter('pending', 'pending')
            ->setParameter('threshold', $threshold)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

}
