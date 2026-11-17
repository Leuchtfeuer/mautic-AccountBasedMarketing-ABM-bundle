<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Collects the contact timeline event types whose queries apply the core leadIds filter.
 * Registering a type whose query ignores the filter shows events of all contacts in the company timeline.
 */
final class CompanyTimelineLeadIdsTypesEvent extends Event
{
    /**
     * @param string[] $types
     */
    public function __construct(
        private array $types = [],
    ) {
    }

    public function addType(string $type): void
    {
        $this->types[] = $type;
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_values(array_unique($this->types));
    }
}
