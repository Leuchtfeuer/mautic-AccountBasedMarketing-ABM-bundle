<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class CompanySegmentActionType extends AbstractType
{
    private const FIELDS = [
        'addToLists'      => 'mautic.company_segments.campaign.events.addtolists',
        'removeFromLists' => 'mautic.company_segments.campaign.events.removefromlists',
    ];

    public function __construct(
        private readonly CompanySegmentModel $companySegmentModel,
        private readonly CompanySegmentRepository $companySegmentRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $editableChoices = [];
        foreach ($this->companySegmentModel->getEditableCompanySegments() as $segment) {
            $editableChoices[$segment['name'].' ('.$segment['id'].')'] = (int) $segment['id'];
        }

        $lockedSegmentIds = [];
        foreach (self::FIELDS as $field => $label) {
            $savedSegmentIds          = array_map(intval(...), (array) ($options['data'][$field] ?? []));
            $choices                  = $editableChoices;
            $lockedSegmentIds[$field] = [];
            foreach ($this->companySegmentRepository->findBy(['id' => array_diff($savedSegmentIds, $editableChoices)]) as $segment) {
                $choices[$segment->getName().' ('.$segment->getId().')'] = (int) $segment->getId();
                $lockedSegmentIds[$field][]                              = (int) $segment->getId();
            }
            ksort($choices);

            $builder->add(
                $field,
                CompanySegmentListType::class,
                [
                    'label'      => $label,
                    'label_attr' => ['class' => 'control-label'],
                    'attr'       => [
                        'class'   => 'form-control',
                        'tooltip' => [] !== $lockedSegmentIds[$field] ? 'mautic.company_segments.form.segments.locked_tooltip' : null,
                    ],
                    'choices'     => $choices,
                    'choice_attr' => static fn (int $segmentId): array => in_array($segmentId, $lockedSegmentIds[$field], true) ? ['disabled' => 'disabled'] : [],
                    'multiple'    => true,
                    'expanded'    => false,
                ]
            );
        }

        // Disabled options are not submitted by the browser, so keep the locked segments.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($lockedSegmentIds): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            foreach ($lockedSegmentIds as $field => $segmentIds) {
                $submittedSegmentIds = array_map(intval(...), (array) ($data[$field] ?? []));
                $data[$field]        = array_values(array_unique([...$submittedSegmentIds, ...$segmentIds]));
            }

            $event->setData($data);
        });
    }

    public function getBlockPrefix(): string
    {
        return 'companysegment_action';
    }
}
