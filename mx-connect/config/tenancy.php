<?php

use App\Models\Central\Mutual;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Trimmed but real tenancy config (stancl/tenancy v3).
 * Full defaults ship with the package: publish and merge on install
 * (`php artisan vendor:publish --tag=tenancy-config`). Key choices below.
 */
return [
    'tenant_model' => Mutual::class,
    'domain_model' => Domain::class,

    'central_domains' => [
        env('CENTRAL_DOMAIN', 'www.mx-connect.com'),
        'mx-connect.com',
        'www.mx-connect.com',
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'prefix' => env('TENANT_DB_PREFIX', 'mxconnect_mut_'),
        'suffix' => '',
        'template_tenant_connection' => null,
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

    'migration_parameters' => [
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantParameterSeeder',
    ],

    'features' => [
        // Stancl\Tenancy\Features\UserImpersonation::class,
    ],
];
