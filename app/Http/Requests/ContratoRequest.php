<?php

namespace App\Http\Requests;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Http\Requests\Concerns\RegrasDeItens;
use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\AnoValido;
use App\Rules\LinhasMaximas;
use App\Support\CondicoesDePagamento;
use App\Support\Desconto;
use App\Support\Dinheiro;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ContratoRequest extends FormRequest
{
    use RegrasDeItens {
        after as protected depoisDosItens;
    }
    use RegrasDePagamento;

    /** As três regras de condições de pagamento do contrato (o texto digitado; o tipo vem em `{campo}_tipo`). */
    private const CONDICOES = ['desconto_pontualidade', 'multa', 'juros'];

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
            'data_inicio' => ['required', 'date_format:Y-m-d', new AnoValido],
            'data_fim' => ['nullable', 'date_format:Y-m-d', new AnoValido, 'after_or_equal:data_inicio'],
            'ativo' => ['boolean'],
            'reativar' => ['nullable', 'boolean'],
            'corrigir' => ['nullable', 'boolean'],
            'gerar_retroativas' => ['nullable', 'boolean'],
            ...$this->regrasDeItens(),
            ...$this->regrasDePagamento(false),
            ...$this->regrasDaCondicao('desconto_pontualidade'),
            ...$this->regrasDaCondicao('multa'),
            ...$this->regrasDaCondicao('juros'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'desconto_pontualidade_tipo.required_with' => 'Escolha se o desconto é em % ou em R$.',
            'multa_tipo.required_with' => 'Escolha se a multa é em % ou em R$.',
            'juros_tipo.required_with' => 'Escolha se os juros são em % ou em R$.',
        ];
    }

    /**
     * Tipo (% ou R$) e texto digitado de uma condição. Vazio e 0 valem como sem regra; o texto precisa estar no
     * formato do tipo (percentual até 100%, com até 2 casas).
     *
     * @return array<string, list<mixed>>
     */
    private function regrasDaCondicao(string $campo): array
    {
        return [
            "{$campo}_tipo" => ['nullable', 'required_with:'.$campo, Rule::in([Desconto::PERCENTUAL, Desconto::VALOR])],
            $campo => ['bail', 'nullable', 'string', 'max:20', function (string $atributo, mixed $valor, Closure $falhar) use ($campo) {
                $tipo = $this->input("{$campo}_tipo");

                // Tipo ausente ou inválido já tem erro próprio; aqui só o formato do texto.
                if (! in_array($tipo, [Desconto::PERCENTUAL, Desconto::VALOR], true)) {
                    return;
                }

                if (CondicoesDePagamento::regraDaEntrada($tipo, $valor) === null) {
                    $falhar($tipo === Desconto::PERCENTUAL
                        ? 'Informe um percentual de 0 a 100, com até 2 casas decimais.'
                        : 'Informe um valor em reais, como 15,00.');
                }
            }],
        ];
    }

    /** O desconto em R$ não passa do valor do contrato (a soma dos itens, que só existe com os itens válidos). */
    public function after(): array
    {
        return [
            ...$this->depoisDosItens(),
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->input('desconto_pontualidade_tipo') !== Desconto::VALOR) {
                    return;
                }

                $desconto = CondicoesDePagamento::regraDaEntrada(Desconto::VALOR, $this->input('desconto_pontualidade'));
                $valorDoContrato = $this->resumoDosItens()['valor_centavos'];

                if ($desconto > $valorDoContrato) {
                    $validator->errors()->add('desconto_pontualidade', 'O desconto não pode passar do valor do contrato (R$ '.Dinheiro::formatar($valorDoContrato).').');
                }
            },
        ];
    }

    public function dadosContrato(): array
    {
        $dados = $this->validated();

        // Descrição é complemento opcional (o recibo lista os itens); a coluna não aceita nulo.
        return ['descricao' => (string) ($dados['descricao'] ?? '')]
            + Arr::except($dados, ['descricao', 'itens', 'forma_pagamento', 'chave_pix_id', 'reativar', 'corrigir', 'gerar_retroativas', ...$this->camposDasCondicoes()])
            + ['ativo' => $this->boolean('ativo', true)]
            + $this->resumoDosItens()
            + $this->dadosDePagamento($dados)
            + $this->dadosDasCondicoes($dados);
    }

    /** @return list<string> */
    private function camposDasCondicoes(): array
    {
        return array_merge(...array_map(fn (string $campo) => [$campo, "{$campo}_tipo"], self::CONDICOES));
    }

    /**
     * Cada condição vira `{campo}_tipo` e `{campo}_valor` (pontos-base ou centavos); vazio ou 0 é sem regra, os dois
     * nulos. Condição que não veio no pedido fica de fora: salvar sem ela mantém o que está gravado.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, int|string|null>
     */
    private function dadosDasCondicoes(array $dados): array
    {
        $gravar = [];

        foreach (self::CONDICOES as $campo) {
            if (! array_key_exists($campo, $dados)) {
                continue;
            }

            $valor = CondicoesDePagamento::regraDaEntrada($dados["{$campo}_tipo"] ?? Desconto::PERCENTUAL, $dados[$campo]) ?: null;
            $gravar["{$campo}_tipo"] = $valor === null ? null : $dados["{$campo}_tipo"];
            $gravar["{$campo}_valor"] = $valor;
        }

        return $gravar;
    }
}
