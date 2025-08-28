<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\ServiceRepositoryCompilerPass;
use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use MauticPlugin\MauticDoiBundle\Model\FormDoiActionManager;
use MauticPlugin\MauticDoiBundle\Model\FormDoiSubmissionManager;
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

    $services->alias('mautic.plugin.doi.model.doi_config_manager', DoiConfigManager::class);
    $services->alias('mautic.plugin.doi.model.doi_action_manager', FormDoiActionManager::class);
    $services->alias('mautic.plugin.doi.model.doi_submission_manager', FormDoiSubmissionManager::class);
};
