<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Support;

use Mautic\IntegrationsBundle\Integration\DefaultConfigFormTrait;
use Mautic\IntegrationsBundle\Integration\Interfaces\ConfigFormInterface;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\LeuchtfeuerAccountBasedMarketingAbmIntegration;

class ConfigSupport extends LeuchtfeuerAccountBasedMarketingAbmIntegration implements ConfigFormInterface
{
    use DefaultConfigFormTrait;
}
