<?php

namespace Deployer;

use Deployer\Utility\Httpie;

require_once 'recipe/common.php';

set('key_slack_webhook', '');
set('key_slack_title', '{{application}}');
set('key_slack_text', 'Deploy of `{{target}}` on *{{hostname}}*');

set('key_healthcheck_url', '');
set('key_healthcheck_expected_status', 200);

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

desc('HTTP healthcheck against key_healthcheck_url; fails the deploy on mismatch');
task('key:healthcheck', function () {
    $url = get('key_healthcheck_url');
    if (empty($url)) {
        return;
    }
    $expected = (int) get('key_healthcheck_expected_status');
    /**
     * Pass nothrow=true so a non-2xx response returns a status instead of throwing,
     * letting our comparison below produce the clear RuntimeException message.
     */
    $info = [];
    fetch($url, 'get', [], null, $info, true);
    $status = (int) ($info['http_code'] ?? 0);
    if ($status !== $expected) {
        throw new \RuntimeException("Healthcheck failed for $url: expected $expected, got $status");
    }
});

before('deploy', 'key:notify:start');
after('deploy:success', 'key:healthcheck');
after('deploy:success', 'key:notify:success');
after('deploy:failed', 'key:notify:failure');
