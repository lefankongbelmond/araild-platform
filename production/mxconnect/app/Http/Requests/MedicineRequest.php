<?php

namespace App\Http\Requests;

use App\Models\Tenant\Medicine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            // « 1 250,50 » ou « 1250.50 » : on accepte les deux écritures.
            'reference_price' => is_string($this->input('reference_price'))
                ? str_replace([' ', "\u{202F}", "\u{00A0}", ','], ['', '', '', '.'], $this->input('reference_price'))
                : $this->input('reference_price'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;
        $codes = \App\Models\Central\Currency::on('central')->where('active', true)->pluck('code')->all();

        return [
            'code'            => ['required', 'string', 'max:30', Rule::unique('medicines', 'code')->ignore($id)],
            'label'           => ['required', 'string', 'max:180'],
            'category'        => ['required', Rule::in(array_keys(Medicine::CATEGORIES))],
            'presentation'    => ['nullable', 'string', 'max:120'],
            'supplier'        => ['nullable', 'string', 'max:120'],
            'reference_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],  // unités principales
            'currency_code'   => ['required', Rule::in($codes)],
            'description'     => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'          => 'Ce code est déjà utilisé par un autre médicament (y compris supprimé).',
            'category.required'    => 'Choisissez une catégorie.',
            'currency_code.in'     => 'Devise non disponible.',
            'reference_price.numeric' => 'Prix invalide : saisissez un nombre, par exemple 1250 ou 12,50.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'code', 'label' => 'libellé', 'reference_price' => 'prix de référence', 'currency_code' => 'devise'];
    }
}
