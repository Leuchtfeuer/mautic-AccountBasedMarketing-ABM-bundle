<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Segment\Exception\SegmentQueryException;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Command\UpdateCompanySegmentsCommand;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class UpdateCompanySegmentsCommandCircularDependencyTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    public function testCompanySegmentCircularDependencyDetection(): void
    {
        $segmentA = $this->createCompanySegment('Segment A');
        $segmentB = $this->createCompanySegment('Segment B');
        $segmentC = $this->createCompanySegment('Segment C');

        $this->em->flush();

        $this->addCompanySegmentDependency($segmentA, $segmentB);
        $this->addCompanySegmentDependency($segmentB, $segmentC);
        $this->addCompanySegmentDependency($segmentC, $segmentA);

        $this->em->flush();

        $this->expectException(SegmentQueryException::class);
        $this->expectExceptionMessage('Circular reference detected.');

        $this->testSymfonyCommand(
            UpdateCompanySegmentsCommand::NAME,
            [
                '--companysegment-id' => $segmentA->getId(),
                '--bypass-locking'    => true,
            ]
        );
    }

    public function testCompanySegmentSkippingNonExistentDependentSegment(): void
    {
        $segmentA = $this->createCompanySegment('Segment A');
        $segmentB = $this->createCompanySegment('Segment B');

        $this->em->flush();

        $this->addCompanySegmentDependency($segmentA, $segmentB);

        $nonExistentSegmentId = 9999;
        $filters              = $segmentA->getFilters();
        $filters[]            = [
            'glue'     => 'and',
            'field'    => 'company_segments',
            'object'   => 'company',
            'type'     => 'company_segments',
            'filter'   => [$nonExistentSegmentId],
            'display'  => null,
            'operator' => 'in',
        ];
        $segmentA->setFilters($filters);

        $this->em->persist($segmentA);
        $this->em->flush();

        $output = $this->testSymfonyCommand(
            UpdateCompanySegmentsCommand::NAME,
            [
                '--companysegment-id' => $segmentA->getId(),
                '--bypass-locking'    => true,
            ]
        );

        $this->assertStringContainsString(
            sprintf('Rebuilding company segments for segment %d', $segmentB->getId()),
            $output->getDisplay()
        );

        $this->assertStringContainsString(
            sprintf('Rebuilding company segments for segment %d', $segmentA->getId()),
            $output->getDisplay()
        );

        $this->assertStringNotContainsString('error', strtolower($output->getDisplay()));
    }

    private function createCompanySegment(string $name): CompanySegment
    {
        $segment = new CompanySegment();
        $segment->setName($name);
        $segment->setPublicName($name);
        $segment->setAlias(strtolower(str_replace(' ', '-', $name)));
        $segment->setIsPublished(true);

        $this->em->persist($segment);

        return $segment;
    }

    private function addCompanySegmentDependency(CompanySegment $segment, CompanySegment $includeSegment): void
    {
        $filters   = $segment->getFilters();
        $filters[] = [
            'glue'     => 'and',
            'field'    => 'company_segments',
            'object'   => 'company',
            'type'     => 'company_segments',
            'filter'   => [$includeSegment->getId()],
            'display'  => null,
            'operator' => 'in',
        ];

        $segment->setFilters($filters);
        $this->em->persist($segment);
    }
}
