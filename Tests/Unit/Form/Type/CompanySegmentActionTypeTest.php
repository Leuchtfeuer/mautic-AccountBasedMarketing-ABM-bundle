<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Tests\Unit\Form\Type;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentActionType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentListType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

final class CompanySegmentActionTypeTest extends TypeTestCase
{
    private const EDITABLE_SEGMENT_ID = 1;
    private const FOREIGN_SEGMENT_ID  = 2;
    private const DELETED_SEGMENT_ID  = 3;
    private const UNPUBLISHED_OWN_ID  = 4;
    private const OWN_USER_ID         = 5;

    private MockObject&CompanySegmentModel $companySegmentModel;

    private MockObject&CompanySegmentRepository $companySegmentRepository;

    protected function setUp(): void
    {
        $foreignSegment = $this->createMock(CompanySegment::class);
        $foreignSegment->method('getId')->willReturn(self::FOREIGN_SEGMENT_ID);
        $foreignSegment->method('getName')->willReturn('Foreign');

        $unpublishedOwnSegment = $this->createMock(CompanySegment::class);
        $unpublishedOwnSegment->method('getId')->willReturn(self::UNPUBLISHED_OWN_ID);
        $unpublishedOwnSegment->method('getName')->willReturn('Unpublished own');
        $unpublishedOwnSegment->method('getCreatedBy')->willReturn(self::OWN_USER_ID);

        $this->companySegmentRepository = $this->createMock(CompanySegmentRepository::class);
        $this->companySegmentRepository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => array_values(array_filter(
                [$foreignSegment, $unpublishedOwnSegment],
                static fn (CompanySegment $segment): bool => in_array($segment->getId(), $criteria['id'], true)
            ))
        );

        $this->companySegmentModel = $this->createMock(CompanySegmentModel::class);
        $this->companySegmentModel->method('getEditableCompanySegments')->willReturn([
            ['id' => (string) self::EDITABLE_SEGMENT_ID, 'name' => 'Editable', 'alias' => 'editable', 'createdBy' => null],
        ]);
        $this->companySegmentModel->method('canEditCompanySegment')->willReturnCallback(
            static fn (?int $createdBy): bool => self::OWN_USER_ID === $createdBy
        );

        parent::setUp();
    }

    /**
     * @return PreloadedExtension[]
     */
    protected function getExtensions(): array
    {
        return [
            new PreloadedExtension([
                new CompanySegmentActionType($this->companySegmentModel, $this->companySegmentRepository),
                new CompanySegmentListType($this->companySegmentModel),
            ], []),
        ];
    }

    public function testSavedForeignSegmentIsLockedAndKeptOnSubmit(): void
    {
        $form = $this->factory->create(CompanySegmentActionType::class, [
            'addToLists'      => [(string) self::FOREIGN_SEGMENT_ID, (string) self::DELETED_SEGMENT_ID],
            'removeFromLists' => [],
        ]);

        $view   = $form->createView();
        $choice = array_values(array_filter($view['addToLists']->vars['choices'], static fn ($choice): bool => (string) self::FOREIGN_SEGMENT_ID === $choice->value))[0];
        $this->assertSame(['disabled' => 'disabled'], $choice->attr);
        $this->assertSame('mautic.company_segments.form.segments.locked_tooltip', $view['addToLists']->vars['attr']['tooltip']);
        $this->assertNull($view['removeFromLists']->vars['attr']['tooltip']);

        // Browsers do not submit disabled options.
        $form->submit(['addToLists' => [(string) self::EDITABLE_SEGMENT_ID]]);

        $this->assertTrue($form->isValid());
        $this->assertEqualsCanonicalizing([self::EDITABLE_SEGMENT_ID, self::FOREIGN_SEGMENT_ID], $form->getData()['addToLists']);
        $this->assertSame([], $form->getData()['removeFromLists']);
    }

    public function testForeignSegmentCannotBeAddedToNewAction(): void
    {
        $form = $this->factory->create(CompanySegmentActionType::class);
        $form->submit(['addToLists' => [(string) self::FOREIGN_SEGMENT_ID]]);

        $this->assertFalse($form->isValid());
    }

    public function testSavedUnpublishedSegmentWithEditPermissionCanBeRemoved(): void
    {
        $form = $this->factory->create(CompanySegmentActionType::class, [
            'addToLists'      => [(string) self::UNPUBLISHED_OWN_ID],
            'removeFromLists' => [],
        ]);

        $view   = $form->createView();
        $choice = array_values(array_filter($view['addToLists']->vars['choices'], static fn ($choice): bool => (string) self::UNPUBLISHED_OWN_ID === $choice->value))[0];
        $this->assertSame([], $choice->attr);
        $this->assertNull($view['addToLists']->vars['attr']['tooltip']);

        $form->submit(['addToLists' => []]);

        $this->assertTrue($form->isValid());
        $this->assertSame([], $form->getData()['addToLists']);
    }
}
