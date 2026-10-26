<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creates a guarantee + its first version in one form. */
class GuaranteeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    public function rules(): array
    {
        return [
            'code'                => ['required', 'string', 'max:30', Rule::unique('guarantees', 'code')],
            'name'                => ['required', 'string', 'max:150'],
            'description'         => ['nullable', 'string'],
            // first version terms
            'base_contribution'   => ['required', 'numeric', 'min:0'],
            'periodicity'         => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'coverage_rate'       => ['required', 'numeric', 'between:0,100'],
            'copay_rate'          => ['required', 'numeric', 'between:0,100'],
            'membership_fee'      => ['nullable', 'numeric', 'min:0'],
            'observation_days'    => ['required', 'integer', 'min:0'],
            'valid_from'          => ['nullable', 'date'],
        ];
    }
}
