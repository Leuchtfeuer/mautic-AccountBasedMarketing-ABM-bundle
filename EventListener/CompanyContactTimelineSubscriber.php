<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\LeadRepository;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the core contact timeline events of all contacts of the company.
 */
final readonly class CompanyContactTimelineSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private LeadModel $leadModel,
        private LeadRepository $leadRepository,
        private CompanyLeadRepository $companyLeadRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanyTimelineEvent::class => ['onTimelineGenerate', 0],
        ];
    }

    public function onTimelineGenerate(CompanyTimelineEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $eventTypes = $this->leadModel->getEngagementTypes();
        foreach ($eventTypes as $eventType => $name) {
            $event->addEventType($eventType, $name);
        }

        $applicableTypes = array_values(array_filter(array_keys($eventTypes), $event->isApplicable(...)));
        $companyId       = $event->getCompany()?->getId();
        if ([] === $applicableTypes || null === $companyId) {
            return;
        }

        $contactIds = array_column($this->companyLeadRepository->getCompanyLeads($companyId), 'lead_id');
        if ([] === $contactIds) {
            return;
        }

        $options = $event->getQueryOptions();
        $filters = [
            'search'        => $options['search'],
            'includeEvents' => $applicableTypes,
            'excludeEvents' => [],
            'dateFrom'      => $options['fromDate'],
            'dateTo'        => $options['toDate'],
        ];

        foreach ($this->leadRepository->findBy(['id' => $contactIds]) as $contact) {
            $engagements = $this->leadModel->getEngagements($contact, $filters, $options['order'], 1, $options['limit']);
            $event->addToCounter((int) $engagements['total']);

            foreach ($engagements['events'] as $contactEvent) {
                $contactEvent['eventId']   = $contact->getId().'-'.$contactEvent['eventId'];
                $contactEvent['contactId'] = $contact->getId();
                $contactEvent['contact']   = $contact;
                $event->addEvent($contactEvent);
            }
        }
    }
}
