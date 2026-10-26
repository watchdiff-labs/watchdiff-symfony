<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Console\ConfigFile;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:list', description: 'List configured watchers and their status.')]
final class WatchDiffListCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
        private readonly string $defaultConfigPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('config', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config') ?? $this->defaultConfigPath;

        foreach (ConfigFile::load($configPath) as $watcher) {
            $this->watchDiff->watch($watcher);
        }

        $statuses = $this->watchDiff->status();

        if ([] === $statuses) {
            $output->writeln('<info>No watchers configured.</info>');

            return Command::SUCCESS;
        }

        foreach ($statuses as $status) {
            $output->writeln(sprintf(
                '%s%s | target=%s | interval=%ds | checks=%d changes=%d errors=%d',
                $status->label,
                $status->paused ? ' <comment>[paused]</comment>' : '',
                $status->target ?? '(full page)',
                $status->interval,
                $status->checksCount,
                $status->changesCount,
                $status->errorsCount,
            ));
        }

        return Command::SUCCESS;
    }
}
