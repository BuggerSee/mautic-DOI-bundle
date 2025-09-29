<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\MauticDoiBundle\Service\VerificationEmailSender;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubmissionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VerificationEmailSender $verificationEmailSender,
    ) {
    }

    public function onFormSubmit(SubmissionEvent $event): void
    {
        $this->verificationEmailSender->send($event);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_SUBMIT => ['onFormSubmit', 0],
        ];
    }
}
