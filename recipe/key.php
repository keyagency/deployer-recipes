<?php

namespace Deployer;

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
