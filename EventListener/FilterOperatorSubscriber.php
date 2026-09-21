<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Exception\ChoicesNotFoundException;
use Mautic\LeadBundle\Helper\FormFieldHelper;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\SegmentFilterIconTrait;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerAccountBasedMarketingAbmBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides listable published company custom fields as filter choices for the
 * company segment form. Plugin counterpart of the core FilterOperatorSubscriber
 * additions from PR mautic/mautic#16025.
 */
class FilterOperatorSubscriber implements EventSubscriberInterface
{
    use SegmentFilterIconTrait;

    public function __construct(
        private Config $config,
        private LeadFieldRepository $leadFieldRepository,
        private TypeOperatorProviderInterface $typeOperatorProvider,
        private FieldChoicesProviderInterface $fieldChoicesProvider,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentFiltersChoicesEvent::class => [
                ['onCompanySegmentFiltersAddCustomFields', 0],
            ],
        ];
    }

    public function onCompanySegmentFiltersAddCustomFields(CompanySegmentFiltersChoicesEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $companyFields = $this->leadFieldRepository
            ->getListablePublishedFields()
            ->filter(static fn (LeadField $leadField): bool => 'company' === $leadField->getObject());

        $this->addCustomFieldsToFilterChoices($companyFields, $event);
    }

    /**
     * @param ArrayCollection<int, LeadField> $fields
     */
    private function addCustomFieldsToFilterChoices(
        ArrayCollection $fields,
        CompanySegmentFiltersChoicesEvent $event,
    ): void {
        $fields->map(function (LeadField $field) use ($event): void {
            $type               = $field->getType();
            $properties         = $field->getProperties();
            $properties['type'] = $type;

            if ('boolean' === $type) {
                $properties['list'] = [
                    $properties['no']  => 0,
                    $properties['yes'] => 1,
                ];
            } elseif (in_array($type, ['select', 'multiselect'], true)) {
                $properties['list'] = FormFieldHelper::parseListForChoices($properties['list'] ?? []);
            } else {
                try {
                    $properties['list'] = $this->fieldChoicesProvider->getChoicesForField($type, $field->getAlias());
                } catch (ChoicesNotFoundException) {
                    // That's fine. Not all fields should have choices.
                }
            }

            $event->addChoice(
                $field->getObject(),
                $field->getAlias(),
                [
                    'label'      => $field->getLabel(),
                    'properties' => $properties,
                    'object'     => $field->getObject(),
                    'operators'  => $this->typeOperatorProvider->getOperatorsForFieldType($type),
                    'iconClass'  => $this->getSegmentFilterIcon($field->getAlias()),
                ]
            );
        });
    }
}
