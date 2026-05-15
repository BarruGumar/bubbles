<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'username' => [
                'nullable', 'string', 'min:3', 'max:30',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'bio' => ['nullable', 'string', 'max:300'],
            'avatar_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'O username só pode ter letras minúsculas, números e underscores.',
        ];
    }
}
