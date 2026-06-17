# Changelog

All notable changes to this package are documented here. This project follows
[Semantic Versioning](https://semver.org/).

## [1.0.8] - 2026-06-17

### Added

- `deploy:unlock` runs after `deploy:failed` on every platform
  (`recipe/key.php`).

### Changed

- `key:build:resources` is wired into the deploy flow for Laravel and Statamic
  via `after('deploy:vendors', 'key:build:resources')`.
- The Statamic `deploy` task is overridden to drop `statamic:stache:warm`;
  `statamic:stache:clear` is kept and moved after `deploy:publish`.

## [1.0.6] - 2026-06-15

### Added

- Laravel `key:build:resources` task (`recipe/key/laravel/build.php`): builds
  the frontend locally in a temporary git worktree (using the remote `.env`)
  and uploads `public/build/` to the server. Configurable via
  `key_build_tmp_path` and `key_build_command`.

## [1.0.5] - 2026-06-12

### Changed

- The release workflow authenticates with the `RELEASE_TOKEN` secret when
  available, so releases are authored by that account, and replaces an
  existing release when the same tag is pushed again.

## [1.0.4] - 2026-06-12

### Added

- WordPress (Bedrock) wrapper `recipe/key/bedrock.php` with Bedrock shared
  files/dirs defaults, a deploy flow that includes `deploy:vendors`, and two
  tasks: `key:build:resources` (builds the theme configured via
  `key_bedrock_theme` locally and uploads the `key_build_uploads` artifacts)
  and `key:install:languages` (installs the `key_languages` via wp-cli).
  Neither task is wired into the deploy flow.
- Deployer defaults on every platform: `git_tty` (`true`), `writable_mode`
  (`'skip'`) and `allow_anonymous_stats` (`false`).
- `key_platform`: set by each wrapper recipe (`LARAVEL`, `STATAMIC`,
  `OCTOBER CMS`, `BEDROCK`) and used to prefix task descriptions and log output
  via the new `key_label()` helper in `recipe/helpers/general.php`.

### Changed

- **Breaking:** `key_sync_map` is replaced by a `key_sync_<type>` option per
  sync type (Statamic: `key_sync_content`, `key_sync_assets`,
  `key_sync_forms`, `key_sync_addons`; October CMS: `key_sync_theme`,
  `key_sync_storage`). Each option is a flat list of paths: a trailing slash
  marks a directory, no trailing slash a single file. Paths can be appended
  with `add('key_sync_<type>', [...])` instead of redeclaring the full map.
- **Breaking:** `key_sync_map_has()` is renamed to `key_sync_has()`.
- The Statamic `key_sync_addons` option defaults to `['resources/addons/']`
  instead of empty.
- The shared helpers moved to `recipe/helpers/`: `recipe/sync.php` is now
  `recipe/helpers/sync.php`, and `key_label()` lives in
  `recipe/helpers/general.php`.
- The Statamic wrapper shares `public/assets` and `content` between releases
  via `shared_dirs`.

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
