<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Returns 404 for all plugin routes while the integration is unpublished.
 */
final readonly class IntegrationGuardSubscriber implements EventSubscriberInterface
{
    private const ROUTE_PREFIXES = [
        'mautic_company_segments_',
        'mautic_api_companysegment',
        '_api_/companysegments',
    ];

    private const AJAX_ACTION_PREFIX = 'plugin:leuchtfeuerAccountBasedMarketingAbm:';

    public function __construct(
        private Config $config,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = (string) $request->attributes->get('_route');
        $action  = (string) ($request->query->get('action') ?? $request->request->get('action') ?? '');

        if (!$this->isPluginRoute($route) && !str_starts_with($action, self::AJAX_ACTION_PREFIX)) {
            return;
        }

        if ($this->config->isPublished()) {
            return;
        }

        throw new NotFoundHttpException();
    }

    private function isPluginRoute(string $route): bool
    {
        foreach (self::ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
