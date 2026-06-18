<?php

namespace Deployer;

// October CMS is built on Laravel; Deployer ships no separate October CMS recipe.
require_once 'recipe/laravel.php';

set('key_platform', 'OCTOBER CMS');

require_once __DIR__ . '/../key.php';
require_once __DIR__ . '/october/sync.php';

desc(key_label('Write deployed git revision to .version'));
task('system:update', function () {
    info(key_label('Setting version file...'));
    run('cd {{release_or_current_path}} && echo "VERSION=$(cat REVISION)" > .version');
});

desc(key_label('Run October CMS database migrations'));
task('october:migrate', function () {
    info(key_label('Running October CMS migrations...'));
    info(run('cd {{release_or_current_path}} && {{bin/php}} artisan october:migrate'));
});

desc(key_label('Mirror October CMS plugin and module assets into public/'));
task('october:mirror', function () {
    info(key_label('Mirroring plugin and module assets to public/...'));
    info(run('cd {{release_or_current_path}} && {{bin/php}} artisan october:mirror'));
});

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'system:update',
    'deploy:vendors',
    'october:migrate',
    'october:mirror',
    'artisan:cache:clear',
    'artisan:config:clear',
    'deploy:publish',
]);
