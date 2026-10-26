<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use WatchDiff\Symfony\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsAreApplied(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame('%kernel.project_dir%/watchdiff.config.json', $config['config_path']);
        self::assertSame('json', $config['store']['driver']);
        self::assertSame('%kernel.project_dir%/var/watchdiff', $config['store']['json_directory']);
        self::assertSame('%kernel.project_dir%/var/watchdiff.db', $config['store']['sqlite_path']);
        self::assertSame('%kernel.project_dir%/var/watchdiff-db', $config['store']['db_json_directory']);
    }

    public function testExplicitValuesOverrideDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'config_path' => '/app/watchers.json',
            'store'       => [
                'driver'      => 'sqlite',
                'sqlite_path' => '/app/var/watchdiff.db',
            ],
        ]]);

        self::assertSame('/app/watchers.json', $config['config_path']);
        self::assertSame('sqlite', $config['store']['driver']);
        self::assertSame('/app/var/watchdiff.db', $config['store']['sqlite_path']);
    }

    public function testInvalidStoreDriverIsRejected(): void
    {
        $this->expectException(\Exception::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'store' => ['driver' => 'redis'],
        ]]);
    }
}
