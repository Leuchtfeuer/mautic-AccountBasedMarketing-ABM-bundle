<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Import;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\ImportEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\Event\LeadChangeCompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentChangeEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CompanyEventLogSubscriber implements EventSubscriberInterface
{
    private ?Import $currentImport = null;

    public function __construct(
        private readonly Config $config,
        private readonly CompanyEventLogRepository $companyEventLogRepository,
        private readonly UserHelper $userHelper,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentChangeEvent::class      => ['onCompanySegmentChange', 0],
            LeadEvents::LEAD_COMPANY_CHANGE       => ['onLeadCompanyChange', 0],
            // The core company import handler stops propagation at priority 0.
            LeadEvents::IMPORT_ON_PROCESS         => ['onImportProcess', 10],
            LeadEvents::COMPANY_POST_SAVE         => ['onCompanyPostSave', 0],
            LeadEvents::IMPORT_BATCH_PROCESSED    => ['onImportBatchProcessed', 0],
        ];
    }

    public function onCompanySegmentChange(CompanySegmentChangeEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $segment = $event->getCompanySegment();

        $this->log(
            $event->getCompany(),
            'company_segment',
            $event->wasAdded() ? 'added' : 'removed',
            $segment->getId(),
            [
                'company_segment_id'   => $segment->getId(),
                'company_segment_name' => $segment->getName(),
                'object_description'   => $segment->getName(),
            ],
            $event->getDate(),
        );
    }

    public function onLeadCompanyChange(LeadChangeCompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $leads = $event->getLeads() ?? array_filter([$event->getLead()]);

        foreach ($leads as $lead) {
            $this->log(
                $event->getCompany(),
                'lead',
                $event->wasAdded() ? 'added' : 'removed',
                $lead->getId(),
                ['object_description' => $lead->getPrimaryIdentifier()],
            );
        }
    }

    public function onImportProcess(ImportProcessEvent $event): void
    {
        $this->currentImport = $event->importIsForObject('company') && $this->config->isPublished() ? $event->import : null;
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if (null === $this->currentImport) {
            return;
        }

        $this->log(
            $event->getCompany(),
            'import',
            $event->isNew() ? 'create' : 'update',
            $this->currentImport->getId(),
            [
                'file'            => $this->currentImport->getOriginalFile(),
                'created_by_id'   => $this->currentImport->getCreatedBy(),
                'created_by_name' => $this->currentImport->getCreatedByUser(),
            ],
            null,
            $this->currentImport->getCreatedBy(),
            $this->currentImport->getCreatedByUser() ?? 'System',
        );

        $this->currentImport = null;
    }

    public function onImportBatchProcessed(ImportEvent $event): void
    {
        $this->currentImport = null;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function log(
        Company $company,
        string $object,
        string $action,
        ?int $objectId,
        array $properties,
        ?\DateTimeInterface $date = null,
        ?int $userId = null,
        ?string $userName = null,
    ): void {
        $companyId = $company->getId();
        if (null === $companyId) {
            return;
        }

        if (null === $userName) {
            $user     = $this->userHelper->getUser(true);
            $userId   = $user?->getId();
            $userName = $user?->getUserIdentifier() ?? 'System';
        }

        $this->companyEventLogRepository->insertLog(
            (int) $companyId,
            $object,
            $action,
            $objectId,
            $properties,
            $userId,
            $userName,
            $date ?? new \DateTime(),
        );
    }
}
