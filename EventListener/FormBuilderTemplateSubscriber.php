<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomTemplateEvent;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Integration\Config;
use MauticPlugin\MauticDoiBundle\Model\FormDoiActionManager;
use MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormBuilderTemplateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private FormDoiActionManager $formDoiActionManager,
        private FormDoiActionSessionManager $formDoiActionSessionManager,
        private Config $pluginConfig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_TEMPLATE => ['onTemplateRender', 0],
        ];
    }

    public function onTemplateRender(CustomTemplateEvent $event): void
    {
        if ($this->pluginConfig->isPublished() && '@MauticForm/Builder/index.html.twig' === $event->getTemplate()) {
            $vars = $event->getVars();
            /** @var Form $form */
            $form = $vars['activeForm'];

            if ($form->getId()) {
                $formDoiActions = $this->formDoiActionManager->getFormDoiActions($form);
                $this->formDoiActionSessionManager->loadActionsIntoSession($form->getId(), $formDoiActions);
            } else {
                $mauticForm             = $event->getRequest()->request->all()['mauticform'] ?? null;
                $sessionId              = $mauticForm['sessionId'] ?? '';
                $formDoiActions         = $this->formDoiActionSessionManager->getActionsFromSession($sessionId);
            }
            $vars['formDoiActions'] = $formDoiActions;
            $event->setVars($vars);

            $event->setTemplate('@MauticDoi/Builder/index.html.twig');
        }
    }
}
