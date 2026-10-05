<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Command;

use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mautic:email:prerender',
    description: 'Pre-render and cache fully personalized emails for later high-speed sending'
)]
class PrerenderEmailCommand extends Command
{
    public function __construct(
        private PrerenderModel $prerenderModel,
        private EmailModel $emailModel,
        private ListModel $listModel,
        private LeadModel $leadModel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email ID to pre-render')
            ->addOption('segment', null, InputOption::VALUE_OPTIONAL, 'Segment / List ID whose contacts should be pre-rendered')
            ->addOption('contacts', null, InputOption::VALUE_OPTIONAL, 'Comma-separated contact IDs')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Contacts per batch (memory control)', 100)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum contacts to process in this run', 0)
            ->addOption('ttl', null, InputOption::VALUE_OPTIONAL, 'Cache TTL in hours (0 = no expiry)', 72);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailId  = (int) $input->getOption('email');
        $segmentId = $input->getOption('segment') ? (int) $input->getOption('segment') : null;
        $contactsOpt = $input->getOption('contacts');
        $batch    = max(1, (int) $input->getOption('batch'));
        $limit    = (int) $input->getOption('limit');
        $ttlHours = (int) $input->getOption('ttl');

        if ($emailId <= 0) {
                       $io->error('--email is required');

            return Command::FAILURE;
        }

        $email = $this->emailModel->getEntity($emailId);
        if (null === $email) {
            $io->error(sprintf('Email ID %d not found', $emailId));

            return Command::FAILURE;
        }

        $contactIds = [];

        if ($contactsOpt) {
            $contactIds = array_filter(array_map('intval', explode(',', $contactsOpt)));
        } elseif ($segmentId) {
            // Basic approach: load leads belonging to the list.
            // For very large segments you should replace this with a streaming query.
            $list = $this->listModel->getEntity($segmentId);
            if (null === $list) {
                $io->error(sprintf('Segment/List ID %d not found', $segmentId));

                return Command::FAILURE;
            }

            $leads = $this->listModel->getLeadsByList($list, true, false);
            foreach ($leads as $lead) {
                $contactIds[] = (int) $lead->getId();
            }
        } else {
            $io->error('Provide either --segment or --contacts');

            return Command::FAILURE;
        }

        if (empty($contactIds)) {
            $io->warning('No contacts to process');

            return Command::SUCCESS;
        }

        if ($limit > 0) {
            $contactIds = array_slice($contactIds, 0, $limit);
        }

        $expiresAt = null;
        if ($ttlHours > 0) {
            $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d hours', $ttlHours));
        }

        $io->title(sprintf('Pre-rendering email "%s" (ID %d)', $email->getName(), $emailId));
        $io->progressStart(count($contactIds));

        $success = 0;
        $failed  = 0;

        foreach (array_chunk($contactIds, $batch) as $chunk) {
            foreach ($chunk as $contactId) {
                $lead = $this->leadModel->getEntity($contactId);
                if (null === $lead || !$lead->getEmail()) {
                    ++$failed;
                    $io->progressAdvance();
                    continue;
                }

                if ($this->prerenderModel->prerenderForContact($email, $lead, $expiresAt)) {
                    ++$success;
                } else {
                    ++$failed;
                }

                $io->progressAdvance();
            }

            // Free memory between batches
            $this->leadModel->getRepository()->clear();
            gc_collect_cycles();
        }

        $io->progressFinish();
        $io->success(sprintf('Done. Success: %d, Failed: %d', $success, $failed));

        return Command::SUCCESS;
    }
}
