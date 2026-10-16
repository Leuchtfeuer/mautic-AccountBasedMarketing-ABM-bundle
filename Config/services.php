<?php

declare(strict_types=1);

use Mautic\CoreBundle\DependencyInjection\MauticCoreExtension;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\LeuchtfeuerAccountBasedMarketingAbmIntegration;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Support\ConfigSupport;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $excludes = ['Segment/Decorator'];
    $services->load('MauticPlugin\\LeuchtfeuerAccountBasedMarketingAbmBundle\\', '../')
        ->exclude('../{'.implode(',', array_merge(MauticCoreExtension::DEFAULT_EXCLUDES, $excludes)).'}');

    $services->load('MauticPlugin\\LeuchtfeuerAccountBasedMarketingAbmBundle\\Entity\\', '../Entity/*Repository.php');

    $services->get(LeuchtfeuerAccountBasedMarketingAbmIntegration::class)
        ->tag('mautic.integration')
        ->tag('mautic.basic_integration');

    $services->get(ConfigSupport::class)
        ->tag('mautic.config_integration');

    $services->alias('mautic.integration.leuchtfeueraccountbasedmarketingabm', LeuchtfeuerAccountBasedMarketingAbmIntegration::class);
    $services->alias('mautic.integration.leuchtfeueraccountbasedmarketingabm.configuration', ConfigSupport::class);

    $services->alias('mautic.abm.model.company_segment', MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel::class);

    // Registers the companysegment search command on the company repository.
    $services->set(MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyModelDecorated::class)
        ->decorate(Mautic\LeadBundle\Model\CompanyModel::class);

    // Keeps a listener's negative DWC filter result instead of falling back to core matching.
    $services->set(MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Helper\DynamicContentHelperDecorated::class)
        ->decorate('mautic.helper.dynamicContent');
};
