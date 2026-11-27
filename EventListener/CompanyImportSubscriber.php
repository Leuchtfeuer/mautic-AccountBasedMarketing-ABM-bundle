<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\Event\ImportEvent;
use Mautic\LeadBundle\Event\ImportProcessEvent;
use Mautic\LeadBundle\Event\ImportValidateEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension\LeadImportFieldTypeExtension;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CompanyImportSubscriber implements EventSubscriberInterface
{
    /**
     * @var int[]
     */
    private array $selectedSegmentIds = [];

    /**
     * @var int[]
     */
    private array $currentSegmentIds = [];

    public function __construct(
        private readonly Config $config,
        private readonly CompanySegmentModel $companySegmentModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::IMPORT_ON_VALIDATE     => ['onImportValidate', 0],
            LeadEvents::IMPORT_PRE_SAVE        => ['onImportPreSave', 0],
            // The core company import handler stops propagation at priority 0.
            LeadEvents::IMPORT_ON_PROCESS      => ['onImportProcess', 10],
            LeadEvents::COMPANY_POST_SAVE      => ['onCompanyPostSave', 0],
            LeadEvents::IMPORT_BATCH_PROCESSED => ['onImportBatchProcessed', 0],
        ];
    }

    public function onImportValidate(ImportValidateEvent $event): void
    {
        $form = $event->getForm();
        if (!$event->importIsForRouteObject('companies') || !$form->has(LeadImportFieldTypeExtension::FIELD_NAME)) {
            return;
        }

        $this->selectedSegmentIds = array_map(intval(...), (array) $form->get(LeadImportFieldTypeExtension::FIELD_NAME)->getData());
    }

    public function onImportPreSave(ImportEvent $event): void
    {
        if ([] === $this->selectedSegmentIds) {
            return;
        }

        $event->getEntity()->setDefault(LeadImportFieldTypeExtension::FIELD_NAME, $this->selectedSegmentIds);
        $this->selectedSegmentIds = [];
    }

    public function onImportProcess(ImportProcessEvent $event): void
    {
        $this->currentSegmentIds = $event->importIsForObject('company') && $this->config->isPublished()
            ? array_map(intval(...), (array) $event->import->getDefault(LeadImportFieldTypeExtension::FIELD_NAME))
            : [];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if ([] === $this->currentSegmentIds) {
            return;
        }

        $segmentIds              = $this->currentSegmentIds;
        $this->currentSegmentIds = [];

        $this->companySegmentModel->addCompany($event->getCompany(), $segmentIds, true);
    }

    public function onImportBatchProcessed(ImportEvent $event): void
    {
        $this->currentSegmentIds = [];
    }
}
