<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Tests;

use PHPUnit\Framework\TestCase;
use WatchDiff\Symfony\DependencyInjection\WatchDiffExtension;
use WatchDiff\Symfony\WatchDiffBundle;

final class WatchDiffBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsWatchDiffExtension(): void
    {
        $bundle = new WatchDiffBundle();

        self::assertInstanceOf(WatchDiffExtension::class, $bundle->getContainerExtension());
    }
}
