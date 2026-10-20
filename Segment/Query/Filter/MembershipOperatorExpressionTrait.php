<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Query\Filter;

use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;

trait MembershipOperatorExpressionTrait
{
    /**
     * Builds the WHERE expression for the membership operators. "including all" and
     * "excluding all" follow the core multiselect semantics: AND of one EXISTS per
     * segment, negated for "excluding all".
     *
     * @param array<int>                         $segmentIds
     * @param \Closure(array<int>): QueryBuilder $subQuery   returns an EXISTS sub query for the given segment IDs
     */
    private function buildMembershipExpression(QueryBuilder $queryBuilder, string $operator, array $segmentIds, \Closure $subQuery): string
    {
        if (in_array($operator, [OperatorOptions::INCLUDING_ALL, OperatorOptions::EXCLUDING_ALL], true)) {
            if ([] === $segmentIds) {
                return OperatorOptions::INCLUDING_ALL === $operator ? '1 = 0' : '1 = 1';
            }

            $expressions = array_map(
                static fn (int $segmentId): string => $queryBuilder->expr()->exists($subQuery([$segmentId])->getSQL()),
                $segmentIds
            );

            $all = (string) $queryBuilder->expr()->and(...$expressions);

            return OperatorOptions::INCLUDING_ALL === $operator ? $all : 'NOT ('.$all.')';
        }

        $sql = $subQuery($segmentIds)->getSQL();

        return in_array($operator, ['notExists', 'notIn'], true)
            ? $queryBuilder->expr()->notExists($sql)
            : $queryBuilder->expr()->exists($sql);
    }
}
