<?php

namespace App\Http\Requests\Social;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:5000'],
            'objectives' => ['nullable', 'string', 'max:5000'],
            'zone' => ['nullable', 'string', 'max:200'],
            'beneficiaries_summary' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'progress_percent' => ['required', 'integer', 'between:0,100'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'partners' => ['nullable', 'string', 'max:2000'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
