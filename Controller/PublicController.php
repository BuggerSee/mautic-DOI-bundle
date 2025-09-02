<?php

namespace MauticPlugin\MauticDoiBundle\Controller;

use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfigRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\MauticDoiBundle\Model\FormDoiSubmissionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends AbstractController
{
    public function __construct(
        private FormDoiSubmissionRepository $submissionRepository,
        private FormDoiConfigRepository $configRepository,
        private FormDoiSubmissionManager $submissionManager
    ) {
    }

    public function verifyEmailAction(string $hash): Response
    {
        $submission = $this->submissionRepository->findOneBy(['hash' => $hash]);

        if (!$submission instanceof FormDoiSubmission) {
            return $this->createErrorResponse();
        }

        if ($submission->isConfirmed()) {
            return $this->createSuccessResponse($submission);
        }

        if (!$submission->isPending()) {
            return $this->createErrorResponse();
        }

        $submission->confirm();
        $this->submissionManager->save($submission);

        return $this->createSuccessResponse($submission);
    }

    private function createSuccessResponse(FormDoiSubmission $submission): Response
    {
        $config = $this->configRepository->findOneBy(['form' => $submission->getForm()]);

        if ($config && $config->getSuccessRedirectUrl()) {
            return new RedirectResponse($config->getSuccessRedirectUrl());
        }

        return new Response('Email verified successfully', Response::HTTP_OK);
    }

    private function createErrorResponse(): Response
    {
        return new Response('Something went wrong! Email verification unsuccessful.', Response::HTTP_BAD_REQUEST);
    }
}
