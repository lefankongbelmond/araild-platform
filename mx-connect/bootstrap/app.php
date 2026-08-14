<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Tenant routes are mapped by App\Providers\TenancyServiceProvider.
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
    )
    ->withProviders([
        App\Providers\AppServiceProvider::class,
        App\Providers\TenancyServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Sensitive-role MFA gate, usable as ->middleware('mfa').
        $middleware->alias([
            'mfa' => App\Http\Middleware\EnsureMfaEnabled::class,
        ]);

        // Baseline security headers on every response.
        $middleware->append(App\Http\Middleware\SecurityHeaders::class);

        // Request correlation id + log context, as early as possible.
        $middleware->prepend(App\Http\Middleware\RequestContext::class);

        // Bearer-token APIs and aggregator webhooks are stateless — exempt from CSRF.
        $middleware->validateCsrfTokens(except: [
            'api/member/*',       // member PWA (Sanctum)
            'webhooks/paiement/*', // payment aggregator callbacks
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
