<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Validator\Constraints;

use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\CompanySegmentCircularReference;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\CompanySegmentCircularReferenceValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<CompanySegmentCircularReferenceValidator>
 */
final class CompanySegmentCircularReferenceValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var MockObject&CompanySegmentModel
     */
    private MockObject $model;

    private RequestStack $requestStack;

    protected function createValidator(): CompanySegmentCircularReferenceValidator
    {
        $this->model        = $this->createMock(CompanySegmentModel::class);
        $this->requestStack = new RequestStack();

        return new CompanySegmentCircularReferenceValidator($this->model, $this->requestStack);
    }

    public function testCircularReferenceIsDetectedWithStringSegmentIds(): void
    {
        $this->requestStack->push(new Request([], [], ['_route_params' => ['objectId' => '8']]));

        $referencedSegment = new CompanySegment();
        $referencedSegment->setFilters([
            [
                'glue'       => 'and',
                'field'      => 'company_segments',
                'object'     => 'company_segments',
                'type'       => CompanySegmentModel::PROPERTIES_FIELD,
                'operator'   => OperatorOptions::INCLUDING_ANY,
                'properties' => ['filter' => [8]],
            ],
        ]);

        $this->model->method('getEntity')->willReturnCallback(
            static fn ($id): CompanySegment => 5 === $id ? $referencedSegment : new CompanySegment()
        );

        $constraint          = new CompanySegmentCircularReference();
        $constraint->message = 'Circular reference detected.';

        $this->validator->validate([
            [
                'glue'       => 'and',
                'field'      => 'company_segments',
                'object'     => 'company_segments',
                'type'       => CompanySegmentModel::PROPERTIES_FIELD,
                'operator'   => OperatorOptions::INCLUDING_ANY,
                'properties' => ['filter' => ['5']],
            ],
        ], $constraint);

        $this->buildViolation('Circular reference detected.')->assertRaised();
    }
}
