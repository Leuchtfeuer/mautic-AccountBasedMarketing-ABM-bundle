<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<CompanySegment>
 */
class CompanySegmentRepository extends CommonRepository
{
    /**
     * @return array<array{id: string, name: string, alias: string, createdBy: int|null}>
     */
    public function getSegments(?string $alias = null, ?int $excludeId = null): array
    {
        $q = $this->getEntityManager()->createQueryBuilder()
            ->from(CompanySegment::class, $this->getTableAlias(), $this->getTableAlias().'.id');

        $q->select('partial '.$this->getTableAlias().'.{id, name, alias, createdBy}')
            ->andWhere($q->expr()->eq($this->getTableAlias().'.isPublished', ':true'))
            ->setParameter('true', true, 'boolean');

        if (null !== $alias && '' !== $alias) {
            $q->andWhere($this->getTableAlias().'.alias = :alias');
            $q->setParameter('alias', $alias);
        }

        if (null !== $excludeId && $excludeId > 0) {
            $q->andWhere(
                $q->expr()->neq($this->getTableAlias().'.id', $excludeId)
            );
        }

        $q->orderBy($this->getTableAlias().'.name');

        /** @var array<array{id: string, name: string, alias: string, createdBy: int|null}> $result */
        $result = $q->getQuery()->getArrayResult();

        return $result;
    }

    public function getTableAlias(): string
    {
        return CompanySegment::DEFAULT_ALIAS;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder|\Doctrine\DBAL\Query\QueryBuilder $q
     * @param mixed                                                        $filter
     *
     * @return array<mixed>
     */
    protected function addSearchCommandWhereClause($q, $filter): array
    {
        \assert($filter instanceof \stdClass);
        [$expr, $parameters] = parent::addStandardSearchCommandWhereClause($q, $filter);
        if ($expr) {
            return [$expr, $parameters];
        }

        $command         = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = false;

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.name'):
            case $this->translator->trans('mautic.core.searchcommand.name', [], null, 'en_US'):
                $expr            = $q->expr()->like($this->getTableAlias().'.name', ':'.$unique);
                $returnParameter = true;
                break;
        }

        if ($returnParameter) {
            $string     = ($filter->strict) ? $filter->string : "%{$filter->string}%";
            $parameters = [$unique => $string];
        }

        return [
            $expr,
            $parameters,
        ];
    }

    /**
     * @return string[]|array<string, string[]>
     */
    public function getSearchCommands(): array
    {
        $commands = [
            'mautic.core.searchcommand.ispublished',
            'mautic.core.searchcommand.isunpublished',
            'mautic.core.searchcommand.name',
            'mautic.core.searchcommand.category',
        ];

        return array_merge($commands, parent::getSearchCommands());
    }
}
