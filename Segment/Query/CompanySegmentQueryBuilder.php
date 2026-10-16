<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Query;

use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\ContactSegmentFilters;
use Mautic\LeadBundle\Segment\Exception\SegmentQueryException;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryException;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentFilteringEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentQueryBuilderGeneratedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class CompanySegmentQueryBuilder
{
    use CompanyBatchLimiterTrait;

    /**
     * @var array<int, array<int, string|int>> Contains segment edges mapping
     */
    private array $dependencyMap = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompanyRepository $companyRepository,
        private CompanySegmentRepository $companySegmentRepository,
        private RandomParameterName $randomParameterName,
        private EventDispatcherInterface $dispatcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws SegmentQueryException
     */
    public function assembleCompanySegmentQueryBuilder(CompanySegment $companySegment, ContactSegmentFilters $segmentFilters, bool $changeAlias = false): QueryBuilder
    {
        $connection = $this->entityManager->getConnection();
        if ($connection instanceof PrimaryReadReplicaConnection) {
            // Prefer a replica connection if available.
            $connection->ensureConnectedToReplica();
        }

        $queryBuilder = new QueryBuilder($connection);

        $companyTableAlias        = $changeAlias ? $this->generateRandomParameterName() : $this->companyRepository->getTableAlias();

        $queryBuilder->select($companyTableAlias.'.id')->from($this->getBaseTableName().'companies', $companyTableAlias);
        $this->getResolutionPlan($companySegment);

        $params     = $queryBuilder->getParameters();
        $paramTypes = $queryBuilder->getParameterTypes();

        /** @var ContactSegmentFilter $filter */
        foreach ($segmentFilters as $filter) {
            if ($this->handleDateTimeEmptyOperator($filter, $queryBuilder, $companyTableAlias)) {
                continue;
            }

            if ($this->dispatchPluginFilteringEvent($filter, $queryBuilder)) {
                continue;
            }

            try {
                $queryBuilder = $filter->applyQuery($queryBuilder);
            } catch (\Mautic\LeadBundle\Segment\Exception\TableNotFoundException $e) {
                $this->logger->notice('Error in filter, table '.$filter->contactSegmentFilterCrate->getObject().' not found: '.$e->getMessage());
                continue;
            } catch (\Mautic\LeadBundle\Segment\Exception\FieldNotFoundException $e) {
                $this->logger->notice('Error in filter, field '.$filter->contactSegmentFilterCrate->getField().' not found: '.$e->getMessage());
                continue;
            }

            foreach ($queryBuilder->getParameters() as $k => $v) {
                $params[$k] = $v;
            }
            foreach ($queryBuilder->getParameterTypes() as $k => $v) {
                $paramTypes[$k] = $v;
            }
        }

        $queryBuilder->setParameters($params, $paramTypes);
        $queryBuilder->applyStackLogic();

        return $queryBuilder;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function wrapInCount(QueryBuilder $qb): QueryBuilder
    {
        $connection = $this->entityManager->getConnection();
        if ($connection instanceof PrimaryReadReplicaConnection) {
            $connection->ensureConnectedToReplica();
        }
        $queryBuilder = new QueryBuilder($connection);
        $primary      = $qb->guessPrimaryLeadContactIdColumn();

        if ('orp.lead_id' === $primary) {
            $primary = 'orp.company_id';
        }

        $currentSelects = [];
        $querySelects   = $qb->getQueryParts()['select'];
        \assert(is_array($querySelects));
        foreach ($querySelects as $select) {
            if ($select !== $primary) {
                \assert(is_string($select) || is_array($select));
                $currentSelects[] = $select;
            }
        }

        $qb->select('DISTINCT '.$primary.' as companyIdPrimary');
        foreach ($currentSelects as $select) {
            $qb->addSelect($select);
        }

        $queryBuilder->select('count(companyIdPrimary) count, COALESCE(max(companyIdPrimary), 0) maxId, COALESCE(min(companyIdPrimary), 0) minId')
            ->from('('.$qb->getSQL().')', 'sss');

        $queryBuilder->setParameters($qb->getParameters(), $qb->getParameterTypes());

        return $queryBuilder;
    }

    /**
     * @param array<string, mixed> $batchLimiters
     *
     * @throws QueryException
     */
    public function addNewCompaniesRestrictions(QueryBuilder $queryBuilder, CompanySegment $segment, array $batchLimiters = []): QueryBuilder
    {
        $companiesTableAlias = $queryBuilder->getTableAlias($this->getBaseTableName().'companies');
        \assert(is_string($companiesTableAlias));
        $expr               = $queryBuilder->expr();
        $tableAlias         = $this->generateRandomParameterName();
        $segmentIdParameter = sprintf(':%ssegmentId', $tableAlias);
        $segmentId          = $segment->getId();

        \assert(null !== $expr);
        \assert(null !== $segmentId);

        $segmentQueryBuilder = $queryBuilder->createQueryBuilder()
            ->select($tableAlias.'.company_id')
            ->from($this->getBaseTableName().SegmentCompany::TABLE_NAME, $tableAlias)
            ->andWhere($expr->eq($tableAlias.'.segment_id', $segmentIdParameter));

        $queryBuilder->setParameter(sprintf('%ssegmentId', $tableAlias), $segmentId);

        $this->addMinMaxLimiters($segmentQueryBuilder, $batchLimiters, SegmentCompany::TABLE_NAME, 'company_id');

        $queryBuilder->andWhere($expr->notIn($companiesTableAlias.'.id', $segmentQueryBuilder->getSQL()));

        return $queryBuilder;
    }

    public function queryBuilderGenerated(CompanySegment $companySegment, QueryBuilder $queryBuilder): void
    {
        if (!$this->dispatcher->hasListeners(CompanySegmentQueryBuilderGeneratedEvent::class)) {
            return;
        }

        $event = new CompanySegmentQueryBuilderGeneratedEvent($companySegment, $queryBuilder);
        $this->dispatcher->dispatch($event);
    }

    /**
     * Generate a unique parameter name.
     */
    private function generateRandomParameterName(): string
    {
        return $this->randomParameterName->generateRandomParameterName();
    }

    private function dispatchPluginFilteringEvent(ContactSegmentFilter $filter, QueryBuilder $queryBuilder): bool
    {
        if (!$this->dispatcher->hasListeners(CompanySegmentFilteringEvent::class)) {
            return false;
        }

        $alias = $this->generateRandomParameterName();
        $event = new CompanySegmentFilteringEvent($filter->contactSegmentFilterCrate, $alias, $queryBuilder);
        $this->dispatcher->dispatch($event);
        if ($event->isFilteringDone()) {
            $queryBuilder->addLogic($event->getSubQuery(), $filter->getGlue());

            return true;
        }

        return false;
    }

    /**
     * Returns array with plan for processing.
     *
     * @param array<int, int> $seen
     * @param array<int, int> $resolved
     *
     * @return array<int, int> New resolved
     *
     * @throws SegmentQueryException
     */
    private function getResolutionPlan(CompanySegment $companySegment, array $seen = [], array $resolved = []): array
    {
        $companySegmentId = $companySegment->getId();
        \assert(null !== $companySegmentId);
        $seen[] = $companySegmentId;

        if (!isset($this->dependencyMap[$companySegmentId])) {
            $this->dependencyMap[$companySegmentId] = $this->getSegmentEdges($companySegment);
        }

        $edges = $this->dependencyMap[$companySegmentId];
        foreach ($edges as $edge) {
            if (!in_array($edge, $resolved, true)) {
                if (in_array($edge, $seen, true)) {
                    throw new SegmentQueryException('Circular reference detected.');
                }

                $edgeCompanySegment = $this->companySegmentRepository->find($edge);

                if (null === $edgeCompanySegment) {
                    $this->logger->warning(
                        sprintf(
                            'Company segment dependency not found: segment %d references non-existent segment %d (may have been deleted)',
                            $companySegmentId,
                            $edge
                        )
                    );
                    continue;
                }

                $resolved = $this->getResolutionPlan($edgeCompanySegment, $seen, $resolved);

                $this->companySegmentRepository->detachEntity($edgeCompanySegment);
            }
        }

        $resolved[] = $companySegmentId;

        return $resolved;
    }

    /**
     * @return array<int, int> Array of dependent segment IDs
     */
    private function getSegmentEdges(CompanySegment $companySegment): array
    {
        $segmentFilters = $companySegment->getFilters();
        $segmentEdges   = [];

        foreach ($segmentFilters as $segmentFilter) {
            if (isset($segmentFilter['field']) && CompanySegment::TABLE_NAME === $segmentFilter['field']) {
                $filterEdges = [];
                if (
                    is_array($segmentFilter)
                    && array_key_exists('filter', $segmentFilter)
                    && is_array($segmentFilter['filter'])
                ) {
                    $filterEdges     = $segmentFilter['filter'];
                }

                if (
                    is_array($segmentFilter)
                    && array_key_exists('properties', $segmentFilter)
                    && is_array($segmentFilter['properties'])
                    && array_key_exists('filter', $segmentFilter['properties'])
                    && is_array($segmentFilter['properties']['filter'])
                ) {
                    $filterEdges  = $segmentFilter['properties']['filter'];
                }
                foreach ($filterEdges as $filterEdge) {
                    if (is_numeric($filterEdge)) {
                        $segmentEdges[] = (int) $filterEdge;
                    }
                }
            }
        }

        return array_unique($segmentEdges);
    }

    private function getBaseTableName(): string
    {
        if (is_string(MAUTIC_TABLE_PREFIX)) {
            return MAUTIC_TABLE_PREFIX;
        }

        return '';
    }

    /**
     * Handle special case for datetime/date fields with empty/notEmpty operators.
     * Converts comparisons to IS NULL / IS NOT NULL to avoid SQL errors.
     *
     * @return bool True if the filter was handled, false otherwise
     */
    private function handleDateTimeEmptyOperator(ContactSegmentFilter $filter, QueryBuilder $queryBuilder, string $tableAlias): bool
    {
        $operator = $filter->getOperator();

        if (!in_array($operator, ['empty', '!empty'], true)) {
            return false;
        }

        if (!$filter->contactSegmentFilterCrate->isCompanyType()) {
            return false;
        }

        $isDateTimeField = in_array($filter->contactSegmentFilterCrate->getType(), ['date', 'datetime'], true);
        if (!$isDateTimeField) {
            return false;
        }

        $field = $tableAlias.'.'.$filter->getField();
        $expr  = $queryBuilder->expr();

        if ('empty' === $operator) {
            $expression = $expr->isNull($field);
        } else {
            $expression = $expr->isNotNull($field);
        }

        $queryBuilder->andWhere($expression);

        return true;
    }
}
