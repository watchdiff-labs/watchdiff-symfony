<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Core\WatchDiff;
use WatchDiff\Symfony\Command\WatchDiffCheckCommand;

final class WatchDiffCheckCommandTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-test-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
    }

    public function testFirstCheckSavesASnapshotAndReportsNothingToCompare(): void
    {
        $httpClient = new Client();
        $psr17      = new Psr17Factory();
        $httpClient->addResponse($psr17->createResponse(200)->withBody($psr17->createStream('content')));

        $watchDiff = new WatchDiff(new JsonStore($this->storageDir), $httpClient, $psr17, $psr17);
        $tester    = new CommandTester(new WatchDiffCheckCommand($watchDiff));

        $tester->execute(['url' => 'https://example.test/page']);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('First check', $tester->getDisplay());
    }
}
