# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-06-04

### Added

- `recipe/key.php`: shared, platform-agnostic recipe with safe config defaults
  (no Slack webhook or healthcheck URL baked in).
- Slack notify tasks `key:notify:start`, `key:notify:success`,
  `key:notify:failure` — no-op when `slack_webhook` is empty.
- `key:healthcheck` task — verifies the deployed site's HTTP status after a
  successful deploy; no-op when `healthcheck_url` is empty.
- Deploy-flow hooks wiring the notify and healthcheck tasks into `deploy`,
  `deploy:success` and `deploy:failed`.
- Per-platform wrappers: `recipe/key/laravel.php`, `recipe/key/statamic.php`,
  `recipe/key/october.php`.
