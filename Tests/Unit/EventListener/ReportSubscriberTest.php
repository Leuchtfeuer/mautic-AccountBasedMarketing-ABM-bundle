<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\EventListener;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ChannelBundle\Helper\ChannelListHelper;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Entity\Report;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportFilterConditionEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\Helper\ReportHelper;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener\ReportSubscriber;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ReportSubscriberTest extends TestCase
{
    /** @var Stub&Connection */
    private Stub $connection;

    private ChannelListHelper $channelListHelper;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->connection->method('createQueryBuilder')->willReturnCallback(fn (): QueryBuilder => new QueryBuilder($this->connection));
        $this->connection->method('getExpressionBuilder')->willReturn(new ExpressionBuilder($this->connection));

        $this->channelListHelper = $this->createStub(ChannelListHelper::class);
    }

    #[RequiresMethod(ReportFilterConditionEvent::class, 'setCondition')]
    public function testOnReportBuilderAddsCompanySegmentsTable(): void
    {
        $event = $this->createBuilderEvent();
        $this->createSubscriber()->onReportBuilder($event);

        $table = $event->getTables()[ReportSubscriber::CONTEXT_COMPANY_SEGMENTS];
        $this->assertSame(['comp.id', 'comp.companyname'], array_keys($table['columns']));
        $this->assertSame(['comp.id', 'comp.companyname', ReportSubscriber::SEGMENT_FILTER_COLUMN], array_keys($table['filters']));
        $this->assertSame('multiselect', $table['filters'][ReportSubscriber::SEGMENT_FILTER_COLUMN]['type']);
        $this->assertSame(['1' => 'Segment A', '2' => 'Segment B'], $table['filters'][ReportSubscriber::SEGMENT_FILTER_COLUMN]['list']);
    }

    public function testOnReportBuilderDoesNothingWhenPluginIsUnpublished(): void
    {
        $event = $this->createBuilderEvent();
        $this->createSubscriber(false)->onReportBuilder($event);

        $this->assertSame([], $event->getTables());
    }

    public function testOnReportGenerateSetsCompaniesTable(): void
    {
        $report = (new Report())->setSource(ReportSubscriber::CONTEXT_COMPANY_SEGMENTS);
        $event  = new ReportGeneratorEvent($report, [], new QueryBuilder($this->connection), $this->channelListHelper);

        $this->createSubscriber()->onReportGenerate($event);

        $this->assertSame(
            'SELECT  FROM '.MAUTIC_TABLE_PREFIX.'companies comp WHERE comp.date_added IS NULL OR (comp.date_added BETWEEN :dateFrom AND :dateTo)',
            $event->getQueryBuilder()->getSQL()
        );
    }

    #[RequiresMethod(ReportFilterConditionEvent::class, 'setCondition')]
    public function testOnFilterConditionCastsSegmentIdsToInt(): void
    {
        $event = $this->createFilterEvent(ReportSubscriber::CONTEXT_COMPANY_SEGMENTS, ReportSubscriber::SEGMENT_FILTER_COLUMN, 'in', ['1) OR (1=1', '2']);

        $this->createSubscriber()->onFilterCondition($event);

        $this->assertStringContainsString('csx.segment_id IN (1, 2)', (string) $event->getCondition());
        $this->assertStringNotContainsString('1=1', (string) $event->getCondition());
    }

    #[RequiresMethod(ReportFilterConditionEvent::class, 'setCondition')]
    public function testOnFilterConditionIgnoresOtherColumns(): void
    {
        $event = $this->createFilterEvent(ReportSubscriber::CONTEXT_COMPANY_SEGMENTS, 'comp.companyname', 'eq', 'Acme');

        $this->createSubscriber()->onFilterCondition($event);

        $this->assertNull($event->getCondition());
    }

    #[RequiresMethod(ReportFilterConditionEvent::class, 'setCondition')]
    public function testOnFilterConditionIgnoresOtherContexts(): void
    {
        $event = $this->createFilterEvent('leads', ReportSubscriber::SEGMENT_FILTER_COLUMN, 'in', ['1']);

        $this->createSubscriber()->onFilterCondition($event);

        $this->assertNull($event->getCondition());
    }

    private function createSubscriber(bool $published = true): ReportSubscriber
    {
        $config = $this->createStub(Config::class);
        $config->method('isPublished')->willReturn($published);

        $companyReportData = $this->createStub(CompanyReportData::class);
        $companyReportData->method('getCompanyData')->willReturn([
            'comp.id'                   => ['label' => 'ID', 'type' => 'int'],
            'comp.companyname'          => ['label' => 'Name', 'type' => 'string'],
            'companies_lead.is_primary' => ['label' => 'Primary', 'type' => 'bool'],
        ]);

        $companySegmentModel = $this->createStub(CompanySegmentModel::class);
        $companySegmentModel->method('getCompanySegments')->willReturn([
            ['id' => '1', 'name' => 'Segment A', 'alias' => 'a', 'createdBy' => null],
            ['id' => '2', 'name' => 'Segment B', 'alias' => 'b', 'createdBy' => null],
        ]);

        return new ReportSubscriber($config, $companyReportData, $companySegmentModel, $this->connection);
    }

    private function createBuilderEvent(): ReportBuilderEvent
    {
        return new ReportBuilderEvent(
            $this->createStub(TranslatorInterface::class),
            $this->channelListHelper,
            ReportSubscriber::CONTEXT_COMPANY_SEGMENTS,
            [],
            new ReportHelper($this->createStub(EventDispatcherInterface::class))
        );
    }

    /**
     * @param string|string[] $value
     */
    private function createFilterEvent(string $source, string $column, string $condition, string|array $value): ReportFilterConditionEvent
    {
        $report = (new Report())->setSource($source);
        $filter = ['column' => $column, 'condition' => $condition, 'value' => $value, 'glue' => 'and'];

        return new ReportFilterConditionEvent($report, $filter, [], new QueryBuilder($this->connection));
    }
}
