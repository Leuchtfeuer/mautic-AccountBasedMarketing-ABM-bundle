<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanyEventLog>
 */
class CompanyEventLogRepository extends CommonRepository
{
    public function getTableAlias(): string
    {
        return 'cel';
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function insertLog(
        int $companyId,
        string $object,
        string $action,
        ?int $objectId,
        array $properties,
        ?int $userId,
        string $userName,
        \DateTimeInterface $dateAdded,
    ): void {
        $this->getEntityManager()->getConnection()->insert(
            MAUTIC_TABLE_PREFIX.CompanyEventLog::TABLE_NAME,
            [
                'company_id' => $companyId,
                'user_id'    => $userId,
                'user_name'  => $userName,
                'bundle'     => 'company',
                'object'     => $object,
                'action'     => $action,
                'object_id'  => $objectId,
                'date_added' => $dateAdded,
                'properties' => $properties,
            ],
            [
                'date_added' => Types::DATETIME_MUTABLE,
                'properties' => Types::JSON,
            ]
        );
    }
}
