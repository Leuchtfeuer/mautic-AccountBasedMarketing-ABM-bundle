<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Form\Type\Extension;

use Mautic\CoreBundle\Form\Type\DynamicContentFilterEntryFiltersType;
use Mautic\DynamicContentBundle\Form\Type\DwcEntryFiltersType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension\DwcEntryFiltersTypeExtension;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DwcEntryFiltersTypeExtensionTest extends TestCase
{
    public function testExtendsDwcAndEmailDynamicContentFilterTypes(): void
    {
        $extendedTypes = iterator_to_array(DwcEntryFiltersTypeExtension::getExtendedTypes());

        $this->assertContains(DwcEntryFiltersType::class, $extendedTypes);
        $this->assertContains(DynamicContentFilterEntryFiltersType::class, $extendedTypes);
    }

    public function testConfigureOptionsSetsCompanySegmentsDefault(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(true);

        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companySegmentModel->expects($this->once())
            ->method('getCompanySegments')
            ->willReturn([
                ['id' => '1', 'name' => 'Segment A'],
                ['id' => '2', 'name' => 'Segment B'],
            ]);

        $extension = new DwcEntryFiltersTypeExtension($config, $companySegmentModel);

        $resolver = new OptionsResolver();
        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame([
            'Segment A' => 1,
            'Segment B' => 2,
        ], $options['companySegments']);
    }

    public function testConfigureOptionsSetsEmptyDefaultWhenPluginNotPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(false);

        $companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $companySegmentModel->expects($this->never())
            ->method('getCompanySegments');

        $extension = new DwcEntryFiltersTypeExtension($config, $companySegmentModel);

        $resolver = new OptionsResolver();
        $extension->configureOptions($resolver);
        $options = $resolver->resolve();

        $this->assertSame([], $options['companySegments']);
    }
}
