<?php

namespace Deployer;

set('key_sync_map', [
    'content' => [
        'dirs' => ['content/', 'resources/users/'],
        'files' => ['resources/sites.yaml', 'resources/preferences.yaml'],
    ],
    'assets' => [
        'dirs' => ['public/assets/'],
        'files' => [],
    ],
    'forms' => [
        'dirs' => ['resources/forms/', 'resources/blueprints/forms/'],
        'files' => [],
    ],
    'addons' => [
        'dirs' => [],
        'files' => [],
    ],
]);

/**
 * Ask once for the sync direction and, when pushing to remote, the overwrite
 * and delete confirmations. Returns [$toLocal, $delete], or null if cancelled.
 */
function key_sync_prompt(string $label): ?array
{
    $remoteToLocal = '⬇️ REMOTE → LOCAL';
    $localToRemote = '⬆️ LOCAL → REMOTE (⚠️ this will overwrite remote ' . $label . '!)';
    $direction = askChoice('🔄️ Which direction do you want to sync?', [$remoteToLocal, $localToRemote], 0);

    if ($direction === $remoteToLocal) {
        // Remote → local always mirrors: delete local files that no longer exist remotely.
        return [true, true];
    }

    if (!askConfirmation('⚠️ Sync from LOCAL to REMOTE? This may overwrite remote files! (Default: NO)', false)) {
        writeln('<error>⚠️ Sync cancelled.</error>');
        return null;
    }
    $delete = askConfirmation('🗑️ Also delete remote files that no longer exist locally? (Default: NO)', false);

    return [false, $delete];
}

/**
 * Sync a content type between the server and the local machine via rsync.
 * Pure executor: direction and delete are decided by the caller. Directories
 * may mirror deletions; single files never do.
 */
function key_sync(string $type, bool $toLocal, bool $delete): void
{
    $map = get('key_sync_map');
    if (!isset($map[$type])) {
        writeln("<error>⚠️ Unknown sync type '$type'.</error>");
        return;
    }

    ['dirs' => $dirs, 'files' => $files] = $map[$type];

    foreach ($dirs as $path) {
        key_rsync($type, $path, $toLocal, $delete);
    }
    foreach ($files as $path) {
        key_rsync($type, $path, $toLocal, false);
    }
}

/**
 * Sync a single path with rsync, skipping it when the source side does not
 * exist so a missing optional file/dir never fails the whole sync.
 */
function key_rsync(string $type, string $path, bool $toLocal, bool $delete): void
{
    $remote = get('remote_user') . '@' . get('hostname') . ':' . get('current_path') . '/' . $path;
    $local = getcwd() . '/' . $path;

    $sourceExists = $toLocal ? test("[ -e {{current_path}}/$path ]") : file_exists($local);
    if (!$sourceExists) {
        info('⏭️ Skipping [' . strtoupper($type) . ': ' . $path . '] — source does not exist');
        return;
    }

    [$from, $to] = $toLocal ? [$remote, $local] : [$local, $remote];
    $deleteFlag = $delete ? '--delete' : '';
    info('⭐️ Syncing [' . strtoupper($type) . ': ' . $path . '] ' . ($toLocal ? 'FROM remote TO local' : 'FROM local TO remote'));
    info(runLocally("rsync -chavzPL --stats $deleteFlag '$from' '$to'"));
}

/**
 * Refresh the Statamic stache, locally or on the server.
 */
function key_refresh_statamic_cache(bool $toLocal = true): void
{
    info('⭐️ Refreshing Statamic cache...');
    info($toLocal
        ? runLocally('php please stache:refresh')
        : run('cd {{release_or_current_path}} && {{bin/php}} please stache:refresh'));
}

desc('Sync content (and optionally forms, addons, assets) between server and local');
task('key:sync:content', function () {
    $answer = key_sync_prompt('content');
    if ($answer === null) {
        return;
    }
    [$toLocal, $delete] = $answer;

    $types = ['content'];
    if (askConfirmation('📝️ Also sync forms? (Default: YES)', true)) {
        $types[] = 'forms';
    }
    if (askConfirmation('⚙️️ Also sync addon settings? (Default: YES)', true)) {
        $types[] = 'addons';
    }
    if (askConfirmation('🖼️️ Also sync assets? (Default: YES)', true)) {
        $types[] = 'assets';
    }

    foreach ($types as $type) {
        key_sync($type, $toLocal, $delete);
    }
    key_refresh_statamic_cache($toLocal);
});

foreach (['assets', 'forms', 'addons'] as $type) {
    desc("Sync $type between server and local");
    task('key:sync:' . $type, function () use ($type) {
        $answer = key_sync_prompt($type);
        if ($answer === null) {
            return;
        }
        [$toLocal, $delete] = $answer;
        key_sync($type, $toLocal, $delete);
        key_refresh_statamic_cache($toLocal);
    });
}
