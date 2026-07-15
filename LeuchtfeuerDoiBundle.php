<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\IntegrationsBundle\Bundle\AbstractPluginBundle;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\LastDoiDateFieldInstaller;

class LeuchtfeuerDoiBundle extends AbstractPluginBundle
{
    /**
     * @param array<int, mixed>|null $metadata
     * @param bool|null              $installedSchema
     */
    public static function onPluginInstall(Plugin $plugin, MauticFactory $factory, $metadata = null, $installedSchema = null): void
    {
        self::onPluginUpdate($plugin, $factory, $metadata);
    }

    /**
     * @param array<int, mixed>|null $metadata
     */
    public static function onPluginUpdate(Plugin $plugin, MauticFactory $factory, $metadata = null, ?Schema $installedSchema = null): void
    {
        parent::onPluginUpdate($plugin, $factory, $metadata, $installedSchema);

        $fieldModel = $factory->getModel('lead.field');
        \assert($fieldModel instanceof FieldModel);

        LastDoiDateFieldInstaller::install(
            $fieldModel,
            $factory->getTranslator(),
            $factory->getLogger(),
        );
    }
}
