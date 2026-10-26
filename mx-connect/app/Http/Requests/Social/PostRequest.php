<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Shape validation only — authorship and business rules are enforced in PostService. */
class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('member') !== null;
    }

    public function rules(): array
    {
        $media = config('mxconnect.social.media');

        return [
            'as' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['text', 'event', 'opportunity', 'result'])],
            'body' => ['nullable', 'string', 'max:5000'],
            'visibility' => ['nullable', Rule::in(['public', 'followers'])],
            'project_id' => ['nullable', 'integer'],
            'event_starts_at' => ['nullable', 'required_if:type,event', 'date'],
            'event_location' => ['nullable', 'string', 'max:255'],
            'event_online' => ['nullable', 'boolean'],
            'opportunity_kind' => ['nullable', 'required_if:type,opportunity', Rule::in(config('mxconnect.social.opportunity_kinds'))],
            'media' => ['nullable', 'array', 'max:'.$media['max_files']],
            'media.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm', 'max:'.$media['video_max_kb']],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $limit = config('mxconnect.social.media.image_max_kb');
            foreach ((array) $this->file('media', []) as $i => $file) {
                if (str_starts_with((string) $file->getMimeType(), 'image/') && $file->getSize() > $limit * 1024) {
                    $validator->errors()->add("media.$i", __('mxconnect.social.errors.image_too_large', ['kb' => $limit]));
                }
            }
        }];
    }
}
