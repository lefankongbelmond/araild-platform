<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CareClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return \App\Support\TenantAccess::can('claims.store', $this->user());
    }

    public function rules(): array
    {
        return [
            'member_id'    => ['required', 'integer', Rule::exists('members', 'id')],
            'dependent_id' => ['nullable', 'integer', Rule::exists('dependents', 'id')],
            'opened_on'    => ['required', 'date'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
