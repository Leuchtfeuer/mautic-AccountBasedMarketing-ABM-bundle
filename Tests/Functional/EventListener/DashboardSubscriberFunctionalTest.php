<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CacheBundle\Cache\CacheProviderInterface;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\DashboardBundle\Entity\Widget;
use Mautic\LeadBundle\Entity\Company;
use Mautic\UserBundle\Entity\User;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\DomCrawler\Crawler;

final class DashboardSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
        self::getContainer()->get(CacheProviderInterface::class)->clear();
    }

    public function testWidgetShowsNewestCompaniesInAllSelectedSegments(): void
    {
        $segmentA = $this->createCompanySegment('Segment A', 'segment-a');
        $segmentB = $this->createCompanySegment('Segment B', 'segment-b');

        $inBothOld = $this->createDatedCompany('In both old', '2024-01-01');
        $inBothNew = $this->createDatedCompany('In both new', '2024-03-01');
        $onlyInA   = $this->createDatedCompany('Only in A', '2024-02-01');

        foreach ([$inBothOld, $inBothNew, $onlyInA] as $company) {
            $this->addCompanyToCompanySegment($company, $segmentA);
        }
        $this->addCompanyToCompanySegment($inBothOld, $segmentB);
        $this->addCompanyToCompanySegment($inBothNew, $segmentB);

        $crawler = $this->renderWidget([$segmentA->getId(), $segmentB->getId()]);

        $this->assertSame(['ID', 'Company Name', 'Company Website'], $crawler->filter('table thead th')->each(fn (Crawler $th): string => trim($th->text())));
        $this->assertSame(['In both new', 'In both old'], $crawler->filter('table tbody tr td:nth-child(2)')->each(fn (Crawler $td): string => trim($td->text())));
    }

    /**
     * @param int[] $segmentIds
     */
    private function renderWidget(array $segmentIds): Crawler
    {
        $widget = new Widget();
        $widget->setName('Company list');
        $widget->setType('company.list');
        $widget->setWidth(100);
        $widget->setHeight(330);
        $widget->setParams(['companysegments' => $segmentIds]);
        $widget->setCreatedBy($this->em->getRepository(User::class)->findOneBy(['username' => 'admin']));
        $this->em->persist($widget);
        $this->em->flush();

        $this->client->xmlHttpRequest('GET', '/s/dashboard/widget/'.$widget->getId());
        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);

        return new Crawler($data['widgetHtml']);
    }

    private function createDatedCompany(string $name, string $dateAdded): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setDateAdded(new \DateTime($dateAdded));
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
