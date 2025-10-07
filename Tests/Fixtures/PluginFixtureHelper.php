<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use MauticPlugin\MauticDoiBundle\Integration\Config;

final class PluginFixtureHelper
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function createAndEnablePlugin(): void
    {
        $plugin = new Plugin();
        $plugin->setName('DOI by Leuchtfeuer');
        $plugin->setBundle('MauticDoiBundle');
        $this->em->persist($plugin);

        $integration = new Integration();
        $integration->setPlugin($plugin);
        $integration->setIsPublished(true);
        $integration->setName('MauticDoi');
        $integration->setFeatureSettings(['integration' => [
            'followup_wait_time' => Config::DEFAULT_FOLLOWUP_WAIT_TIME,
        ]]);
        $this->em->persist($integration);
        $this->em->flush();
    }

    public function disablePlugin(): void
    {
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'MauticDoi']);
        if (null === $integration) {
            return;
        }

        $integration->setIsPublished(false);
        $this->em->persist($integration);
        $this->em->flush();
    }

    public function modifyFollowupWaitTime(int $waitTime): void
    {
        $integration = $this->em->getRepository(Integration::class)->findOneBy(['name' => 'MauticDoi']);
        if (null === $integration) {
            return;
        }

        $featureSettings                                      = $integration->getFeatureSettings();
        $featureSettings['integration']['followup_wait_time'] = $waitTime;

        $integration->setFeatureSettings($featureSettings);
        $this->em->persist($integration);
        $this->em->flush();
    }
}
