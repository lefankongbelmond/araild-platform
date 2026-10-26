<?php

namespace App\Http\Requests\Social;

use App\Enums\OrganizationType;
use App\Models\Central\Country;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') !== null;
    }

    /** Unchecking every domain sends no "domains" key at all; treat that as "none". */
    protected function prepareForValidation(): void
    {
        $this->merge(['domains' => $this->input('domains', [])]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'tagline' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'domains' => ['nullable', 'array'],
            'domains.*' => [Rule::in(config('mxconnect.social.domains'))],
            'country_id' => ['nullable', 'integer', Rule::exists(Country::class, 'id')],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'socials' => ['nullable', 'array:facebook,linkedin,youtube,x,instagram'],
            'socials.*' => ['nullable', 'url:http,https', 'max:255'],
            'stats' => ['nullable', 'array', 'max:4'],
            'stats.*.label' => ['nullable', 'string', 'max:60'],
            'stats.*.value' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
