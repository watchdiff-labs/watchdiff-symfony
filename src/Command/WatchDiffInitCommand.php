<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'watchdiff:init', description: 'Create a starter watchdiff config file at the bundle-configured path.')]
final class WatchDiffInitCommand extends Command
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
        $path = $input->getOption('config') ?? $this->defaultConfigPath;

        if (is_file($path)) {
            $output->writeln(sprintf('<error>%s already exists.</error>', $path));

            return Command::FAILURE;
        }

        $template = [
            'watchers' => [
                [
                    'url'      => 'https://example.com',
                    'target'   => '.price',
                    'interval' => 300,
                    'diffMode' => 'line',
                    'webhooks' => [],
                ],
            ],
        ];

        file_put_contents($path, json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        $output->writeln(sprintf('<info>Created %s</info>', $path));

        return Command::SUCCESS;
    }
}
