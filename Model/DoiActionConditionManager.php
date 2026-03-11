<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiAction;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiActionCondition;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiActionConditionRepository;

class DoiActionConditionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormDoiActionConditionRepository $repository
    ) {
    }

    /**
     * @return array<int, FormDoiActionCondition>
     */
    public function getFormActionConditions(Form $form): array
    {
        return $this->repository->findByFormId($form->getId());
    }

    /**
     * @return array<int, FormDoiActionCondition>
     */
    public function getFormActionConditionsByFormId(int $formId): array
    {
        return $this->repository->findByFormId($formId);
    }

    /**
     * Save action conditions for a form.
     *
     * @param array<string|int, FormDoiAction>    $actionsMap           Map of [id => action] for matching new action IDs
     * @param array<string, array<string, mixed>> $actionConditionsData
     */
    public function saveActionConditions(Form $form, array $actionsMap, array $actionConditionsData): void
    {
        $existingConditions = $this->getFormActionConditions($form);
        $processedActionIds = [];

        foreach ($actionConditionsData as $data) {
            $actionId = $data['actionId'] ?? null;

            if (!$actionId) {
                continue;
            }

            $action = $actionsMap[$actionId] ?? null;
            // If action passed in request does not exist on the form object, skip it.
            if (!$action) {
                continue;
            }
            $realActionId = $action->getId();

            if (!$realActionId) {
                continue;
            }

            $processedActionIds[] = $realActionId;
            $conditions           = $data['conditions'] ?? null;

            if ($conditions) {
                // Filter conditions to remove references to deleted form fields
                $conditions = $this->filterConditions($form, $conditions);
            }

            if ($conditions) {
                // Create or update condition. key logic by realActionId
                $condition = $existingConditions[$realActionId] ?? new FormDoiActionCondition();
                $condition->setAction($action);
                $condition->setConditions($conditions);

                $this->entityManager->persist($condition);
            } elseif (isset($existingConditions[$realActionId])) {
                // Remove condition if conditions are empty
                $this->entityManager->remove($existingConditions[$realActionId]);
            }
        }

        // Remove conditions for actions that no longer exist or have no conditions
        foreach ($existingConditions as $actionId => $condition) {
            if (!in_array($actionId, $processedActionIds, true)) {
                $this->entityManager->remove($condition);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Filter conditions to remove references to form fields that no longer exist.
     *
     * @param array<int, array<string, mixed>> $conditions
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function filterConditions(Form $form, array $conditions): ?array
    {
        // Get all current field aliases from the form
        $fieldAliases = $form->getFieldAliases();

        $filtered = array_values(array_filter(
            $conditions,
            function (array $condition) use ($fieldAliases): bool {
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
