<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Form\Type;

use Mautic\EmailBundle\Form\Type\EmailListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class FormDoiConfigType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('verificationEmailId', EmailListType::class, [
                'label' => 'mautic.plugin.doi.form.field.verification_email',
                'label_attr' => ['class' => 'control-label'],
                'required' => true,
                'multiple' => false,
                'model' => 'email',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.verification_email.tooltip',
                ],
                'validation_groups' => ['doi_config'],
                'constraints'       => [
                    new NotBlank([
                        'message' => 'mautic.core.value.required',
                    ]),
                ],
            ])
            ->add('followUpEmailId', EmailListType::class, [
                'label' => 'mautic.plugin.doi.form.field.followup_email',
                'label_attr' => ['class' => 'control-label'],
                'required' => false,
                'multiple' => false,
                'model' => 'email',
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.followup_email.tooltip',
                ],
            ])
            ->add('successRedirectUrl', UrlType::class, [
                'label' => 'mautic.plugin.doi.form.field.success_redirect_url',
                'label_attr' => ['class' => 'control-label'],
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.success_redirect_url.tooltip',
                ],
            ])
            ->add('errorRedirectUrl', UrlType::class, [
                'label' => 'mautic.plugin.doi.form.field.error_redirect_url',
                'label_attr' => ['class' => 'control-label'],
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.error_redirect_url.tooltip',
                ],
            ]);
    }
}