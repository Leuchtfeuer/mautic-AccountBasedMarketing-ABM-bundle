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
     * @param array{search: string, order: array{0: string, 1: string}, fromDate: ?\DateTimeInterface, toDate: ?\DateTimeInterface, start: int, limit: int} $options
     *
     * @return array{total: int, results: array<int, array<string, mixed>>}
     */
    public function getTimelineEvents(int $companyId, string $object, ?string $action, array $options): array
    {
        $qb = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->from(MAUTIC_TABLE_PREFIX.CompanyEventLog::TABLE_NAME)
            ->where('company_id = :companyId')
            ->andWhere('object = :object')
            ->setParameter('companyId', $companyId)
            ->setParameter('object', $object);

        if (null !== $action) {
            $qb->andWhere('action = :action')->setParameter('action', $action);
        }

        if ('' !== $options['search']) {
            $qb->andWhere('properties LIKE :search')->setParameter('search', '%'.addcslashes($options['search'], '%_\\').'%');
        }

        if (null !== $options['fromDate']) {
            $qb->andWhere('date_added >= :fromDate')->setParameter('fromDate', $options['fromDate'], Types::DATETIME_MUTABLE);
        }

        if (null !== $options['toDate']) {
            $qb->andWhere('date_added <= :toDate')->setParameter('toDate', $options['toDate'], Types::DATETIME_MUTABLE);
        }

        $total     = (int) (clone $qb)->select('COUNT(*)')->executeQuery()->fetchOne();
        $direction = 'timestamp' === $options['order'][0] && 'ASC' === $options['order'][1] ? 'ASC' : 'DESC';

        $results = $qb->select('id', 'object_id', 'date_added', 'properties', 'user_name')
            ->orderBy('date_added', $direction)
            ->addOrderBy('id', $direction)
            ->setFirstResult($options['start'])
            ->setMaxResults($options['limit'])
            ->executeQuery()
            ->fetchAllAssociative();

        return ['total' => $total, 'results' => $results];
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
