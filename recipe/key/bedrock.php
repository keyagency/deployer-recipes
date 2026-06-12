<?php

namespace Deployer;

require_once 'recipe/common.php';

add('recipes', ['bedrock']);

// Shared files/dirs between deployments
add('shared_files', ['.env', 'web/.htaccess']);
add('shared_dirs', ['web/app/uploads']);
add('writable_dirs', ['web/app/uploads']);

set('key_platform', 'BEDROCK');

require_once __DIR__ . '/../key.php';
require_once __DIR__ . '/bedrock/build.php';
require_once __DIR__ . '/bedrock/languages.php';

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'deploy:vendors',
    'deploy:publish',
]);
