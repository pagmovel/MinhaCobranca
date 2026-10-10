<?php

namespace App\Http\Requests;

use App\Enums\TipoChavePix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChavePixRequest extends FormRequest
{
    /** Chave de outro usuário vira 404 antes da validação (senão o erro de unicidade revelaria que ela existe). */
    public function authorize(): bool
    {
        $chave = $this->route('chave');

        abort_if($chave !== null && ! $this->user()->chavesPix()->whereKey($chave)->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipo = TipoChavePix::tryFrom((string) $this->input('tipo'));

        $this->merge(['chave' => $tipo?->normalizar($this->input('chave'))]);
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoChavePix::class)],
            'chave' => [
                'required',
                Rule::unique('chaves_pix', 'chave')->where('user_id', $this->user()->id)->ignore($this->route('chave')),
            ],
            'apelido' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'chave.required' => 'Informe uma chave válida para o tipo escolhido.',
            'chave.unique' => 'Esta chave já está cadastrada.',
        ];
    }
}
