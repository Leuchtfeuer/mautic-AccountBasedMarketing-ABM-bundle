<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CompanyTimelineModel
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @param array{search?: string, includeEvents?: string[], excludeEvents?: string[], dateFrom?: ?\DateTimeInterface, dateTo?: ?\DateTimeInterface} $filters
     * @param array{0: string, 1: string}|null                                                                                                         $orderBy
     *
     * @return array{events: array<int, array<string, mixed>>, filters: array<string, mixed>, order: array{0: string, 1: string}, types: array<string, string>, total: int, page: int, limit: int, maxPages: int}
     */
    public function getEngagements(Company $company, array $filters = [], ?array $orderBy = null, int $page = 1, int $limit = 25): array
    {
        $orderBy ??= ['timestamp', 'DESC'];

        $event = $this->dispatcher->dispatch(new CompanyTimelineEvent($company, $filters, $orderBy, $page, $limit));

        return [
            'events'   => array_slice($this->sortEvents($event->getEvents(), $orderBy), ($page - 1) * $limit, $limit),
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => $event->getEventTypes(),
            'total'    => $event->getTotal(),
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => max(1, (int) ceil($event->getTotal() / $limit)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getEngagementTypes(): array
    {
        $event = new CompanyTimelineEvent();
        $event->fetchTypesOnly();

        return $this->dispatcher->dispatch($event)->getEventTypes();
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @param array{0: string, 1: string}      $orderBy
     *
     * @return array<int, array<string, mixed>>
     */
    private function sortEvents(array $events, array $orderBy): array
    {
        [$column, $direction] = $orderBy;

        usort($events, static function (array $a, array $b) use ($column): int {
            if ('timestamp' === $column) {
                return $a['timestamp'] <=> $b['timestamp'];
            }

            $aValue = is_array($a[$column] ?? null) ? ($a[$column]['label'] ?? '') : (string) ($a[$column] ?? '');
            $bValue = is_array($b[$column] ?? null) ? ($b[$column]['label'] ?? '') : (string) ($b[$column] ?? '');

            return strnatcasecmp($aValue, $bValue);
        });

        return 'DESC' === $direction ? array_reverse($events) : $events;
    }
}
