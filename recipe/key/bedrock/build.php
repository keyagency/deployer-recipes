<?php

namespace Deployer;

require_once __DIR__ . '/../../helpers/general.php';

set('key_bedrock_theme', '');
// Lazy: derived from key_bedrock_theme; override for a non-standard layout.
set('key_bedrock_theme_path', fn () => 'web/app/themes/' . get('key_bedrock_theme'));
set('key_build_command', 'yarn && yarn build');
/**
 * Build artifacts to upload, relative to the theme path.
 * Trailing slash = directory, no slash = single file (same convention as the sync tasks).
 */
set('key_build_uploads', ['assets/js/', 'style.css']);

desc(key_label('Build theme resources locally and upload them to the server'));
task('key:build:resources', function () {
    if (get('key_bedrock_theme') === '') {
        warning(key_label("Skipping: set('key_bedrock_theme', '<theme folder>') to enable key:build:resources"));
        return;
    }

    $local = get('key_bedrock_theme_path');
    $remote = '{{release_or_current_path}}/' . $local;

    info(key_label('Building resources'));
    runLocally("cd $local && {{key_build_command}}");

    foreach (get('key_build_uploads') as $path) {
        info(key_label("Uploading $path"));
        if (str_ends_with($path, '/')) {
            run("mkdir -p $remote/$path");
        }
        upload("$local/$path", "$remote/$path");
    }
});
