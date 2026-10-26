<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:history', description: 'List stored snapshots for a URL.')]
final class WatchDiffHistoryCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max snapshots to show', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url       = (string) $input->getArgument('url');
        $snapshots = $this->watchDiff->history((new WatchConfig($url))->key(), (int) $input->getOption('limit'));

        if ([] === $snapshots) {
            $output->writeln('<info>No snapshots stored for this URL yet.</info>');

            return Command::SUCCESS;
        }

        foreach ($snapshots as $snapshot) {
            $output->writeln(sprintf('%s - status %d', $snapshot->capturedAt->format(DATE_ATOM), $snapshot->statusCode));
        }

        return Command::SUCCESS;
    }
}
