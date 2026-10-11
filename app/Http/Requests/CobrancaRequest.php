<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDeItens;
use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\LinhasMaximas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CobrancaRequest extends FormRequest
{
    use RegrasDeItens, RegrasDePagamento;

    /** Cobrança de outro usuário vira 404 antes da validação (o erro não pode revelar que ela existe). */
    public function authorize(): bool
    {
        $cobranca = $this->route('cobranca');

        abort_if($cobranca !== null && ! $this->user()->cobrancas()->whereKey($cobranca)->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
        $this->prepararItens();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['nullable', 'string', 'max:2000', new LinhasMaximas(25)],
            'periodo_inicio' => ['required', 'date_format:Y-m-d'],
            'periodo_fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:periodo_inicio'],
            'vencimento' => ['required', 'date_format:Y-m-d'],
            ...$this->regrasDeItens(),
            ...$this->regrasDePagamento(true),
        ];
    }

    public function dadosCobranca(): array
    {
        $dados = $this->validated();

        // Descrição é complemento opcional (o recibo lista os itens); a coluna não aceita nulo.
        return ['descricao' => (string) ($dados['descricao'] ?? '')]
            + Arr::except($dados, ['descricao', 'itens', 'forma_pagamento', 'chave_pix_id', 'linha_digitavel'])
            + $this->resumoDosItens()
            + $this->dadosDePagamento($dados);
    }
}
