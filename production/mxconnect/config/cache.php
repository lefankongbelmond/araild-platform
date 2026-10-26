<?php

use Illuminate\Support\Str;

return [
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'array' => ['driver' => 'array', 'serialize' => false],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION', 'central'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION', 'central'),
        ],

        'file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],
    ],

    // Prefix isolates cache entries; tenant-scoped keys add their own prefix on top.
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'mxconnect'), '_').'_cache_'),
];
