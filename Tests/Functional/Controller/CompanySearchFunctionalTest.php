<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class CompanySearchFunctionalTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    public function testSearchByCompanySegment(): void
    {
        $segment = $this->createCompanySegment('Tech Companies', 'tech-companies');

        $companyInSegment1   = $this->createCompany('Company A', 'a@example.com');
        $companyInSegment2   = $this->createCompany('Company B', 'b@example.com');
        $companyNotInSegment = $this->createCompany('Company C', 'c@example.com');

        $this->addCompanyToCompanySegment($companyInSegment1, $segment);
        $this->addCompanyToCompanySegment($companyInSegment2, $segment);

        $this->em->clear();

        $crawler = $this->client->request('GET', '/s/companies?search=companysegment:tech-companies');
        $this->assertResponseIsSuccessful();

        $rows = $crawler->filter('#companyTable tbody tr');
        $this->assertCount(2, $rows, 'Should display exactly 2 companies in the segment');

        $rowTexts = [];
        $rows->each(function ($row) use (&$rowTexts): void {
            $rowTexts[] = $row->text();
        });

        $combinedText = implode(' ', $rowTexts);
        $this->assertStringContainsString('Company A', $combinedText);
        $this->assertStringContainsString('Company B', $combinedText);
        $this->assertStringNotContainsString('Company C', $combinedText);
    }

    public function testSearchByNonExistentCompanySegment(): void
    {
        $this->createCompany('Test Company', 'test@example.com');

        $this->em->clear();

        $this->client->request('GET', '/s/companies?search=companysegment:non-existent-segment');
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('No Results Found', $content);
    }
}
