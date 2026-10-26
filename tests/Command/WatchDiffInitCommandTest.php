<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Symfony\Command\WatchDiffInitCommand;

final class WatchDiffInitCommandTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir().'/watchdiff-symfony-config-'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->configPath);
    }

    public function testCreatesAStarterConfigFile(): void
    {
        $tester = new CommandTester(new WatchDiffInitCommand($this->configPath));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertFileExists($this->configPath);

        $data = json_decode((string) file_get_contents($this->configPath), true);
        self::assertArrayHasKey('watchers', $data);
    }

    public function testRefusesToOverwriteAnExistingFile(): void
    {
        file_put_contents($this->configPath, '{}');

        $tester = new CommandTester(new WatchDiffInitCommand($this->configPath));
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
    }
}
