<?php

namespace MauticPlugin\MauticDoiBundle\Form\Extension;

use Mautic\FormBundle\Form\Type\FormType;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Form\Type\FormDoiConfigType;
use MauticPlugin\MauticDoiBundle\Integration\Config;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class FormTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private DoiConfigManager $doiConfigManager,
        private Config $pluginConfig
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

        // Load existing DOI config
        if (!$entity->getId()) {
            $doiConfig = new FormDoiConfig();
        } else {
            $doiConfig = $this->doiConfigManager->getFormDoiConfig($entity) ?? new FormDoiConfig();
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

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
