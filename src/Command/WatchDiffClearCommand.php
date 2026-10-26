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

#[AsCommand(name: 'watchdiff:clear', description: 'Delete all stored snapshots, reports and state for a single URL.')]
final class WatchDiffClearCommand extends Command
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
        $key = (new WatchConfig((string) $input->getArgument('url')))->key();

        $this->watchDiff->clear($key);
        $output->writeln(sprintf('<info>Cleared stored data for "%s".</info>', $key));

        return Command::SUCCESS;
    }
}
