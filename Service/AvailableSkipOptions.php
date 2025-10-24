<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Mautic\CoreBundle\Form\DataTransformer\SortableListTransformer;
use Mautic\FormBundle\Entity\Field;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;

class AvailableSkipOptions
{
    private const EXCLUDED_FORM_FIELDS = ['button', 'captcha'];

    public function __construct(
        private readonly ListModel $listModel,
        private readonly TypeOperatorProviderInterface $typeOperatorProvider,
        private readonly FieldChoicesProviderInterface $fieldChoicesProvider,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAvailableSkipOptions(?Form $form = null, string $search = ''): array
    {
        $choices = $this->listModel->getChoiceFields($search);

        if (null === $form) {
            return $choices;
        }

        foreach ($form->getFields() as $field) {
            if (!$field instanceof Field) {
                continue;
            }

            $entry = $this->createFieldEntry($field);

            if (null !== $entry) {
                $choices['form'][$field->getAlias()] = $entry;
            }
        }

        return $choices;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function createFieldEntry(Field $field): ?array
    {
        $alias = $field->getAlias();
        $type  = $field->getType();

        // Skip fields without alias or button fields
        if ('' === $alias || in_array($type, self::EXCLUDED_FORM_FIELDS, true)) {
            return null;
        }

        $entryType = (in_array($type, ['checkboxgrp', 'radiogrp'], true)) ? 'select' : $type;

        $entry = [
            'label'      => $field->getLabel(),
            'properties' => [
                'type' => $entryType,
            ],
            'object'     => 'form',
        ];

        // Add specific properties based on a field type
        $this->addFieldTypeSpecificProperties($entry, $field);

        $entry['operators'] = $this->typeOperatorProvider->getOperatorsForFieldType($entryType);

        return $entry;
    }

    /**
     * Adds type-specific properties to the field entry.
     *
     * @param array<string, mixed> $entry The field entry to modify
     */
    private function addFieldTypeSpecificProperties(array &$entry, Field $field): void
    {
        $type = $field->getType();
        if ('country' === $type) {
            $entry['properties']['list'] = $this->fieldChoicesProvider->getChoicesForField('country', $field->getAlias());
        }

        if ('select' === $type) {
            $transformer                 = new SortableListTransformer(withLabels: true, useKeyValuePairs: true);
            $fieldProperties             = $field->getProperties();
            $entry['properties']['list'] = $transformer->reverseTransform($fieldProperties['list'] ?? []);
        }

        if (in_array($type, ['checkboxgrp', 'radiogrp'])) {
            $transformer                 = new SortableListTransformer(withLabels: true, useKeyValuePairs: true);
            $fieldProperties             = $field->getProperties();
            $entry['properties']['list'] = $transformer->reverseTransform($fieldProperties['optionlist'] ?? []);
        }
    }
}
