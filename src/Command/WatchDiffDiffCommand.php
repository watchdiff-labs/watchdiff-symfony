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

#[AsCommand(name: 'watchdiff:diff', description: 'Print the last stored diff for a URL.')]
final class WatchDiffDiffCommand extends Command
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
        $url     = (string) $input->getArgument('url');
        $reports = $this->watchDiff->reports((new WatchConfig($url))->key(), 1);

        if ([] === $reports) {
            $output->writeln('<info>No stored diff for this URL yet.</info>');

            return Command::SUCCESS;
        }

        foreach ($reports[0]->changes as $change) {
            $output->writeln(sprintf('[%s] %s -> %s', $change->kind->value, $change->before ?? '-', $change->after ?? '-'));
        }

        return Command::SUCCESS;
    }
}
