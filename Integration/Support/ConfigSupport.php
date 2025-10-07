<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\MauticDoiBundle\Form\Type\FeatureSettingsType;
use MauticPlugin\MauticDoiBundle\Integration\MauticDoiIntegration;

class ConfigSupport extends MauticDoiIntegration implements ConfigFormInterface, ConfigFormFeatureSettingsInterface
{
    use DefaultConfigFormTrait;

    public function getFeatureSettingsConfigFormName(): string
    {
        return FeatureSettingsType::class;
    }
}
