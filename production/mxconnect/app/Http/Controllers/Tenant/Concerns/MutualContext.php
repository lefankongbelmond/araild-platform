<?php

namespace App\Http\Controllers\Tenant\Concerns;

use App\Models\Central\Currency;
use Illuminate\Http\Request;

trait MutualContext
{
    /** Devise de la mutuelle (registre central). */
    protected function currency(): Currency
    {
        return Currency::on('central')->find(tenant('currency_id'))
            ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }

    protected function requireRoles(Request $request, array $roles): void
    {
        abort_unless($request->user()?->hasAnyRole($roles), 403, 'Accès réservé.');
    }

    /**
     * Valide la requête ; en cas d'erreur, redirige vers $url (avec les erreurs et la saisie)
     * plutôt que vers « l'URL précédente », qui peut être un export CSV téléchargé juste avant.
     */
    protected function validateOr(string $url, Request $request, array $rules, array $messages = []): array
    {
        try {
            return $request->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e->redirectTo($url);
        }
    }

    /** « 1 250,50 » ou « 1250.50 » → 1250.5 */
    protected function amount($raw): float
    {
        return (float) str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], (string) $raw);
    }
}
