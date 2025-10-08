<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Form\Extension;

use Mautic\FormBundle\Form\Type\FormType;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Form\Type\FormDoiConfigType;
use MauticPlugin\LeuchtfeuerDoiBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerDoiBundle\Model\DoiConfigManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;

class FormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private DoiConfigManager $doiConfigManager,
        private Config $pluginConfig,
        private RequestStack $requestStack
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
    }

    public function onPreSetData(FormEvent $event): void
    {
        $form   = $event->getForm();
        $entity = $event->getData();

        if ($entity->getId()) {
            // This is an existing form, load its config
            $doiConfig = $this->doiConfigManager->getFormDoiConfig($entity) ?? new FormDoiConfig();
        } else {
            // This is a new or cloned form.
            $doiConfig = null;

            if ($this->isCloneRequest()) {
                $doiConfig = $this->getClonedDoiConfigFromRequest();
            }

            // If it's not a clone or cloning failed, create a fresh config
            if (null === $doiConfig) {
                $doiConfig = new FormDoiConfig();
            }
        }

        // Convert entities to IDs for the form
        $formData = [
            'verificationEmailId' => $doiConfig->getVerificationEmail()?->getId(),
            'followUpEmailId'     => $doiConfig->getFollowUpEmail()?->getId(),
            'successRedirectUrl'  => $doiConfig->getSuccessRedirectUrl(),
            'errorRedirectUrl'    => $doiConfig->getErrorRedirectUrl(),
            'enabled'             => $doiConfig->isEnabled(),
        ];

        // Add DOI config fields with ID data
        $form->add('doiConfig', FormDoiConfigType::class, [
            'data'   => $formData,
            'mapped' => false,
        ]);
    }

    private function isCloneRequest(): bool
    {
        $mainRequest = $this->requestStack->getMainRequest();

        return $mainRequest && 'clone' === $mainRequest->attributes->get('objectAction');
    }

    private function getClonedDoiConfigFromRequest(): ?FormDoiConfig
    {
        $mainRequest  = $this->requestStack->getMainRequest();
        $sourceFormId = (int) $mainRequest?->attributes->get('objectId');

        if ($sourceFormId) {
            $originalDoiConfig = $this->doiConfigManager->getFormDoiConfigByFormId($sourceFormId);
            if ($originalDoiConfig) {
                return clone $originalDoiConfig;
            }
        }

        return null;
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
