<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BatchCompanySegmentControllerTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    public function testCompaniesAreAddedToThenRemovedFromSegmentsInBatch(): void
    {
        $segment    = $this->createCompanySegment('Tech Companies', 'tech-companies', true);
        $segmentId  = $segment->getId();
        $companyA   = $this->createCompany('Company A', 'a@example.com');
        $companyB   = $this->createCompany('Company B', 'b@example.com');
        $companyC   = $this->createCompany('Company C', 'c@example.com');

        $this->em->flush();
        $this->em->clear();

        $this->assertCount(0, $segment->getSegmentCompanies());

        $this->client->request(Request::METHOD_GET, '/s/company-segments/batch/company/view', [], [], $this->createAjaxHeaders());
        $this->assertResponseIsSuccessful();

        $clientResponse = $this->client->getResponse();
        $content        = $clientResponse->getContent();
        $this->assertNotFalse($content);

        $html = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($html);
        $this->assertIsString($html['newContent']);

        $crawler = new Crawler($html['newContent'], 'http://localhost/s/company-segments/batch/company/view');
        $form    = $crawler->filter('form[name=company_batch]')->form([
            'company_batch[add]' => [$segmentId],
        ]);

        $payload = $form->getPhpValues();
        $this->assertCount(1, $payload);
        $this->assertArrayHasKey('company_batch', $payload);
        $this->assertIsArray($payload['company_batch']);

        $payload['company_batch']['ids'] = json_encode([$companyA->getId(), $companyB->getId(), $companyC->getId()], JSON_THROW_ON_ERROR);

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        $this->em->clear();
        $segment = $this->em->getRepository(CompanySegment::class)->find($segmentId);
        $this->assertInstanceOf(CompanySegment::class, $segment);

        $this->assertCount(3, $segment->getSegmentCompanies());

        $segmentCompany = $segment->getSegmentCompanies()->get(0);
        $this->assertInstanceOf(\MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany::class, $segmentCompany);
        $this->assertTrue($segmentCompany->isManuallyAdded());
        $this->assertFalse($segmentCompany->isManuallyRemoved());
        $this->assertSame($companyA->getId(), $segmentCompany->getCompany()->getId());

        $segmentCompany = $segment->getSegmentCompanies()->get(1);
        $this->assertInstanceOf(\MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany::class, $segmentCompany);
        $this->assertTrue($segmentCompany->isManuallyAdded());
        $this->assertFalse($segmentCompany->isManuallyRemoved());
        $this->assertSame($companyB->getId(), $segmentCompany->getCompany()->getId());

        $segmentCompany = $segment->getSegmentCompanies()->get(2);
        $this->assertInstanceOf(\MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany::class, $segmentCompany);
        $this->assertTrue($segmentCompany->isManuallyAdded());
        $this->assertFalse($segmentCompany->isManuallyRemoved());
        $this->assertSame($companyC->getId(), $segmentCompany->getCompany()->getId());

        $content = $clientResponse->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('closeModal', $response);
        $this->assertTrue($response['closeModal']);
        $this->assertIsString($response['flashes']);
        $this->assertStringContainsString('3 companies affected', $response['flashes']);

        $this->client->request(Request::METHOD_GET, '/s/company-segments/batch/company/view', [], [], $this->createAjaxHeaders());
        $this->assertResponseIsSuccessful();

        $clientResponse = $this->client->getResponse();
        $content        = $clientResponse->getContent();
        $this->assertNotFalse($content);

        $html = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($html);
        $this->assertIsString($html['newContent']);

        $crawler = new Crawler($html['newContent'], 'http://localhost/s/company-segments/batch/company/view');
        $form    = $crawler->filter('form[name=company_batch]')->form([
            'company_batch[remove]' => [$segmentId],
        ]);

        $payload = $form->getPhpValues();
        $this->assertIsArray($payload);
        $this->assertCount(1, $payload);
        $this->assertArrayHasKey('company_batch', $payload);
        $this->assertIsArray($payload['company_batch']);

        $payload['company_batch']['ids'] = json_encode([$companyA->getId(), $companyB->getId(), $companyC->getId()], JSON_THROW_ON_ERROR);

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $clientResponse = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        $this->em->clear();
        $segment = $this->em->getRepository(CompanySegment::class)->find($segmentId);
        $this->assertInstanceOf(CompanySegment::class, $segment);

        $this->assertCount(0, $segment->getSegmentCompanies());

        $content = $clientResponse->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('closeModal', $response);
        $this->assertTrue($response['closeModal']);
        $this->assertIsString($response['flashes']);
        $this->assertStringContainsString('3 companies affected', $response['flashes']);
    }

    public function testSetActionRejectsInvalidCsrfToken(): void
    {
        [$segmentId, $companyId] = $this->createSegmentAndCompany();

        $payload                             = $this->buildBatchPayload($segmentId, $companyId);
        $payload['company_batch']['_token']  = 'invalid-token';

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $this->assertBatchRejected($segmentId);
    }

    public function testSetActionRejectsSegmentIdNotOfferedByTheForm(): void
    {
        [$segmentId, $companyId] = $this->createSegmentAndCompany();

        $payload                          = $this->buildBatchPayload($segmentId, $companyId);
        $payload['company_batch']['add']  = [$segmentId + 999];

        $this->client->request(Request::METHOD_POST, '/s/company-segments/batch/company/set', $payload);

        $this->assertBatchRejected($segmentId);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createSegmentAndCompany(): array
    {
        $segment = $this->createCompanySegment('Tech Companies', 'tech-companies', true);
        $company = $this->createCompany('Company A', 'a@example.com');

        $this->em->flush();
        $this->em->clear();

        $segmentId = $segment->getId();
        $companyId = $company->getId();
        $this->assertNotNull($segmentId);
        $this->assertNotNull($companyId);

        return [$segmentId, $companyId];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBatchPayload(int $segmentId, int $companyId): array
    {
        $this->client->request(Request::METHOD_GET, '/s/company-segments/batch/company/view', [], [], $this->createAjaxHeaders());
        $this->assertResponseIsSuccessful();

        $content = $this->client->getResponse()->getContent();
        $this->assertNotFalse($content);

        $html = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($html);
        $this->assertIsString($html['newContent']);

        $crawler = new Crawler($html['newContent'], 'http://localhost/s/company-segments/batch/company/view');
        $form    = $crawler->filter('form[name=company_batch]')->form([
            'company_batch[add]' => [$segmentId],
        ]);

        $payload = $form->getPhpValues();
        $this->assertIsArray($payload['company_batch']);
        $payload['company_batch']['ids'] = json_encode([$companyId], JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function assertBatchRejected(int $segmentId): void
    {
        $clientResponse = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $clientResponse->getStatusCode());

        $content = $clientResponse->getContent();
        $this->assertNotFalse($content);
        $response = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $this->assertTrue($response['closeModal']);
        $this->assertIsString($response['flashes']);
        $this->assertStringNotContainsString('companies affected', $response['flashes']);

        $this->em->clear();
        $segment = $this->em->getRepository(CompanySegment::class)->find($segmentId);
        $this->assertInstanceOf(CompanySegment::class, $segment);
        $this->assertCount(0, $segment->getSegmentCompanies());
    }
}
