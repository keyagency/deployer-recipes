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
set('slack_webhook', getenv('SLACK_WEBHOOK') ?: '');

// Optional: enable the post-deploy healthcheck
set('healthcheck_url', 'https://example.com/up');
set('healthcheck_expected_status', 200);
```

Each wrapper loads Deployer's base recipe for that platform plus the shared
`key` recipe, which registers:

- `key:notify:start` / `key:notify:success` / `key:notify:failure` — Slack
  messages, wired into the deploy flow (`before deploy`, `after deploy:success`,
  `after deploy:failed`).
- `key:healthcheck` — runs after `deploy:success` and fails the deploy if the
  HTTP status of `healthcheck_url` does not match `healthcheck_expected_status`.

## Configuration

| Key | Default | Description |
| --- | --- | --- |
| `slack_webhook` | `''` | Slack incoming-webhook URL. Empty = notifications disabled. |
| `slack_title` | `{{application}}` | Slack message title. |
| `slack_text` | `Deploy of \`{{target}}\` on *{{hostname}}*` | Slack message body. |
| `healthcheck_url` | `''` | URL to check after a successful deploy. Empty = disabled. |
| `healthcheck_expected_status` | `200` | Expected HTTP status. |

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
