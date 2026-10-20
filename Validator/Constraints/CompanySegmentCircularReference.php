<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class CompanySegmentCircularReference extends Constraint
{
    public string $message;

    public function validatedBy(): string
    {
        return CompanySegmentCircularReferenceValidator::class;
    }
}
