<?php

use App\Models\Central\Mutual;
use App\Models\Central\Domain;

/**
 * Trimmed but real tenancy config (stancl/tenancy v3).
 * Full defaults ship with the package: publish and merge on install
 * (`php artisan vendor:publish --tag=tenancy-config`). Key choices below.
 */
return [
    'tenant_model' => Mutual::class,
    'domain_model' => Domain::class,

    // Domaines du site central (jamais des mutuelles). Liste séparée par des
    // virgules dans CENTRAL_DOMAINS ; par défaut CENTRAL_DOMAIN + sa forme sans www.
    'central_domains' => array_values(array_unique(array_filter(array_map('trim', array_merge(
        explode(',', (string) env('CENTRAL_DOMAINS', '')),
        [env('CENTRAL_DOMAIN', 'www.mx-connect.com'), preg_replace('/^www\./', '', (string) env('CENTRAL_DOMAIN', 'www.mx-connect.com'))]
    ))))),

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        // Redis/Memcached : isolation par tags (stancl). Fichier/base : par
        // préfixe/dossier (hébergement mutualisé, pas de tags disponibles).
        in_array(env('CACHE_STORE', 'redis'), ['redis', 'memcached'], true)
            ? Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class
            : App\Tenancy\PrefixCacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'prefix' => env('TENANT_DB_PREFIX', 'mxconnect_mut_'),
        'suffix' => '',
        // CORRECTION #3 : doit pointer sur 'mysql' (null => connexion tenant mal construite).
        'template_tenant_connection' => 'mysql',
        'managers' => [
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
        ],
    ],

    // Isolate cache, filesystem and queues per tenant.
    'cache' => ['tag_base' => 'tenant'],
    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => ['local', 'public'],
    ],

    // '--force' INDISPENSABLE en production : sans lui, tenants:migrate/seed
    // attendent une confirmation interactive et ne font RIEN lorsqu'ils sont
    // lancés par l'application (création d'une mutuelle depuis l'interface).
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--force' => true,
        '--class' => 'Database\\Seeders\\TenantParameterSeeder',
    ],

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
    ],
];
