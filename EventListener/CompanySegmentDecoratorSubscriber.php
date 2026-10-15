<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListFiltersDecoratorDelegateEvent;
use Mautic\LeadBundle\Exception\FilterNotFoundException;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\Decorator\BaseDecorator;
use Mautic\LeadBundle\Segment\Decorator\Date\DateOptionFactory;
use Mautic\LeadBundle\Services\ContactSegmentFilterDictionary;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Decorator\CompanyFieldDecorator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CompanySegmentDecoratorSubscriber implements EventSubscriberInterface
{
    private bool $buildingCompanySegment = false;

    public function __construct(
        private readonly BaseDecorator $baseDecorator,
        private readonly DateOptionFactory $dateOptionFactory,
        private readonly ContactSegmentFilterDictionary $filterDictionary,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::SEGMENT_ON_DECORATOR_DELEGATE => ['onDecoratorDelegate', 0],
        ];
    }

    public function setBuildingCompanySegment(bool $buildingCompanySegment): void
    {
        $this->buildingCompanySegment = $buildingCompanySegment;
    }

    public function onDecoratorDelegate(LeadListFiltersDecoratorDelegateEvent $event): void
    {
        if (!$this->buildingCompanySegment) {
            return;
        }

        $crate = $event->getCrate();

        if (!$crate->isCompanyType()) {
            return;
        }

        try {
            $this->filterDictionary->getFilter((string) $crate->getField());

            return;
        } catch (FilterNotFoundException) {
        }

        $decorator = $crate->isDateType()
            ? $this->dateOptionFactory->getDateOption($crate)
            : $this->baseDecorator;

        $event->setDecorator(new CompanyFieldDecorator($decorator));
    }
}
