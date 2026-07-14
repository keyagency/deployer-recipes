<?php

namespace Deployer;

require_once __DIR__ . '/../../helpers/sync.php';

set('key_october_themes', ['default']);
set('key_sync_excludes', ['blocks.yaml']);

/**
 * Lazy closure: evaluated on first get(), so it picks up the
 * key_october_themes the project configured after requiring this recipe.
 */
set('key_sync_theme', function () {
    $paths = [];
    foreach (get('key_october_themes') as $theme) {
        $paths[] = "themes/$theme/content/";
        $paths[] = "themes/$theme/meta/";
    }
    return $paths;
});
set('key_sync_storage', ['storage/app/uploads/', 'storage/app/media/']);

foreach (['theme', 'storage'] as $type) {
    desc(key_label("Sync $type between server and local"));
    task('key:sync:' . $type, function () use ($type) {
        $answer = key_sync_prompt($type);
        if ($answer === null) {
            return;
        }
        [$toLocal, $delete] = $answer;
        key_sync($type, $toLocal, $delete);
        key_refresh_cache('October CMS', 'artisan cache:clear', $toLocal);
    });
}
