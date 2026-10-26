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
use WatchDiff\Symfony\Command\WatchDiffClearCommand;
use WatchDiff\Symfony\Command\WatchDiffCompareCommand;
use WatchDiff\Symfony\Command\WatchDiffExportCommand;

final class WatchDiffExportClearCompareCommandTest extends TestCase
{
    private string $storageDir;
    private Client $httpClient;
    private Psr17Factory $psr17;
    private WatchDiff $watchDiff;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));
        $this->httpClient  = new Client();
        $this->psr17       = new Psr17Factory();
        $this->watchDiff   = new WatchDiff(new JsonStore($this->storageDir), $this->httpClient, $this->psr17, $this->psr17);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
    }

    public function testExportWritesReportsToAFileWhenOutputIsGiven(): void
    {
        $config = new WatchConfig(url: 'https://example.test/page');
        $this->watchDiff->watch($config);
        $this->httpClient->addResponse($this->psr17->createResponse(200)->withBody($this->psr17->createStream('a')));
        $this->watchDiff->checkOnce($config->key());
        $this->httpClient->addResponse($this->psr17->createResponse(200)->withBody($this->psr17->createStream('b')));
        $this->watchDiff->checkOnce($config->key());

        $outputPath = sys_get_temp_dir().'/watchdiff-symfony-export-'.bin2hex(random_bytes(8)).'.json';

        try {
            $tester = new CommandTester(new WatchDiffExportCommand($this->watchDiff));
            $tester->execute(['url' => 'https://example.test/page', '--output' => $outputPath]);

            self::assertSame(0, $tester->getStatusCode());
            self::assertFileExists($outputPath);
            self::assertStringContainsString('"a"', (string) file_get_contents($outputPath));
        } finally {
            @unlink($outputPath);
        }
    }

    public function testClearRemovesAllStoredDataForTheUrl(): void
    {
        $config = new WatchConfig(url: 'https://example.test/page');
        $this->watchDiff->watch($config);
        $this->httpClient->addResponse($this->psr17->createResponse(200)->withBody($this->psr17->createStream('a')));
        $this->watchDiff->checkOnce($config->key());

        $tester = new CommandTester(new WatchDiffClearCommand($this->watchDiff));
        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertSame([], $this->watchDiff->storedKeys());
    }

    public function testCompareDiffsTwoArbitraryUrls(): void
    {
        $this->httpClient->addResponse($this->psr17->createResponse(200)->withBody($this->psr17->createStream('a')));
        $this->httpClient->addResponse($this->psr17->createResponse(200)->withBody($this->psr17->createStream('b')));

        $tester = new CommandTester(new WatchDiffCompareCommand($this->watchDiff));
        $tester->execute(['urlA' => 'https://example.test/a', 'urlB' => 'https://example.test/b']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('a -> b', $tester->getDisplay());
    }
}
