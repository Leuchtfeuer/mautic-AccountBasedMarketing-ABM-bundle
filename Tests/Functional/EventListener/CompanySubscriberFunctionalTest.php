<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class CompanySubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publishAbmIntegration();
    }

    /**
     * @param string[] $initialSegments
     * @param string[] $selectedSegments
     * @param string[] $expectedSegments
     */
    #[DataProvider('segmentProvider')]
    public function testEditCompanySegmentsViaCompanyForm(array $initialSegments, array $selectedSegments, array $expectedSegments): void
    {
        $company  = $this->createCompany();
        $segments = [];
        foreach (['a', 'b', 'c'] as $segmentName) {
            $segments[$segmentName] = $this->createCompanySegment($segmentName, 'segment-'.$segmentName);
        }

        foreach ($initialSegments as $segmentName) {
            $this->addCompanyToCompanySegment($company, $segments[$segmentName]);
        }

        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies/edit/'.$company->getId());
        $this->assertResponseIsSuccessful();

        $form                              = $crawler->filter('form[name="company"]')->form();
        $form['company[company_segments]'] = array_map(
            static fn (string $segmentName): string => (string) $segments[$segmentName]->getId(),
            $selectedSegments
        );
        $this->client->submit($form);
        $this->assertResponseIsSuccessful();

        $expectedSegmentIds = array_map(static fn (string $segmentName): int => (int) $segments[$segmentName]->getId(), $expectedSegments);
        $this->assertEqualsCanonicalizing($expectedSegmentIds, $this->getSegmentIdsOfCompany($company));

        foreach (array_diff($initialSegments, $selectedSegments) as $removedSegmentName) {
            $this->assertManuallyRemoved($company, $segments[$removedSegmentName]);
        }
    }

    /**
     * @return iterable<string, array{string[], string[], string[]}>
     */
    public static function segmentProvider(): iterable
    {
        yield 'add segment to company without segments' => [[], ['a'], ['a']];
        yield 'add and remove segments' => [['a', 'b'], ['a', 'c'], ['a', 'c']];
        yield 'remove all segments' => [['a', 'b'], [], []];
        yield 'keep segments unchanged' => [['a', 'b'], ['a', 'b'], ['a', 'b']];
    }

    public function testSegmentsAreKeptWhenCompanyIsSavedWithoutCompanyForm(): void
    {
        $company = $this->createCompany();
        $segment = $this->createCompanySegment('a', 'segment-a');
        $this->addCompanyToCompanySegment($company, $segment);

        $this->client->request(Request::METHOD_PUT, '/api/companies/'.$company->getId().'/edit', ['companyname' => 'Acme renamed']);
        $this->assertResponseIsSuccessful();

        $this->assertSame([(int) $segment->getId()], $this->getSegmentIdsOfCompany($company));
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Acme');
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    /**
     * @return int[]
     */
    private function getSegmentIdsOfCompany(Company $company): array
    {
        $this->em->clear();

        $segmentCompanies = $this->em->getRepository(SegmentCompany::class)->findBy([
            'company'         => $company->getId(),
            'manuallyRemoved' => false,
        ]);

        return array_map(
            static fn (SegmentCompany $segmentCompany): int => (int) $segmentCompany->getCompanySegment()->getId(),
            $segmentCompanies
        );
    }

    private function assertManuallyRemoved(Company $company, CompanySegment $companySegment): void
    {
        $segmentCompany = $this->em->getRepository(SegmentCompany::class)->findOneBy([
            'company'        => $company->getId(),
            'companySegment' => $companySegment->getId(),
        ]);

        $this->assertInstanceOf(SegmentCompany::class, $segmentCompany);
        $this->assertTrue($segmentCompany->isManuallyRemoved());
    }
}
