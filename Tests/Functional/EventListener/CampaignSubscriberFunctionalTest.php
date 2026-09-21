<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\CampaignBundle\Entity\Campaign;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\Lead as CampaignLead;
use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener\CampaignSubscriber;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class CampaignSubscriberFunctionalTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    use CreateCompanySegmentTestEntitiesTrait;

    private LeadRepository $contactRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();

        $this->contactRepository = self::getContainer()->get(LeadRepository::class);
    }

    public function testCompanySegmentMembershipCampaignCondition(): void
    {
        $joe  = $this->createContact('joe@glibi.com');
        $mary = $this->createContact('mary@glibi.com');
        $john = $this->createContact('john@tbs.com');
        $anna = $this->createContact('anna@abc.com');

        $companyGlibi = $this->createCompany('Glibi Inc', 'info@glibi.com');
        $companyTBS   = $this->createCompany('TBS Corp', 'info@tbs.com');
        $companyAbc   = $this->createCompany('Abc', 'info@abc.com');
        $this->em->flush();

        $this->createCompanyLead($companyGlibi, $joe, true);
        $this->createCompanyLead($companyGlibi, $mary, true);
        $this->createCompanyLead($companyTBS, $john, true);
        $this->createCompanyLead($companyAbc, $anna, true);

        $segmentGlibi = $this->createCompanySegment('Glibi Segment', 'glibi-segment', true);
        $segmentTBS   = $this->createCompanySegment('TBS Segment', 'tbs-segment', true);

        $this->addCompanyToCompanySegment($companyGlibi, $segmentGlibi);
        $this->addCompanyToCompanySegment($companyTBS, $segmentTBS);
        // make sure $companyAbc is manually removed from $segmentGlibi
        $this->addCompanyToCompanySegment($companyAbc, $segmentGlibi);
        $this->manuallyRemoveCompanyFromCompanySegment($companyAbc, $segmentGlibi);

        $campaign = new Campaign();
        $campaign->setName('Company Segment Membership Test Campaign');
        $this->em->persist($campaign);
        $this->em->flush();

        $this->addContactToCampaign($campaign, $joe);
        $this->addContactToCampaign($campaign, $mary);
        $this->addContactToCampaign($campaign, $john);
        $this->addContactToCampaign($campaign, $anna);

        $condition = $this->createCampaignCondition(
            campaign: $campaign,
            name: 'Check if company is in Glibi Segment',
            type: CampaignSubscriber::CONDITION_COMPANY_SEGMENTS,
            properties: ['companySegments' => [$segmentGlibi->getId()]],
            order: 1
        );

        $actionYes = $this->createCampaignAction(
            campaign: $campaign,
            name: 'Set lastname to PASS',
            type: 'lead.updatelead',
            properties: ['lastname' => 'PASS'],
            order: 2,
            decisionPath: 'yes',
            parent: $condition
        );

        $actionNo = $this->createCampaignAction(
            campaign: $campaign,
            name: 'Set lastname to FAIL',
            type: 'lead.updatelead',
            properties: ['lastname' => 'FAIL'],
            order: 3,
            decisionPath: 'no',
            parent: $condition
        );

        $campaign->setCanvasSettings([
            'nodes' => [
                ['id' => $condition->getId(), 'positionX' => '420', 'positionY' => '155'],
                ['id' => $actionYes->getId(), 'positionX' => '176', 'positionY' => '250'],
                ['id' => $actionNo->getId(), 'positionX' => '664', 'positionY' => '250'],
                ['id' => 'lists', 'positionX' => '420', 'positionY' => '50'],
            ],
            'connections' => [
                [
                    'sourceId' => 'lists',
                    'targetId' => $condition->getId(),
                    'anchors'  => [
                        ['endpoint' => 'leadsource', 'eventId' => 'lists'],
                        ['endpoint' => 'top', 'eventId' => $condition->getId()],
                    ],
                ],
                [
                    'sourceId' => $condition->getId(),
                    'targetId' => $actionYes->getId(),
                    'anchors'  => [
                        ['endpoint' => 'yes', 'eventId' => $condition->getId()],
                        ['endpoint' => 'top', 'eventId' => $actionYes->getId()],
                    ],
                ],
                [
                    'sourceId' => $condition->getId(),
                    'targetId' => $actionNo->getId(),
                    'anchors'  => [
                        ['endpoint' => 'no', 'eventId' => $condition->getId()],
                        ['endpoint' => 'top', 'eventId' => $actionNo->getId()],
                    ],
                ],
            ],
        ]);

        $this->em->persist($campaign);
        $this->em->flush();
        $this->em->clear();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()]);

        $joeAfter  = $this->contactRepository->getEntity($joe->getId());
        $maryAfter = $this->contactRepository->getEntity($mary->getId());
        $johnAfter = $this->contactRepository->getEntity($john->getId());
        $annaAfter = $this->contactRepository->getEntity($anna->getId());

        self::assertNotNull($joeAfter);
        self::assertSame('PASS', $joeAfter->getLastname(), 'Joe\'s company (Glibi) is in the segment, should take YES branch');

        self::assertNotNull($maryAfter);
        self::assertSame('PASS', $maryAfter->getLastname(), 'Mary\'s company (Glibi) is in the segment, should take YES branch');

        self::assertNotNull($johnAfter);
        self::assertSame('FAIL', $johnAfter->getLastname(), 'John\'s company (TBS) is NOT in the segment, should take NO branch');

        self::assertNotNull($annaAfter);
        self::assertSame('FAIL', $annaAfter->getLastname(), 'Anna\'s company (Abc) is manually removed from the segment, should take NO branch');
    }

    public function testModifyCompanySegmentsCampaignAction(): void
    {
        $joe  = $this->createContact('joe@glibi.com');
        $mary = $this->createContact('mary@tbs.com');

        $companyGlibi = $this->createCompany('Glibi Inc', 'info@glibi.com');
        $companyTBS   = $this->createCompany('TBS Corp', 'info@tbs.com');
        $this->em->flush();

        $this->createCompanyLead($companyGlibi, $joe, true);
        $this->createCompanyLead($companyTBS, $mary, true);

        $segmentA = $this->createCompanySegment('Segment A', 'segment-a', true);
        $segmentB = $this->createCompanySegment('Segment B', 'segment-b', true);
        $segmentC = $this->createCompanySegment('Segment C', 'segment-c', true);

        $this->addCompanyToCompanySegment($companyGlibi, $segmentA);
        $this->addCompanyToCompanySegment($companyTBS, $segmentB);

        $this->em->flush();

        $campaign = new Campaign();
        $campaign->setName('Modify Company Segments Test Campaign');
        $this->em->persist($campaign);
        $this->em->flush();

        $this->addContactToCampaign($campaign, $joe);
        $this->addContactToCampaign($campaign, $mary);

        $this->createCampaignAction(
            campaign: $campaign,
            name: 'Modify Company Segments',
            type: CampaignSubscriber::ACTION_CHANGE_COMPANY_SEGMENTS,
            properties: [
                'addToLists'      => [$segmentC->getId()],
                'removeFromLists' => [$segmentA->getId()],
            ],
            order: 1
        );

        $this->em->persist($campaign);
        $this->em->flush();
        $this->em->clear();

        $this->testSymfonyCommand('mautic:campaigns:trigger', ['--campaign-id' => $campaign->getId()]);

        $segmentCompanyRepo = $this->em->getRepository(SegmentCompany::class);

        $glibiSegmentA = $segmentCompanyRepo->findOneBy([
            'company'        => $companyGlibi,
            'companySegment' => $segmentA,
        ]);
        self::assertNotNull($glibiSegmentA, 'Glibi SegmentCompany entry should exist');
        self::assertTrue($glibiSegmentA->isManuallyRemoved(), 'Glibi should be manually removed from Segment A');

        $glibiSegmentC = $segmentCompanyRepo->findOneBy([
            'company'        => $companyGlibi,
            'companySegment' => $segmentC,
        ]);
        self::assertNotNull($glibiSegmentC, 'Glibi should be added to Segment C');
        self::assertFalse($glibiSegmentC->isManuallyRemoved(), 'Glibi should not be manually removed from Segment C');

        $tbsSegmentB = $segmentCompanyRepo->findOneBy([
            'company'        => $companyTBS,
            'companySegment' => $segmentB,
        ]);
        self::assertNotNull($tbsSegmentB, 'TBS should still be in Segment B');
        self::assertFalse($tbsSegmentB->isManuallyRemoved(), 'TBS should not be manually removed from Segment B');

        $tbsSegmentC = $segmentCompanyRepo->findOneBy([
            'company'        => $companyTBS,
            'companySegment' => $segmentC,
        ]);
        self::assertNotNull($tbsSegmentC, 'TBS should be added to Segment C');
        self::assertFalse($tbsSegmentC->isManuallyRemoved(), 'TBS should not be manually removed from Segment C');
    }

    private function createContact(string $email): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);

        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
    }

    private function addContactToCampaign(Campaign $campaign, Lead $lead): void
    {
        $campaignLead = new CampaignLead();
        $campaignLead->setCampaign($campaign);
        $campaignLead->setLead($lead);
        $campaignLead->setDateAdded(new \DateTime());
        $this->em->persist($campaignLead);
        $campaign->addLead($lead->getId(), $campaignLead);
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createCampaignCondition(
        Campaign $campaign,
        string $name,
        string $type,
        array $properties,
        int $order,
    ): Event {
        return $this->createCampaignEvent(
            campaign: $campaign,
            name: $name,
            type: $type,
            properties: $properties,
            eventType: 'condition',
            order: $order
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createCampaignAction(
        Campaign $campaign,
        string $name,
        string $type,
        array $properties,
        int $order,
        string $decisionPath = '',
        ?Event $parent = null,
    ): Event {
        return $this->createCampaignEvent(
            campaign: $campaign,
            name: $name,
            type: $type,
            properties: $properties,
            eventType: 'action',
            order: $order,
            decisionPath: $decisionPath,
            parent: $parent
        );
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function createCampaignEvent(
        Campaign $campaign,
        string $name,
        string $type,
        array $properties,
        string $eventType,
        int $order,
        string $decisionPath = '',
        ?Event $parent = null,
    ): Event {
        $event = new Event();
        $event->setName($name);
        $event->setType($type);
        $event->setEventType($eventType);
        $event->setProperties($properties);
        $event->setOrder($order);
        $event->setCampaign($campaign);

        if ('' !== $decisionPath) {
            $event->setDecisionPath($decisionPath);
        }

        if (null !== $parent) {
            $event->setParent($parent);
        }

        $this->em->persist($event);
        $this->em->flush();

        return $event;
    }
}
