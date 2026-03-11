<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Helper;

class ConditionFilterHelper
{
    /**
     * Filter conditions to remove references to form fields that no longer exist.
     *
     * @param array<string>                         $fieldAliases Current field aliases from the form
     * @param array<int, array<string, mixed>>|null $conditions   Conditions to filter
     *
     * @return array<int, array<string, mixed>>|null
     */
    public static function filterByExistingFormFields(array $fieldAliases, ?array $conditions): ?array
    {
        if (empty($conditions)) {
            return null;
        }

        $filtered = array_values(array_filter(
            $conditions,
            static function (array $condition) use ($fieldAliases): bool {
                // Keep conditions that are not based on form fields
                if ('form' !== ($condition['object'] ?? '')) {
                    return true;
                }

                // Keep conditions where the form field still exists
                $fieldAlias = $condition['field'] ?? '';

                return in_array($fieldAlias, $fieldAliases, true);
            }
        ));

        return $filtered ?: null;
    }
}
