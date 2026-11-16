<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type;

use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\CompanySegmentRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class DashboardCompanySegmentType extends AbstractType
{
    public function __construct(
        private readonly CompanySegmentRepository $companySegmentRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $segmentNames = array_column($this->companySegmentRepository->getSegments(), 'name', 'id');

        $builder->add('companysegments', ChoiceType::class, [
            'label'        => 'mautic.widget.company.segments.filter',
            'multiple'     => true,
            'choices'      => array_keys($segmentNames),
            'choice_label' => fn (int|string $id): string => $segmentNames[$id],
            'label_attr'   => ['class' => 'control-label'],
            'attr'         => ['class' => 'form-control'],
            'required'     => false,
        ]);

        $builder->add('order', ChoiceType::class, [
            'label'      => 'mautic.widget.company.segments.ordertitle',
            'multiple'   => false,
            'choices'    => ['mautic.widget.company.segments.orderbydatecreated' => 1],
            'label_attr' => ['class' => 'control-label'],
            'attr'       => ['class' => 'form-control'],
            'required'   => false,
        ]);
    }
}
