<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Installateur web (mutualisé) : HORS groupe "web" pour fonctionner
        // avant clé/sessions/tables. Protégé par INSTALL_TOKEN.
        then: function () {
            Illuminate\Support\Facades\Route::get('_installation', [App\Http\Controllers\InstallController::class, 'show']);
            Illuminate\Support\Facades\Route::post('_installation', [App\Http\Controllers\InstallController::class, 'run'])
                ->middleware('throttle:20,1');
            // Images publiques du réseau social (sans lien symbolique, sans session).
            Illuminate\Support\Facades\Route::get('media/{path}', App\Http\Controllers\MediaController::class)
                ->where('path', 'social/[A-Za-z0-9/_.-]+')->name('media');
            // Cron par URL (hébergement web sans tâche cron en ligne de commande).
            Illuminate\Support\Facades\Route::get('_cron', App\Http\Controllers\CronController::class)
                ->middleware('throttle:6,1');
        },
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
        // L'installateur reste joignable pendant une mise à jour.
        $middleware->preventRequestsDuringMaintenance(except: ['_installation', '_cron']);

        // Invités non connectés : page de connexion du bon contexte.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->routeIs('social.*')                              => route('social.login'),
            function_exists('tenancy') && tenancy()->initialized        => route('tenant.login'),
            default                                                     => route('network.login'),
        });
        // Utilisateur déjà connecté sur une page « invité » (connexion…).
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('social.*') ? route('social.home') : '/');

        // Sensitive-role MFA gate, usable as ->middleware('mfa').
        $middleware->alias([
            'mfa' => App\Http\Middleware\EnsureMfaEnabled::class,
            'central.only' => App\Http\Middleware\CentralDomainOnly::class,
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
        // Sous-domaine inconnu (aucune mutuelle) -> 404 propre au lieu d'une 500.
        $exceptions->render(function (\Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException $e) {
            abort(404);
        });
        $exceptions->dontReport([
            \Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException::class,
        ]);

        // Base de données saturée (trop de connexions simultanées sur l'hébergement) :
        // page « réseau très sollicité » avec nouvel essai automatique, au lieu d'une erreur 500.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            $msg = $e->getMessage();
            if (str_contains($msg, '[1040]') || str_contains($msg, '[1203]') || str_contains($msg, '[2002]') || str_contains($msg, 'max_user_connections')) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Le réseau est très sollicité. Réessayez dans un instant.'], 503, ['Retry-After' => '20']);
                }

                return response()->view('errors.503', [], 503, ['Retry-After' => '20']);
            }

            return null;
        });

        // Member PWA / Sanctum API (api/member/*) and webhooks expect JSON errors, not HTML pages.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'webhooks/*') || $request->expectsJson(),
        );
    })->create();
