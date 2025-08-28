<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Mautic\PluginBundle\Entity\Plugin;

class MauticDoiBundle extends AbstractPluginBundle
{
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        // run DB migrations instead of automatic schema update
        self::onPluginUpdate($plugin, $factory, $metadata);
    }
}
