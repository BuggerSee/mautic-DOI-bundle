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

    $services->get(MauticPlugin\MauticDoiBundle\Integration\MauticDoiIntegration::class)
        ->tag('mautic.integration')
        ->tag('mautic.basic_integration');
    $services->get(MauticPlugin\MauticDoiBundle\Integration\Support\ConfigSupport::class)
        ->tag('mautic.config_integration');

    $services->alias('mautic.integration.mauticdoi', MauticPlugin\MauticDoiBundle\Integration\MauticDoiIntegration::class);
};
