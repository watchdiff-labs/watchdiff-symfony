<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Symfony\Command\WatchDiffValidateCommand;

final class WatchDiffValidateCommandTest extends TestCase
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

    public function testValidConfigReturnsSuccess(): void
    {
        file_put_contents($this->configPath, json_encode(['watchers' => [['url' => 'https://example.test']]]));

        $tester = new CommandTester(new WatchDiffValidateCommand($this->configPath));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('valid', $tester->getDisplay());
    }

    public function testInvalidUrlReturnsFailureAndListsTheError(): void
    {
        file_put_contents($this->configPath, json_encode(['watchers' => [['url' => 'not-a-url']]]));

        $tester = new CommandTester(new WatchDiffValidateCommand($this->configPath));
        $tester->execute([]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('http://', $tester->getDisplay());
    }
}
