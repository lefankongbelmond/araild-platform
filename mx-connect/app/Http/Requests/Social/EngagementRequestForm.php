<?php

namespace App\Http\Requests\Social;

use App\Enums\ContributionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EngagementRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') !== null;
    }

    public function rules(): array
    {
        return [
            'contribution_type' => ['required', Rule::enum(ContributionType::class)],
            'message' => ['nullable', 'string', 'max:2000'],
            'post_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
        ];
    }
}
