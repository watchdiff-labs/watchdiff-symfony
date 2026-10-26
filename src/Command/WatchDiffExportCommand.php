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

#[AsCommand(name: 'watchdiff:export', description: 'Export stored reports or snapshots for a URL as CSV or JSON.')]
final class WatchDiffExportCommand extends Command
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
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'csv|json', 'json')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'reports|snapshots', 'reports')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED)
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write to this file instead of stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key      = (new WatchConfig((string) $input->getArgument('url')))->key();
        $format   = (string) $input->getOption('format');
        $type     = (string) $input->getOption('type');
        $limit    = $input->getOption('limit');
        $limitInt = null !== $limit ? (int) $limit : null;

        $content = match ([$type, $format]) {
            ['reports', 'csv']    => $this->watchDiff->exportReportsCsv($key, $limitInt),
            ['snapshots', 'csv']  => $this->watchDiff->exportSnapshotsCsv($key, $limitInt),
            ['snapshots', 'json'] => $this->watchDiff->exportSnapshotsJson($key, $limitInt),
            default               => $this->watchDiff->exportReportsJson($key, $limitInt),
        };

        $outputPath = $input->getOption('output');
        if (null !== $outputPath) {
            file_put_contents((string) $outputPath, $content."\n");
            $output->writeln(sprintf('<info>Wrote %s.</info>', $outputPath));

            return Command::SUCCESS;
        }

        $output->writeln($content);

        return Command::SUCCESS;
    }
}
