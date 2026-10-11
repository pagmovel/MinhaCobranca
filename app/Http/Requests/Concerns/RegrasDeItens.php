<?php

namespace App\Http\Requests\Concerns;

use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\LinhasDoRecibo;
use App\Support\TextoDoRecibo;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Itens do catálogo: iguais no contrato e na cobrança. Valor e códigos fiscais saem sempre daqui, nunca da tela. */
trait RegrasDeItens
{
    /** @var list<array<string, mixed>>|null */
    private ?array $itensCalculados = null;

    /** @var array{codigo_servico: ?string, codigo_nbs: ?string, codigo_tributacao_nacional: ?string} */
    private array $codigosDoPrincipal = ['codigo_servico' => null, 'codigo_nbs' => null, 'codigo_tributacao_nacional' => null];

    /** Quantidade digitada com vírgula ("1,5") vira ponto antes da validação numérica. */
    protected function prepararItens(): void
    {
        $itens = $this->input('itens');

        if (! is_array($itens)) {
            return;
        }

        $this->merge(['itens' => array_map(
            fn ($item) => is_array($item) && is_scalar($item['quantidade'] ?? null)
                ? ['quantidade' => str_replace(',', '.', trim((string) $item['quantidade']))] + $item
                : $item,
            $itens,
        )]);
    }

    protected function regrasDeItens(): array
    {
        return [
            'itens' => ['required', 'array', 'min:1', 'max:50'],
            'itens.*.produto_servico_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('produtos_servicos', 'id')->where('user_id', $this->user()->id),
            ],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,5}(\.\d{1,3})?$/'],
            'itens.*.preco' => ['required', new ValorEmReais(aceitaZero: true)],
            'itens.*.principal' => ['nullable', 'boolean'],
        ];
    }

    /** O total precisa caber num valor em reais: nem zero, nem acima do máximo. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $soma = array_sum(array_column($this->itensCalculados(), 'total_centavos'));

                if ($soma < 1 || $soma > ValorEmReais::MAXIMO_CENTAVOS) {
                    $validator->errors()->add('itens', 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.');
                }

                // O recibo tem caixa para 25 linhas: itens (um por linha) + descrição complementar.
                $texto = TextoDoRecibo::compor($this->itensCalculados(), $this->input('descricao'));
                if (count(LinhasDoRecibo::quebrar($texto)) > 25) {
                    $validator->errors()->add('descricao', 'Itens e descrição passam de 25 linhas no recibo. Encurte a descrição ou junte itens.');
                }
            },
        ];
    }

    /** @return list<array{produto_servico_id: int, nome: string, unidade: string, quantidade: string, preco_unitario_centavos: int, total_centavos: int, principal: bool, ordem: int}> */
    public function itensCalculados(): array
    {
        if ($this->itensCalculados !== null) {
            return $this->itensCalculados;
        }

        $itens = array_values($this->input('itens', []));
        $catalogo = $this->user()->produtosServicos()
            ->findMany(array_column($itens, 'produto_servico_id'))
            ->keyBy('id');

        $linhas = [];
        foreach ($itens as $ordem => $item) {
            $produto = $catalogo[(int) $item['produto_servico_id']];
            $milesimos = ItensDeVenda::milesimos($item['quantidade']);
            $preco = Dinheiro::paraCentavos((string) $item['preco']);

            $linhas[] = [
                'produto_servico_id' => $produto->id,
                'nome' => $produto->nome,
                'unidade' => $produto->unidade,
                'quantidade' => ItensDeVenda::quantidade($milesimos),
                'preco_unitario_centavos' => $preco,
                'total_centavos' => ItensDeVenda::total($milesimos, $preco),
                'principal' => filter_var($item['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'ordem' => $ordem,
            ];
        }

        $principal = ItensDeVenda::indicePrincipal($linhas);
        foreach (array_keys($linhas) as $indice) {
            $linhas[$indice]['principal'] = $indice === $principal;
        }

        $this->codigosDoPrincipal = $catalogo[$linhas[$principal]['produto_servico_id']]
            ->only(['codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional']);

        return $this->itensCalculados = $linhas;
    }

    /** Valor = soma dos itens; códigos fiscais = os do item principal no catálogo. */
    protected function resumoDosItens(): array
    {
        $itens = $this->itensCalculados();

        return ['valor_centavos' => array_sum(array_column($itens, 'total_centavos'))] + $this->codigosDoPrincipal;
    }

    /** Linhas para `contrato_itens`: o contrato guarda quantidade e preço; nome e unidade ficam no catálogo. */
    public function itensDoContrato(): array
    {
        return array_map(
            fn (array $item) => Arr::only($item, ['produto_servico_id', 'quantidade', 'preco_unitario_centavos', 'principal', 'ordem']),
            $this->itensCalculados(),
        );
    }

    /** Linhas para `cobranca_itens`: cópia completa (nome, unidade e total), que não muda se o catálogo mudar. */
    public function itensDaCobranca(): array
    {
        return array_map(fn (array $item) => Arr::except($item, ['ordem']), $this->itensCalculados());
    }
}
