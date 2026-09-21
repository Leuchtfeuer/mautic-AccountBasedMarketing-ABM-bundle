<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\EventListener\MatchFilterForLeadTrait;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;

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
    use MatchFilterForLeadTrait;

    public function __construct(
        private readonly Config $config,
        private readonly SegmentCompanyRepository $companySegmentRepository,
        private readonly PrimaryCompanyHelper $primaryCompanyHelper,
        private readonly EventDispatcherInterface $dispatcher,
        protected LeadListRepository $segmentRepository,
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

    /**
     * Evaluates a filter group (AND/OR conditions) against a lead. Routes
     * company_segments conditions to our evaluator and delegates all other
     * condition types to MatchFilterForLeadTrait.
     *
     * @param array<array<string, mixed>> $conditions
     * @param array<string, mixed>        $lead
     */
    private function matchFilterGroupForLead(array $conditions, array $lead): bool
    {
        $groups   = [];
        $groupNum = 0;

        foreach ($conditions as $condition) {
            if (0 === $groupNum || 'or' === ($condition['glue'] ?? 'and')) {
                ++$groupNum;
                $groups[$groupNum] = null;
            }

            if (false === $groups[$groupNum]) {
                continue;
            }

            $groups[$groupNum] = 'company_segments' === ($condition['type'] ?? null)
                ? $this->evaluateCompanySegmentsCondition($condition, $lead)
                : $this->matchFilterForLead([$condition], $lead);
        }

        return in_array(true, $groups, true);
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $lead
     */
    private function evaluateCompanySegmentsCondition(array $condition, array $lead): bool
    {
        $operator    = is_string($condition['operator'] ?? null) ? $condition['operator'] : '';
        $filterValue = $condition['filter'] ?? [];

        if (!is_array($filterValue)) {
            $filterValue = [$filterValue];
        }

        $segmentIds = array_map('intval', array_filter($filterValue));
        $contactId  = (int) $lead['id'];

        return match ($operator) {
            OperatorOptions::EMPTY         => $this->companySegmentRepository->isNotContactPrimaryCompanyInAnySegment($contactId),
            OperatorOptions::NOT_EMPTY     => $this->companySegmentRepository->isContactPrimaryCompanyInAnySegment($contactId),
            OperatorOptions::INCLUDING_ANY => $this->companySegmentRepository->isContactPrimaryCompanyInSegments($contactId, $segmentIds),
            OperatorOptions::EXCLUDING_ANY => $this->companySegmentRepository->isNotContactPrimaryCompanyInSegments($contactId, $segmentIds),
            OperatorOptions::INCLUDING_ALL => $this->companySegmentRepository->isContactPrimaryCompanyInAllSegments($contactId, $segmentIds),
            OperatorOptions::EXCLUDING_ALL => $this->companySegmentRepository->isNotContactPrimaryCompanyInAllSegments($contactId, $segmentIds),
            default                        => throw new \InvalidArgumentException(sprintf("Unexpected operator '%s'", $operator)),
        };
    }
}
