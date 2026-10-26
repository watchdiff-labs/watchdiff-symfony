<?php

declare(strict_types=1);

namespace WatchDiff\Symfony;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use WatchDiff\Symfony\DependencyInjection\WatchDiffExtension;

final class WatchDiffBundle extends Bundle
{
    public function getContainerExtension(): WatchDiffExtension
    {
        return new WatchDiffExtension();
    }
}
