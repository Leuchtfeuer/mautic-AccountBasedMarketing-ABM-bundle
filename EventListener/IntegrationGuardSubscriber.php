<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
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

        if (!$this->isPluginRoute($route) && !$this->isPluginAjaxAction($request)) {
            return;
        }

        if ($this->config->isPublished()) {
            return;
        }

        throw new NotFoundHttpException();
    }

    private function isPluginAjaxAction(Request $request): bool
    {
        $action = $request->query->get('action');
        if (empty($action)) {
            $action = $request->request->get('action');
        }

        return is_string($action) && str_starts_with(strtolower($action), strtolower(self::AJAX_ACTION_PREFIX));
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
