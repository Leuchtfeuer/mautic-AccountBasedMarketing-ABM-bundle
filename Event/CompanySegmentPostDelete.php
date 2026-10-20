<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event;

final class CompanySegmentPostDelete extends CompanySegmentEvent
{
    /**
     * @return array<mixed>
     */
    public function getChanges(): array
    {
        return $this->getCompanySegment()->getChanges();
    }
}
