<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('network')?->can('manage-config') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('locale')?->id;
        return [
            'code'   => ['required', 'string', 'max:10', Rule::unique('central.locales', 'code')->ignore($id)],
            'name'   => ['required', 'string', 'max:80'],
            'active' => ['boolean'],
        ];
    }
}
