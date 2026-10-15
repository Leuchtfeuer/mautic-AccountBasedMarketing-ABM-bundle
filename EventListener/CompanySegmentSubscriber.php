<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentPreDelete;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class CompanySegmentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Config $config,
        private CompanySegmentModel $companySegmentModel,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentPreDelete::class => ['onCompanySegmentPreDelete', 0],
        ];
    }

    public function onCompanySegmentPreDelete(CompanySegmentPreDelete $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $companySegment = $event->getCompanySegment();
        $id             = $companySegment->getId();

        if (null === $id) {
            return;
        }

        // Check for dependencies in other company segments
        $dependentCompanySegments = $this->companySegmentModel->getCompanySegmentsDependingOnCompanySegment($id);
        if ([] !== $dependentCompanySegments) {
            $event->addDependencyError($this->translator->trans(
                'mautic.company_segments.error.cannot.delete.inuse',
                ['%segment%' => $companySegment->getName(), '%segments%' => implode(', ', $dependentCompanySegments)],
                'flashes'
            ));
        }

        // Check for dependencies in contact segments
        $dependentContactSegments = $this->companySegmentModel->getContactSegmentsDependingOnCompanySegment($id);
        if ([] !== $dependentContactSegments) {
            $event->addDependencyError($this->translator->trans(
                'mautic.company_segments.segment.error.cannot.delete.inuse',
                ['%segment%' => $companySegment->getName(), '%segments%' => implode(', ', $dependentContactSegments)],
                'flashes'
            ));
        }
    }
}
