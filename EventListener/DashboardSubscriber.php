<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\DashboardBundle\Event\WidgetDetailEvent;
use Mautic\DashboardBundle\EventListener\DashboardSubscriber as OriginalDashboardSubscriber;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\DashboardCompanySegmentType;
use Symfony\Component\Routing\RouterInterface;

class DashboardSubscriber extends OriginalDashboardSubscriber
{
    /**
     * @var string
     */
    protected $bundle = 'abm';

    /**
     * @var array<string, array<string, string>>
     */
    protected $types = [
        'company.list' => [
            'formAlias' => DashboardCompanySegmentType::class,
        ],
    ];

    /**
     * @var array<string>
     */
    protected $permissions = [
        'lead:leads:viewown',
        'lead:leads:viewother',
    ];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly CompanyRepository $companyRepository,
        private readonly SegmentCompanyRepository $segmentCompanyRepository,
    ) {
    }

    public function onWidgetDetailGenerate(WidgetDetailEvent $event): void
    {
        if ('company.list' !== $event->getType()) {
            return;
        }

        if ($event->isCached()) {
            $this->finalizeWidget($event);

            return;
        }

        $selectedSegments = array_map(intval(...), (array) ($event->getWidget()->getParams()['companysegments'] ?? []));
        $limit            = max(1, (int) round((($event->getWidget()->getHeight() - 80) / 35) - 1));
        $companies        = $this->getNewestCompanies($selectedSegments, $limit);

        $items = [];
        foreach ($companies as $company) {
            $website = $company->getWebsite();

            $items[] = [
                ['value' => $company->getId()],
                [
                    'value'    => $company->getName(),
                    'type'     => 'link',
                    'link'     => $this->router->generate('mautic_company_action', ['objectAction' => 'view', 'objectId' => $company->getId()]),
                    'external' => true,
                ],
                [
                    'value'    => $website,
                    'type'     => null !== $website ? 'link' : 'text',
                    'link'     => $website,
                    'external' => true,
                ],
            ];
        }

        $event->setTemplateData([
            'headItems' => [
                'mautic.widget.company.segments.id',
                'mautic.widget.company.segments.name',
                'mautic.widget.company.segments.website',
            ],
            'bodyItems' => $items,
        ]);

        $this->finalizeWidget($event);
    }

    /**
     * @param array<int> $selectedSegments
     *
     * @return array<Company>
     */
    private function getNewestCompanies(array $selectedSegments, int $limit): array
    {
        $criteria = [];
        if ([] !== $selectedSegments) {
            $criteria['id'] = $this->segmentCompanyRepository->getCompanyIdsInAllSegments($selectedSegments);
        }

        return $this->companyRepository->findBy($criteria, ['dateAdded' => 'DESC'], $limit);
    }

    private function finalizeWidget(WidgetDetailEvent $event): void
    {
        $event->setTemplate('@MauticCore/Helper/table.html.twig');
        $event->stopPropagation();
    }
}
