<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event;

use Mautic\LeadBundle\Entity\Company;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Listeners add their first page * limit events (see getQueryOptions()) and their total count.
 */
final class CompanyTimelineEvent extends Event
{
    /**
     * @var array<string, string>
     */
    private array $eventTypes = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $events = [];

    private int $total = 0;

    private bool $fetchTypesOnly = false;

    /**
     * @param array{search?: string, includeEvents?: string[], excludeEvents?: string[], dateFrom?: ?\DateTimeInterface, dateTo?: ?\DateTimeInterface} $filters
     * @param array{0: string, 1: string}                                                                                                              $orderBy
     */
    public function __construct(
        private readonly ?Company $company = null,
        private readonly array $filters = [],
        private readonly array $orderBy = ['timestamp', 'DESC'],
        private readonly int $page = 1,
        private readonly int $limit = 25,
    ) {
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function fetchTypesOnly(): void
    {
        $this->fetchTypesOnly = true;
    }

    public function addEventType(string $eventType, string $name): void
    {
        $this->eventTypes[$eventType] = $name;
    }

    /**
     * @return array<string, string>
     */
    public function getEventTypes(): array
    {
        natcasesort($this->eventTypes);

        return $this->eventTypes;
    }

    public function isApplicable(string $eventType): bool
    {
        if ($this->fetchTypesOnly || null === $this->company) {
            return false;
        }

        if (in_array($eventType, $this->filters['excludeEvents'] ?? [], true)) {
            return false;
        }

        $includeEvents = $this->filters['includeEvents'] ?? [];

        return [] === $includeEvents || in_array($eventType, $includeEvents, true);
    }

    /**
     * @return array{search: string, order: array{0: string, 1: string}, fromDate: ?\DateTimeInterface, toDate: ?\DateTimeInterface, start: int, limit: int}
     */
    public function getQueryOptions(): array
    {
        return [
            'search'   => $this->filters['search'] ?? '',
            'order'    => $this->orderBy,
            'fromDate' => $this->filters['dateFrom'] ?? null,
            'toDate'   => $this->filters['dateTo'] ?? null,
            'start'    => 0,
            'limit'    => $this->page * $this->limit,
        ];
    }

    /**
     * @param array<string, mixed> $event Same format as the core contact timeline (event, eventType, eventLabel, timestamp, icon, contentTemplate, extra, contactId)
     */
    public function addEvent(array $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function addToCounter(int $count): void
    {
        $this->total += $count;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
