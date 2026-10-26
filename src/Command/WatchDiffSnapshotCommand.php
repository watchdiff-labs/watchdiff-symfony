<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:snapshot', description: 'Print the latest stored snapshot for a URL.')]
final class WatchDiffSnapshotCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('url', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url       = (string) $input->getArgument('url');
        $snapshots = $this->watchDiff->history((new WatchConfig($url))->key(), 1);

        if ([] === $snapshots) {
            $output->writeln('<info>No snapshot stored for this URL yet.</info>');

            return Command::SUCCESS;
        }

        $output->writeln($snapshots[0]->content);

        return Command::SUCCESS;
    }
}
