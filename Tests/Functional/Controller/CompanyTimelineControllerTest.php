<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\Controller;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\UserEntityTrait;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
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

    public function testTimelineIsRendered(): void
    {
        $company = new Company();
        $company->setName('Acme');
        $company->setDateAdded(new \DateTime('-1 day'));
        $this->em->persist($company);
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, '/s/company-timeline/'.$company->getId());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Company created', (string) $this->client->getResponse()->getContent());
    }

    public function testSearchFilterIsAppliedAndRemembered(): void
    {
        $company = new Company();
        $company->setName('Acme');
        $company->setDateAdded(new \DateTime('-1 day'));
        $this->em->persist($company);
        $this->em->flush();
        self::getContainer()->get(CompanySegmentModel::class)->addCompany($company, [$this->createCompanySegment('Key Accounts', 'key-accounts')], true);
        $url = '/s/company-timeline/'.$company->getId();

        $this->client->request(Request::METHOD_POST, $url, ['search' => 'Key Accounts']);
        $this->assertTimelineShowsOnlySegmentEntry();

        $this->client->request(Request::METHOD_GET, $url);
        $this->assertTimelineShowsOnlySegmentEntry();
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

    private function assertTimelineShowsOnlySegmentEntry(): void
    {
        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Key Accounts', $content);
        $this->assertStringNotContainsString('Company created', $content);
    }
}
