<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffCleanCommand;

final class WatchDiffCleanCommandTest extends TestCase
{
    private string $storageDir;
    private string $configPath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));
        $this->configPath = sys_get_temp_dir().'/watchdiff-symfony-config-'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->configPath);
    }

    public function testRemovesOrphanDataNotInTheConfigFileWithYes(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $store      = new JsonStore($this->storageDir);
        $watchDiff  = new WatchDiff($store, $httpClient, $psr17, $psr17);

        // A watcher with stored state that is no longer in the config file.
        $store->setState('https://orphan.test', ['checksCount' => 1]);
        file_put_contents($this->configPath, json_encode(['watchers' => []]));

        $tester = new CommandTester(new WatchDiffCleanCommand($watchDiff, $this->configPath));
        $tester->execute(['--yes' => true]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Removed orphan data for "https://orphan.test"', $tester->getDisplay());
        self::assertSame([], $watchDiff->storedKeys());
    }

    public function testNothingToCleanWhenNoOrphansExist(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        file_put_contents($this->configPath, json_encode(['watchers' => []]));

        $tester = new CommandTester(new WatchDiffCleanCommand($watchDiff, $this->configPath));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to clean', $tester->getDisplay());
    }
}
