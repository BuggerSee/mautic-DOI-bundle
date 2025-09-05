<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Form\Type;

use Mautic\CoreBundle\Form\Type\YesNoButtonGroupType;
use Mautic\EmailBundle\Form\Type\EmailListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class FormDoiConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('enabled', YesNoButtonGroupType::class, [
                'label' => 'mautic.plugin.doi.form.field.enabled',
                'attr'  => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.enabled.tooltip',
                ],
            ])
            ->add('verificationEmailId', EmailListType::class, [
                'label'      => 'mautic.plugin.doi.form.field.verification_email',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'multiple'   => false,
                'model'      => 'email',
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.verification_email.tooltip',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'mautic.plugin.doi.form.field.verification_email.required',
                        'groups'  => ['doi_enabled'],
                    ]),
                ],
            ])
            ->add('followUpEmailId', EmailListType::class, [
                'label'      => 'mautic.plugin.doi.form.field.followup_email',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'multiple'   => false,
                'model'      => 'email',
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.followup_email.tooltip',
                ],
            ])
            ->add('successRedirectUrl', UrlType::class, [
                'label'      => 'mautic.plugin.doi.form.field.success_redirect_url',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.success_redirect_url.tooltip',
                ],
                'constraints' => [
                    new Url([
                        'message' => 'mautic.core.valid_url_required',
                        'groups'  => ['doi_config'],
                    ]),
                ],
            ])
            ->add('errorRedirectUrl', UrlType::class, [
                'label'      => 'mautic.plugin.doi.form.field.error_redirect_url',
                'label_attr' => ['class' => 'control-label'],
                'required'   => false,
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.plugin.doi.form.field.error_redirect_url.tooltip',
                ],
                'constraints' => [
                    new Url([
                        'message' => 'mautic.core.valid_url_required',
                        'groups'  => ['doi_config'],
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'validation_groups' => function (FormInterface $form): array {
                $data   = $form->getData();
                $groups = ['doi_config'];

                if (isset($data['enabled']) && $data['enabled']) {
                    $groups[] = 'doi_enabled';
                }

                return $groups;
            },
            'data_class' => null, // Allow array data
        ]);
    }
}
