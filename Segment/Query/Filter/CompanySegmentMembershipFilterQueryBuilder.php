<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Query\Filter;

use Doctrine\DBAL\ArrayParameterType;
use Mautic\LeadBundle\Event\SegmentDictionaryGenerationEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\ContactSegmentFilter;
use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\LeadBundle\Segment\Query\Filter\BaseFilterQueryBuilder;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Allows filtering companies by their membership in other company segments and filtering leads by their primary company's segment membership.
 */
final class CompanySegmentMembershipFilterQueryBuilder extends BaseFilterQueryBuilder implements EventSubscriberInterface
{
    use MembershipOperatorExpressionTrait;

    public static function getServiceId(): string
    {
        return self::class;
    }

    public function applyQuery(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        if (CompanySegmentModel::PROPERTIES_FIELD !== $filter->getField()) {
            throw new \RuntimeException('The supported field is '.CompanySegmentModel::PROPERTIES_FIELD);
        }

        $from = $queryBuilder->getQueryPart('from');
        assert(is_array($from));

        if (
            array_key_exists(0, $from)
            && is_array($from[0])
            && array_key_exists('table', $from[0])
            && $from[0]['table'] === $this->getPreTable().'leads'
        ) {
            return $this->applyQueryToLeadSegment($queryBuilder, $filter);
        }

        return $this->applyQueryToCompanySegment($queryBuilder, $filter);
    }

    private function applyQueryToCompanySegment(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $companiesTableAlias = $queryBuilder->getTableAlias($this->getPreTable().'companies');
        \assert(is_string($companiesTableAlias));

        $segmentIds = $filter->getParameterValue();
        $operator   = $filter->getOperator();

        if (OperatorOptions::EMPTY === $operator || 'notEmpty' === $operator) {
            $sub   = $queryBuilder->createQueryBuilder();
            $alias = $this->generateRandomParameterName();

            $sub->select('1')
                ->from($this->getPreTable().SegmentCompany::TABLE_NAME, $alias)
                ->where($sub->expr()->eq($alias.'.company_id', $companiesTableAlias.'.id'))
                ->andWhere($sub->expr()->eq($alias.'.manually_removed', 0));

            $expr = (OperatorOptions::EMPTY === $operator)
                ? $queryBuilder->expr()->notExists($sub->getSQL())
                : $queryBuilder->expr()->exists($sub->getSQL());

            $queryBuilder->addLogic($expr, $filter->getGlue());

            return $queryBuilder;
        }

        if (!is_array($segmentIds)) {
            $segmentIds = [(int) $segmentIds];
        }

        // Simple EXISTS query on materialized company_segments_companies table
        $subQuery = function (array $ids) use ($queryBuilder, $companiesTableAlias): QueryBuilder {
            $sub       = $queryBuilder->createQueryBuilder();
            $alias     = $this->generateRandomParameterName();
            $paramName = $this->generateRandomParameterName();

            $sub->select('1')
                ->from($this->getPreTable().SegmentCompany::TABLE_NAME, $alias)
                ->where($sub->expr()->eq($alias.'.company_id', $companiesTableAlias.'.id'))
                ->andWhere($sub->expr()->in($alias.'.segment_id', ':'.$paramName))
                ->andWhere($sub->expr()->eq($alias.'.manually_removed', 0));

            $queryBuilder->setParameter($paramName, $ids, ArrayParameterType::INTEGER);

            return $sub;
        };

        $queryBuilder->addLogic($this->buildMembershipExpression($queryBuilder, $operator, $segmentIds, $subQuery), $filter->getGlue());

        return $queryBuilder;
    }

