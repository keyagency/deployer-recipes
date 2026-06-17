# keyagency/deployer-recipes

Key Agency's shared [Deployer 8](https://deployer.org) recipes: a base `key`
recipe (Slack notifications + healthcheck) and per-platform wrappers with
build and content-sync tasks.

## Install

```bash
composer require --dev keyagency/deployer-recipes
```

## Supported platforms

Each platform has its own section below with everything you need: the require,
the tasks and the configuration.

| Platform | Section | Platform docs |
| --- | --- | --- |
| Laravel | [Laravel](#laravel) | [laravel.com/docs](https://laravel.com/docs) |
| Statamic | [Statamic](#statamic) | [statamic.dev](https://statamic.dev) |
| October CMS | [October CMS](#october-cms) | [docs.octobercms.com](https://docs.octobercms.com) |
| WordPress (Bedrock) | [WordPress (Bedrock)](#wordpress-bedrock) | [roots.io/bedrock/docs](https://roots.io/bedrock/docs/) |

Is your platform not listed? See [Adding a platform](#adding-a-platform).

## All platforms

Every wrapper loads Deployer's base recipe for that platform plus the shared
`key` recipe. In your project's `deploy.php`, require the wrapper for your
platform and define your host:

```php
namespace Deployer;

require 'vendor/keyagency/deployer-recipes/recipe/key/<platform>.php';

host('production')->set('deploy_path', '/var/www/example');
```

The shared `key` recipe registers on every platform:

- `key:notify:start` / `key:notify:success` / `key:notify:failure` — Slack
  messages, wired into the deploy flow (`before deploy`, `after deploy:success`,
  `after deploy:failed`).
- `key:healthcheck` — runs after `deploy:success` and fails the deploy if the
  HTTP status of `key_healthcheck_url` does not match
  `key_healthcheck_expected_status`.

It also runs `deploy:unlock` after `deploy:failed`, so a failed deploy never
leaves the release locked.

To enable Slack notifications, add the webhook to your project's `.env`
(picked up automatically — no webhook means no notifications):

```dotenv
KEY_SLACK_WEBHOOK=https://hooks.slack.com/services/...
```

A real environment variable `KEY_SLACK_WEBHOOK` (e.g. in CI) takes precedence
over the `.env` file, and an explicit `set('key_slack_webhook', ...)` in
`deploy.php` wins over both.

> No webhook URL is bundled in this package. Each project supplies its own, so
> installing the package never posts to anyone else's Slack.

All custom config keys are prefixed with `key_` to avoid collisions with
Deployer's own options. The shared recipe also sets a few Deployer defaults:
`git_tty` (`true`), `writable_mode` (`'skip'`) and `allow_anonymous_stats`
(`false`). Override them in your project's `deploy.php` when needed.

### Shared configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_platform` | per wrapper (`LARAVEL`, `STATAMIC`, `OCTOBER CMS`, `BEDROCK`) | Label that prefixes task descriptions and log output, e.g. `[STATAMIC] Syncing ...`. |
| `key_slack_webhook` | `KEY_SLACK_WEBHOOK` from env/`.env`, else `''` | Slack incoming-webhook URL. Empty = notifications disabled. |
| `key_slack_title` | `{{application}}` | Slack message title. |
| `key_slack_text` | `Deploy of \`{{target}}\` on *{{hostname}}*` | Slack message body. |
| `key_healthcheck_url` | `''` | URL to check after a successful deploy. Empty = disabled. |
| `key_healthcheck_expected_status` | `200` | Expected HTTP status. |
| `key_healthcheck_retries` | `3` | Attempts before the healthcheck fails the deploy. |
| `key_healthcheck_pause` | `5` | Seconds to wait between healthcheck attempts. |

## How syncing works

Statamic and October CMS ship `key:sync:*` tasks that rsync content between
the server and your machine. They are not wired into the deploy flow; call
them directly, e.g. `dep key:sync:content production`.

- You are asked once for the direction (remote → local by default) and, when
  pushing to remote, for overwrite/delete confirmation.
- Every sync type has its own `key_sync_<type>` option holding a flat list of
  paths: a trailing slash marks a directory (which may mirror deletions and
  gets the optional backup), no trailing slash marks a single file (never
  deleted). Use Deployer's `add()` to append project-specific paths without
  redeclaring the defaults:

  ```php
  add('key_sync_content', ['resources/navigation/']);
  ```

- Sources that do not exist are skipped, types without configured paths are
  skipped entirely, and a non-default SSH port on the host is respected.
- With `key_sync_backup` enabled, the destination directory is copied to
  `<dir>-backup` before each sync.

### Sync configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_sync_<type>` | per platform | Paths to sync per type; see the platform sections for the available types and defaults. Extend with `add()` or override with `set()`. |
| `key_sync_excludes` | `[]` (October CMS: `['blocks.yaml']`) | Rsync exclude patterns applied to every sync. |
| `key_sync_backup` | `false` | Copy the destination dir to `<dir>-backup` before each sync. |

## Laravel

```php
require 'vendor/keyagency/deployer-recipes/recipe/key/laravel.php';
```

Loads Deployer's `laravel` recipe plus the shared `key` recipe.

### Tasks

- `key:build:resources` — builds the frontend locally (in a temporary git
  worktree, using the remote `.env`) and uploads `public/build/` to the server.
  Wired into the deploy flow via `after('deploy:vendors', 'key:build:resources')`;
  override the `deploy` task to change or remove it.

### Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_build_tmp_path` | `<system temp>/deployer-build` | Base dir for the temporary build worktree. |
| `key_build_command` | `yarn && yarn build` | Local build command. |

## Statamic

```php
require 'vendor/keyagency/deployer-recipes/recipe/key/statamic.php';
```

Loads Deployer's `statamic` recipe plus the shared `key` recipe, and shares
`public/assets` and `content` between releases. The `deploy` task is overridden
to drop `statamic:stache:warm`; `statamic:stache:clear` is kept so new content
is picked up (the Stache lives in shared storage and the watcher is disabled in
production), but the slow warming is skipped and the Stache rebuilds lazily on
the next request, which avoids slow deploys on large sites. Make sure the
watcher is disabled in production (`STATAMIC_STACHE_WATCHER=auto`).

### Tasks

- `key:build:resources` — builds the frontend locally (in a temporary git
  worktree, using the remote `.env`) and uploads `public/build/` to the server.
  Wired into the deploy flow via `after('deploy:vendors', 'key:build:resources')`;
  override the `deploy` task to change or remove it.
- `key:sync:content` / `key:sync:assets` / `key:sync:forms` / `key:sync:addons`
  — see [How syncing works](#how-syncing-works). `key:sync:content` can also
  include forms, addons and assets in one run. After each sync the Statamic
  stache is refreshed.

### Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_build_tmp_path` | `<system temp>/deployer-build` | Base dir for the temporary build worktree. |
| `key_build_command` | `yarn && yarn build` | Local build command. |
| `key_sync_content` | `['content/', 'resources/users/', 'resources/sites.yaml', 'resources/preferences.yaml']` | Paths synced by `key:sync:content`. |
| `key_sync_assets` | `['public/assets/']` | Paths synced by `key:sync:assets`. |
| `key_sync_forms` | `['resources/forms/', 'resources/blueprints/forms/']` | Paths synced by `key:sync:forms`. |
| `key_sync_addons` | `['resources/addons/']` | Paths synced by `key:sync:addons`. |

## October CMS

```php
require 'vendor/keyagency/deployer-recipes/recipe/key/october.php';
```

Loads Deployer's `laravel` recipe (October CMS is built on Laravel) plus the
shared `key` recipe.

### Tasks

- `key:sync:theme` / `key:sync:storage` — see
  [How syncing works](#how-syncing-works). `theme` syncs
  `themes/<theme>/content/` and `themes/<theme>/meta/` for every theme in
  `key_october_themes`; `storage` syncs `storage/app/uploads/` and
  `storage/app/media/`. `blocks.yaml` is excluded by default. After each sync
  the October CMS cache is cleared.

To sync an extra theme, only the theme list needs to change:

```php
set('key_october_themes', ['default', 'second-theme']);
```

### Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_october_themes` | `['default']` | Themes whose `content/` and `meta/` are synced by `key:sync:theme`. |
| `key_sync_theme` | derived from `key_october_themes` | Paths synced by `key:sync:theme`. Set the themes before calling `add('key_sync_theme', ...)`. |
| `key_sync_storage` | `['storage/app/uploads/', 'storage/app/media/']` | Paths synced by `key:sync:storage`. |

## WordPress (Bedrock)

```php
require 'vendor/keyagency/deployer-recipes/recipe/key/bedrock.php';

set('key_bedrock_theme', 'my-theme');
```

Loads Deployer's `common` recipe (with `deploy:vendors` in the deploy flow,
since Bedrock is composer-based) plus the shared `key` recipe, and shares
`.env`, `web/.htaccess` and `web/app/uploads` between releases.

### Tasks

- `key:build:resources` — runs `key_build_command` locally in
  `web/app/themes/<key_bedrock_theme>` and uploads the build artifacts from
  `key_build_uploads` (default `assets/js/` and `style.css`) to the same theme
  path on the server. Skips with a warning when `key_bedrock_theme` is not set.
- `key:install:languages` — downloads `wp-cli.phar` into the release and
  installs the core, plugin and theme languages for every language in
  `key_languages`.

Neither task is wired into the deploy flow; call them directly or wire them in
your project's `deploy.php`:

```php
after('deploy:vendors', 'key:build:resources');
before('deploy:publish', 'key:install:languages');
```

### Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_bedrock_theme` | `''` | Theme folder name; required for `key:build:resources`. |
| `key_bedrock_theme_path` | `web/app/themes/<key_bedrock_theme>` | Theme path; override for a non-standard layout. |
| `key_build_command` | `yarn && yarn build` | Local build command. |
| `key_build_uploads` | `['assets/js/', 'style.css']` | Build artifacts to upload, relative to the theme path. Trailing slash = directory, no slash = single file. |
| `key_languages` | `['nl_NL']` | Languages installed by `key:install:languages`. |

## Adding a platform

Create `recipe/key/<platform>.php` that requires the matching Deployer base
recipe and the shared `key` recipe:

```php
namespace Deployer;

require_once 'recipe/<platform>.php';

set('key_platform', '<PLATFORM>');

require_once __DIR__ . '/../key.php';
```

`key_platform` must be set before requiring `key.php`, so the task
descriptions pick up the `[<PLATFORM>]` prefix.

Platform-specific features live in per-feature files under
`recipe/key/<platform>/` (e.g. `recipe/key/october/sync.php`), required by the
wrapper. Sync features build on the shared helpers in `recipe/helpers/sync.php`:
define a `key_sync_<type>` option per sync type (a flat list of paths) and
register tasks that call `key_sync_prompt()` and `key_sync()`.

## About Key Agency

[Key Agency](https://keyagency.nl) is a digital agency based in Amsterdam that
helps brands grow with strategy, content, technology and advertising working
together as one digital ecosystem. Our development team builds and maintains
websites and platforms (Laravel, Statamic, October CMS and WordPress) and uses
these recipes to deploy them.

## Contact

Questions, remarks or suggestions? Reach us at
[development@keyagency.nl](mailto:development@keyagency.nl).
