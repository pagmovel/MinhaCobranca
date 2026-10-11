<?php

namespace App\Http\Requests;

use App\Enums\TipoItem;
use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProdutoServicoRequest extends FormRequest
{
    /** Item de outro usuário vira 404 antes da validação (o erro não pode revelar que ele existe). */
    public function authorize(): bool
    {
        $produto = $this->route('produto');

        abort_if($produto !== null && ! $this->user()->produtosServicos()->whereKey($produto)->exists(), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('produtos_servicos', 'nome')->where('user_id', $this->user()->id)->ignore($this->route('produto')),
            ],
            'tipo' => ['required', Rule::enum(TipoItem::class)],
            'unidade' => ['required', 'string', 'max:20'],
            'preco' => ['required', new ValorEmReais(aceitaZero: true)],
            'codigo_servico' => ['nullable', 'string', 'max:255'],
            'codigo_nbs' => ['nullable', 'string', 'max:20'],
            'codigo_tributacao_nacional' => ['nullable', 'string', 'max:20'],
            'ativo' => ['boolean'],
        ];
    }

    public function dados(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['preco']) + [
            'preco_centavos' => Dinheiro::paraCentavos($dados['preco']),
            'ativo' => $this->boolean('ativo', true),
        ];
    }
}
