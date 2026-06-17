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

/**
 * Override Deployer's Statamic deploy task to drop statamic:stache:warm.
 * The Stache lives in shared storage and the watcher is disabled in production,
 * so it must be cleared after deploying new content or stale content is served.
 * We keep statamic:stache:clear but skip warming, which is the slow part on
 * large sites; the Stache rebuilds lazily on the next request instead.
 */
desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'artisan:storage:link',
    'artisan:config:cache',
    'artisan:cache:clear',
    'artisan:migrate',
    'deploy:publish',
    'statamic:stache:clear',
]);
