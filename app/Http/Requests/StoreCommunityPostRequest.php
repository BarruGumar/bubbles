<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
            'image'   => ['nullable', 'image', 'max:6144'],
            'video'   => ['nullable', 'mimetypes:video/mp4,video/webm,video/quicktime,video/x-msvideo,video/mpeg', 'max:102400'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'     => 'O post não pode estar vazio.',
            'content.max'          => 'O post não pode ter mais de 1000 caracteres.',
            'image.image'          => 'O ficheiro enviado não é uma imagem válida. Formatos aceites: JPEG, PNG, GIF, WebP.',
            'image.max'            => 'A imagem não pode ser maior que 6 MB.',
            'video.mimetypes'      => 'O vídeo deve estar em formato MP4, WebM ou MOV.',
            'video.max'            => 'O vídeo não pode ser maior que 100 MB.',
        ];
    }
}
