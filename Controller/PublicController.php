<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Controller;

use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\FormRepository;
use Mautic\LeadBundle\Tracker\ContactTracker;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfigRepository;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\LeuchtfeuerDoiBundle\Model\FormDoiSubmissionManager;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\DoiActionsDispatcher;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\DoiTokenParser;
use MauticPlugin\LeuchtfeuerDoiBundle\Service\RuleEvaluator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    public function verifyEmailAction(string $token, Request $request): Response
    {
        $tokenData = $this->doiTokenParser->decode($token);

        if (null === $tokenData) {
            $this->logger->error('Failed to decode DOI token', ['token' => $token]);

            return $this->createErrorResponse();
        }

        $formId = $tokenData->formId;
        $hash   = $tokenData->hash;

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

        // Generate and store browser proof token
        $browserProofToken = bin2hex(random_bytes(32));
        $submission->setBrowserProofToken($browserProofToken);

        $this->submissionManager->save($submission);
        $this->doiActionsDispatcher->executePostEmailVerificationActions($submission);

        return $this->createSuccessResponseWithCookie($submission, $browserProofToken, $request);
    }

    private function createSuccessResponse(FormDoiSubmission $submission): Response
    {
        $config = $this->configRepository->findOneBy(['form' => $submission->getForm()]);

        if ($config && $config->getSuccessRedirectUrl()) {
            return new RedirectResponse($config->getSuccessRedirectUrl());
        }

        return new Response($this->translator->trans('mautic.plugin.doi.verification.success'), Response::HTTP_OK);
    }

    private function createSuccessResponseWithCookie(FormDoiSubmission $submission, string $browserProofToken, Request $request): Response
    {
        $config = $this->configRepository->findOneBy(['form' => $submission->getForm()]);
        $cookie = Cookie::create(RuleEvaluator::COOKIE_NAME)
            ->withValue($browserProofToken)
            ->withExpires(new \DateTime('+365 days'))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly()
            ->withSameSite(Cookie::SAMESITE_LAX);

        if ($config && $config->getSuccessRedirectUrl()) {
            $response = new RedirectResponse($config->getSuccessRedirectUrl());
            $response->headers->setCookie($cookie);

            return $response;
        }

        $response = new Response($this->translator->trans('mautic.plugin.doi.verification.success'), Response::HTTP_OK);
        $response->headers->setCookie($cookie);

        return $response;
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
