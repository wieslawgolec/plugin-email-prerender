<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
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
    description: 'Pre-render emails once (full pipeline) and cache payload for fast send reuse'
)]
class PrerenderEmailCommand extends Command
{
    public function __construct(
        private PrerenderModel $prerenderModel,
        private EmailModel $emailModel,
        private ListModel $listModel,
        private LeadModel $leadModel,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email ID to pre-render')
            ->addOption('segment', null, InputOption::VALUE_OPTIONAL, 'Segment / List ID')
            ->addOption('contacts', null, InputOption::VALUE_OPTIONAL, 'Comma-separated contact IDs')
            ->addOption('batch', null, InputOption::VALUE_OPTIONAL, 'Contacts per DB batch', 200)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max contacts this run (0 = all)', 0)
            ->addOption('ttl', null, InputOption::VALUE_OPTIONAL, 'Cache TTL hours (0 = no expiry)', 72);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailId     = (int) $input->getOption('email');
        $segmentId   = $input->getOption('segment') ? (int) $input->getOption('segment') : null;
        $contactsOpt = $input->getOption('contacts');
        $batch       = max(1, (int) $input->getOption('batch'));
        $limit       = (int) $input->getOption('limit');
        $ttlHours    = (int) $input->getOption('ttl');

        if ($emailId <= 0) {
            $io->error('--email is required');

            return Command::FAILURE;
        }

        $email = $this->emailModel->getEntity($emailId);
        if (null === $email) {
            $io->error(sprintf('Email ID %d not found', $emailId));

            return Command::FAILURE;
        }

        $expiresAt = null;
        if ($ttlHours > 0) {
            $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d hours', $ttlHours));
        }

        $io->title(sprintf('Pre-rendering email "%s" (ID %d)', $email->getName(), $emailId));

        $success = 0;
        $failed  = 0;
        $processed = 0;

        if ($contactsOpt) {
            $ids = array_values(array_filter(array_map('intval', explode(',', $contactsOpt))));
            if ($limit > 0) {
                $ids = array_slice($ids, 0, $limit);
            }
            $io->progressStart(count($ids));
            foreach (array_chunk($ids, $batch) as $chunk) {
                foreach ($chunk as $contactId) {
                    $this->processContact($email, $contactId, $expiresAt, $success, $failed);
                    ++$processed;
                    $io->progressAdvance();
                }
                $this->em->clear();
                gc_collect_cycles();
            }
            $io->progressFinish();
        } elseif ($segmentId) {
            $list = $this->listModel->getEntity($segmentId);
            if (null === $list) {
                $io->error(sprintf('Segment/List ID %d not found', $segmentId));

                return Command::FAILURE;
            }

            // Streaming batch load via lead_lists_leads (cursor), not getLeadsByList()
            $lastId = 0;
            $io->writeln(sprintf('Streaming contacts from segment %d (batch=%d)...', $segmentId, $batch));

            while (true) {
                if ($limit > 0 && $processed >= $limit) {
                    break;
                }

                $fetchSize = $batch;
                if ($limit > 0) {
                    $fetchSize = min($batch, $limit - $processed);
                }

                $rows = $this->fetchSegmentContactIds($segmentId, $lastId, $fetchSize);
                if ($rows === []) {
                    break;
                }

                foreach ($rows as $contactId) {
                    $this->processContact($email, $contactId, $expiresAt, $success, $failed);
                    ++$processed;
                    $lastId = $contactId;
                }

                $io->writeln(sprintf('  processed=%d success=%d failed=%d lastId=%d', $processed, $success, $failed, $lastId));
                $this->em->clear();
                gc_collect_cycles();
            }
        } else {
            $io->error('Provide either --segment or --contacts');

            return Command::FAILURE;
        }

        $io->success(sprintf('Done. Processed: %d, Success: %d, Failed: %d', $processed, $success, $failed));

        return Command::SUCCESS;
    }

    /**
     * Cursor-based batch of contact IDs currently in the segment (manually_removed = 0).
     *
     * @return list<int>
     */
    private function fetchSegmentContactIds(int $segmentId, int $lastId, int $limit): array
    {
        $prefix = defined('MAUTIC_TABLE_PREFIX') ? MAUTIC_TABLE_PREFIX : '';
        $sql    = sprintf(
            'SELECT lead_id FROM %slead_lists_leads
             WHERE leadlist_id = :segmentId
               AND manually_removed = 0
               AND lead_id > :lastId
             ORDER BY lead_id ASC
             LIMIT %d',
            $prefix,
            $limit
        );

        $conn = $this->em->getConnection();
        $rows = $conn->fetchFirstColumn($sql, [
            'segmentId' => $segmentId,
            'lastId'    => $lastId,
        ]);

        return array_map('intval', $rows);
    }

    private function processContact($email, int $contactId, ?\DateTimeInterface $expiresAt, int &$success, int &$failed): void
    {
        $lead = $this->leadModel->getEntity($contactId);
        if (null === $lead || !$lead->getEmail()) {
            ++$failed;

            return;
        }

        if ($this->prerenderModel->prerenderForContact($email, $lead, $expiresAt)) {
            ++$success;
        } else {
            ++$failed;
        }
    }
}
