<?php

namespace Deployer;

// October CMS is built on Laravel; Deployer ships no separate October CMS recipe.
require_once 'recipe/laravel.php';
require_once __DIR__ . '/../key.php';
require_once __DIR__ . '/october/sync.php';
