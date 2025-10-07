<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Form\Type;

use MauticPlugin\MauticDoiBundle\Integration\Config;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\GreaterThan;

final class FeatureSettingsType extends AbstractType
{
    /**
     * @param mixed[] $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'followup_wait_time',
            IntegerType::class,
            [
                'label' => 'mautic.plugin.doi.config.followup_wait_time',
                'data'  => $options['data']['followup_wait_time'] ?? Config::DEFAULT_FOLLOWUP_WAIT_TIME,
                'attr'  => [
                    'tooltip' => 'mautic.plugin.doi.config.followup_wait_time_tooltip',
                    'class'   => 'form-control',
                ],
                'constraints' => [
                    new GreaterThan([
                        'value'   => 0,
                        'message' => 'mautic.plugin.doi.config.followup_wait_time.positive',
                    ]),
                ],
            ]
        );
    }
}
