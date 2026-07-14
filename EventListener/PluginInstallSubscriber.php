<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\EventListener;

use Mautic\PluginBundle\Bundle\PluginDatabase;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\Event\PluginUpdateEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\LastDoiDateFieldInstaller;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PluginInstallSubscriber implements EventSubscriberInterface
{
    /**
     * Plugin name as defined in Config/config.php; matched against Plugin::getName().
     */
    private const PLUGIN_NAME = 'Email Verification and Double Opt-In (DOI) by Leuchtfeuer';

    public function __construct(
        private readonly PluginDatabase $pluginDatabase,
        private readonly LastDoiDateFieldInstaller $lastDoiDateFieldInstaller,
    ) {
    }

    public function onInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext(self::PLUGIN_NAME)) {
            return;
        }

        // Run DB migrations instead of automatic schema update
        $this->pluginDatabase->onPluginUpdate($event->getPlugin());

        $this->lastDoiDateFieldInstaller->installIfMissing();

        // Prevent core PluginSubscriber from trying to create schema
        $event->stopPropagation();
    }

    public function onUpdate(PluginUpdateEvent $event): void
    {
        if (!$event->checkContext(self::PLUGIN_NAME)) {
            return;
        }

        $this->lastDoiDateFieldInstaller->installIfMissing();
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onInstall', 10],
            PluginEvents::ON_PLUGIN_UPDATE  => ['onUpdate', 0],
        ];
    }
}
