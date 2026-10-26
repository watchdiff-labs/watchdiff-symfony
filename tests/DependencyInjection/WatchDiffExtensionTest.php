<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
use WatchDiff\Symfony\DependencyInjection\WatchDiffExtension;

final class WatchDiffExtensionTest extends TestCase
{
    public function testDefaultConfigurationWiresAJsonStoreAndAllCommands(): void
    {
        $container = new ContainerBuilder();
        (new WatchDiffExtension())->load([[]], $container);

        self::assertSame(JsonStore::class, $container->getDefinition(StoreInterface::class)->getClass());
        self::assertTrue($container->getDefinition(WatchDiff::class)->isAutowired());
        self::assertTrue($container->getDefinition(WatchDiff::class)->isPublic());
        self::assertTrue($container->getDefinition(DbWatcher::class)->isPublic());

        foreach ([
            WatchDiffInitCommand::class,
            WatchDiffTickCommand::class,
            WatchDiffValidateCommand::class,
            WatchDiffListCommand::class,
            WatchDiffPauseCommand::class,
            WatchDiffResumeCommand::class,
            WatchDiffCleanCommand::class,
            WatchDiffCheckCommand::class,
            WatchDiffDiffCommand::class,
            WatchDiffHistoryCommand::class,
            WatchDiffSnapshotCommand::class,
            WatchDiffClearCommand::class,
            WatchDiffReportsCommand::class,
            WatchDiffExportCommand::class,
            WatchDiffCompareCommand::class,
            WatchDiffDbCommand::class,
        ] as $commandClass) {
            self::assertTrue($container->hasDefinition($commandClass), "{$commandClass} should be registered");
            self::assertTrue(
                $container->getDefinition($commandClass)->hasTag('console.command'),
                "{$commandClass} should be tagged console.command",
            );
        }
    }

    public function testSqliteDriverSwapsTheStoreDefinition(): void
    {
        $container = new ContainerBuilder();
        (new WatchDiffExtension())->load([['store' => ['driver' => 'sqlite']]], $container);

        self::assertSame(SqliteStore::class, $container->getDefinition(StoreInterface::class)->getClass());
    }

    public function testConfigPathIsExposedAsAParameter(): void
    {
        $container = new ContainerBuilder();
        (new WatchDiffExtension())->load([['config_path' => '/app/watchers.json']], $container);

        self::assertSame('/app/watchers.json', $container->getParameter('watch_diff.config_path'));
    }
}
