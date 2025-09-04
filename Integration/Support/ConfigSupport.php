<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\MauticDoiBundle\Integration\MauticDoiIntegration;

class ConfigSupport extends MauticDoiIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}