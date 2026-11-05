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

    /** "Próximos N dias": pendentes de amanhã até hoje + N, de qualquer mês (como "Vencidas" e "Hoje"). */
    private const PROXIMOS_DIAS = ['proximos_7' => 7, 'proximos_15' => 15];

    public function __construct(private PrevisaoDeCobrancas $previsao) {}

    /**
     * @param  array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, contrato_id: ?int, canceladas: bool, pagina: int, por_pagina: int}  $filtros
     * @return array{linhas: list<array<string, mixed>>, totais: list<array{situacao: string, quantidade: int, soma_centavos: int}>, total_geral: array{quantidade: int, soma_centavos: int}, abas: list<array{chave: string, quantidade: int, soma_centavos: int}>, pagina: array{inicio: int, por_pagina: int, total: int}}
     */
    public function montar(User $usuario, array $filtros, CarbonImmutable $hoje): array
    {
        $status = $filtros['status'];
        $consulta = $usuario->cobrancas()->orderBy('vencimento')->orderBy('id');

        // "Vencendo hoje", "Vencidas" e "Próximos N dias" valem para qualquer mês: a vencida de setembro precisa aparecer em outubro.
        $dias = self::PROXIMOS_DIAS[$status] ?? null;
        if ($dias === null && ! in_array($status, ['vence_hoje', 'vencida'], true)) {
            // "Pagas" é o que foi pago no período, mesmo que tenha vencido em outro (pedido do usuário, 2026-11-05).
            $consulta->whereBetween($status === 'paga' ? 'pago_em' : 'vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]);
        }

        match ($status) {
            // "Todas" deixa as canceladas de fora, salvo com "Incluir canceladas" marcado (pedido do usuário).
            null => $filtros['canceladas'] ? null : $consulta->where('status', '!=', StatusCobranca::Cancelada),
            'vence_hoje' => $consulta->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje, $hoje->endOfDay()]),
            'vencida' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje),
            'a_vencer' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '>', $hoje->endOfDay()),
            'proximos_7', 'proximos_15' => $consulta->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje->addDay(), $hoje->addDays($dias)->endOfDay()]),
            default => $consulta->where('status', $status),
        };

        // Previstas (calculadas pelos contratos, nada gravado): calculadas sempre (as abas de situação, mais abaixo,
        // precisam delas mesmo numa aba diferente de "A vencer"), mas só entram na listagem onde já entravam.
        $soDoFiltro = fn (array $lista) => array_filter($lista, fn (array $prevista) => ($filtros['cliente_id'] === null || $prevista['cliente_id'] === $filtros['cliente_id'])
            && ($filtros['contrato_id'] === null || $prevista['contrato_id'] === $filtros['contrato_id']));
        $previstas = $soDoFiltro($this->previsao->entre($usuario, $filtros['mes']->max($hoje->addDay()), $filtros['ate']->endOfMonth()));
        // As dos próximos dias não dependem do mês escolhido; as abas "Próximos N dias" também precisam delas.
        $proximas = $soDoFiltro($this->previsao->entre($usuario, $hoje->addDay(), $hoje->addDays(max(self::PROXIMOS_DIAS))->endOfDay()));
        if ($filtros['cliente_id'] !== null) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
        }
        if ($filtros['contrato_id'] !== null) {
            $consulta->where('contrato_id', $filtros['contrato_id']);
        }
        $previstasDaLista = match (true) {
            $dias !== null => $this->ate($proximas, $hoje->addDays($dias)),
            in_array($status, [null, 'a_vencer', 'pendente'], true) => $previstas,
            default => [],
        };

        // ponytail: a lista leve fica inteira na memória (até 36 meses); agregar no SQL se passar de dezenas de milhares de linhas.
        $leves = $consulta->get(['id', 'vencimento', 'status', 'valor_centavos', 'desconto_centavos', 'multa_centavos', 'juros_centavos', 'outros_centavos'])
            ->map(fn (Cobranca $cobranca) => [
                'id' => $cobranca->id,
                'vencimento' => $cobranca->vencimento->toDateString(),
                'situacao' => $cobranca->situacao($hoje),
                // Valor das somas: a paga conta o que entrou (valor − desconto + acréscimos); as demais, o valor cheio.
                'valor_centavos' => $cobranca->status === StatusCobranca::Paga ? $cobranca->valorPagoCentavos() : $cobranca->valor_centavos,
                'prevista' => null,
            ])
            ->concat(array_map(fn (array $prevista) => [
                'id' => $prevista['id'],
                'vencimento' => CarbonImmutable::createFromFormat('!d/m/Y', $prevista['vencimento'])->toDateString(),
                'situacao' => 'prevista',
                'valor_centavos' => $prevista['valor_centavos'],
                'prevista' => $prevista,
            ], array_values($previstasDaLista)))
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
        $completas = $usuario->cobrancas()->with(['cliente:id,nome,apelido', 'recibo', 'auditorias', 'contrato'])
            ->findMany($bloco['itens']->whereNull('prevista')->pluck('id'))
            ->keyBy('id');

        return [
            'linhas' => $bloco['itens']->map(fn (array $leve) => $leve['prevista'] ?? $completas[$leve['id']]->paraLista($hoje))->all(),
            'totais' => $totais,
            'total_geral' => ['quantidade' => $aReceber->count(), 'soma_centavos' => (int) $aReceber->sum('valor_centavos')],
            'abas' => $this->abas($usuario, $filtros, $hoje, $previstas, $proximas),
            'pagina' => $bloco['pagina'],
        ];
    }

    /**
     * T17.16: contagem e soma de cada aba (Todas · Vencidas · Hoje · A vencer · Pagas), sempre do conjunto inteiro
     * admitido pelos filtros de período/cliente/contrato — independente de qual aba está selecionada para a
     * listagem, senão as abas não selecionadas "zerariam" ao clicar numa delas.
     *
     * @param  list<array<string, mixed>>  $previstas  já filtradas por cliente/contrato (ver montar())
     * @param  list<array<string, mixed>>  $proximas  as previstas dos próximos dias, também filtradas
     * @return list<array{chave: string, quantidade: int, soma_centavos: int}>
     */
    private function abas(User $usuario, array $filtros, CarbonImmutable $hoje, array $previstas, array $proximas): array
    {
        $base = fn () => $usuario->cobrancas()
            ->when($filtros['cliente_id'] !== null, fn ($q) => $q->where('cliente_id', $filtros['cliente_id']))
            ->when($filtros['contrato_id'] !== null, fn ($q) => $q->where('contrato_id', $filtros['contrato_id']));

        $agregar = function ($consulta, string $valorSql = 'valor_centavos'): array {
            $linha = $consulta->toBase()->selectRaw("count(*) as quantidade, coalesce(sum({$valorSql}), 0) as soma")->first();

            return ['quantidade' => (int) $linha->quantidade, 'soma_centavos' => (int) $linha->soma];
        };

        // Vencidas e vence-hoje valem para qualquer mês, igual ao filtro principal (match() acima).
        $vencida = $agregar($base()->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje));
        $venceHoje = $agregar($base()->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje, $hoje->endOfDay()]));
        $aVencerReal = $agregar($base()->where('status', StatusCobranca::Pendente)->where('vencimento', '>', $hoje->endOfDay())
            ->whereBetween('vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]));
        $proximos = [];
        foreach (self::PROXIMOS_DIAS as $chave => $dias) {
            $reais = $agregar($base()->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje->addDay(), $hoje->addDays($dias)->endOfDay()]));
            $previstasDaAba = $this->ate($proximas, $hoje->addDays($dias));
            $proximos[] = [
                'chave' => $chave,
                'quantidade' => $reais['quantidade'] + count($previstasDaAba),
                'soma_centavos' => $reais['soma_centavos'] + (int) array_sum(array_column($previstasDaAba, 'valor_centavos')),
            ];
        }
        // A aba "Pagas" soma o valor pago (com desconto e acréscimos), coerente com o "recebido" do painel.
        $paga = $agregar($base()->where('status', StatusCobranca::Paga)
            ->whereBetween('pago_em', [$filtros['mes'], $filtros['ate']->endOfMonth()]), Cobranca::VALOR_PAGO_SQL);

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
            ...$proximos,
            ['chave' => 'a_vencer', ...$aVencer],
            ['chave' => 'paga', ...$paga],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $previstas
     * @return array<int, array<string, mixed>>
     */
    private function ate(array $previstas, CarbonImmutable $limite): array
    {
        return array_filter($previstas, fn (array $prevista) => CarbonImmutable::createFromFormat('!d/m/Y', $prevista['vencimento'])->lte($limite));
    }
}
