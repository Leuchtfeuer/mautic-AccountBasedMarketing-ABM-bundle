<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
class CampaignEventCompanySegmentsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'companySegments',
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.campaign.event.segments',
                'label_attr' => ['class' => 'control-label'],
                'multiple'   => true,
                'required'   => false,
            ]
        );
    }

    public function getBlockPrefix(): string
    {
        return 'campaignevent_company_segments';
    }
}
