<?php

namespace MauticPlugin\MauticDoiBundle\Service;

use Mautic\FormBundle\Entity\SubmissionRepository;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiActionRepository;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmission;
use MauticPlugin\MauticDoiBundle\Mapper\FormDoiActionMapper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class DoiActionsDispatcher
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private FormDoiActionRepository $formDoiActionRepository,
        private FormDoiActionMapper $formDoiActionMapper,
        private SubmissionRepository $submissionRepository,
    ) {
    }

    public function executePostEmailVerificationActions(FormDoiSubmission $doiSubmission): void
    {
        $form             = $doiSubmission->getForm();
        $formSubmissionId = $doiSubmission->getFormSubmission()->getId();
        $formSubmission   = $this->submissionRepository->getEntity($formSubmissionId); // loads entity with results
        $doiActions       = $this->formDoiActionRepository->findBy(['form' => $form]);

        // Recreate the submission context as much as possible
        $results = $formSubmission->getResults() ?: [];
        $post    = $results; // Use the stored results as POST data

        // Recreate server array with available information
        $server = [
            'HTTP_REFERER' => $formSubmission->getReferer() ?: '',
            /** @phpstan-ignore-next-line IpAddress can be null  */
            'REMOTE_ADDR' => $formSubmission->getIpAddress()?->getIpAddress() ?: '',
        ];

        // Create a minimal request object
        $request = new Request($post, [], [], [], [], $server);

        foreach ($doiActions as $doiAction) {
            $submissionEvent = new SubmissionEvent($formSubmission, $post, $server, $request);
            $submissionEvent->setResults($results);
            $submissionEvent->setContext($doiAction->getType());
            $submissionEvent->setAction($this->formDoiActionMapper->mapToAction($doiAction));

            $this->eventDispatcher->dispatch($submissionEvent, FormEvents::ON_EXECUTE_SUBMIT_ACTION);
        }
    }
}
