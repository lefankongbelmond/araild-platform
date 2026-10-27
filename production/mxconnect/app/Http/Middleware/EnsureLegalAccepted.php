<?php

namespace App\Http\Middleware;

use App\Services\Social\LegalService;
use Closure;
use Illuminate\Http\Request;

/**
 * Documents légaux en attente (2026-11-09) : la LECTURE reste possible (on ne bloque pas
 * inutilement l'utilisateur ; un bandeau l'invite à valider), mais toute ACTION d'écriture
 * (publier, commenter, envoyer une demande…) attend la validation des documents dus.
 * Se déconnecter, supprimer son compte et valider les documents restent toujours possibles.
 */
class EnsureLegalAccepted
{
    private const ALWAYS = ['social.legal.accept', 'social.logout', 'social.account.destroy', 'social.feedback.store'];

    public function handle(Request $request, Closure $next)
    {
        $account = auth('member')->user();
        if (! $account || $request->isMethodSafe() || in_array($request->route()?->getName(), self::ALWAYS, true)) {
            return $next($request);
        }
        if (app(LegalService::class)->pendingFor($account)->isNotEmpty()) {
            $url = route('social.legal.pending');
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validez d’abord les documents en attente.', 'redirect' => $url], 409);
            }

            return redirect($url)->with('info', 'Avant de continuer, validez les documents mis à jour.');
        }

        return $next($request);
    }
}
