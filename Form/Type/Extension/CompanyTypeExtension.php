<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Form\Type\CompanyType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentListType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanyTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly Config $config,
        private readonly SegmentCompanyRepository $segmentCompanyRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $company         = $options['data'] ?? null;
        $currentSegments = [];

        if ($company instanceof Company && null !== $company->getId()) {
            $companiesSegments = $this->segmentCompanyRepository->getSegmentsOfCompany($company->getId());
            $currentSegments   = array_map(static fn (array $segment): int => (int) $segment['id'], $companiesSegments);
        }

        // Hidden marker field to detect when company form with segments is submitted
        $builder->add(
            'company_segments_form_marker',
            HiddenType::class,
            [
                'mapped' => false,
                'data'   => '1',
            ]
        );

        $builder->add(
            'company_segments',
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.menu.index',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'                         => 'form-control',
                    'data-placeholder'              => $this->translator->trans('mautic.company_segments.form.segments.placeholder'),
                    'data-company-segments-manual'  => 'true',
                ],
                'required' => false,
                'multiple' => true,
                'mapped'   => false,
                'data'     => $currentSegments,
            ]
        );
    }

    public static function getExtendedTypes(): iterable
    {
        return [CompanyType::class];
    }
}
