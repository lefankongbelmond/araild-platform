<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * Cycle de vie tenant (stancl/tenancy v3.10).
 *
 * Création d'une mutuelle : CreateDatabase -> MigrateDatabase -> SeedDatabase
 * (exécutés en SYNCHRONE : aucune dépendance au worker de file).
 *
 * CORRECTION #1 (isolation) : sans les listeners BootstrapTenancy /
 * RevertToCentralContext, la connexion BD ne bascule JAMAIS vers la base de la
 * mutuelle — toutes les requêtes "tenant" partiraient dans la base centrale.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public static string $controllerNamespace = '';

    public function events(): array
    {
        // auto : MX-CONNECT crée une base par mutuelle (VPS). manual / prefix (mutualisé) : pas de
        // CREATE DATABASE ; tables préfixées dans la base centrale, ou base existante saisie.
        $autoDb = config('mxconnect.tenant_db_mode', 'auto') === 'auto';

        return [
            Events\TenantCreated::class => [
                JobPipeline::make(array_values(array_filter([
                    // Mode "manual" (hébergement mutualisé) : la base existe déjà,
                    // créée dans le panneau ; on ne fait que migrer + initialiser.
                    $autoDb ? \App\Jobs\Tenancy\CreateTenantDatabase::class : null,   // jamais pour une mutuelle à préfixe
                    Jobs\MigrateDatabase::class,   // config('tenancy.migration_parameters')
                    Jobs\SeedDatabase::class,      // config('tenancy.seeder_parameters')
                ])))->send(fn (Events\TenantCreated $event) => $event->tenant)
                  ->shouldBeQueued(false),
            ],

            Events\TenantDeleted::class => $autoDb ? [
                JobPipeline::make([
                    \App\Jobs\Tenancy\DeleteTenantDatabase::class,   // préfixe : ses tables seulement
                ])->send(fn (Events\TenantDeleted $event) => $event->tenant)
                  ->shouldBeQueued(false),
            ] : [],

            // Bascule effective de contexte (BD, cache, fichiers, files).
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            // Cache spatie/permission propre à chaque base (sinon les rôles et
            // permissions chargés dans un contexte fuient vers un autre).
            Events\TenancyBootstrapped::class => [
                function (Events\TenancyBootstrapped $event) {
                    $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
                    $registrar->cacheKey = 'spatie.permission.cache.tenant.' . $event->tenancy->tenant->getTenantKey();
                    $registrar->clearPermissionsCollection();
                },
            ],
            Events\RevertedToCentralContext::class => [
                function () {
                    $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
                    $registrar->cacheKey = config('permission.cache.key');
                    $registrar->clearPermissionsCollection();
                },
            ],
        ];
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }
                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            Middleware\PreventAccessFromCentralDomains::class,
            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
