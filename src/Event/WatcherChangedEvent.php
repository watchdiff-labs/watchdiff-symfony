<?php

declare(strict_types=1);

namespace WatchDiff\Symfony\Event;

use WatchDiff\Core\Model\DiffReport;

/**
 * Dispatched by WatchDiffTickCommand for every watcher that produced a
 * change, in addition to (not instead of) any `onChange` closure set
 * directly on the WatchConfig. This is the integration point for anything
 * that shouldn't live inside a config-file-driven onChange closure (which
 * can't be set from JSON): dispatch a Symfony Messenger message, send a
 * Mailer notification, log via Monolog, record metrics, ...
 */
final class WatcherChangedEvent
{
    public function __construct(
        public readonly DiffReport $report,
    ) {
    }
}
