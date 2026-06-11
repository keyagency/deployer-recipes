<?php

namespace Deployer;

require_once __DIR__ . '/../../sync.php';

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
    /**
     * Only offer the optional types that actually have paths configured in
     * key_sync_map; confirming an empty type would silently do nothing.
     */
    $optional = [
        'forms' => '📝️ Also sync forms? (Default: YES)',
        'addons' => '⚙️️ Also sync addon settings? (Default: YES)',
        'assets' => '🖼️️ Also sync assets? (Default: YES)',
    ];
    foreach ($optional as $type => $question) {
        if (key_sync_map_has($type) && askConfirmation($question, true)) {
            $types[] = $type;
        }
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
