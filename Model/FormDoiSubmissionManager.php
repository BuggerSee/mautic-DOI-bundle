<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;

class FormDoiSubmissionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(FormDoiSubmission $doiSubmission): FormDoiSubmission
    {
        $this->entityManager->persist($doiSubmission);
        $this->entityManager->flush();

        return $doiSubmission;
    }
}
