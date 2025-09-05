<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Helper\MailHelper;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Model\DoiConfigManager;
use Psr\Log\LoggerInterface;

class FollowUpSender
{
    public function __construct(
        private EmailModel $emailModel,
        private DoiConfigManager $doiConfigManager,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private LeadRepository $leadRepository,
        private DoiTokenParser $doiTokenParser
    ) {
    }

    public function send(FormDoiSubmission $submission): bool
    {
        if ($submission->hasFollowupSent() || !$submission->isPending() || null !== $submission->getDateConfirmed()) {
            return false;
        }

        $form   = $submission->getForm();
        $config = $this->doiConfigManager->getFormDoiConfig($form);

        if (!$config || !$config->isEnabled()) {
            return false;
        }

        $emailEntity = $config->getFollowUpEmail();
        if (!$emailEntity || !$emailEntity->isPublished()) {
            return false;
        }

        $lead = $submission->getLead();
        if (!$lead || !$lead->getEmail()) {
            return false;
        }

        $encodedToken = $this->doiTokenParser->encode($form->getId(), $submission->getHash());
        $doiLink      = $this->emailModel->buildUrl('mautic_doi_email_verify_action', [
            'token' => $encodedToken,
        ]);
        $tokens = ['{doi_link}' => $doiLink];

        $fields = $lead->getFields();
        if (empty($fields)) {
            $this->hydrateCustomFieldData($lead);
        }
        $contactFields = $lead->getProfileFields();

        $result = $this->emailModel->sendEmail($emailEntity, $contactFields, [
            'source'        => ['form', $form->getId()],
            'tokens'        => $tokens,
            'return_errors' => true,
            'ignoreDNC'     => true,
            'email_type'    => MailHelper::EMAIL_TYPE_TRANSACTIONAL,
        ]);

        if (true !== $result) {
            $this->logger->error('DOI follow-up send failed', [
                'submission_id' => $submission->getId(),
                'email'         => $submission->getEmail(),
                'errors'        => $result,
            ]);

            return false;
        }

        $submission->markFollowupSent();
        $this->em->persist($submission);
        $this->em->flush();

        return true;
    }

    private function hydrateCustomFieldData(Lead $lead = null): void
    {
        if (null === $lead) {
            return;
        }

        // Hydrate fields with custom field data
        $fields = $this->leadRepository->getFieldValues($lead->getId());
        $lead->setFields($fields);
    }
}
