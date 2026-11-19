<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CompanySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private Config $config,
        private SegmentCompanyRepository $segmentCompanyRepository,
        private CompanySegmentModel $companySegmentModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_POST_SAVE => ['onCompanyPostSave', 0],
        ];
    }

    public function onCompanyPostSave(CompanyEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $companyData = $request->request->all('company');
        if (!isset($companyData['company_segments_form_marker'])) {
            return;
        }

        $company = $event->getCompany();

        $selectedSegmentIds = $this->getSelectedSegmentIds($companyData);
        $currentSegmentIds  = array_map(
            intval(...),
            array_column($this->segmentCompanyRepository->getSegmentsOfCompany((int) $company->getId()), 'id')
        );

        $segmentsToAdd    = array_values(array_diff($selectedSegmentIds, $currentSegmentIds));
        $segmentsToRemove = array_values(array_diff($currentSegmentIds, $selectedSegmentIds));

        if ([] !== $segmentsToAdd) {
            $this->companySegmentModel->addCompany($company, $segmentsToAdd, true);
        }

        if ([] !== $segmentsToRemove) {
            $this->companySegmentModel->removeCompany($company, $segmentsToRemove, true);
        }
    }

    /**
     * @param array<mixed> $companyData
     *
     * @return int[]
     */
    private function getSelectedSegmentIds(array $companyData): array
    {
        $selectedSegmentIds = $companyData['company_segments'] ?? [];
        if (!is_array($selectedSegmentIds)) {
            return [];
        }

        return array_values(array_filter(array_map(intval(...), $selectedSegmentIds)));
    }
}
