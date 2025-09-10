<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\EventListener;

use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use MauticPlugin\MauticDoiBundle\Model\FormDoiSubmissionManager;
use MauticPlugin\MauticDoiBundle\Service\DoiHashContext;
use MauticPlugin\MauticDoiBundle\Service\DoiHashGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FormSubmissionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private DoiConfigManager $doiConfigManager,
        private EmailModel $emailModel,
        private DoiHashGenerator $doiHashGenerator,
        private DoiHashContext $doiHashContext,
        private FormDoiSubmissionManager $doiSubmissionManager,
    ) {
    }

    public function onFormSubmit(SubmissionEvent $event): void
    {
        $formSubmission = $event->getSubmission();
        $form           = $formSubmission->getForm();
        $doiConfig      = $this->doiConfigManager->getFormDoiConfig($form);
        $contact        = $event->getLead();

        if (null === $contact || null === $doiConfig || !$doiConfig->isEnabled()) {
            return;
        }

        $doiSubmission = $this->createDoiSubmission($event, $form, $contact);
        $this->doiSubmissionManager->save($doiSubmission);
        $this->doiHashContext
            ->setDoiHash($doiSubmission->getHash())
            ->setFormId($form->getId());
        $this->sendVerificationEmail($event, $form, $contact, $doiConfig);
    }

    private function createDoiSubmission(SubmissionEvent $event, Form $form, Lead $contact): FormDoiSubmission
    {
        $formSubmission = $event->getSubmission();
        $hash           = $this->doiHashGenerator->generate($formSubmission->getId(), $contact->getEmail());

        $doiSubmission = new FormDoiSubmission();
        $doiSubmission
            ->setFormSubmission($formSubmission)
            ->setForm($form)
            ->setLead($contact)
            ->setEmail($contact->getEmail())
            ->setHash($hash)
            ->setDateCreated(new \DateTime());

        return $doiSubmission;
    }

    private function sendVerificationEmail(SubmissionEvent $event, Form $form, Lead $contact, FormDoiConfig $doiConfig): void
    {
        $verificationEmail = $doiConfig->getVerificationEmail();

        if (null === $verificationEmail || !$verificationEmail->isPublished()) {
            return;
        }

        $contactFields = $contact->getProfileFields();
        $this->emailModel->sendEmail($verificationEmail, $contactFields, [
            'source'        => ['form', $form->getId()],
            'tokens'        => $event->getTokens(),
            'return_errors' => true,
            'ignoreDNC'     => true,
            'email_type'    => MailHelper::EMAIL_TYPE_TRANSACTIONAL,
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_SUBMIT => ['onFormSubmit', 0],
        ];
    }
}
