<?php

namespace MauticPlugin\MauticDoiBundle\Form\Extension;

use Mautic\FormBundle\Form\Type\FormType;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Form\Type\FormDoiConfigType;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class FormTypeExtension extends AbstractTypeExtension {
    public function __construct(
        private DoiConfigManager $doiConfigService
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'onPreSetData']);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'onPostSubmit']);
    }

    public function onPreSetData(FormEvent $event): void
    {
        $form = $event->getForm();
        $entity = $event->getData();

        if (!$entity || !$entity->getId()) {
            return;
        }

        // Load existing DOI config
        $doiConfig = $this->doiConfigService->getFormDoiConfig($entity) ?? new FormDoiConfig();

        // Convert entities to IDs for the form
        $formData = [
            'verificationEmailId' => $doiConfig->getVerificationEmail()?->getId(),
            'followUpEmailId' => $doiConfig->getFollowUpEmail()?->getId(),
            'successRedirectUrl' => $doiConfig->getSuccessRedirectUrl(),
            'errorRedirectUrl' => $doiConfig->getErrorRedirectUrl(),
        ];

        // Add DOI config fields with ID data
        $form->add('doiConfig', FormDoiConfigType::class, [
            'data' => $formData,
            'mapped' => false,
        ]);
    }

    public function onPostSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $entity = $event->getData();

        if (!$form->has('doiConfig') || !$form->get('doiConfig')->isValid()) {
            return;
        }

        $formData = $form->get('doiConfig')->getData();

        // Convert the form data (IDs) back to a proper FormDoiConfig entity
        $this->doiConfigService->saveFormDoiConfig($entity, $formData);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}