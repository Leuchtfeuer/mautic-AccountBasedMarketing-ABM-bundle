<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Doctrine\DBAL\Connection;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompany;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reconciles the plugin schema with tables left behind by the legacy
 * LeuchtfeuerCompanySegmentsBundle. On a fresh database the core creates all
 * tables from the entity metadata; when any plugin table already exists the
 * core skips schema creation entirely, so this subscriber renames the legacy
 * xref table and creates whatever is missing.
 */
final readonly class PluginInstallSubscriber implements EventSubscriberInterface
{
    private const PLUGIN_NAME = 'ABM (Account Based Marketing) by Leuchtfeuer';

    private const LEGACY_XREF_TABLE = 'companies_segments';

    private string $prefix;

    public function __construct(
        private Connection $connection,
        #[Autowire(env: 'MAUTIC_TABLE_PREFIX')]
        ?string $tablePrefix,
    ) {
        $this->prefix = $tablePrefix ?? '';
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 100],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        if (!$event->checkContext(self::PLUGIN_NAME)) {
            return;
        }

        if (null === $event->getInstalledSchema()) {
            // Fresh database: the core creates the schema from the entity metadata.
            return;
        }

        $this->reconcileLegacySchema();
    }

    private function reconcileLegacySchema(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $segmentsTable   = $this->prefix.CompanySegment::TABLE_NAME;
        $xrefTable       = $this->prefix.SegmentCompany::TABLE_NAME;
        $legacyXrefTable = $this->prefix.self::LEGACY_XREF_TABLE;

        if (!$schemaManager->tablesExist([$segmentsTable])) {
            $this->createSegmentsTable($segmentsTable);
        } else {
            $this->ensureCategoryForeignKey($segmentsTable);
        }

        if ($schemaManager->tablesExist([$xrefTable])) {
            return;
        }

        if ($schemaManager->tablesExist([$legacyXrefTable])) {
            $this->connection->executeStatement("RENAME TABLE `{$legacyXrefTable}` TO `{$xrefTable}`");

            return;
        }

        $this->createXrefTable($xrefTable, $segmentsTable);
    }

    private function createSegmentsTable(string $segmentsTable): void
    {
        $this->connection->executeStatement("CREATE TABLE `{$segmentsTable}`
(
    `id`                  INT UNSIGNED AUTO_INCREMENT NOT NULL,
    `category_id`         INT UNSIGNED DEFAULT NULL,
    `is_published`        TINYINT(1)                  NOT NULL,
    `date_added`          DATETIME     DEFAULT NULL,
    `created_by`          INT          DEFAULT NULL,
    `created_by_user`     VARCHAR(191) DEFAULT NULL,
    `date_modified`       DATETIME     DEFAULT NULL,
    `modified_by`         INT          DEFAULT NULL,
    `modified_by_user`    VARCHAR(191) DEFAULT NULL,
    `checked_out`         DATETIME     DEFAULT NULL,
    `checked_out_by`      INT          DEFAULT NULL,
    `checked_out_by_user` VARCHAR(191) DEFAULT NULL,
    `name`                VARCHAR(191)                NOT NULL,
    `description`         LONGTEXT     DEFAULT NULL,
    `alias`               VARCHAR(191)                NOT NULL,
    `public_name`         VARCHAR(191)                NOT NULL,
    `filters`             LONGTEXT                    NOT NULL COMMENT '(DC2Type:json)',
    `last_built_date`     DATETIME     DEFAULT NULL,
    `last_built_time`     DOUBLE       DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `company_segment_alias` (`alias`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC");

        $this->ensureCategoryForeignKey($segmentsTable);
    }

    private function ensureCategoryForeignKey(string $segmentsTable): void
    {
        $categoryFk = $this->generatePropertyName(CompanySegment::TABLE_NAME, 'fk', ['category_id']);

        foreach ($this->connection->createSchemaManager()->listTableForeignKeys($segmentsTable) as $foreignKey) {
            if (['category_id'] === array_map(strtolower(...), $foreignKey->getLocalColumns())) {
                return;
            }
        }

        $this->connection->executeStatement("ALTER TABLE `{$segmentsTable}` ADD CONSTRAINT `{$categoryFk}` FOREIGN KEY (`category_id`) REFERENCES `{$this->prefix}categories` (`id`) ON DELETE SET NULL");
    }

    private function createXrefTable(string $xrefTable, string $segmentsTable): void
    {
        $segmentFk = $this->generatePropertyName(SegmentCompany::TABLE_NAME, 'fk', ['segment_id']);
        $companyFk = $this->generatePropertyName(SegmentCompany::TABLE_NAME, 'fk', ['company_id']);

        $this->connection->executeStatement("CREATE TABLE `{$xrefTable}`
(
    `segment_id`        INT UNSIGNED NOT NULL,
    `company_id`        INT          NOT NULL,
    `date_added`        DATETIME     NOT NULL,
    `manually_removed`  TINYINT(1)   NOT NULL,
    `manually_added`    TINYINT(1)   NOT NULL,
    PRIMARY KEY (`segment_id`, `company_id`),
    INDEX `companies_segment_manually_removed` (`manually_removed`)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB
  ROW_FORMAT = DYNAMIC");

        $this->connection->executeStatement("ALTER TABLE `{$xrefTable}` ADD CONSTRAINT `{$segmentFk}` FOREIGN KEY (`segment_id`) REFERENCES `{$segmentsTable}` (`id`) ON DELETE CASCADE");
        $this->connection->executeStatement("ALTER TABLE `{$xrefTable}` ADD CONSTRAINT `{$companyFk}` FOREIGN KEY (`company_id`) REFERENCES `{$this->prefix}companies` (`id`) ON DELETE CASCADE");
    }

    /**
     * @see \Mautic\CoreBundle\Doctrine\AbstractMauticMigration::generatePropertyName
     *
     * @param string[] $columnNames
     */
    private function generatePropertyName(string $table, string $type, array $columnNames): string
    {
        $columnNames = array_merge([$this->prefix.$table], $columnNames);
        $hash        = implode(
            '',
            array_map(
                static fn (string $column): string => dechex(crc32($column)),
                $columnNames
            )
        );

        return substr(strtoupper($type.'_'.$hash), 0, 63);
    }
}
