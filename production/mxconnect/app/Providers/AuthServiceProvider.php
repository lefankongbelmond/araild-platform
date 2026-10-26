<?php

namespace App\Providers;

use App\Models\Central\Mutual;
use App\Policies\MutualPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /** Models live under App\Models\{Central,Tenant}, so policies are mapped explicitly. */
    protected $policies = [
        Mutual::class => MutualPolicy::class,
    ];

    public function register(): void
    {
        $this->app->bind(
            \App\Payments\Contracts\PaymentDriver::class,
            \App\Payments\Drivers\SandboxDriver::class,   // swap per provider in production
        );
    }

    public function boot(): void
    {
        // Network configuration (countries/currencies/locales/providers).
        Gate::define('manage-config', fn ($user) => $user->hasRole('super_admin'));
        Gate::define('view-config',   fn ($user) => $user->hasAnyRole(['super_admin', 'security_admin']));

        // Mutual parameters (antennas, guarantees, act types, medicines, ceilings).
        // Evaluated against the tenant guard's user once tenancy is initialized.
        Gate::define('manage-parameters', fn ($user) => $user->hasRole('mutual_admin'));
    }
}
