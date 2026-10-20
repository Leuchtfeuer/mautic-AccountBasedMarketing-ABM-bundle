<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Segment\Decorator;

use Mautic\LeadBundle\Segment\ContactSegmentFilterCrate;
use Mautic\LeadBundle\Segment\Decorator\FilterDecoratorInterface;
use Mautic\LeadBundle\Segment\Query\Filter\ComplexRelationValueFilterQueryBuilder;

/**
 * Filters company fields directly on the companies table of a company segment query.
 * The core company decorators resolve them through the contact relation, which
 * requires a leads table that a company segment query does not have.
 */
final readonly class CompanyFieldDecorator implements FilterDecoratorInterface
{
    public function __construct(
        private FilterDecoratorInterface $decorator,
    ) {
    }

    /**
     * @return string|null
     */
    public function getField(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        return $this->decorator->getField($contactSegmentFilterCrate);
    }

    /**
     * @return string
     */
    public function getTable(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        return $this->decorator->getTable($contactSegmentFilterCrate);
    }

    /**
     * @return string
     */
    public function getOperator(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        return $this->decorator->getOperator($contactSegmentFilterCrate);
    }

    /**
     * @param array<mixed>|string $argument
     *
     * @return array<mixed>|string
     */
    public function getParameterHolder(ContactSegmentFilterCrate $contactSegmentFilterCrate, $argument)
    {
        return $this->decorator->getParameterHolder($contactSegmentFilterCrate, $argument);
    }

    /**
     * @return array<mixed>|bool|float|string|null
     */
    public function getParameterValue(ContactSegmentFilterCrate $contactSegmentFilterCrate): mixed
    {
        return $this->decorator->getParameterValue($contactSegmentFilterCrate);
    }

    public function getQueryType(ContactSegmentFilterCrate $contactSegmentFilterCrate): string
    {
        return ComplexRelationValueFilterQueryBuilder::getServiceId();
    }

    public function getAggregateFunc(ContactSegmentFilterCrate $contactSegmentFilterCrate): string|bool
    {
        return $this->decorator->getAggregateFunc($contactSegmentFilterCrate);
    }

    /**
     * @return \Doctrine\DBAL\Query\Expression\CompositeExpression|string|null
     */
    public function getWhere(ContactSegmentFilterCrate $contactSegmentFilterCrate)
    {
        return $this->decorator->getWhere($contactSegmentFilterCrate);
    }
}
