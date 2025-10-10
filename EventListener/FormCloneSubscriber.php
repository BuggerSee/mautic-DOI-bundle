<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomTemplateEvent;
use Mautic\FormBundle\Model\FormModel;
use MauticPlugin\LeuchtfeuerDoiBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerDoiBundle\Model\FormDoiActionManager;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\FormDoiActionSessionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormCloneSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private FormModel $formModel,
        private FormDoiActionManager $formDoiActionManager,
        private FormDoiActionSessionManager $formDoiActionSessionManager,
        private Config $pluginConfig
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_TEMPLATE => ['onFormClone', 10],
        ];
    }

    public function onFormClone(CustomTemplateEvent $event): void
    {
        if ('@MauticForm/Builder/index.html.twig' !== $event->getTemplate() || false === $this->pluginConfig->isPublished()) {
            return;
        }

        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest) {
            return;
        }

        if ('clone' === $mainRequest->attributes->get('objectAction') && 'mautic_form_action' === $mainRequest->attributes->get('_route')) {
            $sourceFormId = (int) $mainRequest->attributes->get('objectId');
            if (!$sourceFormId) {
                return;
            }

            // extract the sessionId from the form vars
            $templateVars = $event->getVars();
            $form         = $templateVars['form'];
            $sessionId    = $form->children['sessionId']->vars['value'] ?? null;

            if (!$sessionId) {
                return;
            }

            $sourceForm = $this->formModel->getEntity($sourceFormId);
            if (!$sourceForm) {
                return;
            }

            $doiActions = $this->formDoiActionManager->getFormDoiActions($sourceForm);
            foreach ($doiActions as &$action) {
                $action['id']   = 'new'.hash('sha1', uniqid((string) mt_rand()));
                $action['form'] = null;
            }

            $this->formDoiActionSessionManager->loadActionsIntoSession($sessionId, $doiActions);
        }
    }
}
