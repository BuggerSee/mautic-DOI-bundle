<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use Mautic\FormBundle\Event\SubmissionEvent;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class DoiSkippedSubmitActionHandler
{
    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Process the custom skip action and set the appropriate response.
     */
    public function processSkipAction(SubmissionEvent $event, FormDoiConfig $doiConfig): void
    {
        $skipAction   = $doiConfig->getSkipPostAction();
        $skipProperty = $doiConfig->getSkipPostActionProperty();

        if (empty($skipAction)) {
            return;
        }

        // Determine request mode (AJAX or messenger)
        $request        = $event->getRequest();
        $post           = $request->request->all()['mauticform'] ?? [];
        $isAjax         = null !== $request->query->get('ajax');
        $messengerMode  = !empty($post['messenger']);
        $asArrayPayload = $isAjax || $messengerMode;

        // Replace tokens first
        $processedProperty = $this->replaceTokens((string) $skipProperty, $event);

        $response = match ($skipAction) {
            'redirect' => $this->createRedirectResponse($processedProperty),
            'message'  => $this->createMessageResponse($processedProperty, $asArrayPayload),
            'return'   => $this->createReturnResponse($processedProperty, $event),
            default    => null,
        };

        if (null !== $response) {
            // Let Mautic handle this response
            $event->setPostSubmitResponse($response);
            // Stop propagation so the controller knows a custom response is set
            $event->stopPropagation();
        }
    }

    private function createRedirectResponse(?string $url): ?Response
    {
        if (empty($url)) {
            return null;
        }

        return new RedirectResponse($url);
    }

    /**
     * Return array payload in AJAX/messenger mode to override default successMessage,
     * otherwise return a Symfony Response for normal requests.
     *
     * @return Response|array<string, array<string>>
     */
    private function createMessageResponse(?string $message, bool $asArrayPayload): Response|array
    {
        if (empty($message)) {
            $message = $this->translator->trans('leuchtfeuer.doi.verification_skipped.default_message');
        }

        if ($asArrayPayload) {
            // This will overwrite the controller's default successMessage via array_merge
            return ['successMessage' => [$message]];
        }

        return new Response($message);
    }

    /**
     * Creates a redirect response back to the referring page, mirroring Mautic's 'return' action.
     */
    private function createReturnResponse(?string $message, SubmissionEvent $event): Response
    {
        $request = $event->getRequest();
        $post    = $request->request->all()['mauticform'] ?? [];
        $server  = $request->server->all();

        $returnUrl = $post['return'] ?? $server['HTTP_REFERER'] ?? null;

        if (null === $returnUrl) {
            // Fall back to a message if no referrer/return param exists
            // Note: In AJAX/messenger mode, createMessageResponse will turn this into an array payload,
            // but here we don't know the mode, so return a Response; processSkipAction decides the type.
            return $this->createMessageResponse($message, false);
        }

        if (!empty($message)) {
            $query     = str_contains($returnUrl, '?') ? '&' : '?';
            $returnUrl .= $query.'mauticMessage='.rawurlencode($message);
        }

        return new RedirectResponse($returnUrl);
    }

    /**
     * Replaces Mautic tokens in a given string (e.g., URL or message).
     */
    private function replaceTokens(string $string, SubmissionEvent $event): string
    {
        $tokens = $event->getTokens();

        foreach ($tokens as $token => $value) {
            $string = str_replace(sprintf('{%s}', $token), (string) $value, $string);
        }

        return $string;
    }
}
