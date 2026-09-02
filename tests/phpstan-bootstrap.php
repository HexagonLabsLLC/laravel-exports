<?php

use Composer\InstalledVersions;

// larastan 3.10 reads LARAVEL_VERSION in phpstan's stub-validator process, which
// never runs its bootstrap when analysing a package; define it for every process
if (!defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', ltrim(InstalledVersions::getPrettyVersion('illuminate/support') ?? '12.0.0', 'v'));
}
