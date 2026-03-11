<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\EmailRepository;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfigRepository;

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

    public function getFormDoiConfigByFormId(int $formId): ?FormDoiConfig
    {
        return $this->repository->findOneBy(['form' => $formId]);
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function saveFormDoiConfig(Form $form, array $formData): FormDoiConfig
    {
        // Get existing config or create new one
        $doiConfig = $this->getFormDoiConfig($form) ?? new FormDoiConfig();

        // Convert IDs back to entities
        if (isset($formData['verificationEmailId']) && $formData['verificationEmailId']) {
            $verificationEmail = $this->emailRepository->find($formData['verificationEmailId']);
            if ($verificationEmail) {
                $doiConfig->setVerificationEmail($verificationEmail);
            }
        } else {
            $doiConfig->setVerificationEmail(null);
        }

        if (isset($formData['followUpEmailId']) && $formData['followUpEmailId']) {
            $followUpEmail = $this->emailRepository->find($formData['followUpEmailId']);
            $doiConfig->setFollowUpEmail($followUpEmail);
        } else {
            $doiConfig->setFollowUpEmail(null);
        }

        $doiConfig->setSuccessRedirectUrl($formData['successRedirectUrl'] ?? null);
        $doiConfig->setErrorRedirectUrl($formData['errorRedirectUrl'] ?? null);
        $doiConfig->setEnabled((bool) $formData['enabled']);
        $doiConfig->setSkipOnCookie((bool) ($formData['skipOnCookie'] ?? false));
        $doiConfig->setSkipPostAction($formData['skipPostAction'] ?? null);
        $doiConfig->setSkipPostActionProperty($formData['skipPostActionProperty'] ?? null);
        $doiConfig->setDeleteAfterTimeout((bool) ($formData['deleteAfterTimeout'] ?? false));

        // Filter skip conditions to remove references to deleted form fields
        $skipConditions = $this->filterSkipConditions($form, $formData['skipConditions'] ?? null);
        $doiConfig->setSkipConditions($skipConditions);

        $doiConfig->setForm($form);
        $doiConfig->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($doiConfig);
        $this->entityManager->flush();

        return $doiConfig;
    }

    /**
     * Filter skip conditions to remove references to form fields that no longer exist.
     *
     * @param array<int, array<string, mixed>>|null $conditions
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function filterSkipConditions(Form $form, ?array $conditions): ?array
    {
        if (empty($conditions)) {
            return null;
        }

        // Get all current field aliases from the form
        $fieldAliases = $form->getFieldAliases();

        $filtered = array_values(array_filter(
            $conditions,
            function (array $condition) use ($fieldAliases): bool {
                // Keep conditions that are not based on form fields
                if ('form' !== ($condition['object'] ?? '')) {
                    return true;
                }

                // Keep conditions where the form field still exists
                $fieldAlias = $condition['field'] ?? '';

                return in_array($fieldAlias, $fieldAliases, true);
            }
        ));

        return $filtered ?: null;
    }
}
