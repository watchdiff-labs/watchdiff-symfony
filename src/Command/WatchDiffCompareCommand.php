<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:compare', description: 'Diff two arbitrary URLs in a single command.')]
final class WatchDiffCompareCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('urlA', InputArgument::REQUIRED)
            ->addArgument('urlB', InputArgument::REQUIRED)
            ->addOption('target', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->watchDiff->compareUrls(
            (string) $input->getArgument('urlA'),
            (string) $input->getArgument('urlB'),
            $input->getOption('target'),
        );

        if (!$report->hasChanges()) {
            $output->writeln('<info>No differences found.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
