<?php

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        // Tenant users (mutual back-office). Default connection is the tenant DB
        // once tenancy is initialized on a mutual subdomain.
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Network back-office (super admin, security, moderator) — central DB.
        'network' => [
            'driver' => 'session',
            'provider' => 'network_users',
        ],

        // Grand-public members (PWA), token-based (Sanctum).
        'member' => [
            'driver' => 'session',
            'provider' => 'public_accounts',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Tenant\User::class,
        ],
        'network_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\NetworkUser::class,
        ],
        'public_accounts' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\PublicAccount::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        'network_users' => [
            'provider' => 'network_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
