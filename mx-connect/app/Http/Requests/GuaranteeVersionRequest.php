<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rolls a guarantee to a new version (new terms from an effective date). */
class GuaranteeVersionRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    public function rules(): array
    {
        return [
            'base_contribution' => ['required', 'numeric', 'min:0'],
            'periodicity'       => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'coverage_rate'     => ['required', 'numeric', 'between:0,100'],
            'copay_rate'        => ['required', 'numeric', 'between:0,100'],
            'membership_fee'    => ['nullable', 'numeric', 'min:0'],
            'observation_days'  => ['required', 'integer', 'min:0'],
            'effective_from'    => ['required', 'date', 'after:today'],
        ];
    }
}
