<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\ReportBundle\Event\ReportFilterConditionEvent;
use Mautic\ReportBundle\Tests\Functional\AbstractReportSubscriberTestCase;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener\ReportSubscriber;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Functional\CreateCompanySegmentTestEntitiesTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;

#[RequiresMethod(ReportFilterConditionEvent::class, 'setCondition')]
final class ReportSubscriberFunctionalTest extends AbstractReportSubscriberTestCase
{
    use CreateCompanySegmentTestEntitiesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publishAbmIntegration();
    }

    /**
     * @param array<int, array{column: string, glue: string, dynamic: null, condition: string, value: string|list<string>}> $filters
     * @param list<string>                                                                                                  $expectedCompanies
     */
    #[DataProvider('segmentFilterProvider')]
    public function testSegmentFilter(array $filters, array $expectedCompanies): void
    {
        $segments = [
            'A' => $this->createCompanySegment('Segment A', 'segment-a'),
            'B' => $this->createCompanySegment('Segment B', 'segment-b'),
        ];

        $companies = [];
        foreach (['Alpha', 'Beta', 'Delta', 'Epsilon', 'Gamma'] as $name) {
            $companies[$name] = $this->createCompany($name);
        }

        $this->addCompanyToCompanySegment($companies['Alpha'], $segments['A']);
        $this->addCompanyToCompanySegment($companies['Beta'], $segments['B']);
        $this->addCompanyToCompanySegment($companies['Gamma'], $segments['A']);
        $this->addCompanyToCompanySegment($companies['Gamma'], $segments['B']);
        $this->addCompanyToCompanySegment($companies['Epsilon'], $segments['A']);
        $this->manuallyRemoveCompanyFromCompanySegment($companies['Epsilon'], $segments['A']);

        foreach ($filters as &$filter) {
            if (is_array($filter['value'])) {
                $filter['value'] = array_map(static fn (string $key): string => (string) $segments[$key]->getId(), $filter['value']);
            }
        }

        $report = $this->createReport(
            source: ReportSubscriber::CONTEXT_COMPANY_SEGMENTS,
            columns: ['comp.companyname'],
            filters: $filters,
            order: [['column' => 'comp.companyname', 'direction' => 'ASC']]
        );

        $this->verifyReport($report->getId(), array_map(static fn (string $name): array => [$name], $expectedCompanies));
    }

    /**
     * @return iterable<string, array{array<int, array{column: string, glue: string, dynamic: null, condition: string, value: string|list<string>}>, list<string>}>
     */
    public static function segmentFilterProvider(): iterable
    {
        yield 'includes any of one segment' => [
            [self::segmentFilter('in', ['A'])],
            ['Alpha', 'Gamma'],
        ];
        yield 'includes any of two segments' => [
            [self::segmentFilter('in', ['A', 'B'])],
            ['Alpha', 'Beta', 'Gamma'],
        ];
        yield 'excludes all of' => [
            [self::segmentFilter('notIn', ['A'])],
            ['Beta', 'Delta', 'Epsilon'],
        ];
        yield 'is empty' => [
            [self::segmentFilter('empty', '')],
            ['Delta', 'Epsilon'],
        ];
        yield 'is not empty' => [
            [self::segmentFilter('notEmpty', '')],
            ['Alpha', 'Beta', 'Gamma'],
        ];
        yield 'or with a company field filter' => [
            [
                self::segmentFilter('in', ['B']),
                ['column' => 'comp.companyname', 'glue' => 'or', 'dynamic' => null, 'condition' => 'eq', 'value' => 'Delta'],
            ],
            ['Beta', 'Delta', 'Gamma'],
        ];
    }

    /**
     * @param string|list<string> $value
     *
     * @return array{column: string, glue: string, dynamic: null, condition: string, value: string|list<string>}
     */
    private static function segmentFilter(string $condition, string|array $value): array
    {
        return ['column' => ReportSubscriber::SEGMENT_FILTER_COLUMN, 'glue' => 'and', 'dynamic' => null, 'condition' => $condition, 'value' => $value];
    }

    private function createCompany(string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);
        $this->em->flush();

        return $company;
    }
}
