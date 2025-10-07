<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

class Config
{
    public const DEFAULT_FOLLOWUP_WAIT_TIME = 24;

    public function __construct(
        private IntegrationsHelper $integrationsHelper
    ) {
    }

    public function isPublished(): bool
    {
        try {
            $integration = $this->getIntegrationEntity();

            return (bool) $integration->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function getFollowUpWaitTime(): int
    {
        try {
            $integrationSettings = $this->getIntegrationEntity()->getFeatureSettings();
            assert(is_array($integrationSettings));

            $waitTime = $integrationSettings['integration']['followup_wait_time'] ?? null;

            if (is_numeric($waitTime) && (int) $waitTime >= 1) {
                return (int) $waitTime;
            }

            return self::DEFAULT_FOLLOWUP_WAIT_TIME;
        } catch (IntegrationNotFoundException) {
            return self::DEFAULT_FOLLOWUP_WAIT_TIME;
        }
    }

    /**
     * @throws IntegrationNotFoundException
     */
    public function getIntegrationEntity(): Integration
    {
        $integrationObject = $this->integrationsHelper->getIntegration(LeuchtfeuerDoiIntegration::INTEGRATION_NAME);

        return $integrationObject->getIntegrationConfiguration();
    }
}
