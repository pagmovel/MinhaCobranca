<?php

namespace App\Http\Requests;

use App\Enums\FamiliaModelo;
use App\Rules\DocumentoValido;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'documento' => Documento::normalizar($this->input('documento')),
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')),
            'uf' => mb_strtoupper((string) $this->input('uf')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'documento' => ['required', new DocumentoValido],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'telefone' => ['required', 'string', 'max:20'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', Rule::in(Ufs::TODAS)],
            'cep' => ['required', 'digits:8'],
            'modelo_padrao' => ['required', Rule::enum(FamiliaModelo::class)],
            'dias_antecedencia' => ['required', 'integer', 'between:0,60'],
            'dias_aviso' => ['required', 'integer', 'between:1,30'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ];
    }
}
