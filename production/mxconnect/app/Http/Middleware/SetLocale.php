<?php

namespace App\Http\Middleware;

use App\Models\Central\Locale;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Applique la langue de l'interface (M1 multilingue, 2026-11-27) — voir App\Support\Locales.
 *
 * Sur le domaine central : compte connecté de l'espace visité (membre, prestataire, super-admin),
 * puis cookie, puis navigateur. Sur le sous-domaine d'une mutuelle, ce passage du groupe « web »
 * s'efface : SetTenantLocale prend le relais APRÈS l'identification de la mutuelle (compte du
 * personnel, cookie, langue de la mutuelle, navigateur).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        if (! $this->applies($request)) {
            return $next($request);
        }
        Locales::resetRequestState();
        Locales::apply($this->resolve($request));

        return $next($request);
    }

    protected function applies(Request $request): bool
    {
        return in_array($request->getHost(), config('tenancy.central_domains', []), true);
    }

    public function resolve(Request $request): string
    {
        foreach ($this->accounts($request) as $account) {
            $code = $account ? ($account->getAttributes()['locale'] ?? null) : null;   // colonne peut-être absente (modèle strict)
            if (Locales::isActive($code)) {
                return $code;
            }
        }
        $cookie = $request->cookie(Locales::COOKIE);
        if (is_string($cookie) && Locales::isActive($cookie)) {
            return $cookie;
        }
        if ($code = $this->mutualDefault()) {
            return $code;
        }

        return Locales::fromBrowser($request) ?? Locales::SOURCE;
    }

    /** Compte(s) dont la préférence compte pour l'espace visité. */
    protected function accounts(Request $request): array
    {
        $name = (string) $request->route()?->getName();
        $guard = match (true) {
            str_starts_with($name, 'pro.') => 'provider',
            str_starts_with($name, 'network.') => 'network',
            default => 'member',
        };
        try {
            return [auth($guard)->user()];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function mutualDefault(): ?string
    {
        return null;
    }

    /** Code de la langue d'une mutuelle (mutuals.locale_id), mis en cache. */
    public static function localeCode(?int $localeId): ?string
    {
        if (! $localeId) {
            return null;
        }
        try {
            return Cache::remember('mx.locale.code.' . $localeId, 600, fn () => Locale::query()->whereKey($localeId)->value('code'));
        } catch (\Throwable) {
            return null;
        }
    }
}
