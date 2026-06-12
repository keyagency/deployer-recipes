<?php

namespace Deployer;

require_once __DIR__ . '/../../helpers/sync.php';

set('key_sync_content', [
    'content/',
    'resources/users/',
    'resources/sites.yaml',
    'resources/preferences.yaml',
]);
set('key_sync_assets', ['public/assets/']);
set('key_sync_forms', ['resources/forms/', 'resources/blueprints/forms/']);
set('key_sync_addons', ['resources/addons/']);

/**
 * Refresh the Statamic stache, locally or on the server.
 */
function key_refresh_statamic_cache(bool $toLocal = true): void
{
    info(key_label('⭐️ Refreshing Statamic cache...'));
    info($toLocal
        ? runLocally('php please stache:refresh')
        : run('cd {{release_or_current_path}} && {{bin/php}} please stache:refresh'));
}

desc(key_label('Sync content (and optionally forms, addons, assets) between server and local'));
task('key:sync:content', function () {
    $answer = key_sync_prompt('content');
    if ($answer === null) {
        return;
    }
    [$toLocal, $delete] = $answer;

    $types = ['content'];
    /**
     * Only offer the optional types that actually have paths configured in
     * key_sync_<type>; confirming an empty type would silently do nothing.
     */
    $optional = [
        'forms' => '📝️ Also sync forms? (Default: YES)',
        'addons' => '⚙️️ Also sync addon settings? (Default: YES)',
        'assets' => '🖼️️ Also sync assets? (Default: YES)',
    ];
    foreach ($optional as $type => $question) {
        if (key_sync_has($type) && askConfirmation($question, true)) {
            $types[] = $type;
        }
    }

    foreach ($types as $type) {
        key_sync($type, $toLocal, $delete);
    }
    key_refresh_statamic_cache($toLocal);
});

foreach (['assets', 'forms', 'addons'] as $type) {
    desc(key_label("Sync $type between server and local"));
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
