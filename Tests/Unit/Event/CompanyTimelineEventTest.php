<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\Event;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineEvent;
use PHPUnit\Framework\TestCase;

final class CompanyTimelineEventTest extends TestCase
{
    public function testQueryOptionsFetchAllEventsUpToTheRequestedPage(): void
    {
        $options = (new CompanyTimelineEvent(new Company(), [], ['timestamp', 'DESC'], 3, 10))->getQueryOptions();

        $this->assertSame(0, $options['start']);
        $this->assertSame(30, $options['limit']);
    }

    public function testIsApplicableRespectsFilters(): void
    {
        $event = new CompanyTimelineEvent(new Company(), ['includeEvents' => ['a', 'b'], 'excludeEvents' => ['b']]);

        $this->assertTrue($event->isApplicable('a'));
        $this->assertFalse($event->isApplicable('b'));
        $this->assertFalse($event->isApplicable('c'));
    }
}
