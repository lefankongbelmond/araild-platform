<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('network')?->can('manage-config') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('country')?->id;
        return [
            'iso2'                => ['required', 'string', 'size:2', Rule::unique('central.countries', 'iso2')->ignore($id)],
            'name'                => ['required', 'string', 'max:120'],
            'default_currency_id' => ['required', 'integer', Rule::exists('central.currencies', 'id')],
            'default_locale_id'   => ['required', 'integer', Rule::exists('central.locales', 'id')],
            'phone_prefix'        => ['nullable', 'string', 'max:6'],
            'active'              => ['boolean'],
            'providers'           => ['array'],
            'providers.*'         => ['integer', Rule::exists('central.payment_providers', 'id')],
        ];
    }
}
