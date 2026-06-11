# keyagency/deployer-recipes

Key Agency's shared [Deployer 8](https://deployer.org) recipes: a base `key`
recipe (Slack notifications + healthcheck) and per-platform wrappers with
build and content-sync tasks for Statamic and October CMS.

## Install

```bash
composer require --dev keyagency/deployer-recipes
```

## Supported platforms

| Platform | Require in `deploy.php` | Platform-specific tasks |
| --- | --- | --- |
| Laravel | `vendor/keyagency/deployer-recipes/recipe/key/laravel.php` | — |
| Statamic | `vendor/keyagency/deployer-recipes/recipe/key/statamic.php` | `key:build:resources`, `key:sync:content`, `key:sync:assets`, `key:sync:forms`, `key:sync:addons` |
| October CMS | `vendor/keyagency/deployer-recipes/recipe/key/october.php` | `key:sync:theme`, `key:sync:storage` |

Every wrapper loads Deployer's base recipe for that platform plus the shared
`key` recipe (Slack notifications + healthcheck). Is your platform not listed?
See [Adding a platform](#adding-a-platform).

## Usage

In your project's `deploy.php`, require the wrapper for your platform (see the
table above):

```php
namespace Deployer;

require 'vendor/keyagency/deployer-recipes/recipe/key/statamic.php';

host('production')->set('deploy_path', '/var/www/example');

// Optional: enable the post-deploy healthcheck
set('key_healthcheck_url', 'https://example.com/up');
set('key_healthcheck_expected_status', 200);

// Optional (sync tasks): back up the destination dir before each sync
set('key_sync_backup', true);

// Optional (October CMS): themes synced by key:sync:theme
set('key_october_themes', ['default']);
```

To enable Slack notifications, add the webhook to your project's `.env`
(picked up automatically — no webhook means no notifications):

```dotenv
KEY_SLACK_WEBHOOK=https://hooks.slack.com/services/...
```

A real environment variable `KEY_SLACK_WEBHOOK` (e.g. in CI) takes precedence
over the `.env` file, and an explicit `set('key_slack_webhook', ...)` in
`deploy.php` wins over both.

## Tasks

The shared `key` recipe registers on every platform:

- `key:notify:start` / `key:notify:success` / `key:notify:failure` — Slack
  messages, wired into the deploy flow (`before deploy`, `after deploy:success`,
  `after deploy:failed`).
- `key:healthcheck` — runs after `deploy:success` and fails the deploy if the
  HTTP status of `key_healthcheck_url` does not match
  `key_healthcheck_expected_status`.

The Statamic wrapper additionally registers:

- `key:build:resources` — builds the frontend locally (in a temporary git
  worktree, using the remote `.env`) and uploads `public/build/` to the server.
- `key:sync:content` / `key:sync:assets` / `key:sync:forms` / `key:sync:addons`
  — rsync content between the server and your machine. You are asked once for
  the direction (remote → local by default) and, when pushing to remote, for
  overwrite/delete confirmation; `key:sync:content` can also include forms,
  addons and assets in one run. Paths come from `key_sync_map`. Sources that do
  not exist are skipped, types without configured paths are skipped entirely,
  and a non-default SSH port on the host is respected.

The October CMS wrapper additionally registers:

- `key:sync:theme` / `key:sync:storage` — same rsync-based sync as Statamic.
  `theme` syncs `themes/<theme>/content/` and `themes/<theme>/meta/` for every
  theme in `key_october_themes`; `storage` syncs `storage/app/uploads/` and
  `storage/app/media/`. `blocks.yaml` is excluded by default via
  `key_sync_excludes`. After each sync the October CMS cache is cleared.

The sync tasks share their helpers (`recipe/sync.php`). With
`key_sync_backup` enabled, the destination directory is copied to
`<dir>-backup` before each sync, on both platforms.

These sync/build tasks are not wired into the deploy flow; call them directly,
e.g. `dep key:sync:content <host>`.

All custom config keys are prefixed with `key_` to avoid collisions with
Deployer's own options.

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_slack_webhook` | `KEY_SLACK_WEBHOOK` from env/`.env`, else `''` | Slack incoming-webhook URL. Empty = notifications disabled. |
| `key_slack_title` | `{{application}}` | Slack message title. |
| `key_slack_text` | `Deploy of \`{{target}}\` on *{{hostname}}*` | Slack message body. |
| `key_healthcheck_url` | `''` | URL to check after a successful deploy. Empty = disabled. |
| `key_healthcheck_expected_status` | `200` | Expected HTTP status. |
| `key_healthcheck_retries` | `3` | Attempts before the healthcheck fails the deploy. |
| `key_healthcheck_pause` | `5` | Seconds to wait between healthcheck attempts. |
| `key_build_tmp_path` | `<system temp>/deployer-build` | Statamic: base dir for the temporary build worktree. |
| `key_build_command` | `yarn && yarn build` | Statamic: local build command. |
| `key_sync_map` | per platform | Dirs/files to sync per type (Statamic: `content`, `assets`, `forms`, `addons`; October CMS: `theme`, `storage`). Override to match your project. |
| `key_sync_excludes` | `[]` (October CMS: `['blocks.yaml']`) | Rsync exclude patterns applied to every sync. |
| `key_sync_backup` | `false` | Copy the destination dir to `<dir>-backup` before each sync. |
| `key_october_themes` | `['default']` | October CMS: themes whose `content/` and `meta/` are synced by `key:sync:theme`. |

> No webhook URL is bundled in this package. Each project supplies its own, so
> installing the package never posts to anyone else's Slack.

## Adding a platform

Create `recipe/key/<platform>.php` that requires the matching Deployer base
recipe and the shared `key` recipe:

```php
namespace Deployer;

require_once 'recipe/<platform>.php';
require_once __DIR__ . '/../key.php';
```

Platform-specific features live in per-feature files under
`recipe/key/<platform>/` (e.g. `recipe/key/october/sync.php`), required by the
wrapper. Sync features build on the shared helpers in `recipe/sync.php`:
define a `key_sync_map` with the dirs/files per type and register tasks that
call `key_sync_prompt()` and `key_sync()`.

## About Key Agency

[Key Agency](https://keyagency.nl) is a digital agency based in Amsterdam that
helps brands grow with strategy, content, technology and advertising working
together as one digital ecosystem. Our development team builds and maintains
websites and platforms (Laravel, Statamic, October CMS and WordPress) and uses
these recipes to deploy them.

## Contact

Questions, remarks or suggestions? Reach us at
[development@keyagency.nl](mailto:development@keyagency.nl).
