<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AntennaRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'locality'  => ['nullable', 'string', 'max:120'],
            'manager'   => ['nullable', 'string', 'max:120'],
        ];
    }
}
