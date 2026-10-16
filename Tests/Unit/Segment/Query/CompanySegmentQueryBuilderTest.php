<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\Segment\Query;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Test\Doctrine\MockedConnectionTrait;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use Mautic\LeadBundle\Segment\RandomParameterName;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Query\CompanySegmentQueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class CompanySegmentQueryBuilderTest extends TestCase
{
    use MockedConnectionTrait;

    public function testAddNewCompaniesRestrictions(): void
    {
        $queryBuilder = new QueryBuilder($this->createConnection());
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'companies', 'comp');
        $queryBuilder->where('NULL');

        $companySegment = new CompanySegment();
        $reflection     = new \ReflectionClass($companySegment);
        $property       = $reflection->getProperty('id');
        $property->setValue($companySegment, 8);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('getTableAlias')->willReturn('comp');

        $filterQueryBuilder = new CompanySegmentQueryBuilder(
            $this->createStub(EntityManager::class),
            $companyRepository,
            $this->createStub(CompanySegmentRepository::class),
            new RandomParameterName(),
            new EventDispatcher(),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame($queryBuilder, $filterQueryBuilder->addNewCompaniesRestrictions($queryBuilder, $companySegment));
        $this->assertSame('SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'companies comp WHERE (NULL) AND (comp.id NOT IN (SELECT par0.company_id FROM '.MAUTIC_TABLE_PREFIX.SegmentCompany::TABLE_NAME.' par0 WHERE par0.segment_id = 8))', $queryBuilder->getDebugOutput());
    }

    /**
     * @return array<mixed>
     */
    public static function dataAddNewCompaniesRestrictionsWithBatchLimiters(): iterable
    {
        yield [['minId' => 1,  'maxId' => 2], 'par0.company_id BETWEEN 1 and 2'];
        yield [['minId' => 1], 'par0.company_id >= 1'];
        yield [['maxId' => 2], 'par0.company_id <= 2'];
    }

    /**
     * @param array<string, mixed> $batchLimiters
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataAddNewCompaniesRestrictionsWithBatchLimiters')]
    public function testAddNewCompaniesRestrictionsWithBatchLimiters(array $batchLimiters, string $expectedWhereClause): void
    {
        $queryBuilder = new QueryBuilder($this->createConnection());
        $queryBuilder->select('1');
        $queryBuilder->from(MAUTIC_TABLE_PREFIX.'companies', 'comp');
        $queryBuilder->where('NULL');

        $companySegment = new CompanySegment();
        $reflection     = new \ReflectionClass($companySegment);
        $property       = $reflection->getProperty('id');
        $property->setValue($companySegment, 8);

        $companyRepository = $this->createMock(CompanyRepository::class);
        $companyRepository->method('getTableAlias')->willReturn('comp');

        $filterQueryBuilder = new CompanySegmentQueryBuilder(
            $this->createStub(EntityManager::class),
            $companyRepository,
            $this->createStub(CompanySegmentRepository::class),
            new RandomParameterName(),
            new EventDispatcher(),
            $this->createStub(LoggerInterface::class)
        );

        $this->assertSame($queryBuilder, $filterQueryBuilder->addNewCompaniesRestrictions($queryBuilder, $companySegment, $batchLimiters));
        $this->assertSame('SELECT 1 FROM '.MAUTIC_TABLE_PREFIX.'companies comp WHERE (NULL) AND (comp.id NOT IN (SELECT par0.company_id FROM '.MAUTIC_TABLE_PREFIX.SegmentCompany::TABLE_NAME.' par0 WHERE (par0.segment_id = 8) AND ('.$expectedWhereClause.')))', $queryBuilder->getDebugOutput());
    }

    private function createConnection(): Connection
    {
        return $this->getMockedConnection();
    }
}
