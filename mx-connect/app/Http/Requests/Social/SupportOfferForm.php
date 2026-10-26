<?php

namespace App\Http\Requests\Social;

use App\Enums\SupportType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportOfferForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') !== null;
    }

    public function rules(): array
    {
        return [
            'support_type' => ['required', Rule::enum(SupportType::class)],
            'message' => ['required', 'string', 'max:2000'],
            'contact_organization' => ['nullable', 'string', 'max:180'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'post_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}
