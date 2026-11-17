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

    public function testGetNewestCompaniesInAllSegments(): void
    {
        $segmentA = $this->createCompanySegment('Segment A', 'segment-a');
        $segmentB = $this->createCompanySegment('Segment B', 'segment-b');

        $inBoth       = $this->createCompany('In both', '2024-01-01');
        $onlyInA      = $this->createCompany('Only in A', '2024-01-03');
        $removedFromB = $this->createCompany('Removed from B', '2024-01-02');

        foreach ([$inBoth, $onlyInA, $removedFromB] as $company) {
            $this->addCompanyToCompanySegment($company, $segmentA);
        }
        $this->addCompanyToCompanySegment($inBoth, $segmentB);
        $this->addCompanyToCompanySegment($removedFromB, $segmentB);
        $this->manuallyRemoveCompanyFromCompanySegment($removedFromB, $segmentB);

        $repository = $this->em->getRepository(SegmentCompany::class);
        $this->assertInstanceOf(SegmentCompanyRepository::class, $repository);

        $this->assertSame(
            [$inBoth],
            $repository->getNewestCompaniesInAllSegments([$segmentA->getId(), $segmentB->getId(), $segmentB->getId()], 10)
        );
        $this->assertSame(
            [$onlyInA, $removedFromB, $inBoth],
            $repository->getNewestCompaniesInAllSegments([$segmentA->getId()], 10)
        );
        $this->assertSame(
            [$onlyInA, $removedFromB],
            $repository->getNewestCompaniesInAllSegments([], 2)
        );
    }

    private function createCompany(string $name, string $dateAdded): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setDateAdded(new \DateTime($dateAdded));
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
