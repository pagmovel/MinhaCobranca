<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use App\Support\BlocoDePaginas;
use Carbon\CarbonImmutable;

/** Lista da tela de cobranças (reais + previstas) em blocos de páginas, com os totais do resultado inteiro. */
final class ListaDeCobrancas
{
    private const ORDEM = ['vencida', 'vence_hoje', 'a_vencer', 'prevista', 'paga', 'cancelada'];

    public function __construct(private PrevisaoDeCobrancas $previsao) {}

    /**
     * @param  array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, contrato_id: ?int, canceladas: bool, pagina: int, por_pagina: int}  $filtros
     * @return array{linhas: list<array<string, mixed>>, totais: list<array{situacao: string, quantidade: int, soma_centavos: int}>, total_geral: array{quantidade: int, soma_centavos: int}, abas: list<array{chave: string, quantidade: int, soma_centavos: int}>, pagina: array{inicio: int, por_pagina: int, total: int}}
     */
    public function montar(User $usuario, array $filtros, CarbonImmutable $hoje): array
    {
        $status = $filtros['status'];
        $consulta = $usuario->cobrancas()->orderBy('vencimento')->orderBy('id');

        // "Vencendo hoje" e "Vencidas" valem para qualquer mês: a vencida de setembro precisa aparecer em outubro.
        if (! in_array($status, ['vence_hoje', 'vencida'], true)) {
            $consulta->whereBetween('vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]);
        }

        match ($status) {
            // "Todas" deixa as canceladas de fora, salvo com "Incluir canceladas" marcado (pedido do usuário).
            null => $filtros['canceladas'] ? null : $consulta->where('status', '!=', StatusCobranca::Cancelada),
            'vence_hoje' => $consulta->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje, $hoje->endOfDay()]),
            'vencida' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje),
            'a_vencer' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '>', $hoje->endOfDay()),
            default => $consulta->where('status', $status),
        };

        // Previstas (calculadas pelos contratos, nada gravado): calculadas sempre (as abas de situação, mais abaixo,
        // precisam delas mesmo numa aba diferente de "A vencer"), mas só entram na listagem onde já entravam.
        $previstas = $this->previsao->entre($usuario, $filtros['mes']->max($hoje->addDay()), $filtros['ate']->endOfMonth());
        if ($filtros['cliente_id'] !== null) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
            $previstas = array_filter($previstas, fn (array $prevista) => $prevista['cliente_id'] === $filtros['cliente_id']);
        }
        if ($filtros['contrato_id'] !== null) {
            $consulta->where('contrato_id', $filtros['contrato_id']);
            $previstas = array_filter($previstas, fn (array $prevista) => $prevista['contrato_id'] === $filtros['contrato_id']);
        }
        $incluirPrevistasNaLista = in_array($status, [null, 'a_vencer', 'pendente'], true);

        // ponytail: a lista leve fica inteira na memória (até 36 meses); agregar no SQL se passar de dezenas de milhares de linhas.
        $leves = $consulta->get(['id', 'vencimento', 'status', 'valor_centavos'])
            ->map(fn (Cobranca $cobranca) => [
                'id' => $cobranca->id,
                'vencimento' => $cobranca->vencimento->toDateString(),
                'situacao' => $cobranca->situacao($hoje),
                'valor_centavos' => $cobranca->valor_centavos,
                'prevista' => null,
            ])
            ->concat($incluirPrevistasNaLista ? array_map(fn (array $prevista) => [
                'id' => $prevista['id'],
                'vencimento' => CarbonImmutable::createFromFormat('!d/m/Y', $prevista['vencimento'])->toDateString(),
                'situacao' => 'prevista',
                'valor_centavos' => $prevista['valor_centavos'],
                'prevista' => $prevista,
            ], array_values($previstas)) : [])
            ->sortBy('vencimento') // estável: no mesmo dia, a real (que veio antes) fica antes da prevista
            ->values();

        $totais = collect(self::ORDEM)
            ->map(fn (string $situacao) => [
                'situacao' => $situacao,
                'quantidade' => $leves->where('situacao', $situacao)->count(),
                'soma_centavos' => (int) $leves->where('situacao', $situacao)->sum('valor_centavos'),
            ])
            ->where('quantidade', '>', 0)
            ->values()
            ->all();
        // Cancelada não é dinheiro a receber: fica fora do total.
        $aReceber = $leves->where('situacao', '!=', 'cancelada');

        $bloco = BlocoDePaginas::de($leves, $filtros['pagina'], $filtros['por_pagina']);

        // Só as linhas do bloco carregam recibo, cliente e histórico.
        $completas = $usuario->cobrancas()->with(['cliente:id,nome,apelido', 'recibo', 'auditorias'])
            ->findMany($bloco['itens']->whereNull('prevista')->pluck('id'))
            ->keyBy('id');

        return [
            'linhas' => $bloco['itens']->map(fn (array $leve) => $leve['prevista'] ?? $completas[$leve['id']]->paraLista($hoje))->all(),
            'totais' => $totais,
            'total_geral' => ['quantidade' => $aReceber->count(), 'soma_centavos' => (int) $aReceber->sum('valor_centavos')],
            'abas' => $this->abas($usuario, $filtros, $hoje, $previstas),
            'pagina' => $bloco['pagina'],
        ];
    }

    /**
     * T17.16: contagem e soma de cada aba (Todas · Vencidas · Hoje · A vencer · Pagas), sempre do conjunto inteiro
     * admitido pelos filtros de período/cliente/contrato — independente de qual aba está selecionada para a
     * listagem, senão as abas não selecionadas "zerariam" ao clicar numa delas.
     *
     * @param  list<array<string, mixed>>  $previstas  já filtradas por cliente/contrato (ver montar())
     * @return list<array{chave: string, quantidade: int, soma_centavos: int}>
     */
    private function abas(User $usuario, array $filtros, CarbonImmutable $hoje, array $previstas): array
    {
        $base = fn () => $usuario->cobrancas()
            ->when($filtros['cliente_id'] !== null, fn ($q) => $q->where('cliente_id', $filtros['cliente_id']))
            ->when($filtros['contrato_id'] !== null, fn ($q) => $q->where('contrato_id', $filtros['contrato_id']));

        $agregar = function ($consulta): array {
            $linha = $consulta->toBase()->selectRaw('count(*) as quantidade, coalesce(sum(valor_centavos), 0) as soma')->first();

            return ['quantidade' => (int) $linha->quantidade, 'soma_centavos' => (int) $linha->soma];
        };

        // Vencidas e vence-hoje valem para qualquer mês, igual ao filtro principal (match() acima).
        $vencida = $agregar($base()->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje));
        $venceHoje = $agregar($base()->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje, $hoje->endOfDay()]));
        $aVencerReal = $agregar($base()->where('status', StatusCobranca::Pendente)->where('vencimento', '>', $hoje->endOfDay())
            ->whereBetween('vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]));
        $paga = $agregar($base()->where('status', StatusCobranca::Paga)
            ->whereBetween('vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]));

        $aVencer = [
            'quantidade' => $aVencerReal['quantidade'] + count($previstas),
            'soma_centavos' => $aVencerReal['soma_centavos'] + (int) array_sum(array_column($previstas, 'valor_centavos')),
        ];
        $todas = [
            'quantidade' => $vencida['quantidade'] + $venceHoje['quantidade'] + $aVencer['quantidade'] + $paga['quantidade'],
            'soma_centavos' => $vencida['soma_centavos'] + $venceHoje['soma_centavos'] + $aVencer['soma_centavos'] + $paga['soma_centavos'],
        ];

        return [
            ['chave' => 'todas', ...$todas],
            ['chave' => 'vencida', ...$vencida],
            ['chave' => 'vence_hoje', ...$venceHoje],
            ['chave' => 'a_vencer', ...$aVencer],
            ['chave' => 'paga', ...$paga],
        ];
    }
}
