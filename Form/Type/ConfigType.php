<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;

final class ConfigType extends AbstractType
{
    /**
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'doi_followup_wait_time',
            IntegerType::class,
            [
                'label' => 'mautic.doi.config.doi_followup_wait_time',
                'data'  => $options['data']['doi_followup_wait_time'] ?? 24,
                'attr'  => [
                    'tooltip' => 'mautic.doi.config.doi_followup_wait_time_tooltip',
                    'class'   => 'form-control',
                ],
                'constraints' => [
                    new GreaterThan([
                        'value'   => 0,
                        'message' => 'mautic.doi.config.doi_followup_wait_time.positive',
                    ]),
                ],
            ]
        );
    }
}
