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

#[AsCommand(name: 'watchdiff:reports', description: 'List stored change reports for a URL.')]
final class WatchDiffReportsCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max reports to show', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url     = (string) $input->getArgument('url');
        $reports = $this->watchDiff->reports((new WatchConfig($url))->key(), (int) $input->getOption('limit'));

        if ([] === $reports) {
            $output->writeln('<info>No stored reports for this URL yet.</info>');

            return Command::SUCCESS;
        }

        foreach ($reports as $report) {
            $output->writeln(sprintf('%s - %d change(s)', $report->comparedAt->format(DATE_ATOM), \count($report->changes)));
        }

        return Command::SUCCESS;
    }
}
