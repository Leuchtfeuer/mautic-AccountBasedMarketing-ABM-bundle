<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Validator\Constraints;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\UniqueCompanySegmentAlias;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\UniqueCompanySegmentAliasValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends \Symfony\Component\Validator\Test\ConstraintValidatorTestCase<\MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\UniqueCompanySegmentAliasValidator>
 */
final class UniqueCompanySegmentAliasValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @var MockObject&CompanySegmentRepository
     */
    private MockObject $segmentRepository;

    public function testValueIsNotCompanySegment(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->assertNoActionsTaken();

        $this->validator->validate(new \stdClass(), $this->createStub(UniqueCompanySegmentAlias::class));
    }

    public function testConstraintIsNotUniqueAlias(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $this->assertNoActionsTaken();

        $this->validator->validate($this->createStub(CompanySegment::class), $this->createStub(Constraint::class));
    }

    public function testFieldOptionIsRequired(): void
    {
        $this->assertNoActionsTaken();

        $constraint        = $this->createStub(UniqueCompanySegmentAlias::class);
        $constraint->field = '';
        $this->validator->validate($this->createStub(CompanySegment::class), $constraint);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideNullOrEmpty')]
    public function testAliasIsNullOrEmpty(?string $value): void
    {
        $this->assertNoActionsTaken();

        $constraint        = $this->createStub(UniqueCompanySegmentAlias::class);
        $constraint->field = 'alias';
        $companySegment    = $this->createMock(CompanySegment::class);
        $companySegment->method('getAlias')
            ->willReturn($value);

        $this->validator->validate($companySegment, $constraint);
        $this->assertNoViolation();
    }

    public static function provideNullOrEmpty(): \Generator
    {
        yield 'alias is null' => [null];
        yield 'alias is empty' => [''];
    }

    public function testNoSegmentsFoundNoViolation(): void
    {
        $aliasName = 'alias';
        $segmentId = 38746584375698237;

        $constraint        = $this->createStub(UniqueCompanySegmentAlias::class);
        $constraint->field = 'alias';
        $companySegment    = $this->createMock(CompanySegment::class);
        $companySegment->method('getAlias')
            ->willReturn($aliasName);
        $companySegment->method('getId')
            ->willReturn($segmentId);

        $this->segmentRepository->expects($this->once())
            ->method('getSegments')
            ->with($aliasName, $segmentId)
            ->willReturn([]);

        $this->validator->validate($companySegment, $constraint);
        $this->assertNoViolation();
    }

    public function testDuplicateSegmentViolation(): void
    {
        $aliasName  = 'alias';
        $aliasField = 'alias_field';
        $segmentId  = 38746584375698237;

        $constraint        = $this->createStub(UniqueCompanySegmentAlias::class);
        $constraint->field = $aliasField;
        $companySegment    = $this->createMock(CompanySegment::class);
        $companySegment->method('getAlias')
            ->willReturn($aliasName);
        $companySegment->method('getId')
            ->willReturn($segmentId);

        $this->segmentRepository->expects($this->once())
            ->method('getSegments')
            ->with($aliasName, $segmentId)
            ->willReturn([$this->createStub(CompanySegment::class)]);

        $this->validator->validate($companySegment, $constraint);

        $this->buildViolation('This alias is already in use.')
            ->atPath('property.path.'.$aliasField)
            ->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->segmentRepository = $this->createMock(CompanySegmentRepository::class);

        return new UniqueCompanySegmentAliasValidator($this->segmentRepository);
    }

    private function assertNoActionsTaken(): void
    {
        $this->segmentRepository->expects($this->never())
            ->method('getSegments');
    }
}
