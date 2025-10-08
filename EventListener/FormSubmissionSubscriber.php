<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\LeuchtfeuerDoiBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\VerificationEmailSender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubmissionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VerificationEmailSender $verificationEmailSender,
        private Config $pluginConfig
    ) {
    }

    public function onFormSubmit(SubmissionEvent $event): void
    {
        if (!$this->pluginConfig->isPublished()) {
            return;
        }
        $this->verificationEmailSender->send($event);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_SUBMIT => ['onFormSubmit', 0],
        ];
    }
}
