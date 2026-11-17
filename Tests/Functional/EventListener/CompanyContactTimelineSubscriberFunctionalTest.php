<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyTimelineModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class CompanyContactTimelineSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    public function testContactEventsOfAllContactsAreMergedIntoOneTimeline(): void
    {
        $this->publishAbmIntegration();
        $company = new Company();
        $company->setName('Acme');
        $this->em->persist($company);
        $this->createCompanyLead($company, $this->createLead('John', '2026-01-01'));
        $this->createCompanyLead($company, $this->createLead('Jane', '2026-01-03'));
        $this->createCompanyLead($company, $this->createLead('Jack', '2026-01-02'));
        $this->createLead('Other', '2026-01-04');

        $result = self::getContainer()->get(CompanyTimelineModel::class)
            ->getEngagements($company, ['includeEvents' => ['lead.create']], null, 1, 2);

        $this->assertSame(['Jane', 'Jack'], array_map(static fn (array $e): ?string => $e['contact']->getFirstname(), $result['events']));
        $this->assertSame(3, $result['total']);
        $this->assertArrayHasKey('lead.create', $result['types']);
    }

    private function createLead(string $firstname, string $dateAdded): Lead
    {
        $lead = new Lead();
        $lead->setFirstname($firstname);
        $lead->setDateAdded(new \DateTime($dateAdded));
        $this->em->persist($lead);
        $this->em->flush();

        return $lead;
    }
}
