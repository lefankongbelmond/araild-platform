<?php

namespace App\Http\Middleware;

use App\Models\Provider\ProviderMembership;
use App\Support\ProviderAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace prestataire central (2026-11-16), dans cet ordre :
 *  1. compte désactivé => déconnexion immédiate ;
 *  2. mot de passe provisoire => à remplacer avant tout ;
 *  3. double authentification obligatoire (activation, puis code à chaque session) ;
 *  4. établissement en cours (choisi parmi les rattachements actifs) ;
 *  5. droit exigé par la route (App\Support\ProviderAccess::ROUTES ; route non classée = refus).
 * La clé de session « provider_mfa_passed » est propre à cet espace (jamais partagée avec
 * l'espace super-admin, servi par le même domaine).
 */
class ProviderSpaceGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = Auth::guard('provider')->user();
        if (! $account) {
            return redirect()->route('pro.login');
        }
        if (! $account->active) {
            Auth::guard('provider')->logout();
            $request->session()->forget(['provider_mfa_passed', 'provider_membership']);

            return redirect()->route('pro.login')->withErrors(['email' => 'Ce compte a été désactivé. Contactez l’administrateur de votre établissement.']);
        }
        if ($account->must_change_password) {
            return redirect()->route('pro.password')->with('error', 'Choisissez votre mot de passe personnel avant de continuer.');
        }
        if (! $account->mfa_enabled) {
            return redirect()->route('pro.mfa.enroll');
        }
        if (! $request->session()->get('provider_mfa_passed', false)) {
            return redirect()->route('pro.mfa.challenge');
        }

        $memberships = $account->activeMemberships()->with('provider')->get();
        if ($memberships->isEmpty()) {
            return response()->view('provider.none', ['account' => $account], 403);
        }
        $current = $memberships->firstWhere('id', (int) $request->session()->get('provider_membership')) ?? $memberships->first();
        $request->session()->put('provider_membership', $current->id);
        app()->instance('provider.membership', $current);
        view()->share(['providerMembership' => $current, 'providerMemberships' => $memberships, 'providerAccount' => $account]);

        $route = (string) $request->route()?->getName();
        abort_unless(ProviderAccess::canRoute($route, $current), 403, 'Cette rubrique n’est pas ouverte à votre rôle dans l’établissement. Demandez à son administrateur.');

        return $next($request);
    }
}
