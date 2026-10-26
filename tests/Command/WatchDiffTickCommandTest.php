<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffTickCommand;
use WatchDiff\Symfony\Event\WatcherChangedEvent;

final class WatchDiffTickCommandTest extends TestCase
{
    private string $storageDir;
    private string $configPath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));
        $this->configPath = sys_get_temp_dir().'/watchdiff-symfony-config-'.bin2hex(random_bytes(8)).'.json';

        file_put_contents($this->configPath, json_encode([
            'watchers' => [['url' => 'https://example.test/page']],
        ]));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->configPath);
    }

    public function testTicksEveryWatcherFromTheConfigFile(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('content')));

        $watchDiff = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);
        $tester    = new CommandTester(new WatchDiffTickCommand($watchDiff, $this->configPath));

        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No changes detected', $tester->getDisplay());
    }

    public function testTheConfigOptionOverridesTheDefaultConfigPath(): void
    {
        $otherConfigPath = sys_get_temp_dir().'/watchdiff-symfony-config-'.bin2hex(random_bytes(8)).'.json';
        file_put_contents($otherConfigPath, json_encode(['watchers' => []]));

        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);
        $tester     = new CommandTester(new WatchDiffTickCommand($watchDiff, $this->configPath));

        try {
            $tester->execute(['--config' => $otherConfigPath]);

            self::assertSame(0, $tester->getStatusCode());
            self::assertSame([], $watchDiff->watchers());
        } finally {
            @unlink($otherConfigPath);
        }
    }

    public function testDispatchesWatcherChangedEventForEveryChangedWatcher(): void
    {
        // interval: 0 so the second tick() considers the watcher due
        // immediately, instead of waiting out the default 300s interval.
        file_put_contents($this->configPath, json_encode([
            'watchers' => [['url' => 'https://example.test/page', 'interval' => 0]],
        ]));

        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $watchDiff  = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        $config = new WatchConfig(url: 'https://example.test/page', interval: 0);
        $watchDiff->watch($config);
        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('before')));
        $watchDiff->checkOnce($config->key());

        $dispatcher    = new EventDispatcher();
        $dispatched    = [];
        $dispatcher->addListener(WatcherChangedEvent::class, function (WatcherChangedEvent $event) use (&$dispatched) {
            $dispatched[] = $event;
        });

        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('after')));
        $tester = new CommandTester(new WatchDiffTickCommand($watchDiff, $this->configPath, $dispatcher));
        $tester->execute([]);

        self::assertCount(1, $dispatched);
        self::assertTrue($dispatched[0]->report->hasChanges());
    }
}
