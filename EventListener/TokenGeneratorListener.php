<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailBuilderEvent;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\PageBundle\Event\UntrackableUrlsEvent;
use Mautic\PageBundle\PageEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TokenGeneratorListener implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private EmailModel $emailModel,
    ) {
    }

    public function onEmailBuild(EmailBuilderEvent $event): void
    {
        $tokens = $this->getCustomTokens();

        if ($event->tokensRequested(array_keys($tokens))) {
            $event->addTokens(
                $event->filterTokens($tokens)
            );
        }
    }

    public function onEmailGenerate(EmailSendEvent $event): void
    {
        $event->addToken('{doi_link}', $this->emailModel->buildUrl('mautic_doi_email_verify_action', ['hash' => uniqid()]));
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
