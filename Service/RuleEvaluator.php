<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerDoiBundle\Service;

use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiConfig;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\LeuchtfeuerDoiBundle\Entity\FormDoiSubmissionRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service to evaluate skip rules for DOI verification.
 */
class RuleEvaluator
{
    private const COOKIE_NAME = 'mautic_doi_receipt';

    public function __construct(
        private FormDoiSubmissionRepository $submissionRepository,
        private RequestStack $requestStack
    ) {
    }

    /**
     * Evaluates if DOI verification should be skipped based on browser cookie.
     *
     * @param FormDoiConfig $config The DOI configuration for the form
     * @param string        $email  The email address being submitted
     *
     * @return bool True if verification should be skipped, false otherwise
     */
    public function shouldSkipBasedOnCookie(FormDoiConfig $config, string $email): bool
    {
        if (!$config->isSkipOnCookie()) {
            return false;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        $browserProofToken = $request->cookies->get(self::COOKIE_NAME);
        if (!$browserProofToken) {
            return false;
        }

        $submission = $this->submissionRepository->findOneBy(['browserProofToken' => $browserProofToken]);
        if (!$submission instanceof FormDoiSubmission) {
            return false;
        }

        if (!$submission->isConfirmed()) {
            return false;
        }

        // Validate the email address matches
        if ($submission->getEmail() !== $email) {
            return false;
        }

        return true;
    }
}
