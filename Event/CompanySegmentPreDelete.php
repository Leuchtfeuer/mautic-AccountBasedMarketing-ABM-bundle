<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event;

use Mautic\CoreBundle\Event\DependencyErrorEventInterface;
use Mautic\CoreBundle\Event\DependencyErrorEventTrait;

final class CompanySegmentPreDelete extends CompanySegmentEvent implements DependencyErrorEventInterface
{
    use DependencyErrorEventTrait;
}
