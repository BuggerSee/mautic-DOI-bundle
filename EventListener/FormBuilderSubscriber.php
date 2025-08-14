<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\FormBundle\Event\FormEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\MauticDoiBundle\Model\FormDoiActionManager;
use MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FormBuilderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FormDoiActionManager $formDoiActionManager,
        private FormDoiActionSessionManager $formDoiActionSessionManager
    ){
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_POST_SAVE => ['onFormPostSave', 0],
        ];
    }

    public function onFormPostSave(FormEvent $event): void
    {
        $form = $event->getForm();
        $formId = $form->getId();
        $actions = $this->formDoiActionSessionManager->getActionsFromSession($formId);
        if (!empty($actions)) {
            $this->formDoiActionManager->saveActions($form, $actions);
        }
    }
}