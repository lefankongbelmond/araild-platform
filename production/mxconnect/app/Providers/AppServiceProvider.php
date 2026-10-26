<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Application-level bindings and, importantly for Phase 3, the named rate limiters
 * used by the throttle middleware. Limits are keyed so one abusive caller can't
 * starve others: the member API by token/IP, webhooks by mutual, login by IP.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Les routes Fortify (/login, /register…) ne sont pas utilisées : la
        // connexion passe par /reseau/connexion (réseau) et /connexion (mutuelle).
        // Laissées actives, elles renvoyaient une erreur 500 (aucune vue liée).
        if (class_exists(\Laravel\Fortify\Fortify::class)) {
            \Laravel\Fortify\Fortify::ignoreRoutes();
        }

        // Hébergement mutualisé : la racine web est htdocs/, pas <appli>/public.
        if ($public = config('mxconnect.public_path')) {
            $this->app->usePublicPath(rtrim($public, '/'));
        }
    }

    public function boot(): void
    {
        // Strict model behaviour outside production. Lazy-loading prevention (the
        // aggressive N+1 catcher) is opt-in via config so it doesn't break views
        // during normal runs; the low-risk checks stay on in dev/CI.
        $dev = ! $this->app->isProduction();
        Model::preventSilentlyDiscardingAttributes($dev);
        Model::preventAccessingMissingAttributes($dev);
        Model::preventLazyLoading($dev && (bool) config('mxconnect.strict_lazy_loading', false));

        // Réseau social : limites par COMPTE membre (garde « member »), sinon par IP.
        // Limiteurs nommés = compteurs séparés (une action n'épuise pas les autres).
        $who = fn (Request $r) => auth('member')->id() ? 'm' . auth('member')->id() : 'ip' . $r->ip();
        RateLimiter::for('social-actions', fn (Request $r) => Limit::perMinute(120)->by($who($r)));
        RateLimiter::for('social-publish', fn (Request $r) => Limit::perMinute(10)->by($who($r)));
        RateLimiter::for('social-comment', fn (Request $r) => Limit::perMinute(30)->by($who($r)));
        RateLimiter::for('social-search', fn (Request $r) => Limit::perMinute(90)->by($who($r)));
        RateLimiter::for('social-upload', fn (Request $r) => Limit::perMinute(240)->by($who($r)));   // morceaux de vidéo
        RateLimiter::for('social-report', fn (Request $r) => Limit::perHour(30)->by($who($r)));
        RateLimiter::for('social-contact', fn (Request $r) => Limit::perMinutes(10, 5)->by($who($r)));
        RateLimiter::for('social-auth', fn (Request $r) => Limit::perMinute(20)->by($r->ip()));
        RateLimiter::for('social-register', fn (Request $r) => Limit::perMinutes(10, 6)->by($r->ip()));

        // Force HTTPS URLs in production (behind the TLS-terminating proxy).
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Observability: warn on slow queries so N+1 or missing-index regressions
        // surface in the structured log with the request context attached.
        $slowMs = (int) config('mxconnect.slow_query_ms', 500);
        DB::whenQueryingForLongerThan($slowMs, function ($connection, $event) use ($slowMs) {
            Log::warning('slow_query', [
                'connection' => $connection->getName(),
                'threshold_ms' => $slowMs,
            ]);
        });

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        // Member PWA API: per authenticated account when available, else per IP.
        RateLimiter::for('member-api', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();
            return [Limit::perMinute(60)->by('member-api:'.$key)];
        });

        // Payment webhooks: generous but bounded, keyed by the mutual (subdomain).
        RateLimiter::for('webhooks', function (Request $request) {
            $scope = function_exists('tenant') && tenant() ? tenant('id') : $request->ip();
            return [Limit::perMinute(120)->by('webhooks:'.$scope)];
        });

        // Interactive logins: throttle brute force by IP.
        RateLimiter::for('login', function (Request $request) {
            return [Limit::perMinute(10)->by('login:'.$request->ip())];
        });
    }
}
