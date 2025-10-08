<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormFeatureSettingsInterface;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerDoiBundle\Form\Type\FeatureSettingsType;
use MauticPlugin\LeuchtfeuerDoiBundle\Integration\LeuchtfeuerDoiIntegration;

class ConfigSupport extends LeuchtfeuerDoiIntegration implements ConfigFormInterface, ConfigFormFeatureSettingsInterface
{
    use DefaultConfigFormTrait;

    public function getFeatureSettingsConfigFormName(): string
    {
        return FeatureSettingsType::class;
    }
}
