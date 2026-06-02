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
            'content'     => ['nullable', 'string', 'max:2000', 'required_without_all:image,image_url'],
            'image'       => ['nullable', 'image', 'max:5120'],
            'image_url'   => ['nullable', 'string', 'max:500', 'regex:/^https:\/\/res\.cloudinary\.com\//'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_without' => 'A mensagem não pode estar vazia se não incluir imagem.',
            'content.max'              => 'A mensagem não pode ter mais de 2000 caracteres.',
            'image.image'              => 'O ficheiro enviado não é uma imagem válida. Formatos aceites: JPEG, PNG, GIF, WebP.',
            'image.max'                => 'A imagem não pode ser maior que 5 MB.',
        ];
    }
}
