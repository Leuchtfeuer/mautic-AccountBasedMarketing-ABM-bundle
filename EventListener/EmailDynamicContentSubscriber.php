<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Evaluates company_segments filters in email dynamic content.
 *
 * EmailBundle bypasses ON_CONTACTS_FILTER_EVALUATE, so this subscriber intercepts
 * TOKEN_REPLACEMENT at priority -200 (before the core TokenSubscriber at -254),
 * handles DC tokens with company_segments filters directly and removes them from
 * the clickthrough so the core TokenSubscriber does not process them again.
 */
final class EmailDynamicContentSubscriber implements EventSubscriberInterface
{
    use CompanySegmentFilterMatchTrait;

    public function __construct(
        private readonly Config $config,
        private readonly SegmentCompanyRepository $companySegmentRepository,
        private readonly PrimaryCompanyHelper $primaryCompanyHelper,
        private readonly EventDispatcherInterface $dispatcher,
        // @phpstan-ignore property.onlyWritten
        private LeadListRepository $segmentRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::TOKEN_REPLACEMENT => ['onTokenReplacement', -200],
        ];
    }

    public function onTokenReplacement(TokenReplacementEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $clickthrough = $event->getClickthrough();
        if (!isset($clickthrough['dynamicContent']) || !is_array($clickthrough['dynamicContent'])) {
            return;
        }

        $lead = $event->getLead();
        if (null === $lead) {
            return;
        }

        if ($lead instanceof Lead) {
            $leadArray = $this->primaryCompanyHelper->getProfileFieldsWithPrimaryCompany($lead);
        } else {
            \assert(is_array($lead) && isset($lead['id']));
            $leadArray = $this->primaryCompanyHelper->mergePrimaryCompanyWithProfileFields((int) $lead['id'], $lead);
        }

        if (!isset($leadArray['id'])) {
            // Preview mode with faked data
            return;
        }

        $remainingDynamicContent = [];

        foreach ($clickthrough['dynamicContent'] as $data) {
            if (!$this->hasCompanySegmentsFilter($data['filters'] ?? [])) {
                $remainingDynamicContent[] = $data;
                continue;
            }

            $filterContent = $data['content'];

            foreach ($data['filters'] as $filter) {
                if ($this->matchFilterGroupForLead($filter['filters'] ?? [], $leadArray)) {
                    $filterContent = $filter['content'];
                    break;
                }
            }

            // Dispatch EMAIL_ON_DISPLAY so contact-field tokens in the DC content are
            // replaced, mirroring what the core TokenSubscriber does for its DC items.
            $emailSendEvent = new EmailSendEvent(
                null,
                [
                    'content' => $filterContent,
                    'email'   => $event->getPassthrough(),
                    'idHash'  => $clickthrough['idHash'] ?? null,
                    'tokens'  => $clickthrough['tokens'] ?? [],
                    'lead'    => $leadArray,
                ],
                true
            );
            $this->dispatcher->dispatch($emailSendEvent, EmailEvents::EMAIL_ON_DISPLAY);

            $event->addToken(
                '{dynamiccontent="'.$data['tokenName'].'"}',
                $emailSendEvent->getContent(!$event->isInternalSend())
            );
        }

        $clickthrough['dynamicContent'] = $remainingDynamicContent;
        $event->setClickthrough($clickthrough);
    }

    /**
     * @param array<array<string, mixed>> $filters
     */
    private function hasCompanySegmentsFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            /** @var array<array<string, mixed>> $subFilters */
            $subFilters = $filter['filters'] ?? [];
            foreach ($subFilters as $condition) {
                if ('company_segments' === ($condition['type'] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }
}
