<?php

namespace Deployer;

require_once __DIR__ . '/../../helpers/general.php';

set('key_languages', ['nl_NL']);

desc(key_label('Install WordPress core/plugin/theme languages via wp-cli'));
task('key:install:languages', function () {
    $languages = get('key_languages');
    if (empty($languages)) {
        info(key_label('⏭️ Skipping — no languages configured in key_languages'));
        return;
    }

    run('cd {{release_or_current_path}} && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar');
    run('chmod +x {{release_or_current_path}}/wp-cli.phar');

    foreach ($languages as $language) {
        info(key_label("Installing language \"$language\""));
        run('cd {{release_or_current_path}} && {{bin/php}} wp-cli.phar language core install ' . $language);
        run('cd {{release_or_current_path}} && {{bin/php}} wp-cli.phar language plugin install --all ' . $language);
        run('cd {{release_or_current_path}} && {{bin/php}} wp-cli.phar language theme install --all ' . $language);
    }
});
