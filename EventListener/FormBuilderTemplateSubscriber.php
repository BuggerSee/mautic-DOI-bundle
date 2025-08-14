<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomTemplateEvent;
use Mautic\FormBundle\Entity\Form;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiAction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FormBuilderTemplateSubscriber implements EventSubscriberInterface
{

    public function __construct(private EntityManagerInterface $em, private SessionInterface $session)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_TEMPLATE => ['onTemplateRender', 0],
        ];
    }

    public function onTemplateRender(CustomTemplateEvent $event): void
    {
        if ('@MauticForm/Builder/index.html.twig' === $event->getTemplate()) {
            $vars = $event->getVars();
            /** @var Form $form */
            $form = $vars['activeForm'];

            // Fetch FormDoiActions for this form
            $formDoiActions = $this->getFormDoiActions($form);

            // Load actions into session
            $this->loadActionsIntoSession($form->getId(), $formDoiActions);

            // Add FormDoiActions to template variables
            $vars['formDoiActions'] = $formDoiActions;

            $event->setVars($vars);
            $event->setTemplate('@MauticDoi/Builder/index.html.twig');
        }
    }

    private function getFormDoiActions(Form $form): array
    {
        $actions = $this->em->getRepository(FormDoiAction::class)
            ->findBy(['form' => $form], ['order' => 'ASC']);

        // Convert entities to arrays
        return array_map(function (FormDoiAction $action) {
            return [
                'id' => $action->getId(),
                'name' => $action->getName(),
                'description' => $action->getDescription(),
                'type' => $action->getType(),
                'order' => $action->getOrder(),
                'properties' => $action->getProperties(),
            ];
        }, $actions);
    }

    private function loadActionsIntoSession(int $formId, array $actions): void
    {
        $modifiedActions = [];

        foreach ($actions as $action) {
            $id = $action['id'];
            $modifiedActions[$id] = $action;
        }

        $this->session->set('mautic.form.'.$formId.'.actions.doi_verified.modified', $modifiedActions);
    }
}