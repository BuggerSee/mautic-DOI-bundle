<?php

namespace MauticPlugin\MauticDoiBundle\Mapper;

use Mautic\FormBundle\Entity\Action;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;

class FormDoiActionMapper
{
    public function mapToAction(FormDoiAction $formDoiAction): Action
    {
        $action = new Action();

        $action->setName($formDoiAction->getName());
        $action->setDescription($formDoiAction->getDescription());
        $action->setType($formDoiAction->getType());
        $action->setOrder($formDoiAction->getOrder());
        $action->setProperties($formDoiAction->getProperties());
        $action->setForm($formDoiAction->getForm());

        return $action;
    }
}
