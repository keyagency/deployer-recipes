<?php

namespace Deployer;

require_once 'recipe/laravel.php';

set('key_platform', 'LARAVEL');

require_once __DIR__ . '/../key.php';
require_once __DIR__ . '/../helpers/build.php';
