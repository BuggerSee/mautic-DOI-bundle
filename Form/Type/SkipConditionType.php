<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Form\Type;

use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\AvailableSkipOptions;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SkipConditionType extends AbstractType
{
    public function __construct(
        private FormAdjustmentsProviderInterface $formAdjustmentsProvider,
        private AvailableSkipOptions $availableSkipOptions,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Store the form entity if provided
        if (isset($options['mautic_form'])) {
            $builder->setAttribute('mautic_form', $options['mautic_form']);
        }

        $formEntity   = $options['mautic_form'] ?? null;
        $fieldChoices = $this->availableSkipOptions->getAvailableSkipOptions($formEntity);

        $builder->add(
            'glue',
            ChoiceType::class,
            [
                'label'   => false,
                'choices' => [
                    'mautic.lead.list.form.glue.and' => 'and',
                    'mautic.lead.list.form.glue.or'  => 'or',
                ],
                'attr' => [
                    'class'    => 'form-control not-chosen glue-select',
                    'onchange' => 'Mautic.updateFilterPositioning(this)',
                ],
            ]
        );

        $formModifier = function (FormEvent $event) use ($fieldChoices): void {
            $data        = (array) $event->getData();
            $form        = $event->getForm();
            $fieldAlias  = $data['field'] ?? null;
            $fieldObject = $data['object'] ?? 'behaviors';
            // Looking for behaviors for BC reasons as some filters were moved from 'lead' to 'behaviors'.
            $field       = $fieldChoices[$fieldObject][$fieldAlias] ?? $fieldChoices['behaviors'][$fieldAlias] ?? null;
            $operators   = $field['operators'] ?? [];
            $operator    = $data['operator'] ?? null;

            if ($operators && !$operator) {
                $operator = array_key_first($operators);
            }

            $form->add(
                'operator',
                ChoiceType::class,
                [
                    'label'   => false,
                    'choices' => $operators,
                    'attr'    => [
                        'class'    => 'form-control not-chosen',
                        'onchange' => 'Mautic.doiConvertLeadFilterInput(this)',
                    ],
                ]
            );

            $form->add(
                'properties',
                FilterPropertiesType::class,
                [
                    'label' => false,
                ]
            );

            if (null === $field) {
                // The field was probably deleted since the segment was created.
                // Do not show up the filter based on a deleted field.
                return;
            }

            $filterPropertiesType = $form->get('properties');
            $filterPropertiesType->setData($data['properties'] ?? []);

            if ($fieldAlias && $operator) {
                $this->formAdjustmentsProvider->adjustForm(
                    $filterPropertiesType,
                    $fieldAlias,
                    $fieldObject,
                    $operator,
                    $field
                );
            }
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, $formModifier);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, $formModifier);
        $builder->add('field', HiddenType::class);
        $builder->add('object', HiddenType::class);
        $builder->add('type', HiddenType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
                'label'          => false,
                'error_bubbling' => false,
                'mautic_form'    => null,
            ]
        );
        $resolver->setAllowedTypes('mautic_form', ['null', Form::class]);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $formEntity           = $form->getConfig()->getAttribute('mautic_form');
        $view->vars['fields'] = $this->availableSkipOptions->getAvailableSkipOptions($formEntity);
    }

    public function getBlockPrefix(): string
    {
        return '_doiconfig_skipcondition_entry';
    }
}
