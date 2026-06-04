# keyagency/deployer-recipes

Key Agency's shared [Deployer 8](https://deployer.org) recipes: a base `key`
recipe (Slack notifications + healthcheck) and thin per-platform wrappers.

## Install

```bash
composer require --dev keyagency/deployer-recipes
```

## Usage

In your project's `deploy.php`, require the wrapper for your platform:

```php
namespace Deployer;

require 'vendor/keyagency/deployer-recipes/recipe/key/laravel.php';
// or .../recipe/key/statamic.php
// or .../recipe/key/october.php

host('production')->set('deploy_path', '/var/www/example');

// Optional: enable Slack notifications (no webhook = no notifications)
set('key_slack_webhook', getenv('SLACK_WEBHOOK') ?: '');

// Optional: enable the post-deploy healthcheck
set('key_healthcheck_url', 'https://example.com/up');
set('key_healthcheck_expected_status', 200);
```

Each wrapper loads Deployer's base recipe for that platform plus the shared
`key` recipe, which registers:

- `key:notify:start` / `key:notify:success` / `key:notify:failure` — Slack
  messages, wired into the deploy flow (`before deploy`, `after deploy:success`,
  `after deploy:failed`).
- `key:healthcheck` — runs after `deploy:success` and fails the deploy if the
  HTTP status of `key_healthcheck_url` does not match
  `key_healthcheck_expected_status`.

The Statamic wrapper additionally registers `key:build:resources`, which builds
the frontend locally (in a temporary git worktree, using the remote `.env`) and
uploads `public/build/` to the server. It is not wired into the deploy flow;
call it directly (`dep key:build:resources <host>`) or hook it yourself.

All custom config keys are prefixed with `key_` to avoid collisions with
Deployer's own options.

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `key_slack_webhook` | `''` | Slack incoming-webhook URL. Empty = notifications disabled. |
| `key_slack_title` | `{{application}}` | Slack message title. |
| `key_slack_text` | `Deploy of \`{{target}}\` on *{{hostname}}*` | Slack message body. |
| `key_healthcheck_url` | `''` | URL to check after a successful deploy. Empty = disabled. |
| `key_healthcheck_expected_status` | `200` | Expected HTTP status. |
| `key_build_tmp_path` | `<system temp>/deployer-build` | Statamic: base dir for the temporary build worktree. |
| `key_build_command` | `yarn && yarn build` | Statamic: local build command. |

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
