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
        $apelido = $this->input('apelido');
        // Cliente informal: sem documento é nulo (não ''), para não colidir no índice único com outro informal.
        $documento = Documento::normalizar($this->input('documento'));

        $this->merge([
            // Sem apelido = '' (a coluna não aceita nulo: é parte do índice único com o documento).
            'apelido' => is_string($apelido) ? trim($apelido) : ($apelido ?? ''),
            'documento' => $documento === '' ? null : $documento,
            'cep' => $cep === '' ? null : $cep,
            'uf' => $uf === '' ? null : $uf,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'apelido' => ['string', 'max:60'],
            'contato' => ['nullable', 'string', 'max:100'],
            // Cliente informal pode não ter CPF/CNPJ. Quando tem, vale a mesma regra de sempre: repete com apelido
            // diferente, não na mesma unidade.
            'documento' => [
                'nullable',
                new DocumentoValido,
                Rule::unique('clientes', 'documento')
                    ->where('user_id', $this->user()->id)
                    ->where('apelido', is_string($this->input('apelido')) ? $this->input('apelido') : '')
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
            // Troca de modelo: refazer também as cobranças já enviadas e não pagas (Fase 22).
            'regerar_enviadas' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        // O erro já diz a saída: outra unidade do mesmo CNPJ entra com outro apelido.
        return ['documento.unique' => 'Este CPF/CNPJ já está cadastrado. Para outra unidade do mesmo cliente, preencha um apelido diferente.'];
    }
}
