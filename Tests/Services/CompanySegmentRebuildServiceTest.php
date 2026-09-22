<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Services;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Services\CompanySegmentRebuildService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CompanySegmentRebuildServiceTest extends TestCase
{
    public function testRebuildDependentSegmentsReadsPropertiesFilterFormat(): void
    {
        $dependentSegment = new CompanySegment();
        $dependentSegment->setName('Dependent');
        $dependentSegment->setIsPublished(true);
        $this->setId($dependentSegment, 5);

        $segment = new CompanySegment();
        $segment->setName('Parent');
        $segment->setIsPublished(true);
        $segment->setFilters([
            [
                'glue'       => 'and',
                'field'      => 'company_segments',
                'object'     => 'company_segments',
                'type'       => CompanySegmentModel::PROPERTIES_FIELD,
                'operator'   => 'in',
                'properties' => ['filter' => [5]],
            ],
        ]);
        $this->setId($segment, 8);

        $model = $this->createMock(CompanySegmentModel::class);
        $model->method('getEntity')->with(5)->willReturn($dependentSegment);
        $model->expects($this->once())
            ->method('rebuildCompanySegment')
            ->with($dependentSegment)
            ->willReturn(0);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('');

        $service = new CompanySegmentRebuildService(
            $model,
            $translator,
            $this->createStub(CompanySegmentRepository::class)
        );

        $rebuiltSegments = [];
        $service->rebuildDependentSegments($segment, $rebuiltSegments, 100, null, new BufferedOutput(), false);

        $this->assertSame([5], $rebuiltSegments);
    }

    private function setId(CompanySegment $segment, int $id): void
    {
        $property = new \ReflectionProperty(CompanySegment::class, 'id');
        $property->setValue($segment, $id);
    }
}
