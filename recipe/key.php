<?php

namespace Deployer;

use Deployer\Utility\Httpie;

require_once 'recipe/common.php';
require_once __DIR__ . '/helpers/general.php';

// Deployer defaults for all platforms
set('git_tty', true);
set('writable_mode', 'skip');
set('allow_anonymous_stats', false);

/**
 * Read a variable from the real environment or, as a fallback, from the
 * project's .env file in the current working directory. Returns null when
 * the variable is not defined in either place.
 */
function key_env(string $name): ?string
{
    $value = getenv($name);
    if ($value !== false) {
        return $value;
    }

    $file = getcwd() . '/.env';
    if (!is_readable($file)) {
        return null;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*(?:export\s+)?' . preg_quote($name, '/') . '\s*=\s*(.*)$/', $line, $matches)) {
            return trim($matches[1], " \t\"'");
        }
    }
    return null;
}

// Lazy default: resolved on first get(), after deploy.php has fully loaded.
set('key_slack_webhook', fn () => key_env('KEY_SLACK_WEBHOOK') ?? '');
set('key_slack_title', '{{application}}');
set('key_slack_text', 'Deploy of `{{target}}` to *{{alias}}* on `{{hostname}}`');

set('key_healthcheck_url', '');
set('key_healthcheck_expected_status', 200);
set('key_healthcheck_retries', 3);
set('key_healthcheck_pause', 5);

function key_slack_notify(string $color, string $status): void
{
    $webhook = get('key_slack_webhook');
    if (empty($webhook)) {
        return;
    }
    $payload = [
        'attachments' => [[
            'color' => $color,
            'title' => get('key_slack_title'),
            'text' => get('key_slack_text') . ' — ' . $status,
            'mrkdwn_in' => ['text'],
        ]],
    ];
    /**
     * Fire-and-forget: nothrow() prevents transport failures from propagating
     * and aborting the deploy when Slack is unreachable.
     */
    Httpie::post($webhook)->jsonBody($payload)->nothrow()->send();
}

desc(key_label('Notify Slack that the deploy started'));
task('key:notify:start', function () {
    key_slack_notify('#cccccc', 'started');
});

desc(key_label('Notify Slack that the deploy succeeded'));
task('key:notify:success', function () {
    key_slack_notify('good', 'succeeded');
});

desc(key_label('Notify Slack that the deploy failed'));
task('key:notify:failure', function () {
    key_slack_notify('danger', 'failed');
});

desc(key_label('HTTP healthcheck against key_healthcheck_url; fails the deploy on mismatch'));
task('key:healthcheck', function () {
    $url = get('key_healthcheck_url');
    if (empty($url)) {
        return;
    }
    $expected = (int) get('key_healthcheck_expected_status');
    $retries = max(1, (int) get('key_healthcheck_retries'));
    $pause = (int) get('key_healthcheck_pause');

    /**
     * Retry a few times: right after the release switch php-fpm/opcache can
     * briefly serve errors, and one hiccup should not fail the whole deploy.
     */
    $status = 0;
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        /**
         * Pass nothrow=true so a non-2xx response returns a status instead of throwing,
         * letting our comparison below produce the clear RuntimeException message.
         */
        $info = [];
        fetch($url, 'get', [], null, $info, true);
        $status = (int) ($info['http_code'] ?? 0);
        if ($status === $expected) {
            return;
        }
        if ($attempt < $retries) {
            warning(key_label("Healthcheck attempt $attempt/$retries for $url got $status (expected $expected), retrying in {$pause}s"));
            sleep($pause);
        }
    }
    throw new \RuntimeException("Healthcheck failed for $url after $retries attempt(s): expected $expected, got $status");
});

before('deploy', 'key:notify:start');
after('deploy:success', 'key:healthcheck');
after('deploy:success', 'key:notify:success');
after('deploy:failed', 'key:notify:failure');
