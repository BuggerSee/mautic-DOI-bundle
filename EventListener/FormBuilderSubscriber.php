<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Event\FormEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use MauticPlugin\MauticDoiBundle\Model\FormDoiActionManager;
use MauticPlugin\MauticDoiBundle\Service\FormDoiActionSessionManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class FormBuilderSubscriber implements EventSubscriberInterface
{
    private const SESSION_ID_KEY = 'sessionId';
    private const DOI_CONFIG_KEY = 'doiConfig';

    public function __construct(
        private FormDoiActionManager $formDoiActionManager,
        private FormDoiActionSessionManager $formDoiActionSessionManager,
        private DoiConfigManager $doiConfigManager,
        private RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_POST_SAVE => ['onFormPostSave', 0],
        ];
    }

    public function onFormPostSave(FormEvent $event): void
    {
        $form       = $event->getForm();
        $request    = $this->requestStack->getCurrentRequest();
        $mauticForm = $request->request->all('mauticform');

        if (empty($mauticForm)) {
            return;
        }

        $sessionId = $mauticForm[self::SESSION_ID_KEY] ?? '';
        $doiConfig = $mauticForm[self::DOI_CONFIG_KEY] ?? [];

        $this->handleSaveDoiActions($form, $sessionId);
        $this->handleSaveDoiConfig($form, $doiConfig);
    }

    private function handleSaveDoiActions(Form $form, string $sessionId): void
    {
        $actions = $this->formDoiActionSessionManager->getActionsFromSession($sessionId);
        $this->formDoiActionManager->saveActions($form, $actions);
    }

    /**
     * @param array<string, mixed> $doiConfigData
     */
    private function handleSaveDoiConfig(Form $form, array $doiConfigData = []): void
    {
        $this->doiConfigManager->saveFormDoiConfig($form, $doiConfigData);
    }
}
