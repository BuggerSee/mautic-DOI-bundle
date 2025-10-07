<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Mautic\PluginBundle\Entity\Plugin;

class LeuchtfeuerDoiBundle extends AbstractPluginBundle
{
    /**
     * @param array<int, mixed>|null $metadata
     * @param bool|null              $installedSchema
     */
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        // run DB migrations instead of automatic schema update
        self::onPluginUpdate($plugin, $factory, $metadata);
    }
}
