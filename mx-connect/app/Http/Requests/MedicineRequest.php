<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicineRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('manage-parameters') ?? false; }

    public function rules(): array
    {
        $id = $this->route('medicine')?->id;
        return [
            'code'             => ['required', 'string', 'max:30', Rule::unique('medicines', 'code')->ignore($id)],
            'label'            => ['required', 'string', 'max:180'],
            'reference_price'  => ['required', 'numeric', 'min:0'],  // major units; converted to minor in controller
        ];
    }
}