    private function applyQueryToLeadSegment(QueryBuilder $queryBuilder, ContactSegmentFilter $filter): QueryBuilder
    {
        $leadAlias = $queryBuilder->getTableAlias($this->getPreTable().'leads');
        \assert(is_string($leadAlias));

        $segmentIds = $filter->getParameterValue();
        $operator   = $filter->getOperator();

        // Handle empty/notEmpty operators - check if lead has ANY company in ANY segment
        if (OperatorOptions::EMPTY === $operator || 'notEmpty' === $operator) {
            $sub                  = $queryBuilder->createQueryBuilder();
            $clAlias              = $this->generateRandomParameterName();
            $csAlias              = $this->generateRandomParameterName();
            $isPrimaryParam       = $this->generateRandomParameterName();
            $manuallyRemovedParam = $this->generateRandomParameterName();

            $sub->select('1')
                ->from($this->getPreTable().'companies_leads', $clAlias)
                ->join($clAlias, $this->getPreTable().SegmentCompany::TABLE_NAME, $csAlias, $csAlias.'.company_id = '.$clAlias.'.company_id')
                ->where($sub->expr()->eq($clAlias.'.lead_id', $leadAlias.'.id'))
                ->andWhere($sub->expr()->eq($clAlias.'.is_primary', ':'.$isPrimaryParam))
                ->andWhere($sub->expr()->eq($csAlias.'.manually_removed', ':'.$manuallyRemovedParam));

            $queryBuilder->setParameter($isPrimaryParam, 1);
            $queryBuilder->setParameter($manuallyRemovedParam, 0);

            $expr = (OperatorOptions::EMPTY === $operator)
                ? $queryBuilder->expr()->notExists($sub->getSQL())
                : $queryBuilder->expr()->exists($sub->getSQL());

            $queryBuilder->addLogic($expr, $filter->getGlue());

            return $queryBuilder;
        }

        if (!is_array($segmentIds)) {
            $segmentIds = [(int) $segmentIds];
        }

        // EXISTS query with 2-table JOIN: companies_leads -> company_segments_companies
        $subQuery = function (array $ids) use ($queryBuilder, $leadAlias): QueryBuilder {
            $sub                  = $queryBuilder->createQueryBuilder();
            $clAlias              = $this->generateRandomParameterName();
            $csAlias              = $this->generateRandomParameterName();
            $segmentIdsParam      = $this->generateRandomParameterName();
            $isPrimaryParam       = $this->generateRandomParameterName();
            $manuallyRemovedParam = $this->generateRandomParameterName();

            $sub->select('1')
                ->from($this->getPreTable().'companies_leads', $clAlias)
                ->join($clAlias, $this->getPreTable().SegmentCompany::TABLE_NAME, $csAlias, $csAlias.'.company_id = '.$clAlias.'.company_id')
                ->where($sub->expr()->eq($clAlias.'.lead_id', $leadAlias.'.id'))
                ->andWhere($sub->expr()->eq($clAlias.'.is_primary', ':'.$isPrimaryParam))
                ->andWhere($sub->expr()->in($csAlias.'.segment_id', ':'.$segmentIdsParam))
                ->andWhere($sub->expr()->eq($csAlias.'.manually_removed', ':'.$manuallyRemovedParam));

            $queryBuilder->setParameter($segmentIdsParam, $ids, ArrayParameterType::INTEGER);
            $queryBuilder->setParameter($isPrimaryParam, 1);
            $queryBuilder->setParameter($manuallyRemovedParam, 0);

            return $sub;
        };

        $queryBuilder->addLogic($this->buildMembershipExpression($queryBuilder, $operator, $segmentIds, $subQuery), $filter->getGlue());

        return $queryBuilder;
    }

    public function onAddFilter(SegmentDictionaryGenerationEvent $event): void
    {
        $event->addTranslation(CompanySegmentModel::PROPERTIES_FIELD, [
            'type' => self::getServiceId(),
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::SEGMENT_DICTIONARY_ON_GENERATE => 'onAddFilter',
        ];
    }

    private function getPreTable(): string
    {
        if (is_string(MAUTIC_TABLE_PREFIX)) {
            return MAUTIC_TABLE_PREFIX;
        }

        return '';
    }
}
