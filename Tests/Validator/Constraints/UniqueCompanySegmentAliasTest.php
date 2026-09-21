<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Validator\Constraints;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Validator\Constraints\UniqueCompanySegmentAlias;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Exception\MissingOptionsException;

final class UniqueCompanySegmentAliasTest extends TestCase
{
    public function testThrowsConstraintExceptionIfNoFieldIsSet(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The options "field" must be set for constraint');
        new UniqueCompanySegmentAlias();
    }
}
