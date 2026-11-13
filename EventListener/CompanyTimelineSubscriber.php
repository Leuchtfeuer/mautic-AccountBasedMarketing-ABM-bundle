<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanyEventLogRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CompanyTimelineSubscriber implements EventSubscriberInterface
{
    /**
     * Event type => [log object, log action, icon].
     */
    private const LOG_TYPES = [
        'company.segment.added'   => ['company_segment', 'added', 'ri-pie-chart-line'],
        'company.segment.removed' => ['company_segment', 'removed', 'ri-pie-chart-line'],
        'company.contact.added'   => ['lead', 'added', 'ri-user-add-line'],
        'company.contact.removed' => ['lead', 'removed', 'ri-user-unfollow-line'],
        'company.import'          => ['import', null, 'ri-import-line'],
    ];

    public function __construct(
        private Config $config,
        private CompanyEventLogRepository $companyEventLogRepository,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $router,
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

        $this->addCompanyCreatedEvent($event);

        foreach (self::LOG_TYPES as $eventType => [$object, $action, $icon]) {
            $this->addLogEvents($event, $eventType, $object, $action, $icon);
        }
    }

    private function addCompanyCreatedEvent(CompanyTimelineEvent $event): void
    {
        $eventTypeName = $this->translator->trans('mautic.abm.timeline.company.created');
        $event->addEventType('company.created', $eventTypeName);

        $company   = $event->getCompany();
        $dateAdded = $company?->getDateAdded();
        $options   = $event->getQueryOptions();

        if (!$event->isApplicable('company.created') || null === $dateAdded || '' !== $options['search']
            || (null !== $options['fromDate'] && $dateAdded < $options['fromDate'])
            || (null !== $options['toDate'] && $dateAdded > $options['toDate'])) {
            return;
        }

        $event->addToCounter(1);
        $event->addEvent([
            'event'      => 'company.created',
            'eventId'    => 'company.created'.$company->getId(),
            'eventType'  => $eventTypeName,
            'eventLabel' => $eventTypeName,
            'timestamp'  => $dateAdded,
            'icon'       => 'ri-building-line',
        ]);
    }

    private function addLogEvents(CompanyTimelineEvent $event, string $eventType, string $object, ?string $action, string $icon): void
    {
        $eventTypeName = $this->translator->trans('mautic.abm.timeline.'.$eventType);
        $event->addEventType($eventType, $eventTypeName);

        $companyId = $event->getCompany()?->getId();
        if (!$event->isApplicable($eventType) || null === $companyId) {
            return;
        }

        $logs = $this->companyEventLogRepository->getTimelineEvents((int) $companyId, $object, $action, $event->getQueryOptions());
        $event->addToCounter($logs['total']);

        foreach ($logs['results'] as $log) {
            $properties = json_decode((string) $log['properties'], true) ?: [];
            $objectId   = (int) $log['object_id'];

            $entry = [
                'event'      => $eventType,
                'eventId'    => $eventType.$log['id'],
                'eventType'  => $eventTypeName,
                'eventLabel' => match ($object) {
                    'company_segment' => $this->createLabel($properties['company_segment_name'] ?? '#'.$objectId, 'mautic_company_segments_action', ['objectAction' => 'view', 'objectId' => $objectId]),
                    'lead'            => $this->createLabel(($properties['contact_name'] ?? '') ?: '#'.$objectId, 'mautic_contact_action', ['objectAction' => 'view', 'objectId' => $objectId]),
                    default           => $this->createLabel($properties['file'] ?? '#'.$objectId, 'mautic_import_action', ['object' => 'company', 'objectAction' => 'view', 'objectId' => $objectId]),
                },
                'timestamp'  => new \DateTime((string) $log['date_added'], new \DateTimeZone('UTC')),
                'icon'       => $icon,
                'extra'      => $properties + ['user_name' => $log['user_name']],
            ];

            if ('lead' === $object) {
                $entry['contactId'] = $objectId;
            }

            $event->addEvent($entry);
        }
    }

    /**
     * @param array<string, int|string> $routeParameters
     *
     * @return array{label: string, href: string}
     */
    private function createLabel(string $label, string $route, array $routeParameters): array
    {
        return [
            'label' => $label,
            'href'  => $this->router->generate($route, $routeParameters),
        ];
    }
}
