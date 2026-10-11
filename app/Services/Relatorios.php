<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\ContratoItem;
use App\Models\ProdutoServico;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Relatórios do módulo Cobrança: cada método devolve uma tabela (colunas, linhas e totais) para a tela e para o CSV. */
final class Relatorios
{
    public function faturamento(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $fim = $ate->endOfMonth();
        $hoje = $hoje->startOfDay();

        // ponytail: soma em PHP sobre as cobranças do usuário até o fim do período; agrupar no SQL se passar de dezenas de milhares.
        $cobrancas = $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->where(fn ($consulta) => $consulta->where('vencimento', '<=', $fim)->orWhereBetween('pago_em', [$de, $fim]))
            ->get(['vencimento', 'pago_em', 'status', 'valor_centavos']);

        $linhas = [];
        for ($mes = $de; $mes->lte($ate); $mes = $mes->addMonthNoOverflow()) {
            $inicioMes = $mes->startOfMonth();
            $fimMes = $mes->endOfMonth();
            $corte = $fimMes->startOfDay()->min($hoje);
            $linha = ['mes' => $mes->format('m/Y'), 'previsto' => 0, 'recebido' => 0, 'inadimplencia' => 0];

            foreach ($cobrancas as $cobranca) {
                /** @var Cobranca $cobranca */
                if ($cobranca->vencimento->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['previsto'] += $cobranca->valor_centavos;
                }
                if ($cobranca->status === StatusCobranca::Paga && $cobranca->pago_em?->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['recebido'] += $cobranca->valor_centavos;
                }
                if ($inicioMes->lte($hoje) && $cobranca->vencimento->lt($corte)
                    && ($cobranca->pago_em === null || $cobranca->pago_em->gt($corte))) {
                    $linha['inadimplencia'] += $cobranca->valor_centavos;
                }
            }

            $linhas[] = $linha;
        }

        return $this->tabela(
            'Faturamento por período',
            'Mês a mês: previsto (vencimentos), recebido (pagamentos do mês) e o que estava em atraso no fim do mês.',
            [
                self::coluna('mes', 'Mês', 'texto'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('inadimplencia', 'Em atraso no fim do mês', soma: false),
            ],
            $linhas,
        );
    }

    public function porCliente(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $fim = $ate->endOfMonth();
        $hoje = $hoje->startOfDay();

        $pagas = $usuario->cobrancas()->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$de, $fim])
            ->selectRaw('cliente_id, sum(valor_centavos) as recebido, count(*) as quantidade')
            ->groupBy('cliente_id')->get()->keyBy('cliente_id');
        $contratos = $usuario->contratos()->where('ativo', true)
            ->selectRaw('cliente_id, count(*) as quantidade')->groupBy('cliente_id')->pluck('quantidade', 'cliente_id');
        $vencidas = $usuario->cobrancas()->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje)
            ->get(['cliente_id', 'vencimento'])->groupBy('cliente_id');

        $ids = $pagas->keys()->merge($contratos->keys())->merge($vencidas->keys())->unique()->all();

        $linhas = $usuario->clientes()->whereIn('id', $ids)->pluck('nome', 'id')
            ->map(function (string $nome, int $id) use ($pagas, $contratos, $vencidas, $hoje) {
                $recebido = (int) ($pagas[$id]->recebido ?? 0);
                $quantidade = (int) ($pagas[$id]->quantidade ?? 0);
                $atrasos = ($vencidas[$id] ?? collect())->map(fn (Cobranca $cobranca) => (int) $cobranca->vencimento->diffInDays($hoje));

                return [
                    'cliente' => $nome,
                    'recebido' => $recebido,
                    'contratos' => (int) ($contratos[$id] ?? 0),
                    'media' => $quantidade > 0 ? intdiv($recebido, $quantidade) : 0,
                    'vencidas' => $atrasos->count(),
                    'atraso' => $atrasos->isEmpty() ? 0 : (int) round($atrasos->avg()),
                ];
            })
            ->sortBy([['recebido', 'desc'], ['cliente', 'asc']])->values()->all();

        return $this->tabela(
            'Receita por cliente',
            'Recebido no período, contratos ativos, valor médio por cobrança paga e cobranças vencidas em aberto hoje.',
            [
                self::coluna('cliente', 'Cliente', 'texto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('contratos', 'Contratos ativos', 'numero'),
                self::coluna('media', 'Valor médio por cobrança paga', soma: false),
                self::coluna('vencidas', 'Vencidas em aberto', 'numero'),
                self::coluna('atraso', 'Atraso médio (dias)', 'dias', soma: false),
            ],
            $linhas,
        );
    }

    public function porProduto(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $fim = $ate->endOfMonth();
        $somaDosItens = fn () => CobrancaItem::query()
            ->join('cobrancas', 'cobrancas.id', '=', 'cobranca_itens.cobranca_id')
            ->where('cobrancas.user_id', $usuario->id)
            ->selectRaw('cobranca_itens.produto_servico_id as produto, sum(cobranca_itens.total_centavos) as soma')
            ->groupBy('cobranca_itens.produto_servico_id');

        $recebido = $somaDosItens()->where('cobrancas.status', StatusCobranca::Paga)
            ->whereBetween('cobrancas.pago_em', [$de, $fim])->pluck('soma', 'produto');
        $previsto = $somaDosItens()->where('cobrancas.status', '!=', StatusCobranca::Cancelada)
            ->whereBetween('cobrancas.vencimento', [$de, $fim])->pluck('soma', 'produto');
        $contratos = ContratoItem::query()
            ->join('contratos', 'contratos.id', '=', 'contrato_itens.contrato_id')
            ->where('contratos.user_id', $usuario->id)->where('contratos.ativo', true)
            ->selectRaw('contrato_itens.produto_servico_id as produto, count(distinct contrato_itens.contrato_id) as quantidade')
            ->groupBy('contrato_itens.produto_servico_id')->pluck('quantidade', 'produto');

        $ids = $recebido->keys()->merge($previsto->keys())->merge($contratos->keys())->unique()->all();

        $linhas = $usuario->produtosServicos()->whereIn('id', $ids)->get(['id', 'nome'])
            ->map(fn (ProdutoServico $produto) => [
                'item' => $produto->nome,
                'recebido' => (int) ($recebido[$produto->id] ?? 0),
                'previsto' => (int) ($previsto[$produto->id] ?? 0),
                'contratos' => (int) ($contratos[$produto->id] ?? 0),
            ])
            ->sortBy([['recebido', 'desc'], ['item', 'asc']])->values()->all();

        $semItens = fn () => $usuario->cobrancas()->whereDoesntHave('itens');
        $semItemRecebido = (int) $semItens()->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$de, $fim])->sum('valor_centavos');
        $semItemPrevisto = (int) $semItens()->where('status', '!=', StatusCobranca::Cancelada)->whereBetween('vencimento', [$de, $fim])->sum('valor_centavos');

        if ($semItemRecebido > 0 || $semItemPrevisto > 0) {
            $linhas[] = [
                'item' => 'Sem item (cobranças anteriores ao catálogo)',
                'recebido' => $semItemRecebido,
                'previsto' => $semItemPrevisto,
                'contratos' => 0,
            ];
        }

        return $this->tabela(
            'Receita por produto ou serviço',
            'Pelos itens das cobranças: recebido (pagas no período) e previsto (vencendo no período), com os contratos ativos que usam cada item.',
            [
                self::coluna('item', 'Produto ou serviço', 'texto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('contratos', 'Contratos ativos', 'numero', soma: false),
            ],
            $linhas,
        );
    }

    /** @return array{chave: string, rotulo: string, tipo: string, soma: bool} */
    private static function coluna(string $chave, string $rotulo, string $tipo = 'dinheiro', bool $soma = true): array
    {
        return ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo, 'soma' => $soma];
    }

    /**
     * @param  list<array{chave: string, rotulo: string, tipo: string, soma: bool}>  $colunas
     * @param  list<array<string, int|string>>  $linhas
     */
    private function tabela(string $titulo, string $descricao, array $colunas, array $linhas): array
    {
        $totais = [];
        foreach ($colunas as $indice => $coluna) {
            $totais[$coluna['chave']] = match (true) {
                $indice === 0 => 'Total',
                $coluna['soma'] && in_array($coluna['tipo'], ['dinheiro', 'numero'], true) => array_sum(array_column($linhas, $coluna['chave'])),
                default => null,
            };
        }

        return ['titulo' => $titulo, 'descricao' => $descricao, 'colunas' => $colunas, 'linhas' => $linhas, 'totais' => $totais];
    }
}
