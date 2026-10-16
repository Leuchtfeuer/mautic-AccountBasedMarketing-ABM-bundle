<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\EventListener;

use Mautic\DynamicContentBundle\Event\ContactFiltersEvaluateEvent;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadListRepository;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener\DynamicContentSubscriber;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DynamicContentSubscriberTest extends TestCase
{
    /**
     * @var MockObject&SegmentCompanyRepository
     */
    private MockObject $companySegmentRepository;

    /**
     * @var MockObject&PrimaryCompanyHelper
     */
    private MockObject $primaryCompanyHelper;

    private DynamicContentSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->companySegmentRepository = $this->createMock(SegmentCompanyRepository::class);
        $this->primaryCompanyHelper     = $this->createMock(PrimaryCompanyHelper::class);
        $this->primaryCompanyHelper->method('getProfileFieldsWithPrimaryCompany')
            ->willReturnCallback(static fn (Lead $lead): array => ['id' => $lead->getId()]);
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(true);
        $this->subscriber = new DynamicContentSubscriber(
            $config,
            $this->companySegmentRepository,
            $this->primaryCompanyHelper,
            $this->createStub(LeadListRepository::class)
        );

        parent::setUp();
    }

    public function testOnContactFilterEvaluateDoesNothingWhenPluginNotPublished(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('isPublished')->willReturn(false);
        $subscriber = new DynamicContentSubscriber(
            $config,
            $this->companySegmentRepository,
            $this->primaryCompanyHelper,
            $this->createStub(LeadListRepository::class)
        );

        $filters = [
            [
                'type'     => 'company_segments',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [1, 2],
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId(1));

        $subscriber->onContactFilterEvaluate($event);
        $this->assertFalse($event->isEvaluated());
        $this->assertFalse($event->isMatched());
    }

    public function testOnContactFilterEvaluateIgnoresOtherFilterTypes(): void
    {
        $filters = [
            [
                'type'     => 'leadlist',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [1],
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId(1));

        $this->subscriber->onContactFilterEvaluate($event);
        $this->assertFalse($event->isEvaluated());
        $this->assertFalse($event->isMatched());
    }

    public function testOnContactFilterEvaluateCompanySegmentsIncludingAny(): void
    {
        $contactId = 1;
        $filters   = [
            [
                'type'     => 'company_segments',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [1, 2, 3],
            ],
        ];
        $contact = (new Lead())->setId($contactId);

        $event = new ContactFiltersEvaluateEvent($filters, $contact);

        $this->companySegmentRepository->expects($this->once())
            ->method('isContactPrimaryCompanyInSegments')
            ->with($contactId, [1, 2, 3])
            ->willReturn(true);

        $this->subscriber->onContactFilterEvaluate($event);
        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOnContactFilterEvaluateCompanySegmentsExcludingAny(): void
    {
        $contactId = 2;
        $filters   = [
            [
                'type'     => 'company_segments',
                'operator' => OperatorOptions::EXCLUDING_ANY,
                'filter'   => [4],
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId($contactId));

        $this->companySegmentRepository->expects($this->once())
            ->method('isNotContactPrimaryCompanyInSegments')
            ->with($contactId, [4])
            ->willReturn(false);

        $this->subscriber->onContactFilterEvaluate($event);
        $this->assertTrue($event->isEvaluated());
        $this->assertFalse($event->isMatched());
    }

    public function testOnContactFilterEvaluateCompanySegmentsEmpty(): void
    {
        $contactId = 3;
        $filters   = [
            [
                'type'     => 'company_segments',
                'operator' => OperatorOptions::EMPTY,
                'filter'   => null,
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId($contactId));

        $this->companySegmentRepository->expects($this->once())
            ->method('isNotContactPrimaryCompanyInAnySegment')
            ->with($contactId)
            ->willReturn(true);

        $this->subscriber->onContactFilterEvaluate($event);
        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOnContactFilterEvaluateCompanySegmentsNotEmpty(): void
    {
        $contactId = 4;
        $filters   = [
            [
                'type'     => 'company_segments',
                'operator' => OperatorOptions::NOT_EMPTY,
                'filter'   => null,
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId($contactId));

        $this->companySegmentRepository->expects($this->once())
            ->method('isContactPrimaryCompanyInAnySegment')
            ->with($contactId)
            ->willReturn(true);

        $this->subscriber->onContactFilterEvaluate($event);
        $this->assertTrue($event->isEvaluated());
        $this->assertTrue($event->isMatched());
    }

    public function testOnContactFilterEvaluateMixedFilterSetMustNotClaimFullMatch(): void
    {
        $contactId = 6;
        $filters   = [
            [
                'glue'     => 'and',
                'type'     => 'company_segments',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [1],
            ],
            [
                'glue'     => 'and',
                'type'     => 'text',
                'field'    => 'city',
                'operator' => '=',
                'filter'   => 'Berlin',
            ],
        ];
        // Contact has no city, so the AND-ed text filter does not match.
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId($contactId));

        $this->companySegmentRepository->method('isContactPrimaryCompanyInSegments')
            ->with($contactId, [1])
            ->willReturn(true);

        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertFalse(
            $event->isMatch(),
            'A matching company_segments filter must not shortcut the whole filter set to "matched" while an AND-ed filter does not match.'
        );
    }

    public function testOnContactFilterEvaluateEvaluatesAllCompanySegmentsFilters(): void
    {
        $contactId = 7;
        $filters   = [
            [
                'glue'     => 'and',
                'type'     => 'company_segments',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [1],
            ],
            [
                'glue'     => 'and',
                'type'     => 'company_segments',
                'operator' => OperatorOptions::INCLUDING_ANY,
                'filter'   => [2],
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId($contactId));

        $this->companySegmentRepository->expects($this->atLeast(2))->method('isContactPrimaryCompanyInSegments')
            ->willReturnMap([
                [$contactId, [1], true],
                [$contactId, [2], false],
            ]);

        $this->subscriber->onContactFilterEvaluate($event);

        $this->assertFalse(
            $event->isMatch(),
            'Only the first company_segments filter was evaluated; the second AND-ed one does not match, so the set must not match.'
        );
    }

    public function testOnContactFilterEvaluateUnexpectedOperatorThrows(): void
    {
        $filters = [
            [
                'type'     => 'company_segments',
                'operator' => 'gt',
                'filter'   => [1],
            ],
        ];
        $event = new ContactFiltersEvaluateEvent($filters, (new Lead())->setId(5));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unexpected operator 'gt'");

        $this->subscriber->onContactFilterEvaluate($event);
    }
}
