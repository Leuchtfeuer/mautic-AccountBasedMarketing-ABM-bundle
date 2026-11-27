<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension;

use Mautic\LeadBundle\Form\Type\LeadImportFieldType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\CompanySegmentListType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LeadImportFieldTypeExtension extends AbstractTypeExtension
{
    public const FIELD_NAME = 'company_segments';

    public function __construct(
        private readonly Config $config,
        private readonly CompanySegmentModel $companySegmentModel,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [LeadImportFieldType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ('company' !== $options['object'] || !$this->config->isPublished()) {
            return;
        }

        $choices = [];
        foreach ($this->companySegmentModel->getEditableCompanySegments() as $segment) {
            $choices[$segment['name'].' ('.$segment['id'].')'] = (int) $segment['id'];
        }
        ksort($choices);

        $builder->add(
            self::FIELD_NAME,
            CompanySegmentListType::class,
            [
                'label'      => 'mautic.company_segments.menu.index',
                'label_attr' => ['class' => 'form-label'],
                'attr'       => [
                    'class'            => 'form-control',
                    'data-placeholder' => $this->translator->trans('mautic.company_segments.form.import.segments.placeholder'),
                    'tooltip'          => 'mautic.company_segments.form.import.segments.tooltip',
                ],
                'choices'  => $choices,
                'multiple' => true,
                'mapped'   => false,
                // The import controller only checks root form errors before it saves the import.
                'error_bubbling' => true,
            ]
        );
    }
}
