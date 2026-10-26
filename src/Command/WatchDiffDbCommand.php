<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WatchDiff\Core\Db\DbDiffMode;
use WatchDiff\Core\Db\DbWatchConfig;
use WatchDiff\Core\Db\DbWatcher;

#[AsCommand(name: 'watchdiff:db', description: 'Run a single one-off check against a database table.')]
final class WatchDiffDbCommand extends Command
{
    public function __construct(
        private readonly DbWatcher $dbWatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('connectionString', InputArgument::REQUIRED, 'e.g. sqlite://./app.db')
            ->addArgument('table', InputArgument::REQUIRED)
            ->addOption('primary-key', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Primary key column(s)', [])
            ->addOption('diff-mode', null, InputOption::VALUE_REQUIRED, 'row|schema|aggregate|value', 'row')
            ->addOption('query', null, InputOption::VALUE_REQUIRED)
            ->addOption('threshold', null, InputOption::VALUE_REQUIRED, 'Percent change required to alert (aggregate mode)', 0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new DbWatchConfig(
            connectionString: (string) $input->getArgument('connectionString'),
            table: (string) $input->getArgument('table'),
            diffMode: DbDiffMode::from((string) $input->getOption('diff-mode')),
            primaryKey: $input->getOption('primary-key'),
            query: $input->getOption('query'),
            threshold: (float) $input->getOption('threshold'),
        );

        $this->dbWatcher->watch($config);
        $report = $this->dbWatcher->checkOnce($config->key());

        if (null === $report) {
            $output->writeln('<info>First check - snapshot saved, nothing to compare yet.</info>');

            return Command::SUCCESS;
        }

        if (!$report->hasChanges()) {
            $output->writeln('<info>No changes detected.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->changes as $change) {
            $output->writeln(sprintf(
                '[%s]%s%s',
                $change->kind->value,
                null !== $change->rowKey ? ' key='.$change->rowKey : '',
                null !== $change->column ? ' column='.$change->column.' ('.$change->context.')' : '',
            ));
        }

        return Command::SUCCESS;
    }
}
