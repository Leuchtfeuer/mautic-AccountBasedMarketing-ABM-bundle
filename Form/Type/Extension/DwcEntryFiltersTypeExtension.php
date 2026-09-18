<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Form\Type\Extension;

use Mautic\CoreBundle\Form\Type\DynamicContentFilterEntryFiltersType;
use Mautic\DynamicContentBundle\Form\Type\DwcEntryFiltersType;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DwcEntryFiltersTypeExtension extends AbstractTypeExtension
{
    /**
     * @var array<string, int>|null
     */
    private ?array $companySegmentChoices = null;

    public function __construct(
        private readonly CompanySegmentModel $companySegmentModel,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        // Covers dynamic web content and email dynamic content filter entries;
        // both build their filter child via the core FilterTrait.
        return [DwcEntryFiltersType::class, DynamicContentFilterEntryFiltersType::class];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('companySegments', $this->getCompanySegmentChoices());
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Extension listeners run after the type listeners, so this replaces
        // the filter child built by the core FilterTrait.
        $formModifier = function (FormEvent $event, string $eventName): void {
            $data = $event->getData();
            $form = $event->getForm();

            if (!is_array($data) || 'company_segments' !== ($data['type'] ?? null)) {
                return;
            }

            $options  = $form->getConfig()->getOptions();
            $operator = $data['operator'] ?? '';

            if (!isset($data['filter'])) {
                $data['filter'] = [];
            } elseif (!is_array($data['filter'])) {
                $data['filter'] = [$data['filter']];
            }

            $filter = $data['filter'];
            if (in_array($operator, ['empty', '!empty'], true)) {
                // @see Symfony\Component\Form\Extension\Core\Type\ChoiceType::configureOptions
                $filter = null;
            }

            $form->add(
                'filter',
                ChoiceType::class,
                [
                    'label'                     => false,
                    'attr'                      => ['class' => 'form-control filter-value'],
                    'data'                      => $filter,
                    'error_bubbling'            => false,
                    'choices'                   => $options['companySegments'] ?? [],
                    'multiple'                  => in_array($operator, ['in', '!in'], true),
                    'choice_translation_domain' => false,
                ]
            );

            if (FormEvents::PRE_SUBMIT === $eventName) {
                $event->setData($data);
            }
        };

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($formModifier): void {
                $formModifier($event, FormEvents::PRE_SET_DATA);
            }
        );

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) use ($formModifier): void {
                $formModifier($event, FormEvents::PRE_SUBMIT);
            }
        );
    }

    /**
     * @return array<string, int>
     */
    private function getCompanySegmentChoices(): array
    {
        if (null === $this->companySegmentChoices) {
            $choices = [];

            foreach ($this->companySegmentModel->getCompanySegments() as $segment) {
                $choices[$segment['name']] = (int) $segment['id'];
            }

            $this->companySegmentChoices = $choices;
        }

        return $this->companySegmentChoices;
    }
}
