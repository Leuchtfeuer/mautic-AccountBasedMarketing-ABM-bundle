<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Form\Type\CompanyType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Entity\SegmentCompanyRepository;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentListType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
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
        private readonly CompanySegmentModel $companySegmentModel,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $choices = [];
        foreach ($this->companySegmentModel->getEditableCompanySegments() as $segment) {
            $choices[$segment['name'].' ('.$segment['id'].')'] = (int) $segment['id'];
        }

        $currentSegments  = [];
        $lockedSegmentIds = [];
        $company          = $options['data'] ?? null;
        if ($company instanceof Company && null !== $company->getId()) {
            foreach ($this->segmentCompanyRepository->getSegmentsOfCompany($company->getId()) as $segment) {
                $currentSegments[$segment['name'].' ('.$segment['id'].')'] = (int) $segment['id'];
                if (!in_array((int) $segment['id'], $choices, true) && !$this->companySegmentModel->canEditCompanySegment((int) $segment['createdBy'])) {
                    $lockedSegmentIds[] = (int) $segment['id'];
                }
            }
        }

        $choices += $currentSegments;
        ksort($choices);

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
                    'class'                        => 'form-control',
                    'data-placeholder'             => $this->translator->trans('mautic.company_segments.form.segments.placeholder'),
                    'data-company-segments-manual' => 'true',
                    'tooltip'                      => [] !== $lockedSegmentIds ? 'mautic.company_segments.form.segments.locked_tooltip' : null,
                ],
                'choices'     => $choices,
                'choice_attr' => static fn (int $segmentId): array => in_array($segmentId, $lockedSegmentIds, true) ? ['disabled' => 'disabled'] : [],
                'required'    => false,
                'multiple'    => true,
                'mapped'      => false,
                'data'        => array_values($currentSegments),
            ]
        );
    }

    public static function getExtendedTypes(): iterable
    {
        return [CompanyType::class];
    }
}
