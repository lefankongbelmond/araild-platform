<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back-office des mutuelles :
 *  - un compte désactivé est déconnecté immédiatement (pas seulement à la connexion suivante) ;
 *  - un mot de passe provisoire doit être remplacé avant tout autre écran.
 */
class TenantAccountGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        if ($user && ! $user->active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('tenant.login')->withErrors(['email' => __('mxconnect.auth.inactive')]);
        }
        if ($user && ($user->must_change_password ?? false)
            && ! $request->routeIs('account.password', 'account.password.update', 'tenant.logout', 'tenant.mfa.*')) {
            return redirect()->route('account.password')->with('error', 'Choisissez votre mot de passe personnel avant de continuer.');
        }

        return $next($request);
    }
}
