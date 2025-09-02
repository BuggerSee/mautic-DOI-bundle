<?php

namespace MauticPlugin\MauticDoiBundle\Controller;

use Mautic\FormBundle\Entity\Form;
use Mautic\FormBundle\Entity\FormRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiConfigRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\MauticDoiBundle\Model\FormDoiSubmissionManager;
use MauticPlugin\MauticDoiBundle\Service\DoiTokenParser;
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
    ) {
    }

    public function verifyEmailAction(string $token): Response
    {
        $decodedData = $this->doiTokenParser->decode($token);

        if (!$decodedData) {
            return $this->createErrorResponse();
        }

        [$formId, $hash] = $decodedData;

        $form = $this->formRepository->find($formId);
        $submission = $this->submissionRepository->findOneBy(['hash' => $hash]);

        if (!$form instanceof Form) {
            return $this->createErrorResponse();
        }

        if (!$submission instanceof FormDoiSubmission) {
            return $this->createErrorResponse($form);
        }

        if ($submission->isConfirmed()) {
            return $this->createSuccessResponse($submission);
        }

        if (!$submission->isPending()) {
            return $this->createErrorResponse($form);
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
