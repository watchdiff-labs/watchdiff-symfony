<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WatchDiff\Core\Db\DbWatcher;
use WatchDiff\Core\Store\JsonStore;
use WatchDiff\Symfony\Command\WatchDiffDbCommand;

final class WatchDiffDbCommandTest extends TestCase
{
    private string $storageDir;
    private string $dbPath;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/watchdiff-symfony-db-test-'.bin2hex(random_bytes(8));
        $this->dbPath      = sys_get_temp_dir().'/watchdiff-symfony-db-'.bin2hex(random_bytes(8)).'.sqlite';

        if (!\in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('pdo_sqlite is not installed in this environment.');
        }

        $pdo = new \PDO('sqlite:'.$this->dbPath);
        $pdo->exec('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO products (id, name) VALUES (1, 'Widget')");
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        @rmdir($this->storageDir);
        @unlink($this->dbPath);
    }

    public function testFirstCheckSavesASnapshot(): void
    {
        $dbWatcher = new DbWatcher(new JsonStore($this->storageDir));
        $tester    = new CommandTester(new WatchDiffDbCommand($dbWatcher));

        $tester->execute([
            'connectionString' => 'sqlite://'.$this->dbPath,
            'table'            => 'products',
            '--primary-key'    => ['id'],
        ]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('First check', $tester->getDisplay());
    }
}
