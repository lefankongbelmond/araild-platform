<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('network')?->can('manage-config') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('currency')?->id;
        return [
            'code'       => ['required', 'string', 'size:3', Rule::unique('central.currencies', 'code')->ignore($id)],
            'name'       => ['required', 'string', 'max:80'],
            'symbol'     => ['required', 'string', 'max:8'],
            'minor_unit' => ['required', 'integer', 'between:0,4'],
            'active'     => ['boolean'],
        ];
    }
}
