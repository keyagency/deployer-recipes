<?php

namespace Deployer;

require_once 'recipe/statamic.php';
require_once __DIR__ . '/../key.php';

set('key_build_tmp_path', sys_get_temp_dir() . '/deployer-build');
set('key_build_command', 'yarn && yarn build');

desc('Build frontend resources locally and upload them to the server');
task('key:build:resources', function () {
    $branch = get('branch');
    $tmpDir = get('key_build_tmp_path') . '/' . currentHost()->getAlias();

    // Clean up leftover state from a previous failed build.
    runLocally("git worktree remove --force $tmpDir 2>/dev/null || rm -rf $tmpDir");

    info("Checking out '$branch' to a temporary directory");
    runLocally("git worktree add --detach $tmpDir $branch");

    try {
        // Build against the remote .env so Vite picks up production env vars, not local dev values.
        info('Fetching .env from the remote server');
        download('{{deploy_path}}/shared/.env', "$tmpDir/.env");

        info('Building resources');
        runLocally("cd $tmpDir && {{key_build_command}}");

        run('mkdir -p {{release_or_current_path}}/public/build');
        upload("$tmpDir/public/build/", '{{release_or_current_path}}/public/build/');
    } finally {
        runLocally("git worktree remove --force $tmpDir");
    }
});
