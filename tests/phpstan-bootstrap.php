<?php

use Composer\InstalledVersions;

// larastan 3.10's own bootstrap can miss defining this when analysing a package,
// which crashes its stub-files extension
if (!defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', InstalledVersions::getPrettyVersion('illuminate/support') ?? '12.0.0');
}
