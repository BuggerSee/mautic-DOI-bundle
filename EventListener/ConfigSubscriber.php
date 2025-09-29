<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use Mautic\ConfigBundle\Event\ConfigEvent;
use MauticPlugin\MauticDoiBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
            ConfigEvents::CONFIG_PRE_SAVE    => ['onConfigSave', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm(
            [
                'formAlias'  => 'doi_config',
                'formType'   => ConfigType::class,
                'formTheme'  => '@MauticDoi/FormTheme/Config/_config_doi_config_widget.html.twig',
                'parameters' => $event->getParametersFromConfig('MauticDoiBundle'),
            ]
        );
    }

    public function onConfigSave(ConfigEvent $event): void
    {
        /** @var array<string,mixed> $values */
        $values = $event->getConfig();

        $value = $values['doi_config']['doi_followup_wait_time'] ?? null;
        if (is_numeric($value)) {
            $followupWaitTime = (int) $value;
            if ($followupWaitTime < 1) {
                $followupWaitTime = 24; // Default fallback
            }
            $values['doi_config']['doi_followup_wait_time'] = $followupWaitTime;
        }

        // Set updated values
        $event->setConfig($values);
    }
}
