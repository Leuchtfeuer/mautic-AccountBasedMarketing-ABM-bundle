<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyTimelineModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class CompanyTimelineSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    public function testTimelineShowsCompanyCreatedAndLoggedEvents(): void
    {
        $this->publishAbmIntegration();
        $company = $this->createCompany();
        $lead    = $this->createLead();

        self::getContainer()->get(CompanySegmentModel::class)->addCompany($company, [$this->createCompanySegment('Key Accounts', 'key-accounts')], true);
        self::getContainer()->get(CompanyModel::class)->addLeadToCompany($company, $lead);

        $events = $this->getTimelineEvents($company);

        $this->assertSame(['company.contact.added', 'company.segment.added', 'company.created'], array_column($events, 'event'));
        $this->assertSame('John Doe', $events[0]['eventLabel']['label']);
        $this->assertSame('Key Accounts', $events[1]['eventLabel']['label']);
    }

    public function testLogEventsAreSortedByLabelBeforePagination(): void
    {
        $this->publishAbmIntegration();
        $company      = $this->createCompany();
        $segmentModel = self::getContainer()->get(CompanySegmentModel::class);
        $segmentModel->addCompany($company, [$this->createCompanySegment('Alpha', 'alpha')], true);
        $segmentModel->addCompany($company, [$this->createCompanySegment('Beta', 'beta')], true);

        $result = self::getContainer()->get(CompanyTimelineModel::class)
            ->getEngagements($company, ['includeEvents' => ['company.segment.added']], ['eventLabel', 'ASC'], 1, 1);

        $this->assertSame('Alpha', $result['events'][0]['eventLabel']['label']);
        $this->assertSame(2, $result['total']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTimelineEvents(Company $company): array
    {
        $filters = ['includeEvents' => ['company.created', 'company.segment.added', 'company.contact.added']];

        return self::getContainer()->get(CompanyTimelineModel::class)->getEngagements($company, $filters)['events'];
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Acme');
        $company->setDateAdded(new \DateTime('-1 day'));
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }

    private function createLead(): Lead
    {
        $lead = new Lead();
        $lead->setFirstname('John');
        $lead->setLastname('Doe');
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
