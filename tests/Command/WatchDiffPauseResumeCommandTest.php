<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffPauseCommand;
use WatchDiff\Symfony\Command\WatchDiffResumeCommand;

final class WatchDiffPauseResumeCommandTest extends TestCase
{
    private string $storageDir;
    private string $configPath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));
        $this->configPath = sys_get_temp_dir().'/watchdiff-symfony-config-'.bin2hex(random_bytes(8)).'.json';

        file_put_contents($this->configPath, json_encode([
            'watchers' => [['url' => 'https://example.test/page', 'id' => 'w1']],
        ]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->configPath);
    }

    public function testPauseThenResumeRoundTrips(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        $pause = new CommandTester(new WatchDiffPauseCommand($watchDiff, $this->configPath));
        $pause->execute(['tagOrId' => 'w1']);
        self::assertSame(0, $pause->getStatusCode());
        self::assertStringContainsString('Paused', $pause->getDisplay());

        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('content')));
        self::assertSame([], $watchDiff->tick(), 'a paused watcher must be skipped by tick()');

        $resume = new CommandTester(new WatchDiffResumeCommand($watchDiff, $this->configPath));
        $resume->execute(['tagOrId' => 'w1']);
        self::assertSame(0, $resume->getStatusCode());
        self::assertStringContainsString('Resumed', $resume->getDisplay());
    }
}
