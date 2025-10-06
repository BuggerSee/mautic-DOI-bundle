<?php

declare(strict_types=1);

namespace MauticPlugin\MauticDoiBundle\Command;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use MauticPlugin\MauticDoiBundle\Entity\FormDoiSubmissionRepository;
use MauticPlugin\MauticDoiBundle\Service\FollowUpSender;
use MauticPlugin\MauticDoiBundle\Integration\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'leuchtfeuer:doi:send-followup', description: 'Send DOI follow-up emails')]
class SendFollowUpCommand extends Command
{
    private const DEFAULT_BATCH_SIZE = 50;

    public function __construct(
        private FormDoiSubmissionRepository $submissionRepo,
        private FollowUpSender $followUpSender,
        private CoreParametersHelper $parameters,
        private Config $pluginConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', '-l', InputOption::VALUE_REQUIRED, 'Max submissions to process (no limit if not specified)')
            ->addOption(
                'batch',
                '-b',
                InputOption::VALUE_REQUIRED,
                'Number of submissions to process in each batch',
                self::DEFAULT_BATCH_SIZE
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->pluginConfig->isPublished()) {
            $output->writeln('DOI plugin is disabled');
            return Command::FAILURE;
        }

        $limitOption      = $input->getOption('limit');
        $totalLimit       = null !== $limitOption ? (int) $limitOption : null;
        $batchLimit       = (int) $input->getOption('batch');

        $waitHours = (int) $this->parameters->get('doi_followup_wait_time', 24);
        $threshold = new \DateTimeImmutable(sprintf('-%d hours', $waitHours), new \DateTimeZone('UTC'));

        $processed = 0;
        $sent      = 0;

        // Process submissions in batches until the total limit is reached or no more submissions
        do {
            $remainingBatch = null !== $totalLimit ? min($batchLimit, $totalLimit - $processed) : $batchLimit;
            $submissions    = $this->submissionRepo->findPendingDueForFollowup($threshold, $remainingBatch);

            $batchProcessed = 0;
            $batchSent      = 0;

            foreach ($submissions as $submission) {
                ++$batchProcessed;
                if ($this->followUpSender->send($submission)) {
                    ++$batchSent;
                }
            }

            $processed += $batchProcessed;
            $sent += $batchSent;

            // Continue only if submissions were processed and limit not reached
        } while ($batchProcessed > 0 && (null === $totalLimit || $processed < $totalLimit));

        $output->writeln(sprintf('Processed: %d | Sent: %d', $processed, $sent));

        return Command::SUCCESS;
    }
}
