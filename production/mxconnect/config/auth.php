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

        // Espace prestataire central (2026-11-16) : personnel des établissements de soins.
        'provider' => [
            'driver' => 'session',
            'provider' => 'provider_accounts',
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
        'provider_accounts' => [
            'driver' => 'eloquent',
            'model' => App\Models\Provider\ProviderAccount::class,
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
        // Comptes membres du réseau social (lien envoyé par e-mail, valable 60 min).
        'public_accounts' => [
            'provider' => 'public_accounts',
            'connection' => 'central',
            'table' => 'public_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        // Espace prestataire (lien par e-mail, 60 min).
        'provider_accounts' => [
            'provider' => 'provider_accounts',
            'connection' => 'central',
            'table' => 'provider_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
