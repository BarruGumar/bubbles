<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'image'       => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'    => 'O nome do grupo não pode ter mais de 100 caracteres.',
            'image.image' => 'O ficheiro não é uma imagem válida.',
            'image.max'   => 'A imagem não pode ser maior que 4 MB.',
        ];
    }
}
