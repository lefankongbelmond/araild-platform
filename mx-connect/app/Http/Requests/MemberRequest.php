<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Enrollment agents and mutual admins may capture members.
        return $this->user()?->hasAnyRole(['enrollment_agent', 'mutual_admin']) ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('member')?->id;

        return [
            'first_name'   => ['required', 'string', 'max:120'],
            'last_name'    => ['required', 'string', 'max:120'],
            'birth_date'   => ['required', 'date', 'before:today'],
            'sex'          => ['required', Rule::in(['M', 'F'])],
            'phone'        => ['nullable', 'string', 'max:20'],
            'id_document'  => ['nullable', 'string', 'max:60'],
            'address'      => ['nullable', 'string', 'max:255'],
            'antenna_id'   => ['nullable', 'integer', Rule::exists('antennas', 'id')],
            'member_group_id' => ['nullable', 'integer', Rule::exists('member_groups', 'id')],
            // set when the agent has acknowledged a weak-duplicate warning
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }
}
