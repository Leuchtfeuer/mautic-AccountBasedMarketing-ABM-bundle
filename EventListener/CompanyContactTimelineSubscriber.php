<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
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
    /**
     * Core types whose queries honour the leadIds filter, fetched with one call for all contacts.
     * Other types are fetched per contact, as their listeners may ignore the filter.
     */
    private const LEAD_IDS_TYPES = [
        'asset.download',
        'campaign.event',
        'campaign.event.scheduled',
        'campaign_membership',
        'dynamic.content.sent',
        'email.failed',
        'email.read',
        'email.replied',
        'email.sent',
        'focus.on_click',
        'focus.on_view',
        'form.submitted',
        'integration_sync_issues',
        'lead.apiadded',
        'lead.create',
        'lead.donotcontact',
        'lead.identified',
        'lead.imported',
        'lead.ipadded',
        'lead.source.created',
        'lead.source.identified',
        'lead.utmtagsadded',
        'message.queue',
        'page.hit',
        'page.videohit',
        'point.gained',
        'segment_membership',
        'sms.failed',
        'sms.sent',
        'sms_reply',
        'stage.changed',
    ];

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
            'excludeEvents' => [],
            'dateFrom'      => $options['fromDate'],
            'dateTo'        => $options['toDate'],
        ];

        $leadIdsTypes    = array_values(array_intersect($applicableTypes, self::LEAD_IDS_TYPES));
        $perContactTypes = array_values(array_diff($applicableTypes, $leadIdsTypes));

        if ([] !== $leadIdsTypes) {
            $engagements = $this->leadModel->getEngagements(
                null,
                $filters + ['includeEvents' => $leadIdsTypes, 'leadIds' => $contactIds],
                $options['order'],
                1,
                $options['limit']
            );
            $event->addToCounter((int) $engagements['total']);

            $contacts = $this->findContacts(array_column($engagements['events'], 'contactId'));
            foreach ($engagements['events'] as $contactEvent) {
                $contact = $contacts[(int) ($contactEvent['contactId'] ?? 0)] ?? null;
                if (null !== $contact) {
                    $this->addContactEvent($event, $contactEvent, $contact);
                }
            }
        }

        if ([] === $perContactTypes) {
            return;
        }

        foreach ($this->findContacts($contactIds) as $contact) {
            $engagements = $this->leadModel->getEngagements($contact, $filters + ['includeEvents' => $perContactTypes], $options['order'], 1, $options['limit']);
            $event->addToCounter((int) $engagements['total']);

            foreach ($engagements['events'] as $contactEvent) {
                $this->addContactEvent($event, $contactEvent, $contact);
            }
        }
    }

    /**
     * @param array<int|string> $contactIds
     *
     * @return array<int, Lead>
     */
    private function findContacts(array $contactIds): array
    {
        $contacts = [];
        foreach ($this->leadRepository->findBy(['id' => array_unique($contactIds)]) as $contact) {
            $contacts[(int) $contact->getId()] = $contact;
        }

        return $contacts;
    }

    /**
     * @param array<string, mixed> $contactEvent
     */
    private function addContactEvent(CompanyTimelineEvent $event, array $contactEvent, Lead $contact): void
    {
        $contactEvent['eventId']   = $contact->getId().'-'.$contactEvent['eventId'];
        $contactEvent['contactId'] = $contact->getId();
        $contactEvent['contact']   = $contact;
        $event->addEvent($contactEvent);
    }
}
