<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = [];

    $services->load('MauticPlugin\\MauticDoiBundle\\Entity\\', '../Entity/*Repository.php')
        ->tag(ServiceRepositoryCompilerPass::REPOSITORY_SERVICE_TAG);

    $services->load('MauticPlugin\\MauticDoiBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    // Explicit service registration for config components
    $services->set('mautic.doi.config.subscriber', 'MauticPlugin\\MauticDoiBundle\\EventListener\\ConfigSubscriber')
        ->tag('kernel.event_subscriber');

    $services->set('mautic.doi.form.type.config', 'MauticPlugin\\MauticDoiBundle\\Form\\Type\\ConfigType')
        ->tag('form.type');
};
