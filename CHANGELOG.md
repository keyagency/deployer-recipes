# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [1.0.3] - 2026-06-11

### Added

- October CMS sync tasks: `key:sync:theme` (per-theme `content/` and `meta/`,
  themes configurable via `key_october_themes`) and `key:sync:storage`
  (`storage/app/uploads/` and `storage/app/media/`), with the same
  direction/overwrite prompts as the Statamic sync. The October CMS cache is
  cleared after each sync.
- `key_sync_excludes`: rsync exclude patterns (October CMS defaults to
  `['blocks.yaml']`).
- `key_sync_backup`: when enabled, the destination directory is copied to
  `<dir>-backup` before each sync — works for both October CMS and Statamic.
- Healthcheck retries: `key_healthcheck_retries` (default 3) and
  `key_healthcheck_pause` (default 5s), so one hiccup right after the release
  switch no longer fails the deploy.
- The Slack webhook is read from `KEY_SLACK_WEBHOOK` in the environment or the
  project's `.env`. An explicit `set('key_slack_webhook', ...)` takes
  precedence.

### Changed

- Extracted the shared sync helpers into `recipe/sync.php`;
  `recipe/key/statamic/sync.php` now only contains the Statamic map, tasks and
  cache refresh.

### Fixed

- Sync tasks now respect a non-default SSH port configured on the host and no
  longer require `remote_user` to be set (ssh config decides when it is empty).
- Sync types without configured paths in `key_sync_map` are skipped instead of
  being offered as a silent no-op (the default `addons` map ships empty).
- `key:build:resources` quotes the temporary path and branch for the shell, and
  its cleanup falls back to `rm -rf` plus `git worktree prune` so leftover or
  stale worktree state can never break the task.

## [1.0.2] - 2026-06-04

### Added

- Statamic content sync tasks: `key:sync:content` (optionally including forms,
  addons and assets), `key:sync:assets`, `key:sync:forms`, `key:sync:addons`.
  Rsync-based, with a single direction/overwrite/delete prompt and a Statamic
  cache refresh afterwards. Paths are configurable via `key_sync_map`, and
  sources that do not exist are skipped instead of failing the sync.

### Changed

- Split the Statamic recipe into per-feature files
  (`recipe/key/statamic/build.php`, `recipe/key/statamic/sync.php`) loaded by
  `recipe/key/statamic.php`.

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
