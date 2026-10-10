<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\LinhasMaximas;
use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CobrancaRequest extends FormRequest
{
    use RegrasDePagamento;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['required', 'string', 'max:2000', new LinhasMaximas(25)],
            'codigo_servico' => ['nullable', 'string', 'max:255'],
            'codigo_nbs' => ['nullable', 'string', 'max:20'],
            'codigo_tributacao_nacional' => ['nullable', 'string', 'max:20'],
            'valor' => ['required', new ValorEmReais],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'vencimento' => ['required', 'date'],
            ...$this->regrasDePagamento(true),
        ];
    }

    public function dadosCobranca(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['valor', 'forma_pagamento', 'chave_pix_id', 'linha_digitavel'])
            + ['valor_centavos' => Dinheiro::paraCentavos($dados['valor'])]
            + $this->dadosDePagamento($dados);
    }
}
