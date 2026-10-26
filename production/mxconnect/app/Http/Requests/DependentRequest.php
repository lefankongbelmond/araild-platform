<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DependentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['enrollment_agent', 'mutual_admin']) ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:120'],
            'last_name'    => ['required', 'string', 'max:120'],
            'birth_date'   => ['required', 'date', 'before_or_equal:today'],
            'sex'          => ['required', Rule::in(['M', 'F'])],
            'relationship' => ['required', Rule::in(['spouse', 'child', 'ascendant', 'other'])],
        ];
    }
}
