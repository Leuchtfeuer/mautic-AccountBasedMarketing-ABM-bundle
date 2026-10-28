<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerFloodlightB2BBundle\Integration\Config;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEntityListener(event: Events::postLoad, method: 'applyPrimaryIdentifierOrder', entity: Lead::class)]
#[AsEntityListener(event: Events::prePersist, method: 'applyPrimaryIdentifierOrder', entity: Lead::class)]
final readonly class LeadPrimaryIdentifierListener
{
    private const ROUTES = ['mautic_contact_index', 'mautic_contact_action'];

    public function __construct(
        private Config $config,
        private RequestStack $requestStack,
    ) {
    }

    public function applyPrimaryIdentifierOrder(Lead $lead): void
    {
        if (!method_exists($lead, 'setPrimaryIdentifierOrder')) {
            return;
        }

        $route = $this->requestStack->getMainRequest()?->attributes->get('_route');
        if (!in_array($route, self::ROUTES, true) || !$this->config->isPublished()) {
            return;
        }

        $lead->setPrimaryIdentifierOrder(['name', 'email', 'company', 'social', 'ip']);
    }
}
