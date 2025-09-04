<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class MauticDoiIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const INTEGRATION_NAME = 'DoiByLeuchtfeuer';
    public const DISPLAY_NAME     = 'DOI by Leuchtfeuer';

    public function getName(): string
    {
        return self::INTEGRATION_NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/MauticDoiBundle/Assets/img/icon.png';
    }

    /**
     * @param Form|FormBuilder     $builder
     * @param array<string, mixed> $data
     * @param string               $formArea
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('features' == $formArea) {
            $builder->add(
                'extra_hosts',
                TextareaType::class,
                [
                    'label'      => 'doi.form.features.extra_hosts.label',
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'    => 'form-control',
                        'tooltip'  => 'doi.form.features.extra_hosts.tooltip',
                    ],
                    'required'   => false,
                ]
            );
        }
    }
}
