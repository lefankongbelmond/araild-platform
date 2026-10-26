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
            'tariff'   => ['nullable', 'numeric', 'min:0', 'max:999999999999'],   // tarif conventionnel, unités principales
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code'   => mb_strtoupper(trim((string) $this->input('code'))),
            'tariff' => is_string($this->input('tariff')) && trim($this->input('tariff')) !== ''
                ? str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], $this->input('tariff')) : null,
        ]);
    }
}
