<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:1', 'max:1000'],
            'image'   => ['nullable', 'image', 'max:4096'],
        ];
    }
}
