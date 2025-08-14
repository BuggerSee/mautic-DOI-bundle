<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiActionRepository;

class FormDoiActionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FormDoiActionRepository $formDoiActionRepository
    ) {}

    public function getFormDoiActions(Form $form): array
    {
        $actions = $this->formDoiActionRepository->findBy(['form' => $form], ['order' => 'ASC']);
        return array_map(fn(FormDoiAction $action) => $action->convertToArray(), $actions);
    }

    public function saveActions(Form $form, array $newActions): void
    {
        $existingActions = $this->formDoiActionRepository->findBy(['form' => $form]);
        $existingActionMap = [];
        foreach ($existingActions as $action) {
            $existingActionMap[$action->getId()] = $action;
        }

        foreach ($newActions as $newAction) {
            if (isset($newAction['id']) && isset($existingActionMap[$newAction['id']])) {
                // Update existing action
                $action = $existingActionMap[$newAction['id']];
                unset($existingActionMap[$newAction['id']]);
            } else {
                // Create new action
                $action = new FormDoiAction();
                $action->setForm($form);
            }

            $action->setName($newAction['name']);
            $action->setDescription($newAction['description']);
            $action->setType($newAction['type']);
            $action->setProperties($newAction['properties']);
            $action->setOrder($newAction['order'] ?? 0);

            $this->entityManager->persist($action);
        }

        // Remove actions that are no longer present
        foreach ($existingActionMap as $actionToRemove) {
            $this->entityManager->remove($actionToRemove);
        }

        $this->entityManager->flush();
    }
}