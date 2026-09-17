<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Mautic\LeadBundle\Event\FormAdjustmentEvent;
use Mautic\LeadBundle\Event\LeadListFiltersChoicesEvent;
use Mautic\LeadBundle\Event\ListFieldChoicesEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\LeadBundle\Segment\SegmentFilterIconTrait;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Plugin counterpart of the core TypeOperatorSubscriber additions from
 * PR mautic/mautic#16025: choices for the company_segments field alias,
 * multiselect rendering of the company_segments filter, static fields for
 * the company segment filter form and the company_segments filter choice
 * in the contact segment form.
 */
class TypeOperatorSubscriber implements EventSubscriberInterface
{
    use SegmentFilterIconTrait;

    public function __construct(
        private CompanySegmentModel $companySegmentModel,
        private FieldChoicesProviderInterface $fieldChoicesProvider,
        private TypeOperatorProviderInterface $typeOperatorProvider,
        private TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COLLECT_FILTER_CHOICES_FOR_LIST_FIELD_TYPE => ['onTypeListCollect', 0],
            // Must run before the core select handler (priority 200), which does
            // not know the company_segments field type.
            LeadEvents::ADJUST_FILTER_FORM_TYPE_FOR_FIELD => [
                ['onSegmentFilterFormHandleCompanySegments', 300],
            ],
            CompanySegmentFiltersChoicesEvent::class => [
                ['onCompanySegmentFiltersAddStaticFields', 0],
            ],
            LeadEvents::LIST_FILTERS_CHOICES_ON_GENERATE => [
                ['onLeadSegmentFiltersAddCompanySegments', 0],
            ],
        ];
    }

    public function onTypeListCollect(ListFieldChoicesEvent $event): void
    {
        $event->setChoicesForFieldAlias(CompanySegmentModel::PROPERTIES_FIELD, $this->getCompanySegmentChoices());
    }

    /**
     * Renders the company_segments filter as (multi)select. Mirrors the core
     * onSegmentFilterFormHandleSelect logic and stops propagation so the core
     * default handler is never reached.
     */
    public function onSegmentFilterFormHandleCompanySegments(FormAdjustmentEvent $event): void
    {
        if (!$event->fieldTypeIsOneOf(CompanySegmentModel::PROPERTIES_FIELD)) {
            return;
        }

        $mustBeText = $event->operatorIsOneOf(OperatorOptions::REGEXP, OperatorOptions::NOT_REGEXP, OperatorOptions::STARTS_WITH, OperatorOptions::ENDS_WITH, OperatorOptions::CONTAINS, OperatorOptions::LIKE, OperatorOptions::NOT_LIKE);

        if ($mustBeText) {
            return;
        }

        $form     = $event->getForm();
        $data     = $form->getData();
        $multiple = $event->operatorIsOneOf(
            OperatorOptions::INCLUDING_ANY,
            OperatorOptions::EXCLUDING_ANY,
            OperatorOptions::INCLUDING_ALL,
            OperatorOptions::EXCLUDING_ALL,
        );

        $filter = $data['filter'] ?? '';

        // Conversion between select and multiselect values.
        if ($multiple) {
            if (!isset($data['filter'])) {
                $filter = [];
            } elseif (!is_array($data['filter'])) {
                $filter = [$data['filter']];
            }
        }

        $form->add(
            'filter',
            ChoiceType::class,
            [
                'label'                     => false,
                'attr'                      => ['class' => 'form-control'],
                'data'                      => $filter,
                'choices'                   => $event->getFieldChoices(),
                'multiple'                  => $multiple,
                'choice_translation_domain' => false,
                'disabled'                  => $event->filterShouldBeDisabled(),
                'constraints'               => $event->filterShouldBeDisabled() ? [] : [new NotBlank(message: 'mautic.core.value.required')],
            ]
        );
        $event->stopPropagation();
    }

    public function onCompanySegmentFiltersAddStaticFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        $this->addIncludeExcludeOperatorsToTextFilters($event);

        // Static company fields (date_added, date_modified)
        $staticFields = [
            'date_added' => [
                'label'      => $this->translator->trans('mautic.core.date.added'),
                'properties' => ['type' => 'date'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('default'),
                'object'     => 'company',
            ],
            'date_modified' => [
                'label'      => $this->translator->trans('mautic.lead.list.filter.date_modified'),
                'properties' => ['type' => 'datetime'],
                'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('default'),
                'object'     => 'company',
            ],
        ];

        foreach ($staticFields as $alias => $fieldOptions) {
            $fieldOptions['iconClass'] = $this->getSegmentFilterIcon($alias);
            $event->addChoice('company', $alias, $fieldOptions);
        }

        $companySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
            'properties' => [
                'type' => CompanySegmentModel::PROPERTIES_FIELD,
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $event->getSearch()),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
        ];
        $companySegmentFieldOptions['iconClass'] = $this->getSegmentFilterIcon('leadlist');
        $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, CompanySegmentModel::PROPERTIES_FIELD, $companySegmentFieldOptions);

        $leadSegmentMembership = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lead_lists'),
            'properties' => [
                'type' => 'leadlist',
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', 'leadlist'),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
        ];
        $leadSegmentMembership['iconClass'] = $this->getSegmentFilterIcon('leadlist');
        $event->addChoice('any_companycontact', 'contactsegmentmembership', $leadSegmentMembership);
    }

    public function onLeadSegmentFiltersAddCompanySegments(LeadListFiltersChoicesEvent $event): void
    {
        // Only add the company_segments filter - other company fields are already handled by Core
        $companySegmentFieldOptions = [
            'label'      => $this->translator->trans('mautic.company_segments.filter.lists'),
            'properties' => [
                'type' => CompanySegmentModel::PROPERTIES_FIELD,
                'list' => $this->fieldChoicesProvider->getChoicesForField('multiselect', CompanySegmentModel::PROPERTIES_FIELD, $event->getSearch()),
            ],
            'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType('multiselect'),
            'object'     => 'company',
            'iconClass'  => $this->getSegmentFilterIcon('leadlist'),
        ];
        $event->addChoice(CompanySegmentModel::PROPERTIES_FIELD, CompanySegmentModel::PROPERTIES_FIELD, $companySegmentFieldOptions);
    }

    /**
     * @param array<int,string> $groupAllow
     */
    private function addIncludeExcludeOperatorsToTextFilters(CompanySegmentFiltersChoicesEvent $event, array $groupAllow = []): void
    {
        $choices = $event->getChoices();

        $normalized = [];

        foreach ($choices as $group => $groups) {
            if (!is_string($group) || !is_array($groups)) {
                continue;
            }

            foreach ($groups as $alias => $choice) {
                if (!is_string($alias) || !is_array($choice)) {
                    continue;
                }

                $normalized[$group][$alias] = $choice;
            }
        }

        foreach ($normalized as $group => $groups) {
            if ([] !== $groupAllow && !in_array($group, $groupAllow, true)) {
                continue;
            }

            foreach ($groups as $alias => $choice) {
                $type = null;
                if (isset($choice['properties']) && is_array($choice['properties'])) {
                    $type = $choice['properties']['type'] ?? null;
                }

                if ('text' === $type) {
                    $normalized[$group][$alias]['operators'] = $this->typeOperatorProvider->getOperatorsIncluding([
                        OperatorOptions::EQUAL_TO,
                        OperatorOptions::NOT_EQUAL_TO,
                        OperatorOptions::EMPTY,
                        OperatorOptions::NOT_EMPTY,
                        OperatorOptions::LIKE,
                        OperatorOptions::NOT_LIKE,
                        OperatorOptions::REGEXP,
                        OperatorOptions::NOT_REGEXP,
                        OperatorOptions::INCLUDING_ANY,
                        OperatorOptions::EXCLUDING_ANY,
                        OperatorOptions::STARTS_WITH,
                        OperatorOptions::ENDS_WITH,
                        OperatorOptions::CONTAINS,
                    ]);
                }
            }
        }

        $event->setChoices($normalized);
    }

    /**
     * @return mixed[]
     */
    private function getCompanySegmentChoices(): array
    {
        $choices = [];

        foreach ($this->companySegmentModel->getCompanySegments() as $segment) {
            $choices[$segment['name']] = $segment['id'];
        }

        return $choices;
    }
}
