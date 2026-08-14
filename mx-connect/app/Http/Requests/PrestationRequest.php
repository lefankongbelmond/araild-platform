<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Capture a prestation (maker). Validation is a separate, gated action. */
class PrestationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['benefits_manager', 'controller_validator', 'mutual_admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'act_type_id' => ['required', 'integer', Rule::exists('act_types', 'id')],
            'provider_id' => ['required', 'integer'],           // central care_providers id
            'care_date'   => ['required', 'date'],
            'total'       => ['required', 'numeric', 'min:0'],  // major units, converted in controller
            'channel'     => ['required', Rule::in(['reimbursement', 'tiers_payant'])],
        ];
    }
}
