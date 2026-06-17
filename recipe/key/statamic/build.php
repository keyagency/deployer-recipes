<?php

namespace Deployer;

set('key_build_tmp_path', sys_get_temp_dir() . '/deployer-build');
set('key_build_command', 'yarn && yarn build');

desc(key_label('Build frontend resources locally and upload them to the server'));
task('key:build:resources', function () {
    $branch = get('branch');
    $tmpDir = get('key_build_tmp_path') . '/' . currentHost()->getAlias();

    $tmpDirArg = quote($tmpDir);
    $branchArg = quote($branch);

    /**
     * Clean up leftover state from a previous failed build. The prune drops
     * stale worktree metadata left behind when only the directory was removed,
     * which would otherwise make `git worktree add` refuse the path.
     */
    runLocally("git worktree remove --force $tmpDirArg 2>/dev/null || rm -rf $tmpDirArg");
    runLocally('git worktree prune');

    info(key_label("Checking out '$branch' to a temporary directory"));
    runLocally("git worktree add --detach $tmpDirArg $branchArg");

    try {
        // Build against the remote .env so Vite picks up production env vars, not local dev values.
        info(key_label('Fetching .env from the remote server'));
        download('{{deploy_path}}/shared/.env', "$tmpDir/.env");

        info(key_label('Building resources'));
        runLocally("cd $tmpDirArg && {{key_build_command}}");

        run('mkdir -p {{release_or_current_path}}/public/build');
        upload("$tmpDir/public/build/", '{{release_or_current_path}}/public/build/');
    } finally {
        // Fall back to rm -rf so cleanup itself can never fail the task.
        runLocally("git worktree remove --force $tmpDirArg 2>/dev/null || rm -rf $tmpDirArg");
    }
});

after('deploy:vendors', 'key:build:resources');
