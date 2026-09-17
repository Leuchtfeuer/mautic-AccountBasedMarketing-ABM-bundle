<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Event\LeadListEvent as SegmentEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Blocks deleting a contact segment that is referenced by company segment
 * filters (core PR 16025 added this check to the core SegmentSubscriber).
 */
final class SegmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly CompanySegmentModel $companySegmentModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_PRE_DELETE => ['onSegmentPreDelete', 0],
        ];
    }

    public function onSegmentPreDelete(SegmentEvent $event): void
    {
        $leadList = $event->getList();

        $companySegments = $this->companySegmentModel->getCompanySegmentsUsingContactSegment($leadList->getId());
        if (count($companySegments)) {
            $message = $this->translator->trans(
                'mautic.lead_list.is_in_use.delete.company_segments',
                [
                    '%segments%'     => implode(', ', $companySegments),
                    '%segmentNames%' => $leadList->getName(),
                ],
                'validators');
            $event->addDependencyError($message);
        }
    }
}
