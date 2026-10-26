<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use WatchDiff\Core\Console\ConfigFile;
use WatchDiff\Core\WatchDiff;

#[AsCommand(name: 'watchdiff:clean', description: 'Delete stored data for watchers no longer in the config file.')]
final class WatchDiffCleanCommand extends Command
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
            ->addOption('config', null, InputOption::VALUE_REQUIRED)
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configPath     = $input->getOption('config') ?? $this->defaultConfigPath;
        $configuredKeys = array_map(
            static fn ($watcher) => $watcher->key(),
            ConfigFile::load($configPath),
        );

        $orphans = array_diff($this->watchDiff->storedKeys(), $configuredKeys);

        if ([] === $orphans) {
            $output->writeln('<info>Nothing to clean.</info>');

            return Command::SUCCESS;
        }

        if (!$input->getOption('yes')) {
            $output->writeln(sprintf('<comment>%d orphan watcher(s) will be deleted:</comment>', \count($orphans)));
            foreach ($orphans as $orphan) {
                $output->writeln(' - '.$orphan);
            }

            if (!$input->isInteractive()) {
                $output->writeln('<error>Refusing to delete without confirmation in non-interactive mode. Pass --yes to proceed.</error>');

                return Command::FAILURE;
            }

            /** @var QuestionHelper $helper */
            $helper    = $this->getHelper('question');
            $confirmed = $helper->ask($input, $output, new ConfirmationQuestion('Proceed? [y/N] ', false));

            if (!$confirmed) {
                $output->writeln('<info>Aborted, nothing was deleted.</info>');

                return Command::SUCCESS;
            }
        }

        foreach ($orphans as $orphan) {
            $this->watchDiff->clear($orphan);
            $output->writeln(sprintf('Removed orphan data for "%s".', $orphan));
        }

        return Command::SUCCESS;
    }
}
