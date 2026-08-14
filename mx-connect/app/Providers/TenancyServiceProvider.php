<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

/**
 * Wires the tenant lifecycle so that creating a Mutual automatically:
 *   creates its database -> runs tenant migrations -> seeds initial parameters.
 * Also maps the tenant routes (routes/tenant.php) onto tenant domains.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                Listeners\CreatePendingTenantAwareCommands::class,
                Jobs\CreateDatabase::class,
                Jobs\MigrateDatabase::class,   // uses config('tenancy.migration_parameters')
                Jobs\SeedDatabase::class,      // uses config('tenancy.seeder_parameters')
            ],
            Events\TenantDeleted::class => [
                Jobs\DeleteDatabase::class,
            ],
        ];
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof \Stancl\Tenancy\Events\JobPipeline) {
                    $listener = $listener->toListener();
                }
                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        if (file_exists(base_path('routes/tenant.php'))) {
            Route::namespace('App\Http\Controllers')
                ->group(base_path('routes/tenant.php'));
        }
    }
}
