<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanyTimelineController;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanyTimelineModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class CompanyCustomContentSubscriber implements EventSubscriberInterface
{
    private const COMPANY_VIEW = '@MauticLead/Company/company.html.twig';

    public function __construct(
        private Config $config,
        private CompanyTimelineModel $companyTimelineModel,
        private RequestStack $requestStack,
        private SegmentCompanyRepository $segmentCompanyRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => [
                ['injectTimelineContent', 0],
                ['injectCompanySegmentContent', 0],
            ],
        ];
    }

    public function injectTimelineContent(CustomContentEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if ($event->checkContext(self::COMPANY_VIEW, 'company.tabs')) {
            $event->addTemplate('@LeuchtfeuerAccountBasedMarketingAbm/Timeline/tab.html.twig');

            return;
        }

        $company = $event->getVars()['company'] ?? null;
        if (!$event->checkContext(self::COMPANY_VIEW, 'company.tabs.content') || !$company instanceof Company) {
            return;
        }

        $filters = $this->requestStack->getSession()->get(sprintf(CompanyTimelineController::SESSION_KEY, $company->getId()).'.filters', []);

        $event->addTemplate('@LeuchtfeuerAccountBasedMarketingAbm/Timeline/tab_content.html.twig', [
            'company' => $company,
            'events'  => [
                'filters' => $filters + ['search' => '', 'includeEvents' => [], 'excludeEvents' => []],
                'types'   => $this->companyTimelineModel->getEngagementTypes(),
            ],
        ]);
    }

    public function injectCompanySegmentContent(CustomContentEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $company = $event->getVars()['company'] ?? null;
        if (!$company instanceof Company || !$event->checkContext(self::COMPANY_VIEW, 'company.sidebar.bottom')) {
            return;
        }

        $event->addTemplate('@LeuchtfeuerAccountBasedMarketingAbm/Company/company_segments.html.twig', [
            'companySegments' => $this->segmentCompanyRepository->getSegmentsOfCompany((int) $company->getId()),
        ]);
    }
}
