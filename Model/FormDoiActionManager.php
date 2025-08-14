<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use Mautic\FormBundle\Entity\Form;

class FormDoiActionManager
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getFormDoiActions(Form $form): array
    {
        $actions = $this->entityManager->getRepository(FormDoiAction::class)
            ->findBy(['form' => $form], ['order' => 'ASC']);

        return array_map(fn(FormDoiAction $action) => $action->convertToArray(), $actions);
    }

    public function saveActions(Form $form, array $actions): void
    {
        // Remove existing actions
        $this->deleteExistingActions($form);

        // Save new actions
        foreach ($actions as $action) {
            $actionDoi = new FormDoiAction();
            $actionDoi->setForm($form);
            $actionDoi->setName($action['name']);
            $actionDoi->setDescription($action['description']);
            $actionDoi->setType($action['type']);
            $actionDoi->setProperties($action['properties']);
            $actionDoi->setOrder($action['order'] ?? 0);

            $this->entityManager->persist($actionDoi);
        }

        $this->entityManager->flush();
    }

    private function deleteExistingActions(Form $form): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(FormDoiAction::class, 'a')
            ->where('a.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->execute();
    }
}