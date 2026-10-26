<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use WatchDiff\Core\Contract\StoreInterface;
use WatchDiff\Core\Db\DbWatcher;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\Store\SqliteStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffCheckCommand;
use WatchDiff\Symfony\Command\WatchDiffCleanCommand;
use WatchDiff\Symfony\Command\WatchDiffClearCommand;
use WatchDiff\Symfony\Command\WatchDiffCompareCommand;
use WatchDiff\Symfony\Command\WatchDiffDbCommand;
use WatchDiff\Symfony\Command\WatchDiffDiffCommand;
use WatchDiff\Symfony\Command\WatchDiffExportCommand;
use WatchDiff\Symfony\Command\WatchDiffHistoryCommand;
use WatchDiff\Symfony\Command\WatchDiffInitCommand;
use WatchDiff\Symfony\Command\WatchDiffListCommand;
use WatchDiff\Symfony\Command\WatchDiffPauseCommand;
use WatchDiff\Symfony\Command\WatchDiffReportsCommand;
use WatchDiff\Symfony\Command\WatchDiffResumeCommand;
use WatchDiff\Symfony\Command\WatchDiffSnapshotCommand;
use WatchDiff\Symfony\Command\WatchDiffTickCommand;
use WatchDiff\Symfony\Command\WatchDiffValidateCommand;

final class WatchDiffExtension extends Extension
{
    /** @param array<array<string, mixed>> $configs */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('watch_diff.config_path', $config['config_path']);

        $storeDefinition = 'sqlite' === $config['store']['driver']
            ? (new Definition(SqliteStore::class))->setArgument('$path', $config['store']['sqlite_path'])
            : (new Definition(JsonStore::class))->setArgument('$directory', $config['store']['json_directory']);

        $container->setDefinition(StoreInterface::class, $storeDefinition);

        // The PSR-18 client and PSR-17 factories are autowired from whatever
        // implementation the application provides (e.g. symfony/http-client's
        // Psr18Client, which implements all three interfaces at once).
        $container->setDefinition(WatchDiff::class, (new Definition(WatchDiff::class))
            ->setArgument('$store', new Reference(StoreInterface::class))
            ->setAutowired(true)
            ->setPublic(true));

        // DbWatcher deliberately gets its own JsonStore instance (see the
        // db_json_directory doc comment on Configuration) rather than reusing
        // the StoreInterface service above.
        $container->setDefinition(DbWatcher::class, (new Definition(DbWatcher::class))
            ->setArgument('$store', new Definition(JsonStore::class, [$config['store']['db_json_directory']]))
            ->setAutowired(true)
            ->setPublic(true));

        $container->setDefinition(WatchDiffInitCommand::class, (new Definition(WatchDiffInitCommand::class))
            ->setArgument('$defaultConfigPath', $config['config_path'])
            ->addTag('console.command'));

        $container->setDefinition(WatchDiffValidateCommand::class, (new Definition(WatchDiffValidateCommand::class))
            ->setArgument('$defaultConfigPath', $config['config_path'])
            ->addTag('console.command'));

        // tick optionally takes the event dispatcher (nullable constructor
        // default) so dispatching WatcherChangedEvent keeps working even in
        // an app that hasn't registered symfony/event-dispatcher.
        $container->setDefinition(WatchDiffTickCommand::class, (new Definition(WatchDiffTickCommand::class))
            ->setArgument('$watchDiff', new Reference(WatchDiff::class))
            ->setArgument('$defaultConfigPath', $config['config_path'])
            ->setAutowired(true)
            ->addTag('console.command'));

        foreach ([
            WatchDiffListCommand::class,
            WatchDiffPauseCommand::class,
            WatchDiffResumeCommand::class,
            WatchDiffCleanCommand::class,
        ] as $commandClass) {
            $container->setDefinition($commandClass, (new Definition($commandClass))
                ->setArgument('$watchDiff', new Reference(WatchDiff::class))
                ->setArgument('$defaultConfigPath', $config['config_path'])
                ->addTag('console.command'));
        }

        foreach ([
            WatchDiffCheckCommand::class,
            WatchDiffDiffCommand::class,
            WatchDiffHistoryCommand::class,
            WatchDiffSnapshotCommand::class,
            WatchDiffClearCommand::class,
            WatchDiffReportsCommand::class,
            WatchDiffExportCommand::class,
            WatchDiffCompareCommand::class,
        ] as $commandClass) {
            $container->setDefinition($commandClass, (new Definition($commandClass))
                ->setArgument('$watchDiff', new Reference(WatchDiff::class))
                ->addTag('console.command'));
        }

        $container->setDefinition(WatchDiffDbCommand::class, (new Definition(WatchDiffDbCommand::class))
            ->setArgument('$dbWatcher', new Reference(DbWatcher::class))
            ->addTag('console.command'));
    }
}
