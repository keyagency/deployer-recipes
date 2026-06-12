<?php

namespace Deployer;

require_once 'recipe/statamic.php';

// Shared files/dirs between deployments
add('shared_files', []);
add('shared_dirs', ['public/assets', 'content']);
add('writable_dirs', []);

set('key_platform', 'STATAMIC');

require_once __DIR__ . '/../key.php';
require_once __DIR__ . '/statamic/build.php';
require_once __DIR__ . '/statamic/sync.php';
