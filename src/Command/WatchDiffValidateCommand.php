<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Console\ConfigFile;

#[AsCommand(name: 'watchdiff:validate', description: 'Validate the watchdiff config file.')]
final class WatchDiffValidateCommand extends Command
{
    public function __construct(
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
        $errors     = ConfigFile::validate($configPath);

        if ([] === $errors) {
            $output->writeln('<info>Config is valid.</info>');

            return Command::SUCCESS;
        }

        foreach ($errors as $error) {
            $output->writeln(sprintf('<error>%s</error>', $error));
        }

        return Command::FAILURE;
    }
}
