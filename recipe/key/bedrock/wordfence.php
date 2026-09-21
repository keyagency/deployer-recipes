<?php

namespace Deployer;

require_once __DIR__ . '/../../helpers/general.php';

// Replace Wordfence's hardcoded release path with the "current" symlink
task('key:wordfence:fix-waf', function () {
    $file = get('wordfence_waf_file', null);

    // Only configured for hosts that use this Wordfence setup
    if (!$file) {
        info(key_label('Skipping: no WAF file configured in wordfence_waf_file'));
        return;
    }

    if (!test("[ -f {$file} ]")) {
        warning(key_label("Wordfence WAF file not found: {$file}"));
        return;
    }

    info(key_label('Replacing Wordfence hardcoded release path with "current" symlink...'));

    run(
        "sed -E -i 's#/releases/[0-9]+#/current#g' {$file}"
    );

    // Validate the PHP file after changing it
    run("php -l {$file}");
});
