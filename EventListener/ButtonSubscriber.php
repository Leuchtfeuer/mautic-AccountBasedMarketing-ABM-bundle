<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;

/**
 * Adds the "Change company segments" bulk action button to the companies list.
 */
final class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly TranslatorInterface $translator,
        private readonly RouterInterface $router,
        private readonly CorePermissions $security,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        if (!str_contains($event->getRoute(), 'mautic_company_index')) {
            return;
        }

        if (!$this->security->isGranted('lead:leads:editother')) {
            return;
        }

        $event->addButton(
            [
                'attr'      => [
                    'class'       => 'btn btn-default btn-sm btn-nospin',
                    'data-toggle' => 'ajaxmodal',
                    'data-target' => '#MauticSharedModal',
                    'href'        => $this->router->generate('mautic_company_segments_batch_company_view'),
                    'data-header' => $this->translator->trans('mautic.company_segments.action.change'),
                ],
                'btnText'   => $this->translator->trans('mautic.company_segments.action.change'),
                'iconClass' => 'ri-pie-chart-line',
            ],
            ButtonHelper::LOCATION_BULK_ACTIONS
        );
    }
}
