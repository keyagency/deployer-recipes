<?php

namespace Deployer;

require_once __DIR__ . '/../../sync.php';

set('key_october_themes', ['default']);
set('key_sync_excludes', ['blocks.yaml']);

/**
 * Lazy closure: evaluated on first get(), so the map picks up the
 * key_october_themes the project configured after requiring this recipe.
 */
set('key_sync_map', function () {
    $map = [
        'theme' => ['dirs' => [], 'files' => []],
        'storage' => [
            'dirs' => ['storage/app/uploads/', 'storage/app/media/'],
            'files' => [],
        ],
    ];
    foreach (get('key_october_themes') as $theme) {
        $map['theme']['dirs'][] = "themes/$theme/content/";
        $map['theme']['dirs'][] = "themes/$theme/meta/";
    }
    return $map;
});

/**
 * Refresh the October CMS (Laravel) cache, locally or on the server.
 */
function key_refresh_october_cache(bool $toLocal = true): void
{
    info('⭐️ Refreshing October CMS cache...');
    info($toLocal
        ? runLocally('php artisan cache:clear')
        : run('cd {{release_or_current_path}} && {{bin/php}} artisan cache:clear'));
}

foreach (['theme', 'storage'] as $type) {
    desc("Sync October CMS $type between server and local");
    task('key:sync:' . $type, function () use ($type) {
        $answer = key_sync_prompt($type);
        if ($answer === null) {
            return;
        }
        [$toLocal, $delete] = $answer;
        key_sync($type, $toLocal, $delete);
        key_refresh_october_cache($toLocal);
    });
}
