<?php

namespace App\Http\Requests;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Http\Requests\Concerns\RegrasDeItens;
use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\LinhasMaximas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ContratoRequest extends FormRequest
{
    use RegrasDeItens, RegrasDePagamento;

    /** Contrato de outro usuário vira 404 antes da validação (o erro não pode revelar que ele existe). */
    public function authorize(): bool
    {
        $contrato = $this->route('contrato');

        abort_if($contrato !== null && ! $this->user()->contratos()->whereKey($contrato)->exists(), 404);

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
            'periodicidade' => ['required', Rule::enum(Periodicidade::class)],
            'modalidade' => ['required', Rule::enum(Modalidade::class)],
            'dia_vencimento' => ['required', 'integer', 'between:1,31'],
            'data_inicio' => ['required', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:data_inicio'],
            'ativo' => ['boolean'],
            'reativar' => ['nullable', 'boolean'],
            'corrigir' => ['nullable', 'boolean'],
            'gerar_retroativas' => ['nullable', 'boolean'],
            ...$this->regrasDeItens(),
            ...$this->regrasDePagamento(false),
        ];
    }

    public function dadosContrato(): array
    {
        $dados = $this->validated();

        // Descrição é complemento opcional (o recibo lista os itens); a coluna não aceita nulo.
        return ['descricao' => (string) ($dados['descricao'] ?? '')]
            + Arr::except($dados, ['descricao', 'itens', 'forma_pagamento', 'chave_pix_id', 'reativar', 'corrigir', 'gerar_retroativas'])
            + ['ativo' => $this->boolean('ativo', true)]
            + $this->resumoDosItens()
            + $this->dadosDePagamento($dados);
    }
}
