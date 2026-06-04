# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [1.0.1] - 2026-06-04

### Added

- Statamic wrapper: `key:build:resources` task — builds the frontend locally in
  a temporary git worktree (using the remote `.env`) and uploads `public/build/`
  to the server. Configurable via `key_build_tmp_path` and `key_build_command`.

### Changed

- **BREAKING:** Prefixed all custom config keys with `key_` to avoid collisions
  with Deployer's own options. Rename in your `deploy.php`:
  `slack_webhook` → `key_slack_webhook`, `slack_title` → `key_slack_title`,
  `slack_text` → `key_slack_text`, `healthcheck_url` → `key_healthcheck_url`,
  `healthcheck_expected_status` → `key_healthcheck_expected_status`.
  (Released as a patch because no projects depend on `1.0.0` yet.)

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
