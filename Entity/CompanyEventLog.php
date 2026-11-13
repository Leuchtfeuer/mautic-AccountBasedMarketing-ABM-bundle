<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Company;

class CompanyEventLog
{
    public const TABLE_NAME = 'company_event_log';

    private ?string $id = null;

    private ?Company $company = null;

    private ?int $userId = null;

    private ?string $userName = null;

    private ?string $bundle = null;

    private ?string $object = null;

    private ?int $objectId = null;

    private ?string $action = null;

    private \DateTimeInterface $dateAdded;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $properties = null;

    public function __construct()
    {
        $this->dateAdded = new \DateTime();
    }

    public static function loadMetadata(ORMClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CompanyEventLogRepository::class)
            ->addIndex(['company_id'], 'company_id_index')
            ->addIndex(['object', 'object_id'], 'company_object_index')
            ->addIndex(['bundle', 'object', 'action', 'object_id'], 'company_timeline_index')
            ->addIndex(['bundle', 'object', 'action', 'object_id', 'date_added'], 'IDX_SEARCH')
            ->addIndex(['action'], 'company_timeline_action_index')
            ->addIndex(['date_added'], 'company_date_added_index')
            ->addBigIntIdField()
            ->addNullableField('userId', Types::INTEGER, 'user_id')
            ->addNullableField('userName', Types::STRING, 'user_name')
            ->addNullableField('bundle', Types::STRING)
            ->addNullableField('object', Types::STRING)
            ->addNullableField('action', Types::STRING)
            ->addNullableField('objectId', Types::INTEGER, 'object_id')
            ->addNamedField('dateAdded', Types::DATETIME_MUTABLE, 'date_added')
            ->addNullableField('properties', Types::JSON);

        $builder->createManyToOne('company', Company::class)
            ->addJoinColumn('company_id', 'id', true, false, 'CASCADE')
            ->build();
    }

    public function getId(): ?int
    {
        return null === $this->id ? null : (int) $this->id;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;

        return $this;
    }

    public function getBundle(): ?string
    {
        return $this->bundle;
    }

    public function setBundle(?string $bundle): self
    {
        $this->bundle = $bundle;

        return $this;
    }

    public function getObject(): ?string
    {
        return $this->object;
    }

    public function setObject(?string $object): self
    {
        $this->object = $object;

        return $this;
    }

    public function getObjectId(): ?int
    {
        return $this->objectId;
    }

    public function setObjectId(?int $objectId): self
    {
        $this->objectId = $objectId;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getDateAdded(): \DateTimeInterface
    {
        return $this->dateAdded;
    }

    public function setDateAdded(\DateTimeInterface $dateAdded): self
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * @param array<string, mixed>|null $properties
     */
    public function setProperties(?array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }
}
