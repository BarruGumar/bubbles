<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:100'],
            'description'       => ['nullable', 'string', 'max:500'],
            'participant_ids'   => ['required', 'array', 'min:2'],
            'participant_ids.*' => ['integer', 'exists:users,id', 'distinct'],
            'image'             => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'O grupo precisa de um nome.',
            'name.max'                 => 'O nome do grupo não pode ter mais de 100 caracteres.',
            'participant_ids.required' => 'Seleciona pelo menos 2 participantes.',
            'participant_ids.min'      => 'Um grupo precisa de pelo menos 2 participantes além de ti.',
            'participant_ids.*.exists' => 'Um ou mais utilizadores selecionados não existem.',
            'participant_ids.*.distinct' => 'Não podes adicionar o mesmo utilizador duas vezes.',
            'image.image'              => 'O ficheiro não é uma imagem válida.',
            'image.max'                => 'A imagem não pode ser maior que 4 MB.',
        ];
    }
}
