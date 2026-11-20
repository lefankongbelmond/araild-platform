<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Illuminate\Http\Request;

/** Langue sur le sous-domaine d'une mutuelle, une fois la mutuelle identifiée (voir SetLocale). */
class SetTenantLocale extends SetLocale
{
    protected function applies(Request $request): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }

    protected function accounts(Request $request): array
    {
        try {
            return [auth('web')->user()];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function mutualDefault(): ?string
    {
        $code = self::localeCode((int) (tenant('locale_id') ?? 0));

        return Locales::isActive($code) ? $code : null;
    }
}
