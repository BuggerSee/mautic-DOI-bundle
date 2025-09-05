<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Command;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\MauticDoiBundle\Service\FollowUpSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'leuchtfeuer:doi:send-followup', description: 'Send DOI follow-up emails')]
class SendFollowUpCommand extends Command
{
    public function __construct(
        private FormDoiSubmissionRepository $submissionRepo,
        private FollowUpSender $followUpSender,
        private CoreParametersHelper $parameters
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max submissions to process', 100);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit  = (int) $input->getOption('limit');

        $waitHours = (int) $this->parameters->get('doi_followup_wait_time', 24);
        $threshold = new \DateTimeImmutable(sprintf('-%d hours', $waitHours));

        $submissions = $this->submissionRepo->findPendingDueForFollowup($threshold, $limit);

        $processed = 0;
        $sent      = 0;

        foreach ($submissions as $submission) {
            ++$processed;
            if ($this->followUpSender->send($submission)) {
                ++$sent;
            }
        }

        $output->writeln(sprintf('Processed: %d | Sent: %d', $processed, $sent));

        return Command::SUCCESS;
    }
}
