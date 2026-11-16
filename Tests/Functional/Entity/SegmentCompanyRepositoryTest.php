<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Entity;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class SegmentCompanyRepositoryTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    public function testGetCompanyIdsInAllSegments(): void
    {
        $segmentA = $this->createCompanySegment('Segment A', 'segment-a');
        $segmentB = $this->createCompanySegment('Segment B', 'segment-b');

        $inBoth       = $this->createCompany('In both');
        $onlyInA      = $this->createCompany('Only in A');
        $removedFromB = $this->createCompany('Removed from B');

        foreach ([$inBoth, $onlyInA, $removedFromB] as $company) {
            $this->addCompanyToCompanySegment($company, $segmentA);
        }
        $this->addCompanyToCompanySegment($inBoth, $segmentB);
        $this->addCompanyToCompanySegment($removedFromB, $segmentB);
        $this->manuallyRemoveCompanyFromCompanySegment($removedFromB, $segmentB);

        $repository = $this->em->getRepository(SegmentCompany::class);
        $this->assertInstanceOf(SegmentCompanyRepository::class, $repository);

        $this->assertSame(
            [$inBoth->getId()],
            $repository->getCompanyIdsInAllSegments([$segmentA->getId(), $segmentB->getId()])
        );

        $companyIdsInA = $repository->getCompanyIdsInAllSegments([$segmentA->getId()]);
        sort($companyIdsInA);
        $this->assertSame([$inBoth->getId(), $onlyInA->getId(), $removedFromB->getId()], $companyIdsInA);
    }

    private function createCompany(string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
