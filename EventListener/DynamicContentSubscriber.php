<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class DynamicContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private SegmentCompanyRepository $companySegmentRepository,
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

        foreach ($event->getFilters() as $filter) {
            if ('company_segments' === $filter['type']) {
                $event->setIsMatched(
                    $this->isContactPrimaryCompanySegmentRelationshipValid($event->getContact(), $filter['operator'], $filter['filter'])
                );
                $event->setIsEvaluated(true);

                return;
            }
        }
    }

    /**
     * @param string $operator   empty, !empty, in, !in
     * @param ?int[] $segmentIds
     */
    private function isContactPrimaryCompanySegmentRelationshipValid(Lead $contact, string $operator, ?array $segmentIds = null): bool
    {
        $contactId = $contact->getId();

        return match ($operator) {
            OperatorOptions::EMPTY         => $this->companySegmentRepository->isNotContactPrimaryCompanyInAnySegment($contactId),
            OperatorOptions::NOT_EMPTY     => $this->companySegmentRepository->isContactPrimaryCompanyInAnySegment($contactId),
            OperatorOptions::INCLUDING_ANY => $this->companySegmentRepository->isContactPrimaryCompanyInSegments($contactId, $segmentIds),
            OperatorOptions::EXCLUDING_ANY => $this->companySegmentRepository->isNotContactPrimaryCompanyInSegments($contactId, $segmentIds),
            OperatorOptions::INCLUDING_ALL => $this->companySegmentRepository->isContactPrimaryCompanyInAllSegments($contactId, $segmentIds),
            OperatorOptions::EXCLUDING_ALL => $this->companySegmentRepository->isNotContactPrimaryCompanyInAllSegments($contactId, $segmentIds),
            default                        => throw new \InvalidArgumentException(sprintf("Unexpected operator '%s'", $operator)),
        };
    }
}
