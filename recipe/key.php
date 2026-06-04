<?php

namespace Deployer;

use Deployer\Utility\Httpie;

// Key Agency shared recipe. Platform-agnostic; included by the per-platform
// wrappers in recipe/key/.
require_once 'recipe/common.php';

// Slack webhook is NEVER baked in. Empty default = notify tasks are a no-op,
// so external users of this public package never post to Key Agency's Slack.
set('slack_webhook', '');
set('slack_title', '{{application}}');
set('slack_text', 'Deploy of `{{target}}` on *{{hostname}}*');

// Healthcheck is a no-op until a project sets healthcheck_url.
set('healthcheck_url', '');
set('healthcheck_expected_status', 200);

// Posts a Slack message. No-op when slack_webhook is empty so the public
// package never sends anywhere unless a project configures its own webhook.
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
    // Deployer\Utility\Httpie — real API: ::post(url)->jsonBody(array)->send()
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
