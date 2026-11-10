<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\UserEntityTrait;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class CompanyTimelineControllerTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;
    use UserEntityTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    public function testUnknownCompanyReturnsNotFound(): void
    {
        $this->client->request(Request::METHOD_GET, '/s/company-timeline/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testUserWithoutContactPermissionIsDenied(): void
    {
        $company = new Company();
        $company->setName('Acme');
        $this->em->persist($company);

        $role = $this->createRole('no_contacts');
        $this->createPermission($role, 'lead:lists', 63);
        $user = $this->createUser('no-contacts@mautic-test.com', 'no-contacts', 'No', 'Contacts', $role);
        $this->em->flush();
        $this->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/s/company-timeline/'.$company->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
