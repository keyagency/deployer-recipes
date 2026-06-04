<?php
// Composer autoloader (PHPUnit + Deployer classes)
require __DIR__ . '/../vendor/autoload.php';

// Make Deployer's own recipes resolvable via bare paths (e.g. require 'recipe/common.php')
// exactly like the global dep binary does at runtime.
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../vendor/deployer/deployer');

// Httpie::send() references DEPLOYER_VERSION in the User-Agent header. The
// constant is normally set by Deployer::fromComposer(), which is not called in
// unit tests, so we define a stub value here to avoid an "Undefined constant"
// fatal when tests exercise the HTTP layer.
if (!defined('DEPLOYER_VERSION')) {
    define('DEPLOYER_VERSION', '0.0.0-test');
}
