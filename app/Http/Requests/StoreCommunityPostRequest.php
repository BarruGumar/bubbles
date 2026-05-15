<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $bubble = \App\Models\Bubble::find($this->route('id'));

        return $bubble && $bubble->memberships()->where('user_id', auth()->id())->exists();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
            'image'   => ['nullable', 'image', 'max:4096'],
            'video'   => ['nullable', 'mimetypes:video/mp4,video/webm,video/quicktime,video/x-msvideo,video/mpeg', 'max:102400'],
        ];
    }
}
