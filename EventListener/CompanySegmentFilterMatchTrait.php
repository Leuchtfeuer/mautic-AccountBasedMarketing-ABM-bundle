<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\EmailBundle\EventListener\MatchFilterForLeadTrait;
use Mautic\LeadBundle\Segment\OperatorOptions;

trait CompanySegmentFilterMatchTrait
{
    use MatchFilterForLeadTrait;

    /**
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

        $segmentIds = array_map(intval(...), array_filter($filterValue));
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
