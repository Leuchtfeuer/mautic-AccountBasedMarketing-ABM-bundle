<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration;

use Mautic\IntegrationsBundle\Exception\IntegrationNotFoundException;
use Mautic\IntegrationsBundle\Helper\IntegrationsHelper;
use Mautic\PluginBundle\Entity\Integration;

class Config
{
    public function __construct(private IntegrationsHelper $integrationsHelper)
    {
    }

    public function isPublished(): bool
    {
        try {
            return (bool) $this->getIntegrationEntity()->getIsPublished();
        } catch (IntegrationNotFoundException) {
            return false;
        }
    }

    public function getIntegrationEntity(): Integration
    {
        return $this->integrationsHelper
            ->getIntegration(LeuchtfeuerAccountBasedMarketingAbmIntegration::NAME)
            ->getIntegrationConfiguration();
    }
}
