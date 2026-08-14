<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('network')?->can('manage-config') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('provider')?->id;
        return [
            'code'   => ['required', 'string', 'max:40', Rule::unique('central.payment_providers', 'code')->ignore($id)],
            'name'   => ['required', 'string', 'max:120'],
            'driver' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }
}
