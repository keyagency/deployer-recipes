<?php

namespace Deployer;

use Deployer\Utility\Httpie;

require_once 'recipe/common.php';

set('slack_webhook', '');
set('slack_title', '{{application}}');
set('slack_text', 'Deploy of `{{target}}` on *{{hostname}}*');

set('healthcheck_url', '');
set('healthcheck_expected_status', 200);

function key_slack_notify(string $color, string $status): void
{
    $webhook = get('slack_webhook');
    if (empty($webhook)) {
        return;
    }
    $payload = [
        'attachments' => [[
            'color' => $color,
            'title' => get('slack_title'),
            'text' => get('slack_text') . ' — ' . $status,
            'mrkdwn_in' => ['text'],
        ]],
    ];
    // Fire-and-forget: a failed notification must not abort the deploy.
    Httpie::post($webhook)->jsonBody($payload)->send();
}

desc('Notify Slack that the deploy started');
task('key:notify:start', function () {
    key_slack_notify('#cccccc', 'started');
});

desc('Notify Slack that the deploy succeeded');
task('key:notify:success', function () {
    key_slack_notify('good', 'succeeded');
});

desc('Notify Slack that the deploy failed');
task('key:notify:failure', function () {
    key_slack_notify('danger', 'failed');
});

desc('HTTP healthcheck against healthcheck_url; fails the deploy on mismatch');
task('key:healthcheck', function () {
    $url = get('healthcheck_url');
    if (empty($url)) {
        return;
    }
    $expected = (int) get('healthcheck_expected_status');
    $status = (int) runLocally("curl -s -o /dev/null -w '%{http_code}' {{healthcheck_url}}");
    if ($status !== $expected) {
        throw new \RuntimeException("Healthcheck failed for $url: expected $expected, got $status");
    }
});
