<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\Service\FlashBag;
use Mautic\LeadBundle\Event\LeadListEvent as SegmentEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Blocks deleting a contact segment that is referenced by company segment
 * filters (core PR 16025 added this check to the core SegmentSubscriber).
 */
final readonly class SegmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private TranslatorInterface $translator,
        private CompanySegmentModel $companySegmentModel,
        private RequestStack $requestStack,
        private FlashBag $flashBag,
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
        if (!$this->config->isPublished()) {
            return;
        }

        $leadList = $event->getList();

        $companySegments = $this->companySegmentModel->getCompanySegmentsUsingContactSegment($leadList->getId());
        if (count($companySegments)) {
            $messageVars = [
                '%segments%'     => implode(', ', $companySegments),
                '%segmentNames%' => $leadList->getName(),
            ];
            $message = $this->translator->trans(
                'mautic.lead_list.is_in_use.delete.company_segments',
                $messageVars,
                'validators');
            $event->addDependencyError($message);

            // On batch delete the core ListModel discards the dependency error
            // texts and shows only a generic message, so flash the details here.
            if ($this->isSegmentBatchDeleteRequest()) {
                $this->flashBag->add('mautic.lead_list.is_in_use.delete.company_segments', $messageVars, FlashBag::LEVEL_ERROR, 'validators');
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
