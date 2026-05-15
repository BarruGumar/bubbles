<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:2000', 'required_without:image'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
