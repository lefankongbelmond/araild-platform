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

    /** « 1 250,50 » ou « 1250.50 » → 1250.5 */
    protected function amount($raw): float
    {
        return (float) str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], (string) $raw);
    }
}
