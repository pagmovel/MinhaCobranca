<?php

namespace App\Http\Requests;

use App\Enums\TipoAvisoModelo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeloMensagemRequest extends FormRequest
{
    /** Modelo de outro usuário vira 404 antes da validação (padrão de FeriadoRequest/ChavePixRequest). */
    public function authorize(): bool
    {
        $mensagem = $this->route('mensagem');

        abort_if($mensagem !== null && ! $this->user()->modelosMensagem()->whereKey($mensagem)->exists(), 404);

        return true;
    }

    /** Checkbox desmarcado não manda nada: sem isto, `validated()` nem traria a chave e update() deixaria o padrão antigo intacto. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'padrao' => $this->boolean('padrao'),
            // "Nenhuma" no select manda '': vira null para a coluna nulável, nunca uma string vazia salva.
            'tipo_situacao' => $this->input('tipo_situacao') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string', 'max:2000'],
            'padrao' => ['required', 'boolean'],
            'tipo_situacao' => ['nullable', Rule::enum(TipoAvisoModelo::class)],
        ];
    }
}
