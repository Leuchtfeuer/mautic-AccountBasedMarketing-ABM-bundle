<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Helper;

use Mautic\DynamicContentBundle\DynamicContentEvents;
use Mautic\DynamicContentBundle\Entity\DynamicContent;
use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\DynamicContentBundle\Helper\DynamicContentHelper;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Honors the plugin's "evaluated but not matched" result for company_segments
 * filters. Core only trusts a positive match and otherwise falls back to
 * matchFilterForLead(), which does not know the company_segments filter type.
 */
final class DynamicContentHelperDecorated extends DynamicContentHelper
{
    public function getDynamicContentSlotForLead($slotName, $lead)
    {
        $leadArray = $lead instanceof Lead ? $this->convertLeadToArray($lead) : [];

        /** @var DynamicContent $dwc */
        foreach ($this->getDwcsBySlotName($slotName, true) as $dwc) {
            if ($dwc->getIsCampaignBased()) {
                continue;
            }
            if ($lead && $this->filtersMatch($dwc->getFilters(), $leadArray)) {
                return $this->getRealDynamicContent($dwc->getSlotName(), $lead, $dwc);
            }
        }

        return '';
    }

    /**
     * @param mixed[] $filters
     * @param mixed[] $contactArray
     */
    private function filtersMatch(array $filters, array $contactArray): bool
    {
        if (empty($contactArray['id'])) {
            return false;
        }

        $contact = $this->leadModel->getEntity($contactArray['id']);
        if (!$contact instanceof Lead) {
            return false;
        }

        $event = new ContactFiltersEvaluateEvent($filters, $contact);
        $this->dispatcher->dispatch($event, DynamicContentEvents::ON_CONTACTS_FILTER_EVALUATE);

        if ($event->isEvaluated() && $this->containsCompanySegmentsFilter($filters)) {
            return $event->isMatched();
        }

        return $this->matchFilterForLead($filters, $contactArray);
    }

    /**
     * @param mixed[] $filters
     */
    private function containsCompanySegmentsFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            if ('company_segments' === ($filter['type'] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
