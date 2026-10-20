<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class DynamicContentSubscriber implements EventSubscriberInterface
{
    use CompanySegmentFilterMatchTrait;

    public function __construct(
        private Config $config,
        private SegmentCompanyRepository $companySegmentRepository,
        private PrimaryCompanyHelper $primaryCompanyHelper,
        // @phpstan-ignore property.onlyWritten
        private LeadListRepository $segmentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE => ['onContactFilterEvaluate', 0],
        ];
    }

    public function onContactFilterEvaluate(ContactFiltersEvaluateEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $filters = $event->getFilters();

        if (!$this->containsCompanySegmentsFilter($filters)) {
            return;
        }

        $contact   = $event->getContact();
        $leadArray = $this->primaryCompanyHelper->getProfileFieldsWithPrimaryCompany($contact)
            ?? ['id' => $contact->getId()];

        $event->setIsMatched($this->matchFilterGroupForLead($filters, $leadArray));
        $event->setIsEvaluated(true);
    }

    /**
     * @param array<array<string, mixed>> $filters
     */
    private function containsCompanySegmentsFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ('company_segments' === ($filter['type'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
