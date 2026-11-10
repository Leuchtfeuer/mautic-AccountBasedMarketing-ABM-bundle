<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class CompanyCustomContentSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    #[DataProvider('publishedProvider')]
    public function testHistoryTabIsShownOnlyWhenIntegrationIsPublished(bool $isPublished): void
    {
        $this->publishAbmIntegration($isPublished);
        $company = new Company();
        $company->setName('Acme');
        $this->em->persist($company);
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/s/companies/view/'.$company->getId());

        $this->assertResponseIsSuccessful();
        $this->assertCount($isPublished ? 1 : 0, $crawler->filter('#company-timeline-container #timeline-table[data-target-url]'));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function publishedProvider(): iterable
    {
        yield 'published' => [true];
        yield 'unpublished' => [false];
    }
}
