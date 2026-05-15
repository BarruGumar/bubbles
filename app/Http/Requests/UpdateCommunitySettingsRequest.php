<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunitySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'community_title' => ['nullable', 'string', 'max:120'],
            'community_tagline' => ['nullable', 'string', 'max:160'],
            'community_description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'string', 'max:40'],
            'community_guidelines' => ['nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ];
    }
}
