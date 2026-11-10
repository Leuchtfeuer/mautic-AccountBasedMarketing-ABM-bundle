<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomContentEvent;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Controller\CompanyTimelineController;
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
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_CONTENT => ['injectContent', 0],
        ];
    }

    public function injectContent(CustomContentEvent $event): void
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
}
