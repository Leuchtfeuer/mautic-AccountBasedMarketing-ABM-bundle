<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use Symfony\Contracts\EventDispatcher\Event;

abstract class CompanySegmentEvent extends Event
{
    public function __construct(private readonly CompanySegment $companySegment)
    {
    }

    public function getCompanySegment(): CompanySegment
    {
        return $this->companySegment;
    }
}
