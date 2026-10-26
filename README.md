# watchdiff-symfony

[![Packagist Version](https://img.shields.io/packagist/v/watchdiff-labs/watchdiff-symfony)](https://packagist.org/packages/watchdiff-labs/watchdiff-symfony)
[![Tests](https://github.com/watchdiff-labs/watchdiff-symfony/actions/workflows/ci.yml/badge.svg)](https://github.com/watchdiff-labs/watchdiff-symfony/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue)](LICENSE)
[![Packagist Downloads](https://img.shields.io/packagist/dt/watchdiff-labs/watchdiff-symfony)](https://packagist.org/packages/watchdiff-labs/watchdiff-symfony)

Symfony bundle for [`watchdiff-core`](https://packagist.org/packages/watchdiff-labs/watchdiff-core):
web change monitoring (page diffs, APIs, file/SSL/DB watchers, webhooks) wired into Symfony's
service container and console. Installing this package pulls in `watchdiff-core` automatically -
you never need to require it yourself.

- **Monitors:** web pages, JSON APIs, RSS/Atom feeds, sitemaps, local files, SSL
  certificates, and database tables.
- **Notifies via:** webhooks (Discord/Slack/Telegram/ntfy/...), callbacks in your own code,
  or an AI-generated summary of each change.
- **Also handles:** retries, cooldowns, scheduling, pausing/tagging watchers, and export.

This README covers the Symfony-specific wiring (DI, configuration, console commands). For
the full feature list with examples, see
[`watchdiff-core`'s own README](https://github.com/watchdiff-labs/watchdiff-php#readme), or
its [Packagist page](https://packagist.org/packages/watchdiff-labs/watchdiff-core).

## Table of contents

- [Install](#install)
- [Quick start](#quick-start)
- [Configuration reference](#configuration-reference)
- [Usage in your own code](#usage-in-your-own-code)
  - [Controller](#controller)
  - [Doctrine repository](#doctrine-repository)
  - [API Platform state processor](#api-platform-state-processor)
- [Reacting to changes: events](#reacting-to-changes-events)
- [Database watchers](#database-watchers)
- [Scheduling](#scheduling)
- [Store drivers](#store-drivers)
- [What this bundle does and doesn't add](#what-this-bundle-does-and-doesnt-add)
- [Security](#security)
- [Commands](#commands)
  - [`watchdiff:init`](#watchdiffinit)
  - [`watchdiff:tick`](#watchdifftick)
  - [`watchdiff:check`](#watchdiffcheck)
  - [`watchdiff:validate`](#watchdiffvalidate)
  - [`watchdiff:list`](#watchdifflist)
  - [`watchdiff:pause` / `watchdiff:resume`](#watchdiffpause--watchdiffresume)
  - [`watchdiff:clean`](#watchdiffclean)
  - [`watchdiff:diff` / `watchdiff:history` / `watchdiff:snapshot` / `watchdiff:reports`](#watchdiffdiff--watchdiffhistory--watchdiffsnapshot--watchdiffreports)
  - [`watchdiff:clear`](#watchdiffclear)
  - [`watchdiff:export`](#watchdiffexport)
  - [`watchdiff:compare`](#watchdiffcompare)
  - [`watchdiff:db`](#watchdiffdb)
- [Development](#development)
- [License](#license)

## Install

```bash
composer require watchdiff-labs/watchdiff-symfony
```

Supported: Symfony 6.4, 7.x, PHP 8.1+.

Not a PHP project? watchdiff also ships natively for Node.js and Python - same monitoring
concepts, ported to each ecosystem rather than wrapped:

```bash
npm install watchdiff-core
```

```bash
pip install watchdiff-core
```

- npm (Node.js): https://www.npmjs.com/package/watchdiff-core
- PyPI (Python): https://pypi.org/project/watchdiff-core/

This bundle needs a PSR-18 HTTP client and PSR-17 factories to be available for autowiring
(`watchdiff-core` itself stays HTTP-client-agnostic by design). If your app doesn't already
have one registered:

```bash
composer require symfony/http-client
```

`symfony/http-client`'s `Psr18Client` implements all three PSR interfaces at once, so no
further wiring is needed - `WatchDiff\Core\WatchDiff`'s constructor is autowired.

Enable the bundle (skip this if you use Symfony Flex, which does it for you):

```php
// config/bundles.php
return [
    // ...
    WatchDiff\Symfony\WatchDiffBundle::class => ['all' => true],
];
```

## Quick start

1. Create `watchdiff.config.json` at your project root (same format as
   `watchdiff-core`'s own CLI - see its README for the full option reference):

   ```json
   {
       "watchers": [
           { "url": "https://example.com/pricing", "target": ".price", "interval": 300 }
       ]
   }
   ```

2. Run a tick manually to confirm it's wired correctly:

   ```bash
   bin/console watchdiff:tick
   ```

3. Schedule `watchdiff:tick` with cron (or a Symfony Scheduler task) on a short interval -
   each watcher's own `interval` decides how often it actually runs, `tick()` just checks
   which ones are due.

## Configuration reference

```yaml
# config/packages/watch_diff.yaml
watch_diff:
    config_path: '%kernel.project_dir%/watchdiff.config.json'
    store:
        driver: json # or 'sqlite'
        json_directory: '%kernel.project_dir%/var/watchdiff'
        sqlite_path: '%kernel.project_dir%/var/watchdiff.db'
        db_json_directory: '%kernel.project_dir%/var/watchdiff-db'
```

| Key | Default | Meaning |
|---|---|---|
| `config_path` | `%kernel.project_dir%/watchdiff.config.json` | Default path read by every command below unless `--config` overrides it. |
| `store.driver` | `json` | `json` (one file per watcher, human-readable) or `sqlite` (single file, better for many watchers). |
| `store.json_directory` | `%kernel.project_dir%/var/watchdiff` | Used when `driver: json`. |
| `store.sqlite_path` | `%kernel.project_dir%/var/watchdiff.db` | Used when `driver: sqlite`. |
| `store.db_json_directory` | `%kernel.project_dir%/var/watchdiff-db` | Storage for the separate `DbWatcher` service (see [Database watchers](#database-watchers)) - always its own directory, never shared with the main store (see why below). |

## Usage in your own code

`WatchDiff` and `DbWatcher` are autowired services - inject them into any class the
container builds. Every feature documented in `watchdiff-core`'s own README (diff modes,
webhooks, AI summaries, retries, flapping detection, maintenance windows, ...) is available
through the same `WatchConfig`/`DbWatchConfig` options - this bundle only adds the DI wiring
and console commands, it doesn't reimplement or restrict anything from the core library. A
few concrete examples:

### Controller

```php
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WatchDiff\Core\WatchDiff;

final class WatcherStatusController
{
    #[Route('/admin/watchers', methods: ['GET'])]
    public function __invoke(WatchDiff $watchDiff): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn ($s) => ['label' => $s->label, 'paused' => $s->paused, 'changes' => $s->changesCount],
            $watchDiff->status(),
        ));
    }
}
```

### Doctrine repository

Extra constructor arguments after `ManagerRegistry` are autowired too, same as any service:

```php
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use WatchDiff\Core\Db\DbWatcher;

final class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly DbWatcher $dbWatcher)
    {
        parent::__construct($registry, Product::class);
    }

    public function watchStockLevels(): void
    {
        $this->dbWatcher->watch(new \WatchDiff\Core\Db\DbWatchConfig(
            connectionString: 'mysql://user:pass@localhost/shop',
            table: 'products',
            query: 'SELECT SUM(stock) FROM products',
            diffMode: \WatchDiff\Core\Db\DbDiffMode::Value,
        ));
    }
}
```

### API Platform state processor

State processors/providers are plain Symfony services, so the same injection works there:

```php
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use WatchDiff\Core\Config\WatchConfig;
use WatchDiff\Core\WatchDiff;

final class WatchedUrlProcessor implements ProcessorInterface
{
    public function __construct(private readonly WatchDiff $watchDiff) {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->watchDiff->watch(new WatchConfig(url: $data->url, target: $data->selector));

        return $data;
    }
}
```

## Reacting to changes: events

`watchdiff:tick` dispatches `WatchDiff\Symfony\Event\WatcherChangedEvent` (carrying the
`DiffReport`) for every watcher that changed, through the standard Symfony event dispatcher -
this is the integration point for Messenger, Mailer, Monolog, or anything else, without this
bundle taking a hard dependency on any of them:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use WatchDiff\Symfony\Event\WatcherChangedEvent;

#[AsEventListener]
final class DispatchWatcherChangeToMessenger
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    public function __invoke(WatcherChangedEvent $event): void
    {
        $this->bus->dispatch(new NotifyWatcherChanged($event->report->url, $event->report->label));
    }
}
```

```php
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use WatchDiff\Symfony\Event\WatcherChangedEvent;

#[AsEventListener]
final class LogWatcherChange
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function __invoke(WatcherChangedEvent $event): void
    {
        $this->logger->info('Watcher changed', ['url' => $event->report->url, 'changes' => \count($event->report->changes)]);
    }
}
```

For errors, there's no bundle-level event (config-file-driven watchers have no way to
express an `onError` closure from JSON) - but a programmatically-built `WatchConfig` can
still set `onError` directly and dispatch its own event/Messenger message from there:

```php
new WatchConfig(
    url: $url,
    onError: fn ($exception, $config) => $eventDispatcher->dispatch(new MyWatcherErrorEvent($exception, $config)),
);
```

## Database watchers

`WatchDiff\Core\Db\DbWatcher` is registered as a separate public, autowired service, backed
by its own storage directory (`store.db_json_directory`) rather than the main `StoreInterface`
service:

```php
use WatchDiff\Core\Db\DbWatchConfig;
use WatchDiff\Core\Db\DbWatcher;

final class OrdersMonitor
{
    public function __construct(private readonly DbWatcher $dbWatcher)
    {
    }

    public function watchPendingOrders(): void
    {
        $this->dbWatcher->watch(new DbWatchConfig(
            connectionString: 'mysql://user:pass@localhost/shop',
            table: 'orders',
            primaryKey: ['id'],
        ));
    }
}
```

The separate storage directory is deliberate, not a Symfony-specific quirk: `watchdiff:clean`
deletes any key in the main store that isn't a currently-configured URL/file/cert watcher -
if `DbWatcher` shared that storage, its own watchers would look like orphans and get deleted.

`watchdiff:db` (above) wraps this same service for a one-off CLI check; the
`watchdiff-core` package also ships its own `vendor/bin/watchdiff db ...` binary that works
identically regardless of framework, if you'd rather not go through `bin/console`.

## Scheduling

This bundle does not register a Symfony Scheduler task itself (to avoid forcing that
component on apps that just use cron). Wire it up yourself if you use Scheduler:

```php
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;

#[AsSchedule('watchdiff')]
final class WatchDiffSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::every('1 minute', new RunWatchDiffTick()),
        );
    }
}
```

(where `RunWatchDiffTick` is your own message with a handler that calls the `WatchDiff`
service's `tick()`, or shells out to `watchdiff:tick`). Cron calling
`bin/console watchdiff:tick` on a short interval works just as well and needs no extra code.

## Store drivers

- **`json`** (default): one `.json` file per watcher under `store.json_directory`, easy to
  inspect/diff in version control for debugging, fine for a handful to a few dozen watchers.
- **`sqlite`**: a single `store.sqlite_path` file, better suited to many watchers or frequent
  ticks, needs `ext-pdo_sqlite`.

Both implement `WatchDiff\Core\Contract\StoreInterface` - to use your own backend (Redis,
a Doctrine entity, ...), implement that interface yourself and override the
`WatchDiff\Core\Contract\StoreInterface` service definition in your own app's container
configuration instead of using `store.driver`.

## What this bundle does and doesn't add

This bundle wraps every `watchdiff-core` CLI command as a `watchdiff:*` command, **except
`serve`**. `serve` binds its own raw TCP socket and runs its own accept loop (see
`watchdiff-core`'s README) - standing that up *inside* a Symfony app, which already has its
own HTTP server/routing, is architecturally the wrong shape, not just an unwrapped command.
If you want watcher status reachable over HTTP in a Symfony app, build a real controller
instead - see the [Controller example](#controller) above, which does the same job
(`WatchDiff::status()` as JSON) the idiomatic way. `watchdiff-core`'s own
`vendor/bin/watchdiff serve` binary is still there if you want the raw version regardless.

## Security

This bundle adds no HTTP-facing surface of its own (no controllers, no routes) - all of
`watchdiff-core`'s hardening (SSRF guards, response size caps, SQL-identifier validation,
credential-redacted storage keys, ...) applies unchanged. See `watchdiff-core`'s own README,
"Security considerations" section, for the full list. The one bundle-specific note: don't
point `store.json_directory`/`store.sqlite_path`/`store.db_json_directory` at a path your
web server serves directly (e.g. inside `public/`) - stored snapshots can contain the full
content of whatever you're watching.

## Commands

### `watchdiff:init`

```bash
bin/console watchdiff:init [--config=path/to/other.json]
```

Creates a starter config file at `config_path` (or `--config`) with one example watcher.
Refuses to overwrite an existing file.

### `watchdiff:tick`

```bash
bin/console watchdiff:tick [--config=path/to/other.json]
```

Checks every watcher from the config file whose `interval` has elapsed and prints a summary
line per changed watcher. This is the one to put on a cron schedule.

### `watchdiff:check`

```bash
bin/console watchdiff:check https://example.com --target .price --diff-mode word
```

Runs a single one-off check against a URL, independent of any config file - useful for a
quick manual check, or for wiring a one-off check into your own application code path
(e.g. a debug command, an admin action).

### `watchdiff:validate`

```bash
bin/console watchdiff:validate [--config=path/to/other.json]
```

Validates the config file: each watcher has a `url`/`localPath`/`certHost`, `url` is a
well-formed `http(s)://` URL, `interval` is a positive integer, `diffMode` is valid,
`jsonPath` starts with `$`, `schedule` is a parseable cron expression, and every `webhooks`
entry is a well-formed `http(s)://` URL. Every problem across every watcher is reported in
one pass, not just the first one found. Exits non-zero on any error - safe to run as a CI/
deploy-pipeline check before `watchdiff:tick` ever runs against a bad config.

### `watchdiff:list`

```bash
bin/console watchdiff:list [--config=path/to/other.json]
```

Prints every configured watcher with its paused state and check/change/error counters -
the config-file-driven equivalent of calling `WatchDiff::status()` yourself.

### `watchdiff:pause` / `watchdiff:resume`

```bash
bin/console watchdiff:pause <id|url|tag>
bin/console watchdiff:resume <id|url|tag>
```

Pauses/resumes a watcher (or every watcher sharing a `tags` entry) - `tick()` skips paused
watchers entirely. The pause flag is stored alongside the watcher's snapshots, so it
persists across deploys as long as the store itself does (don't point `store.json_directory`
at an ephemeral/per-request-wiped path).

### `watchdiff:clean`

```bash
bin/console watchdiff:clean [--config=path/to/other.json] [--yes]
```

Deletes stored data (snapshots, reports, state) for any watcher key that's no longer in the
config file - e.g. after removing a watcher from `watchdiff.config.json`. Lists what it's
about to delete and asks for confirmation unless `--yes`/`-y` is passed; refuses to delete
anything in a non-interactive run (cron, CI) without `--yes`, rather than deleting silently.

### `watchdiff:diff` / `watchdiff:history` / `watchdiff:snapshot` / `watchdiff:reports`

```bash
bin/console watchdiff:diff https://example.com          # last stored change
bin/console watchdiff:history https://example.com       # list of stored snapshots
bin/console watchdiff:snapshot https://example.com       # latest snapshot's raw content
bin/console watchdiff:reports https://example.com        # list of stored change reports
```

Read-only inspection of what's stored for a single URL, independent of the config file -
useful when debugging why a watcher did or didn't alert.

### `watchdiff:clear`

```bash
bin/console watchdiff:clear https://example.com
```

Deletes everything stored for a single URL (snapshots, reports, state) - the single-URL
counterpart to `watchdiff:clean`, which only removes watchers no longer in the config file.

### `watchdiff:export`

```bash
bin/console watchdiff:export https://example.com --format csv --type reports --output reports.csv
```

Exports stored reports or snapshots as CSV or JSON, to stdout or to a file (`--output`).

### `watchdiff:compare`

```bash
bin/console watchdiff:compare https://example.com/v1 https://example.com/v2 --target .price
```

Diffs two arbitrary URLs against each other directly, without storing anything - useful for
a one-off "are these two pages different" check.

### `watchdiff:db`

```bash
bin/console watchdiff:db "mysql://user:pass@localhost/shop" orders --primary-key id
```

One-off check against a database table, through the same `DbWatcher` service described in
[Database watchers](#database-watchers) below (so it uses `store.db_json_directory`, not
the main store).

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Code style aligns consecutive `=` and `=>` signs within a block, done by hand rather than by
`php-cs-fixer fix` (see `watchdiff-core`'s README, "Development" section, for why) - CI only
runs PHPStan and PHPUnit, not a style-lint gate.

## License

This project is licensed under the BSD 3-Clause License.

You are free to use, modify, and distribute this software in source and binary forms,
with or without modification, provided the copyright notice and disclaimer are
retained. No copyleft restrictions - compatible with MIT and proprietary projects.
