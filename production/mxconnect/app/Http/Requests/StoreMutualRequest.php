<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMutualRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only a super admin may create a mutual (enforced again by the policy).
        return $this->user('network')?->can('create', \App\Models\Central\Mutual::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:180'],
            'slug'        => ['required', 'string', 'max:60', 'alpha_dash',
                              Rule::unique('mutuals', 'slug')->where(fn ($q) => $q->getConnection()->getName() === 'central')],
            'country_id'  => ['required', 'integer', Rule::exists('central.countries', 'id')],
            'currency_id' => ['required', 'integer', Rule::exists('central.currencies', 'id')],
            'locale_id'   => ['required', 'integer', Rule::exists('central.locales', 'id')],
            'region'      => ['nullable', 'string', 'max:120'],
            'city'        => ['nullable', 'string', 'max:120'],

            // Hébergement mutualisé (TENANT_DB_MODE=manual) : base créée à
            // l'avance dans le panneau LWS. Utilisateur/mot de passe facultatifs
            // (par défaut : ceux de la base centrale).
            'db_name'     => [config('mxconnect.tenant_db_mode') === 'manual' ? 'required' : 'prohibited',
                              'nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_username' => ['nullable', 'string', 'max:80'],
            'db_password' => ['nullable', 'string', 'max:190'],

            // Premier administrateur de la mutuelle (créé dans SA base).
            'admin_email'      => ['nullable', 'email', 'max:180'],
            'admin_first_name' => ['nullable', 'string', 'max:120'],
            'admin_last_name'  => ['nullable', 'string', 'max:120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('mxconnect.mutual.name'),
            'slug' => __('mxconnect.mutual.slug'),
            'country_id' => __('mxconnect.mutual.country'),
            'currency_id' => __('mxconnect.mutual.currency'),
            'locale_id' => __('mxconnect.mutual.locale'),
            'db_name' => 'nom de la base de données',
            'db_username' => 'utilisateur de la base',
            'db_password' => 'mot de passe de la base',
            'admin_email' => "e-mail de l'administrateur",
        ];
    }
}
