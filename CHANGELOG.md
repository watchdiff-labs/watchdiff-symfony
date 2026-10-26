# Changelog

## Unreleased

- Added `watchdiff:init`, `watchdiff:diff`, `watchdiff:history`, `watchdiff:snapshot`,
  `watchdiff:clear`, `watchdiff:reports`, `watchdiff:export`, `watchdiff:compare`, and
  `watchdiff:db` console commands, bringing command coverage close to parity with
  `watchdiff-core`'s own CLI (`watchdiff:serve` is deliberately not wrapped - see the
  README's "What this bundle does and doesn't add").
- `watchdiff:tick` now dispatches a `WatchDiff\Symfony\Event\WatcherChangedEvent` for every
  changed watcher, via the optional, autowired `EventDispatcherInterface` - the integration
  point for Messenger/Mailer/Monolog and any other listener-based notification.

## 0.1.0

- Initial release: DI wiring for `WatchDiff` and `DbWatcher`, JSON/SQLite store
  configuration, and the `watchdiff:tick`, `watchdiff:validate`, `watchdiff:list`,
  `watchdiff:pause`, `watchdiff:resume`, `watchdiff:clean`, and `watchdiff:check` commands.
