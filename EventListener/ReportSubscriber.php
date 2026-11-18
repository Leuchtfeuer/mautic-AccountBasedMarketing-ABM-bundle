<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Model\CompanyReportData;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use Mautic\ReportBundle\Event\ReportFilterConditionEvent;
use Mautic\ReportBundle\Event\ReportGeneratorEvent;
use Mautic\ReportBundle\ReportEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ReportSubscriber implements EventSubscriberInterface
{
    public const CONTEXT_COMPANY_SEGMENTS = 'company_segments';

    public const SEGMENT_FILTER_COLUMN = 'csx.segment_id';

    private const COMPANY_COLUMNS = [
        'comp.id',
        'comp.companyaddress1',
        'comp.companyaddress2',
        'comp.companyemail',
        'comp.companyphone',
        'comp.companycity',
        'comp.companystate',
        'comp.companyzipcode',
        'comp.companycountry',
        'comp.companyname',
        'comp.companywebsite',
        'comp.companynumber_of_employees',
        'comp.companyfax',
        'comp.companyannual_revenue',
        'comp.companyindustry',
        'comp.companydescription',
    ];

    public function __construct(
        private Config $config,
        private CompanyReportData $companyReportData,
        private CompanySegmentModel $companySegmentModel,
        private Connection $db,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReportEvents::REPORT_ON_BUILD     => 'onReportBuilder',
            ReportEvents::REPORT_ON_GENERATE  => 'onReportGenerate',
            ReportFilterConditionEvent::class => 'onFilterCondition',
        ];
    }

    public function onReportBuilder(ReportBuilderEvent $event): void
    {
        if (!$this->config->isPublished() || !$event->checkContext([self::CONTEXT_COMPANY_SEGMENTS])) {
            return;
        }

        $columns = array_intersect_key($this->companyReportData->getCompanyData(), array_flip(self::COMPANY_COLUMNS));

        $filters = $columns;

        // TODO: remove the check and require the Mautic version with ReportFilterConditionEvent in composer.json.
        if (class_exists(ReportFilterConditionEvent::class)) {
            $filters[self::SEGMENT_FILTER_COLUMN] = [
                'alias'     => 'companysegments',
                'label'     => 'mautic.company_segments.report.company_segments',
                'type'      => 'multiselect',
                'list'      => array_column($this->companySegmentModel->getCompanySegments(), 'name', 'id'),
                'operators' => [
                    'in'       => 'mautic.core.operator.in',
                    'notIn'    => 'mautic.core.operator.notin',
                    'empty'    => 'mautic.core.operator.isempty',
                    'notEmpty' => 'mautic.core.operator.isnotempty',
                ],
            ];
        }

        $event->addTable(
            self::CONTEXT_COMPANY_SEGMENTS,
            [
                'display_name' => 'mautic.company_segments.report.company_segments',
                'columns'      => $columns,
                'filters'      => $filters,
            ],
            'companies'
        );
    }

    public function onReportGenerate(ReportGeneratorEvent $event): void
    {
        if (!$event->checkContext([self::CONTEXT_COMPANY_SEGMENTS])) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder()->from(MAUTIC_TABLE_PREFIX.'companies', 'comp');
        $event->applyDateFilters($queryBuilder, 'date_added', 'comp');
    }

    public function onFilterCondition(ReportFilterConditionEvent $event): void
    {
        $filter = $event->getFilter();
        if (!$event->checkContext(self::CONTEXT_COMPANY_SEGMENTS) || self::SEGMENT_FILTER_COLUMN !== $filter['column']) {
            return;
        }

        $subQuery = $this->db->createQueryBuilder()
            ->select('csx.company_id')
            ->from(MAUTIC_TABLE_PREFIX.SegmentCompany::TABLE_NAME, 'csx')
            ->where('csx.manually_removed = 0');

        if (in_array($filter['condition'], ['in', 'notIn'], true) && !empty($filter['value'])) {
            $segmentIds = array_map(static fn ($value): string => (string) (int) $value, (array) $filter['value']);
            $subQuery->andWhere($subQuery->expr()->in('csx.segment_id', $segmentIds));
        }

        $expr = $event->getQueryBuilder()->expr();
        $event->setCondition(in_array($filter['condition'], ['in', 'notEmpty'], true)
            ? $expr->in('comp.id', $subQuery->getSQL())
            : $expr->notIn('comp.id', $subQuery->getSQL()));
    }
}
