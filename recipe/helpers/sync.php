<?php

namespace Deployer;

/**
 * Shared rsync-based sync helpers, used by the per-platform sync recipes in
 * recipe/key/<platform>/sync.php. Each platform defines a key_sync_<type>
 * option per sync type (a flat list of paths; trailing slash = directory,
 * no slash = single file) and registers tasks on top of these. Projects can
 * append paths with add('key_sync_<type>', [...]).
 */

require_once __DIR__ . '/general.php';

set('key_sync_excludes', []);
set('key_sync_backup', false);

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
        writeln('<error>' . key_label('⚠️ Sync cancelled.') . '</error>');
        return null;
    }
    $delete = askConfirmation('🗑️ Also delete remote files that no longer exist locally? (Default: NO)', false);

    return [false, $delete];
}

/**
 * Refresh the platform cache after a sync by running the given artisan-style
 * command, locally or on the server.
 */
function key_refresh_cache(string $label, string $command, bool $toLocal = true): void
{
    info(key_label("⭐️ Refreshing $label cache..."));
    info($toLocal
        ? runLocally("php $command")
        : run('cd {{release_or_current_path}} && {{bin/php}} ' . $command));
}

/**
 * Build a shell-safe remote path below {{current_path}}. Only the sub-path is
 * quoted: the placeholder is expanded by Deployer after this string is built,
 * so quoting it too would also quote whatever it expands to and stop the
 * remote shell from expanding a leading ~ in the deploy path.
 */
function key_remote_path(string $path): string
{
    return '{{current_path}}/' . quote($path);
}

/**
 * Whether any paths are configured for the given sync type.
 */
function key_sync_has(string $type): bool
{
    return !empty(get("key_sync_$type", null));
}

/**
 * Sync a content type between the server and the local machine via rsync.
 * Pure executor: direction and delete are decided by the caller. Paths with a
 * trailing slash are directories and may mirror deletions; single files never do.
 */
function key_sync(string $type, bool $toLocal, bool $delete): void
{
    $paths = get("key_sync_$type", null);
    if ($paths === null) {
        writeln('<error>' . key_label("⚠️ Unknown sync type '$type'.") . '</error>');
        return;
    }
    if (empty($paths)) {
        info(key_label('⏭️ Skipping [' . strtoupper($type) . "] — no paths configured in key_sync_$type"));
        return;
    }

    foreach ($paths as $path) {
        $isDir = str_ends_with($path, '/');
        if ($isDir && get('key_sync_backup')) {
            key_sync_backup_destination($path, $toLocal);
        }
        key_rsync($type, $path, $toLocal, $isDir && $delete);
    }
}

/**
 * Copy the destination directory to "<dir>-backup" before it gets overwritten,
 * so one sync mistake is always recoverable. Skipped silently when the
 * destination does not exist yet.
 */
function key_sync_backup_destination(string $path, bool $toLocal): void
{
    $dir = rtrim($path, '/');
    $backup = $dir . '-backup';

    if ($toLocal) {
        $local = getcwd() . '/' . $dir;
        if (!is_dir($local)) {
            return;
        }
        info(key_label("🛟 Backing up local $dir to $backup"));
        runLocally('rsync -a --delete ' . quote($local . '/') . ' ' . quote(getcwd() . '/' . $backup . '/'));
    } else {
        $remoteDir = key_remote_path($dir);
        $remoteBackup = key_remote_path($backup);
        if (!test("[ -d $remoteDir ]")) {
            return;
        }
        info(key_label("🛟 Backing up remote $dir to $backup"));
        run("rm -rf $remoteBackup && cp -a $remoteDir $remoteBackup");
    }
}

/**
 * Sync a single path with rsync, skipping it when the source side does not
 * exist so a missing optional file/dir never fails the whole sync.
 */
function key_rsync(string $type, string $path, bool $toLocal, bool $delete): void
{
    $host = currentHost();
    // Omit the user when unset so ssh falls back to the user's own ssh config.
    $user = $host->getRemoteUser();
    $remote = ($user ? $user . '@' : '') . $host->getHostname() . ':' . get('current_path') . '/' . $path;
    $local = getcwd() . '/' . $path;

    // Respect a non-default SSH port configured on the host.
    $port = $host->getPort();
    $ssh = $port ? "-e 'ssh -p $port'" : '';

    $sourceExists = $toLocal ? test('[ -e ' . key_remote_path($path) . ' ]') : file_exists($local);
    if (!$sourceExists) {
        info(key_label('⏭️ Skipping [' . strtoupper($type) . ': ' . $path . '] — source does not exist'));
        return;
    }

    $excludes = '';
    foreach ((array) get('key_sync_excludes') as $exclude) {
        $excludes .= ' --exclude=' . quote($exclude);
    }

    [$from, $to] = $toLocal ? [$remote, $local] : [$local, $remote];
    $deleteFlag = $delete ? '--delete' : '';
    info(key_label('⭐️ Syncing [' . strtoupper($type) . ': ' . $path . '] ' . ($toLocal ? 'FROM remote TO local' : 'FROM local TO remote')));
    info(runLocally("rsync -chavzPL --stats $ssh$excludes $deleteFlag " . quote($from) . ' ' . quote($to)));
}
