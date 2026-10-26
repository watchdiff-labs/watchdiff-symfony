<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffDiffCommand;
use WatchDiff\Symfony\Command\WatchDiffHistoryCommand;
use WatchDiff\Symfony\Command\WatchDiffReportsCommand;
use WatchDiff\Symfony\Command\WatchDiffSnapshotCommand;

final class WatchDiffDiffHistorySnapshotReportsCommandTest extends TestCase
{
    private string $storageDir;
    private WatchDiff $watchDiff;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));

        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $this->watchDiff = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);

        $config = new WatchConfig(url: 'https://example.test/page');
        $this->watchDiff->watch($config);

        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('first')));
        $this->watchDiff->checkOnce($config->key());
        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('second')));
        $this->watchDiff->checkOnce($config->key());
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
    }

    public function testDiffPrintsTheLastStoredChange(): void
    {
        $tester = new CommandTester(new WatchDiffDiffCommand($this->watchDiff));
        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('first -> second', $tester->getDisplay());
    }

    public function testHistoryListsBothSnapshots(): void
    {
        $tester = new CommandTester(new WatchDiffHistoryCommand($this->watchDiff));
        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame(2, substr_count($tester->getDisplay(), 'status 200'));
    }

    public function testSnapshotPrintsTheLatestContent(): void
    {
        $tester = new CommandTester(new WatchDiffSnapshotCommand($this->watchDiff));
        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('second', $tester->getDisplay());
    }

    public function testReportsListsTheStoredReport(): void
    {
        $tester = new CommandTester(new WatchDiffReportsCommand($this->watchDiff));
        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('1 change(s)', $tester->getDisplay());
    }
}
