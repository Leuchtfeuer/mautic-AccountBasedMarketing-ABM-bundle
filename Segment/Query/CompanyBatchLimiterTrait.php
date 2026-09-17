<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Query;

use Mautic\LeadBundle\Segment\Query\QueryBuilder;

/**
 * Company counterpart of the core LeadBatchLimiterTrait.
 * Heads-up! Do not use any query parameters within this trait as it could cause conflicts.
 */
trait CompanyBatchLimiterTrait
{
    /**
     * @param array<string, mixed> $batchLimiters
     */
    private function addMinMaxLimiters(QueryBuilder $queryBuilder, array $batchLimiters, string $tableName, string $columnName = 'company_id'): void
    {
        $tableAlias = $queryBuilder->getTableAlias(MAUTIC_TABLE_PREFIX.$tableName);

        if (!empty($batchLimiters['minId']) && !empty($batchLimiters['maxId'])) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->comparison(
                    $tableAlias.'.'.$columnName,
                    'BETWEEN',
                    "{$batchLimiters['minId']} and {$batchLimiters['maxId']}"
                )
            );
        } elseif (!empty($batchLimiters['maxId'])) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->lte($tableAlias.'.'.$columnName, (int) $batchLimiters['maxId'])
            );
        } elseif (!empty($batchLimiters['minId'])) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte($tableAlias.'.'.$columnName, (int) $batchLimiters['minId'])
            );
        }
    }
}
