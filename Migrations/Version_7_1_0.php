<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\Tools\SchemaTool;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLog;

class Version_7_1_0 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix(CompanyEventLog::TABLE_NAME));
    }

    protected function up(): void
    {
        $metadata = $this->entityManager->getClassMetadata(CompanyEventLog::class);
        $table    = (new SchemaTool($this->entityManager))->getSchemaFromMetadata([$metadata])->getTable($metadata->getTableName());

        foreach ($this->entityManager->getConnection()->getDatabasePlatform()->getCreateTablesSQL([$table]) as $sql) {
            $this->addSql($sql);
        }
    }
}
