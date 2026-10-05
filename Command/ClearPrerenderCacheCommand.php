<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailPreRenderBundle\Command;

use MauticPlugin\MauticEmailPreRenderBundle\Model\PrerenderModel;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mautic:email:prerender:clear',
    description: 'Clear pre-rendered email cache'
)]
class ClearPrerenderCacheCommand extends Command
{
    public function __construct(
        private PrerenderModel $prerenderModel,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_OPTIONAL, 'Clear cache only for this email ID')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Clear the entire pre-render cache')
            ->addOption('expired', null, InputOption::VALUE_NONE, 'Clear only expired entries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailId = $input->getOption('email') ? (int) $input->getOption('email') : null;
        $all     = (bool) $input->getOption('all');
        $expired = (bool) $input->getOption('expired');

        if ($all) {
            $count = $this->prerenderModel->clearAll();
            $io->success(sprintf('Cleared entire pre-render cache (%d rows).', $count));

            return Command::SUCCESS;
        }

        if ($expired) {
            $count = $this->prerenderModel->clearExpired();
            $io->success(sprintf('Cleared %d expired cache entries.', $count));

            return Command::SUCCESS;
        }

        if ($emailId) {
            $count = $this->prerenderModel->clearByEmailId($emailId);
            $io->success(sprintf('Cleared %d cache entries for email ID %d.', $count, $emailId));

            return Command::SUCCESS;
        }

        $io->error('Specify --email=ID, --all or --expired');

        return Command::FAILURE;
    }
}
