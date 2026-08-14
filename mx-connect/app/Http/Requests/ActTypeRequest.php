<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActTypeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    public function rules(): array
    {
        $id = $this->route('act_type')?->id;
        return [
            'code'     => ['required', 'string', 'max:30', Rule::unique('act_types', 'code')->ignore($id)],
            'label'    => ['required', 'string', 'max:180'],
            'category' => ['nullable', 'string', 'max:80'],
        ];
    }
}
