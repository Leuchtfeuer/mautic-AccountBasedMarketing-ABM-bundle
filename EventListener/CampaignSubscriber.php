<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CampaignEventCompanySegmentsType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentActionType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Campaign action "Modify Company Segments" and condition "Company Segments"
 * for the contact's primary company (core PR 16025 added these to the core
 * CampaignSubscriber; keys are plugin-prefixed here).
 */
final readonly class CampaignSubscriber implements EventSubscriberInterface
{
    public const ACTION_CHANGE_COMPANY_SEGMENTS = 'leuchtfeuer.abm.changecompanysegments';

    public const CONDITION_COMPANY_SEGMENTS     = 'leuchtfeuer.abm.company_segments';

    public function __construct(
        private Config $config,
        private CompanySegmentModel $companySegmentModel,
        private \Mautic\LeadBundle\Entity\CompanyRepository $companyRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD    => ['onCampaignBuild', 0],
            LeadEvents::ON_CAMPAIGN_BATCH_ACTION => [
                ['onCampaignTriggerActionChangeCompanySegments', 0],
            ],
            // Must run after the core CampaignSubscriber (priority 0), which
            // unconditionally sets the result to false for unknown contexts.
            LeadEvents::ON_CAMPAIGN_TRIGGER_CONDITION => [
                ['onCampaignTriggerCondition', -10],
            ],
        ];
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $event->addAction(self::ACTION_CHANGE_COMPANY_SEGMENTS, [
            'label'          => 'mautic.campaign.leuchtfeuer.abm.changecompanysegments',
            'description'    => 'mautic.campaign.leuchtfeuer.abm.changecompanysegments_descr',
            'formType'       => CompanySegmentActionType::class,
            'batchEventName' => LeadEvents::ON_CAMPAIGN_BATCH_ACTION,
        ]);

        $event->addCondition(self::CONDITION_COMPANY_SEGMENTS, [
            'label'       => 'mautic.campaign.leuchtfeuer.abm.company_segments',
            'description' => 'mautic.campaign.leuchtfeuer.abm.company_segments_descr',
            'formType'    => CampaignEventCompanySegmentsType::class,
            'eventName'   => LeadEvents::ON_CAMPAIGN_TRIGGER_CONDITION,
        ]);
    }

    public function onCampaignTriggerActionChangeCompanySegments(PendingEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!$event->checkContext(self::ACTION_CHANGE_COMPANY_SEGMENTS)) {
            return;
        }

        $properties = $event->getEvent()->getProperties();
        $addTo      = $properties['addToLists'] ?? [];
        $removeFrom = $properties['removeFromLists'] ?? [];

        $logs = $event->getPending();

        foreach ($logs as $log) {
            $lead = $log->getLead();

            $primaryCompany = $lead->getPrimaryCompany();
            if (empty($primaryCompany) || !is_array($primaryCompany) || empty($primaryCompany['id'])) {
                $event->fail($log, 'No primary company found for contact');

                continue;
            }

            $companyEntity = $this->companyRepository->find($primaryCompany['id']);
            if (!$companyEntity) {
                $event->fail($log, 'Company not found');

                continue;
            }

            $somethingHappened = false;

            try {
                if (!empty($addTo)) {
                    $this->companySegmentModel->addCompany($companyEntity, $addTo, true);
                    $somethingHappened = true;
                }

                if (!empty($removeFrom)) {
                    $this->companySegmentModel->removeCompany($companyEntity, $removeFrom, true);
                    $somethingHappened = true;
                }
            } catch (\Throwable $exception) {
                $event->fail($log, $exception->getMessage());

                continue;
            }

            if ($somethingHappened) {
                $event->pass($log);
            } else {
                $event->fail($log, 'No segments to add or remove');
            }
        }
    }

    public function onCampaignTriggerCondition(CampaignExecutionEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!$event->checkContext(self::CONDITION_COMPANY_SEGMENTS)) {
            return;
        }

        $lead = $event->getLead();

        if (!$lead || !$lead->getId()) {
            $event->setResult(false);

            return;
        }

        $event->setResult($this->checkCompanySegmentMembership($lead, $event->getConfig()['companySegments'] ?? []));
    }

    /**
     * Check if the lead's primary company is in one of the specified company segments.
     *
     * @param array<int> $companySegmentIds
     */
    private function checkCompanySegmentMembership(Lead $lead, array $companySegmentIds): bool
    {
        if ([] === $companySegmentIds || !$lead->getId()) {
            return false;
        }

        $primaryCompany = $lead->getPrimaryCompany();

        if (
            [] === $primaryCompany
            || !is_array($primaryCompany)
            || !array_key_exists('id', $primaryCompany)
            || empty($primaryCompany['id'])
        ) {
            return false;
        }

        $company = $this->companyRepository->find($primaryCompany['id']);

        if (null === $company) {
            return false;
        }

        $companySegments = $this->companySegmentModel->getSegmentCompanyRepository()->findBy(
            [
                'company'         => $company,
                'companySegment'  => $companySegmentIds,
                'manuallyRemoved' => false,
            ]
        );

        return is_array($companySegments) && count($companySegments) > 0;
    }
}
