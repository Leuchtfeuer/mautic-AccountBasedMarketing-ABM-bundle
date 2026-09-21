<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Command;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\CoreBundle\Tests\Functional\CreateTestEntitiesTrait;
use Mautic\LeadBundle\Command\UpdateLeadListsCommand;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Entity\ListLead;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Command\UpdateCompanySegmentsCommand;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;

final class UpdateCompanySegmentsCommandFunctionalTest extends MauticMysqlTestCase
{
    use CreateTestEntitiesTrait;
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    protected $useCleanupRollback = false;

    public function testLeadSegmentWithCompanySegmentMembershipFilter(): void
    {
        $companyGlobo = $this->createCompany('Globo', 'contact@globo.com');
        $companySbt   = $this->createCompany('SBT', 'contact@sbt.com');
        $this->createCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', emailId: 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', emailId: 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', emailId: 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', emailId: 'leadfour@mautic.com');

        $this->createCompanyLead($companyGlobo, $leadOne, true);
        $this->createCompanyLead($companyGlobo, $leadTwo, true);
        $this->createCompanyLead($companySbt, $leadThree, true);
        $this->createCompanyLead($companySbt, $leadFour, true);

        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');

        // globo added in Company Segment 1
        $this->addCompanyToCompanySegment($companyGlobo, $companySegmentOne);

