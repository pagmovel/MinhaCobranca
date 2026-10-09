<?php

namespace App\Http\Requests;

use App\Enums\FamiliaModelo;
use App\Rules\DocumentoValido;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cep = preg_replace('/\D/', '', (string) $this->input('cep'));
        $uf = mb_strtoupper((string) $this->input('uf'));

        $this->merge([
            'documento' => Documento::normalizar($this->input('documento')),
            'cep' => $cep === '' ? null : $cep,
            'uf' => $uf === '' ? null : $uf,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'documento' => [
                'required',
                new DocumentoValido,
                Rule::unique('clientes', 'documento')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('cliente')),
            ],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'cep' => ['nullable', 'digits:8'],
            'modelo' => ['required', Rule::enum(FamiliaModelo::class)],
        ];
    }
}
