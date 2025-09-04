<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

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
}