        $filtersToCompanySegment = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        // globo will be added in cs2 after the company segment command
        $companySegmentTwo = $this->createCompanySegment('Test Company Segment 2', 'test_comp_segment2', true, $filtersToCompanySegment);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!=',
                'properties' => [
                    'filter' => 'asdasdaadasd',
                ],
                'field'  => 'address1',
                'type'   => 'text',
                'object' => 'lead',
            ],
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentTwo->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $leadSegmentTwo = $this->createSegment('Test Segment 2', $filtersToLeadSegment);

        $this->em->flush();
        $this->em->clear();

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->em->getRepository(LeadList::class);

        // Before segment update, no leads should be in the segment
        $this->assertSame(0, $leadListRepository->getLeadCount([$leadSegmentTwo->getId()]));

        // Company segments first, then lead segments (order of the PR's unified command)
        $this->testSymfonyCommand(UpdateCompanySegmentsCommand::NAME, ['--bypass-locking' => true]);
        $output = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['--bypass-locking' => true]);

        $this->assertStringContainsString('2 total contact(s) to be added', $output->getDisplay());

        // After update, 2 leads should be in the segment (leadThree and leadFour from SBT which is NOT in Company Segment 2)
        $this->assertSame(2, $leadListRepository->getLeadCount([$leadSegmentTwo->getId()]));
    }

    public function testLeadSegmentWithCompanySegmentEmptyFilter(): void
    {
        $companyGlobo = $this->createCompany('Globo', 'contact@globo.com');
        $companySbt   = $this->createCompany('SBT', 'contact@sbt.com');
        $this->createCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', emailId: 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', emailId: 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', emailId: 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', emailId: 'leadfour@mautic.com');

        $this->createCompanyLead($companyGlobo, $leadOne, true);
        $this->createCompanyLead($companyGlobo, $leadTwo, true);
        $this->createCompanyLead($companySbt, $leadThree, true);
        $this->createCompanyLead($companySbt, $leadFour, true);

        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');

        // globo added in Company Segment 1
        $this->addCompanyToCompanySegment($companyGlobo, $companySegmentOne);

        $filtersToLeadSegment = [
            [
                'glue'     => 'and',
                'operator' => 'empty',
                'field'    => 'company_segments',
                'type'     => 'company_segments',
                'object'   => 'company_segments',
            ],
        ];

        $leadSegmentOne = $this->createSegment('Test Segment 1', $filtersToLeadSegment);

        $this->em->flush();
        $this->em->clear();

        $output = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['--bypass-locking' => true]);

        $this->assertStringContainsString('2 total contact(s) to be added', $output->getDisplay());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->em->getRepository(LeadList::class);

        // 2 leads should be in the segment (leadThree and leadFour from SBT which has no company segment)
        $this->assertSame(2, $leadListRepository->getLeadCount([$leadSegmentOne->getId()]));
    }

    public function testLeadSegmentWithCompanySegmentNotEmptyFilter(): void
    {
        $companyGlobo  = $this->createCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->createCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->createCompany('Record', 'contact@record.com');

        $leadOne = $this->createLead('John Globo Doe', emailId: 'leadone@mautic.com');
        $this->createLead('Brian Doe', emailId: 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', emailId: 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', emailId: 'leadfour@mautic.com');

        $this->createCompanyLead($companyGlobo, $leadOne, true);
        $this->createCompanyLead($companySbt, $leadThree, true);
        $this->createCompanyLead($companySbt, $leadFour, true);

        $companySegmentGlobo  = $this->createCompanySegment('Test Company Segment globo', 'test_comp_segment_globo');
        $companySegmentSbt    = $this->createCompanySegment('Test Company Segment Sbt', 'test_comp_segment_sbt');
        $companySegmentRecord = $this->createCompanySegment('Test Company Segment Record', 'test_comp_segment_record');

        // Add companies to segments
        $this->addCompanyToCompanySegment($companyGlobo, $companySegmentGlobo);
        $this->addCompanyToCompanySegment($companySbt, $companySegmentSbt);
        $this->addCompanyToCompanySegment($companyRecord, $companySegmentRecord);

        $filtersToLeadSegment = [
            [
                'glue'     => 'and',
                'operator' => '!empty',
                'field'    => 'company_segments',
                'type'     => 'company_segments',
                'object'   => 'company_segments',
            ],
        ];

        $leadSegmentTwo = $this->createSegment('Test Segment all not empty', $filtersToLeadSegment);

        $this->em->flush();
        $this->em->clear();

        $output = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['--bypass-locking' => true]);

        $this->assertStringContainsString('3 total contact(s) to be added', $output->getDisplay());

        /** @var LeadListRepository $leadListRepository */
        $leadListRepository = $this->em->getRepository(LeadList::class);

        // All 3 leads should be in the segment (they all belong to companies that are in company segments)
        $this->assertSame(3, $leadListRepository->getLeadCount([$leadSegmentTwo->getId()]));
    }

    public function testUpdateLeadSegmentsUsingExcludeACompanySegment(): void
    {
        $companyGlobo = $this->createCompany('Globo', 'contact@globo.com');
        $companySbt   = $this->createCompany('SBT', 'contact@sbt.com');

        $leadOne   = $this->createLead('John Globo Doe', emailId: 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', emailId: 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', emailId: 'leadthree@mautic.com');
        $leadFour  = $this->createLead('Braw Doe', emailId: 'leadfour@mautic.com');

        $this->createCompanyLead($companyGlobo, $leadOne);
        $this->createCompanyLead($companyGlobo, $leadTwo);
        $this->createCompanyLead($companySbt, $leadThree);
        $this->createCompanyLead($companySbt, $leadFour);

        $totalCompanyLeadsBefore = $this->em->getRepository(CompanyLead::class)->findAll();
        $this->assertCount(4, $totalCompanyLeadsBefore);

        $companySegmentOne = $this->createCompanySegment('Test Company Segment 1', 'test_comp_segment');
        $this->addCompanyToCompanySegment($companyGlobo, $companySegmentOne);

        $resultSegmentCompaniesBefore = $this->em->getRepository(SegmentCompany::class)->findAll();
        $this->assertCount(1, $resultSegmentCompaniesBefore);

        $filtersToLeadSegment = [
            [
                'glue'       => 'and',
                'operator'   => '!in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];

        $this->createSegment('test_segment', $filtersToLeadSegment);
        $this->em->flush();

        $leadListModel = self::getContainer()->get(ListModel::class);
        $this->assertInstanceOf(ListModel::class, $leadListModel);
        $leadListTotalBefore = $leadListModel->getListLeadRepository()->findAll();
        $this->assertCount(0, $leadListTotalBefore);

        $output = $this->testSymfonyCommand(UpdateLeadListsCommand::NAME, ['--bypass-locking' => true]);

        $this->assertStringContainsString('2 total contact(s) to be added', $output->getDisplay());

        $leadListTotalAfter = $leadListModel->getListLeadRepository()->findAll();
        $this->assertCount(2, $leadListTotalAfter);
    }

    public function testUpdateCompanySegmentWithCompanySegmentMembershipFilter(): void
    {
        $companyGlobo  = $this->createCompany('Globo', 'contact@globo.com');
        $companySbt    = $this->createCompany('SBT', 'contact@sbt.com');
        $companyRecord = $this->createCompany('Record', 'contact@record.com');

        $leadOne   = $this->createLead('John Globo Doe', emailId: 'leadone@mautic.com');
        $leadTwo   = $this->createLead('Brian Doe', emailId: 'leadtwo@mautic.com');
        $leadThree = $this->createLead('Mat Doe', emailId: 'leadthree@mautic.com');

        $leadOne->setCompany($companySbt);
        $leadOne->setPrimaryCompany($companyGlobo);

        $leadTwo->setPrimaryCompany($companyRecord);

        $leadThree->setPrimaryCompany($companyRecord);
        $leadThree->setCompany($companyGlobo);

        $this->em->persist($leadOne);
        $this->em->persist($leadTwo);
        $this->em->persist($leadThree);
        $this->em->flush();

        $companySegmentOne = $this->createCompanySegment('Test Segment 1', 'test_segment');
        $this->addCompanyToCompanySegment($companyGlobo, $companySegmentOne);
        $filters = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$companySegmentOne->getId()],
                ],
                'field'  => 'company_segments',
                'type'   => 'company_segments',
                'object' => 'company_segments',
            ],
        ];
        $companySegmentTwo            = $this->createCompanySegment('Test Segment 2', 'test_segment2', true, $filters);
        $resultSegmentCompaniesBefore = $this->em->getRepository(SegmentCompany::class)->findAll();

        $this->assertCount(1, $resultSegmentCompaniesBefore);

        $this->testSymfonyCommand(UpdateCompanySegmentsCommand::NAME, ['--bypass-locking' => true]);

        $resultSegmentCompaniesAfter = $this->em->getRepository(SegmentCompany::class)->findAll();
        $this->assertCount(2, $resultSegmentCompaniesAfter);
        $this->assertEquals($resultSegmentCompaniesAfter[0]->getCompany()->getId(), $resultSegmentCompaniesAfter[1]->getCompany()->getId());
        $this->assertEquals($resultSegmentCompaniesAfter[1]->getCompanySegment()->getId(), $companySegmentTwo->getId());
    }

    public function testUpdateCompanySegmentsWithLeadListFilter(): void
    {
        $companyWithLeadWithoutSegment = $this->createCompany('noleadsegment', 'contact@globo.com');
        $companyWithLeadWithSegment1   = $this->createCompany('leadsegment1', 'contact@sbt.com');
        $companyWithLeadWithSegment2   = $this->createCompany('leadsegment2', 'contact@record.com');
        $this->createCompany('companywithoutlead', 'companywithout@lead.com');

        $contactWithoutSegment = $this->createLead('Nosegment', emailId: 'leadone@mautic.com');
        $contactWithSegment1   = $this->createLead('Segment1', emailId: 'leadtwo@mautic.com');
        $contactWithSegment2   = $this->createLead('Segment2', emailId: 'leadthree@mautic.com');

        $leadSegment1 = $this->createSegment('segment_1', []);
        $leadSegment2 = $this->createSegment('segment_2', []);
        $this->em->flush();

        $this->addLeadToSegment($contactWithSegment1, $leadSegment1);
        $this->addLeadToSegment($contactWithSegment2, $leadSegment2);

        $this->createCompanyLead($companyWithLeadWithoutSegment, $contactWithoutSegment);
        $this->createCompanyLead($companyWithLeadWithSegment1, $contactWithSegment1);
        $this->createCompanyLead($companyWithLeadWithSegment2, $contactWithSegment2);

        $this->em->flush();

        $filterSegment1 = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$leadSegment1->getId()],
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterSegment2 = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'in',
                'properties' => [
                    'filter' => [$leadSegment2->getId()],
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterEmptySegment = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => 'empty',
                'properties' => [
                    'filter' => null,
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $filterNotEmptySegment = [
            'filters' => [
                'glue'       => 'and',
                'operator'   => '!empty',
                'properties' => [
                    'filter' => null,
                ],
                'field'  => 'contactsegmentmembership',
                'type'   => 'leadlist',
                'object' => 'any_companycontact',
            ],
        ];
        $companySegmentLeadList1        = $this->createCompanySegment('Lead List 1 Segment Filter', 'lead_list_1_segment_filter', true, $filterSegment1);
        $companySegmentLeadList2        = $this->createCompanySegment('Lead List 2 Segment Filter', 'lead_list_2_segment_filter', true, $filterSegment2);
        $companySegmentEmptyLeadList    = $this->createCompanySegment('Empty Lead Segments', 'empty_lead_segments', true, $filterEmptySegment);
        $companySegmentNotEmptyLeadList = $this->createCompanySegment('Not Empty Lead Segments', 'not_empty_lead_segments', true, $filterNotEmptySegment);

        $this->testSymfonyCommand(UpdateCompanySegmentsCommand::NAME, ['--bypass-locking' => true]);

        $companiesInSegment1 = $this->em->getRepository(SegmentCompany::class)
            ->findBy(['companySegment' => $companySegmentLeadList1]);
        $this->assertCount(1, $companiesInSegment1);
        $this->assertEquals('leadsegment1', $companiesInSegment1[0]->getCompany()->getName());

        $companiesInSegment2 = $this->em->getRepository(SegmentCompany::class)
            ->findBy(['companySegment' => $companySegmentLeadList2]);
        $this->assertCount(1, $companiesInSegment2);
        $this->assertEquals('leadsegment2', $companiesInSegment2[0]->getCompany()->getName());

        $companiesInEmptySegment = $this->em->getRepository(SegmentCompany::class)
            ->findBy(['companySegment' => $companySegmentEmptyLeadList]);
        $companyNames = array_map(fn (SegmentCompany $cs) => $cs->getCompany()->getName(), $companiesInEmptySegment);
        $this->assertCount(2, $companiesInEmptySegment);
        $this->assertContains('noleadsegment', $companyNames);
        $this->assertContains('companywithoutlead', $companyNames);

        $companiesInNotEmptySegment = $this->em->getRepository(SegmentCompany::class)
            ->findBy(['companySegment' => $companySegmentNotEmptyLeadList]);
        $this->assertCount(2, $companiesInNotEmptySegment);
        $companyNames = array_map(fn (SegmentCompany $cs) => $cs->getCompany()->getName(), $companiesInNotEmptySegment);
        $this->assertContains('leadsegment1', $companyNames);
        $this->assertContains('leadsegment2', $companyNames);
    }

    public function testExcludeCompanySegmentIdSkipsExcludedSegment(): void
    {
        $this->createCompany('Company1', 'c1@test.com');
        $this->createCompany('Company2', 'c2@test.com');

        $companyEmailFilter = [
            [
                'glue'     => 'and',
                'operator' => '!empty',
                'field'    => 'companyemail',
                'type'     => 'email',
                'object'   => 'company',
            ],
        ];

        $companySegment1 = $this->createCompanySegment('Segment 1', 'seg-1', true, $companyEmailFilter);
        $companySegment2 = $this->createCompanySegment('Segment 2', 'seg-2', true, $companyEmailFilter);

        $this->em->flush();

        $output = $this->testSymfonyCommand(UpdateCompanySegmentsCommand::NAME, [
            '--exclude-companysegment-id' => [$companySegment1->getId()],
            '--bypass-locking'            => true,
        ]);

        $display = $output->getDisplay();

        // Segment 2 was rebuilt
        $this->assertStringContainsString(sprintf('Rebuilding company segments for segment %d', $companySegment2->getId()), $display);

        // Segment 1 was excluded
        $this->assertStringNotContainsString(sprintf('Rebuilding company segments for segment %d', $companySegment1->getId()), $display);
    }

    private function addLeadToSegment(Lead $lead, LeadList $segment): void
    {
        $listLead = new ListLead();
        $listLead->setLead($lead);
        $listLead->setList($segment);
        $listLead->setDateAdded(new \DateTime());
        $listLead->setManuallyAdded(true);
        $listLead->setManuallyRemoved(false);
        $this->em->persist($listLead);
        $this->em->flush();
    }
}
