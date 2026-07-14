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

/**
 * Themes append ?v=<ASSET_VERSION> to asset URLs for cache busting. Deployer
 * writes the full sha to REVISION during deploy:update_code; the first 8
 * characters are unique enough for a query string.
 */
desc(key_label('Write the deployed commit as ASSET_VERSION to the shared .env'));
task('key:asset:version', function () {
    $revision = trim(run('cat ' . quote('{{release_or_current_path}}/REVISION') . ' 2>/dev/null || true'));
    if ($revision === '') {
        warning(key_label('Skipping ASSET_VERSION: no REVISION file in the release'));
        return;
    }

    $version = substr($revision, 0, 8);
    info(key_label("Writing ASSET_VERSION=$version to the shared .env"));
    $env = quote('{{deploy_path}}/shared/.env');
    run("touch $env");
    run("sed -i '/^ASSET_VERSION=/d' $env");
    run('printf ' . quote('ASSET_VERSION=%s\n') . ' ' . quote($version) . " >> $env");
});

desc('Deploys your project');
task('deploy', [
    'deploy:prepare',
    'system:update',
    'deploy:vendors',
    'october:migrate',
    'october:mirror',
    'key:asset:version',
    'artisan:cache:clear',
    'artisan:config:clear',
    'artisan:view:clear',
    'deploy:publish',
]);
