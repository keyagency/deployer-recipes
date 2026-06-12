<?php

namespace Deployer;

/**
 * Prefix a message with the platform label set by the wrapper recipe via
 * key_platform (e.g. "[STATAMIC] ..."). Returns the message unchanged when
 * no platform is configured, e.g. when a recipe is loaded standalone.
 */
function key_label(string $message): string
{
    $platform = get('key_platform', '');
    return $platform === '' ? $message : "[$platform] $message";
}
