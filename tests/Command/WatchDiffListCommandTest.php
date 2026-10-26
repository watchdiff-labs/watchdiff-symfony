<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffListCommand;

final class WatchDiffListCommandTest extends TestCase
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

    public function testListsConfiguredWatchersWithTheirStatus(): void
    {
        file_put_contents($this->configPath, json_encode([
            'watchers' => [['url' => 'https://example.test/page', 'label' => 'Example page']],
        ]));

        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        $tester = new CommandTester(new WatchDiffListCommand($watchDiff, $this->configPath));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Example page', $tester->getDisplay());
    }

    public function testNoWatchersConfigured(): void
    {
        file_put_contents($this->configPath, json_encode(['watchers' => []]));

        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        $tester = new CommandTester(new WatchDiffListCommand($watchDiff, $this->configPath));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No watchers configured', $tester->getDisplay());
    }
}
