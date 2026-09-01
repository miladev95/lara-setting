<?php

/**
 * Configuration for miladev/lara-setting
 *
 * driver     Which storage backend to use: "database", "file", or "redis".
 * ttl        Cache lifetime in minutes (0 = forever). Applied to file/redis drivers.
 * file_path  Directory used by the "file" driver. Defaults to Laravel's storage_path('framework/cache/lara-setting').
 */

return [
    'driver' => 'database',

    'ttl' => 60,

    'file_path' => null,

    // 'site_url' => 'http://localhost',
    'laravel_settings' => 'running',
];
