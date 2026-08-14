<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Capture a subscription (maker step). Validation is a separate, gated action. */
class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('subscription.capture') ?? false;
    }

    public function rules(): array
    {
        return [
            'member_id'            => ['required', 'integer', Rule::exists('members', 'id')],
            'guarantee_version_id' => ['required', 'integer', Rule::exists('guarantee_versions', 'id')],
            'effective_date'       => ['required', 'date'],
        ];
    }
}
