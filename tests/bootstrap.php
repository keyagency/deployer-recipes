<?php
// Composer autoloader (PHPUnit + Deployer classes)
require __DIR__ . '/../vendor/autoload.php';

// Make Deployer's own recipes resolvable via bare paths (e.g. require 'recipe/common.php')
// exactly like the global dep binary does at runtime.
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../vendor/deployer/deployer');
