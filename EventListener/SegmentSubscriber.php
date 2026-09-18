<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\Service\FlashBag;
use Mautic\LeadBundle\Event\LeadListEvent as SegmentEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
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
        private readonly RequestStack $requestStack,
        private readonly FlashBag $flashBag,
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

            // On batch delete the core ListModel discards the dependency error
            // texts and shows only a generic message, so flash the details here.
            if ($this->isSegmentBatchDeleteRequest()) {
                $this->flashBag->add($message, [], FlashBag::LEVEL_ERROR, false);
            }
        }
    }

    private function isSegmentBatchDeleteRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return null !== $request
            && 'mautic_segment_action' === $request->attributes->get('_route')
            && 'batchDelete' === $request->attributes->get('objectAction');
    }
}
