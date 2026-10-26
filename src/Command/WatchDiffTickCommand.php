<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use WatchDiff\Core\Console\ConfigFile;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Event\WatcherChangedEvent;

#[AsCommand(name: 'watchdiff:tick', description: 'Check every due watcher and report changes.')]
final class WatchDiffTickCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
        private readonly string $defaultConfigPath,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
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

        $reports = $this->watchDiff->tick();

        if ([] === $reports) {
            $output->writeln('<info>No changes detected.</info>');

            return Command::SUCCESS;
        }

        foreach ($reports as $report) {
            $output->writeln(sprintf('<comment>%s</comment>: %d change(s)', $report->label, \count($report->changes)));
            $this->eventDispatcher?->dispatch(new WatcherChangedEvent($report));
        }

        return Command::SUCCESS;
    }
}
