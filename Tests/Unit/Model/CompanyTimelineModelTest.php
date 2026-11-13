<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\Model;

use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanyTimelineEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyTimelineModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class CompanyTimelineModelTest extends TestCase
{
    public function testEventsAreSortedAndSlicedAcrossTypes(): void
    {
        $model = $this->createModel([
            ['eventType' => 'a', 'timestamp' => new \DateTime('2026-01-01')],
            ['eventType' => 'a', 'timestamp' => new \DateTime('2026-01-04')],
            ['eventType' => 'b', 'timestamp' => new \DateTime('2026-01-03')],
            ['eventType' => 'b', 'timestamp' => new \DateTime('2026-01-05')],
            ['eventType' => 'b', 'timestamp' => new \DateTime('2026-01-02')],
        ]);

        $result = $model->getEngagements(new Company(), [], null, 2, 2);

        $this->assertSame(['2026-01-03', '2026-01-02'], array_map(static fn (array $e): string => $e['timestamp']->format('Y-m-d'), $result['events']));
        $this->assertSame(3, $result['maxPages']);
    }

    /**
     * @param array<int, array<string, mixed>> $events
     */
    private function createModel(array $events): CompanyTimelineModel
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(CompanyTimelineEvent::class, static function (CompanyTimelineEvent $event) use ($events): void {
            foreach ($events as $data) {
                $event->addEvent($data);
            }
            $event->addToCounter(count($events));
        });

        return new CompanyTimelineModel($dispatcher);
    }
}
