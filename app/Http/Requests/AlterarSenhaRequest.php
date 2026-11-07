<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class AlterarSenhaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'senha_atual' => ['bail', 'required', 'string', 'current_password'],
            'nova_senha' => ['required', 'string', 'confirmed', 'different:senha_atual', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'senha_atual.required' => 'Digite a senha atual.',
            'senha_atual.current_password' => 'Senha incorreta.',
            'nova_senha.required' => 'Digite a nova senha.',
            'nova_senha.confirmed' => 'A confirmação não confere com a nova senha.',
            'nova_senha.different' => 'A nova senha precisa ser diferente da atual.',
        ];
    }

    public function attributes(): array
    {
        return ['senha_atual' => 'senha atual', 'nova_senha' => 'nova senha'];
    }
}
