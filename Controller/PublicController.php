<?php

namespace MauticPlugin\MauticDoiBundle\Controller;

use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\FormRepository;
use Mautic\LeadBundle\Tracker\ContactTracker;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfigRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\MauticDoiBundle\Model\FormDoiSubmissionManager;
use MauticPlugin\MauticDoiBundle\Service\DoiActionsDispatcher;
use MauticPlugin\MauticDoiBundle\Service\DoiTokenParser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class PublicController extends AbstractController
{
    public function __construct(
        private FormRepository $formRepository,
        private FormDoiSubmissionRepository $submissionRepository,
        private FormDoiConfigRepository $configRepository,
        private FormDoiSubmissionManager $submissionManager,
        private DoiTokenParser $doiTokenParser,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private DoiActionsDispatcher $doiActionsDispatcher,
        private ContactTracker $contactTracker
    ) {
    }

    public function verifyEmailAction(string $token): Response
    {
        $decodedData = $this->doiTokenParser->decode($token);

        if (!$decodedData) {
            $this->logger->error('Failed to decode DOI token', ['token' => $token]);

            return $this->createErrorResponse();
        }

        [$formId, $hash] = $decodedData;

        $form       = $this->formRepository->find($formId);
        $submission = $this->submissionRepository->findOneBy(['hash' => $hash]);

        if (!$form instanceof Form) {
            $this->logger->error('Form not found for DOI verification', ['formId' => $formId]);

            return $this->createErrorResponse();
        }

        if (!$submission instanceof FormDoiSubmission) {
            $this->logger->error('DOI submission not found', ['hash' => $hash, 'formId' => $formId]);

            return $this->createErrorResponse($form);
        }

        $this->contactTracker->setTrackedContact($submission->getLead());

        if ($submission->isConfirmed()) {
            return $this->createSuccessResponse($submission);
        }

        if (!$submission->isPending()) {
            $this->logger->error('DOI submission is not pending', ['hash' => $hash, 'formId' => $formId, 'status' => $submission->getStatus()]);

            return $this->createErrorResponse($form);
        }

        $submission->confirm();
        $this->submissionManager->save($submission);
        $this->doiActionsDispatcher->executePostEmailVerificationActions($submission);

        return $this->createSuccessResponse($submission);
    }

    private function createSuccessResponse(FormDoiSubmission $submission): Response
    {
        $config = $this->configRepository->findOneBy(['form' => $submission->getForm()]);

        if ($config && $config->getSuccessRedirectUrl()) {
            return new RedirectResponse($config->getSuccessRedirectUrl());
        }

        return new Response($this->translator->trans('mautic.plugin.doi.verification.success'), Response::HTTP_OK);
    }

    private function createErrorResponse(?Form $form = null): Response
    {
        if ($form) {
            $config = $this->configRepository->findOneBy(['form' => $form]);
            if ($config && $config->getErrorRedirectUrl()) {
                return new RedirectResponse($config->getErrorRedirectUrl());
            }
        }

        return new Response($this->translator->trans('mautic.plugin.doi.verification.error'), Response::HTTP_BAD_REQUEST);
    }
}
