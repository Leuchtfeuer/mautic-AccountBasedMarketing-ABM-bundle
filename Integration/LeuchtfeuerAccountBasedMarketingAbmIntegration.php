<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration;

use Mautic\IntegrationsBundle\Integration\BasicIntegration;
use Mautic\IntegrationsBundle\Integration\ConfigurationTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\BasicInterface;

class LeuchtfeuerAccountBasedMarketingAbmIntegration extends BasicIntegration implements BasicInterface
{
    use ConfigurationTrait;

    public const NAME = 'leuchtfeueraccountbasedmarketingabm';
    public const DISPLAY_NAME = 'ABM (Account Based Marketing) by Leuchtfeuer';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDisplayName(): string
    {
        return self::DISPLAY_NAME;
    }

    public function getIcon(): string
    {
        return 'plugins/LeuchtfeuerAccountBasedMarketingAbmBundle/Assets/img/icon.png';
    }
}
