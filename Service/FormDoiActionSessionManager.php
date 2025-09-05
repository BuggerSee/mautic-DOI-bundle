<?php

namespace MauticPlugin\MauticDoiBundle\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class FormDoiActionSessionManager
{
    private const SESSION_KEY_PREFIX = 'mautic.form.';
    private const SESSION_KEY_SUFFIX = '.actions.doi_verified';

    public function __construct(private SessionInterface $session)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $actions
     */
    public function loadActionsIntoSession(int|string $formId, array $actions): void
    {
        $modifiedActions = [];

        foreach ($actions as $action) {
            $id                   = $action['id'];
            $modifiedActions[$id] = $action;
        }

        $this->session->set($this->getSessionKey($formId), $modifiedActions);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActionsFromSession(int|string $formId): array
    {
        return $this->session->get($this->getSessionKey($formId), []);
    }

    /**
     * @param array<string, mixed> $updatedAction
     */
    public function updateActionInSession(int|string $formId, int|string $actionId, array $updatedAction): void
    {
        $actions            = $this->getActionsFromSession($formId);
        $actions[$actionId] = $updatedAction;
        $this->loadActionsIntoSession($formId, $actions);
    }

    public function removeActionFromSession(int|string $formId, int|string $actionId): void
    {
        $actions = $this->getActionsFromSession($formId);
        unset($actions[$actionId]);
        $this->loadActionsIntoSession($formId, $actions);
    }

    private function getSessionKey(int|string $formId): string
    {
        return self::SESSION_KEY_PREFIX.$formId.self::SESSION_KEY_SUFFIX;
    }
}
