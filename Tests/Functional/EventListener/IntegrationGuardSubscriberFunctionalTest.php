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
        $this->publishAbmIntegration(false);

        $this->client->request(Request::METHOD_GET, '/s/ajax?action=plugin:leuchtfeuerAccountBasedMarketingAbm:getCompaniesCount&id=1');

        $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testUiRouteIsReachableWhenIntegrationIsPublished(): void
    {
        $this->publishAbmIntegration();

        $this->client->request(Request::METHOD_GET, '/s/company-segments/1');

        $this->assertResponseIsSuccessful();
    }
}
