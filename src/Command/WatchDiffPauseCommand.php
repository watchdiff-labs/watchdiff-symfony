<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Console\ConfigFile;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:pause', description: 'Pause a watcher by id, URL, or tag.')]
final class WatchDiffPauseCommand extends Command
{
    public function __construct(
        private readonly WatchDiff $watchDiff,
        private readonly string $defaultConfigPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tagOrId', InputArgument::REQUIRED)
            ->addOption('config', null, InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath = $input->getOption('config') ?? $this->defaultConfigPath;

        foreach (ConfigFile::load($configPath) as $watcher) {
            $this->watchDiff->watch($watcher);
        }

        $tagOrId = (string) $input->getArgument('tagOrId');
        $this->watchDiff->pause($tagOrId);
        $output->writeln(sprintf('<info>Paused watcher(s) matching "%s".</info>', $tagOrId));

        return Command::SUCCESS;
    }
}
