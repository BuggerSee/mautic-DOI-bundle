<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\EmailRepository;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfigRepository;

class DoiConfigManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormDoiConfigRepository $repository,
        private EmailRepository $emailRepository,
    ) {
    }

    public function getFormDoiConfig(Form $form): ?FormDoiConfig
    {
        return $this->repository->findOneBy(['form' => $form]);
    }

    public function saveFormDoiConfig(Form $form, array $formData): FormDoiConfig
    {
        // Get existing config or create new one
        $doiConfig = $this->getFormDoiConfig($form) ?? new FormDoiConfig();

        // Convert IDs back to entities
        if (!empty($formData['verificationEmailId'])) {
            $verificationEmail = $this->emailRepository->find($formData['verificationEmailId']);
            if ($verificationEmail) {
                $doiConfig->setVerificationEmail($verificationEmail);
            }
        }

        if (!empty($formData['followUpEmailId'])) {
            $followUpEmail = $this->emailRepository->find($formData['followUpEmailId']);
            $doiConfig->setFollowUpEmail($followUpEmail);
        } else {
            $doiConfig->setFollowUpEmail(null);
        }

        $doiConfig->setSuccessRedirectUrl($formData['successRedirectUrl'] ?? null);
        $doiConfig->setErrorRedirectUrl($formData['errorRedirectUrl'] ?? null);

        $doiConfig->setForm($form);
        $doiConfig->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($doiConfig);
        $this->entityManager->flush();

        return $doiConfig;
    }
}