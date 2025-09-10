<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Event\UntrackableUrlsEvent;
use Mautic\PageBundle\PageEvents;
use MauticPlugin\MauticDoiBundle\DTO\DoiTokenData;
use MauticPlugin\MauticDoiBundle\Service\DoiHashContext;
use MauticPlugin\MauticDoiBundle\Service\DoiTokenParser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TokenGeneratorListener implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private EmailModel $emailModel,
        private DoiHashContext $doiHashContext,
        private DoiTokenParser $doiTokenParser,
    ) {
    }

    public function onEmailBuild(EmailBuilderEvent $event): void
    {
        $tokens = $this->getCustomTokens();

        if ([] === $tokens) {
            return;
        }

        $tokenKeys = array_keys($tokens);

        // Check if any of our tokens are actually requested
        if (!$event->tokensRequested($tokenKeys)) {
            return;
        }

        $filteredTokens = $event->filterTokens($tokens);
        if ([] !== $filteredTokens) {
            $event->addTokens($filteredTokens);
        }
    }

    public function onEmailGenerate(EmailSendEvent $event): void
    {
        $hash         = $this->doiHashContext->getDoiHash();
        $formId       = $this->doiHashContext->getFormId();
        $tokenData    = new DoiTokenData($formId, $hash);
        $encodedToken = $this->doiTokenParser->encode($tokenData);
        $event->addToken('{doi_link}', $this->emailModel->buildUrl('mautic_doi_email_verify_action', [
            'token' => $encodedToken,
        ]));
    }

    public function addNonTrackableToken(UntrackableUrlsEvent $event): void
    {
        $event->addNonTrackable('{doi_link}');
    }

    /**
     * @return array<string, string>
     */
    private function getCustomTokens(): array
    {
        return [
            '{doi_link}'    => $this->translator->trans('mautic.plugin.doi.email.token.doi_link'),
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_BUILD       => ['onEmailBuild', 0],
            EmailEvents::EMAIL_ON_SEND        => ['onEmailGenerate', 0],
            EmailEvents::EMAIL_ON_DISPLAY     => ['onEmailGenerate', 0],
            PageEvents::REDIRECT_DO_NOT_TRACK => ['addNonTrackableToken', 0],
        ];
    }
}
