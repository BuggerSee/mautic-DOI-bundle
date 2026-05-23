<?php

namespace MauticPlugin\LeuchtfeuerDoiBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\FormBundle\Entity\Submission;

/**
 * @extends CommonRepository<FormDoiActionExecutionLog>
 */
class FormDoiActionExecutionLogRepository extends CommonRepository
{
    public function logExecution(
        Submission $submission,
        FormDoiSubmission $doiSubmission,
        FormDoiAction $action,
        bool $isExecuted,
        ?string $details = null,
    ): void {
        $log = new FormDoiActionExecutionLog();
        $log->setSubmission($submission);
        $log->setDoiSubmission($doiSubmission);
        $log->setAction($action);
        $log->setIsExecuted($isExecuted);
        $log->setLogDetails($details);
        $this->getEntityManager()->persist($log);
        $this->getEntityManager()->flush();
    }
}
