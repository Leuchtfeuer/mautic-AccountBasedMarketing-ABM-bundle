<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class IntegrationGuardSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    public function testUiRouteIsNotFoundWhenIntegrationIsUnpublished(): void
    {
        $this->publishAbmIntegration(false);

        $this->client->request(Request::METHOD_GET, '/s/company-segments/1');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testApiRouteIsNotFoundWhenIntegrationIsUnpublished(): void
    {
        $this->publishAbmIntegration(false);

        $this->client->request(Request::METHOD_GET, '/api/companysegments');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testAjaxActionIsNotFoundWhenIntegrationIsUnpublished(): void
    {
        $segmentId = $this->createSegmentAndUnpublishIntegration();

        $this->client->request(Request::METHOD_GET, '/s/ajax?action=plugin:leuchtfeuerAccountBasedMarketingAbm:getCompaniesCount&id='.$segmentId, [], [], $this->createAjaxHeaders());

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testAjaxActionWithUppercaseBundleNameIsNotFoundWhenIntegrationIsUnpublished(): void
    {
        $segmentId = $this->createSegmentAndUnpublishIntegration();

        $this->client->request(Request::METHOD_GET, '/s/ajax?action=plugin:LeuchtfeuerAccountBasedMarketingAbm:getCompaniesCount&id='.$segmentId, [], [], $this->createAjaxHeaders());

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testAjaxActionInPostBodyIsNotFoundWhenQueryActionIsEmpty(): void
    {
        $segmentId = $this->createSegmentAndUnpublishIntegration();

        $this->client->request(Request::METHOD_POST, '/s/ajax?action=&id='.$segmentId, [
            'action' => 'plugin:leuchtfeuerAccountBasedMarketingAbm:getCompaniesCount',
        ], [], $this->createAjaxHeaders());

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testAjaxActionIsReachableWhenIntegrationIsPublished(): void
    {
        $this->publishAbmIntegration();
        $segmentId = $this->createCompanySegment('Guard Segment', 'guard-segment')->getId();

        $this->client->request(Request::METHOD_GET, '/s/ajax?action=plugin:leuchtfeuerAccountBasedMarketingAbm:getCompaniesCount&id='.$segmentId, [], [], $this->createAjaxHeaders());

        $this->assertResponseIsSuccessful();
    }

    public function testUiRouteIsReachableWhenIntegrationIsPublished(): void
    {
        $this->publishAbmIntegration();

        $this->client->request(Request::METHOD_GET, '/s/company-segments/1');

        $this->assertResponseIsSuccessful();
    }

    private function createSegmentAndUnpublishIntegration(): int
    {
        $this->publishAbmIntegration();
        $segmentId = $this->createCompanySegment('Guard Segment', 'guard-segment')->getId();
        $this->assertNotNull($segmentId);
        $this->publishAbmIntegration(false);

        return $segmentId;
    }
}
