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
use WatchDiff\Core\Enum\DiffMode;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:check', description: 'Run a single one-off check against a URL.')]
final class WatchDiffCheckCommand extends Command
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
            ->addOption('target', null, InputOption::VALUE_REQUIRED)
            ->addOption('diff-mode', null, InputOption::VALUE_REQUIRED, 'line|word|json|rss|sitemap', 'line');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new WatchConfig(
            url: (string) $input->getArgument('url'),
            target: $input->getOption('target'),
            diffMode: DiffMode::from((string) $input->getOption('diff-mode')),
        );

        $this->watchDiff->watch($config);
        $report = $this->watchDiff->checkOnce($config->key());

        if (null === $report) {
            $output->writeln('<info>First check - snapshot saved, nothing to compare yet.</info>');

            return Command::SUCCESS;
        }

        if (!$report->hasChanges()) {
            $output->writeln('<info>No changes detected.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
