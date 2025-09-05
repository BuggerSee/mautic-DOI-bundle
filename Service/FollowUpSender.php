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
        private DoiHashContext $doiHashContext,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private LeadRepository $leadRepository,
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

        // Ensure DOI tokens resolve to this submission + form
        $this->doiHashContext
            ->setDoiHash($submission->getHash())
            ->setFormId($form->getId());

        $fields = $lead->getFields();
        if (empty($fields)) {
            $this->hydrateCustomFieldData($lead);
        }
        $contactFields = $lead->getProfileFields();

        $result = $this->emailModel->sendEmail($emailEntity, $contactFields, [
            'source'        => ['form', $form->getId()],
            'tokens'        => [],
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
