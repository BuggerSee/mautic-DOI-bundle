<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<FormDoiConfig>
 */
class FormDoiConfigRepository extends CommonRepository
{
    /**
     * Find all DOI configs that have cleanup enabled (deleteAfterTimeout is true).
     *
     * @return list<FormDoiConfig>
     */
    public function findConfigsWithCleanupEnabled(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.form', 'f')
            ->where('c.deleteAfterTimeout = :deleteEnabled')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('deleteEnabled', true)
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();
    }
}
