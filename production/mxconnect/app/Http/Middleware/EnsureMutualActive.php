<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mutuelle suspendue par l'administration du réseau (2026-11-02) : son espace est
 * réellement fermé — back-office, portail des prestataires, espace et API des membres,
 * vérification des cartes. Ses données sont conservées ; la réactivation rouvre tout.
 * Restent permis : la déconnexion et les notifications de paiement (rapprochement).
 */
class EnsureMutualActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized || tenant('status') !== 'suspended'
            || $request->routeIs('tenant.logout', 'payments.webhook')) {
            return $next($request);
        }

        $message = 'L’espace de cette mutuelle est suspendu par l’administration du réseau MX-CONNECT. Les données sont conservées.';
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $message, 'suspended' => true], 403);
        }

        return response()->view('tenant.suspended', ['name' => tenant('name'), 'message' => $message], 403);
    }
}
