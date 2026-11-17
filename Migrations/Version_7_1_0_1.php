<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLog;

/**
 * Adds contact_name to contact log rows of the legacy CompanySegments bundle.
 */
class Version_7_1_0_1 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        $table = $this->concatPrefix(CompanyEventLog::TABLE_NAME);

        return $schema->hasTable($table) && false !== $this->entityManager->getConnection()->fetchOne(
            "SELECT 1 FROM {$table} WHERE object = 'lead' AND JSON_EXTRACT(properties, '$.contact_name') IS NULL LIMIT 1"
        );
    }

    protected function up(): void
    {
        $this->addSql("UPDATE {$this->concatPrefix(CompanyEventLog::TABLE_NAME)} cel
            LEFT JOIN {$this->concatPrefix('leads')} l ON l.id = cel.object_id
            SET cel.properties = JSON_SET(
                IF(JSON_TYPE(cel.properties) = 'OBJECT', cel.properties, '{}'),
                '$.contact_name',
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', l.firstname, l.lastname)), ''), NULLIF(l.company, ''), NULLIF(l.email, ''), '')
            )
            WHERE cel.object = 'lead' AND JSON_EXTRACT(cel.properties, '$.contact_name') IS NULL");
    }
}
