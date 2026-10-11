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
     * @param  array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, canceladas: bool, pagina: int, por_pagina: int}  $filtros
     * @return array{linhas: list<array<string, mixed>>, totais: list<array{situacao: string, quantidade: int, soma_centavos: int}>, total_geral: array{quantidade: int, soma_centavos: int}, pagina: array{inicio: int, por_pagina: int, total: int}}
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

        // Previstas (calculadas pelos contratos, nada gravado) só onde entram as em aberto e só depois de hoje.
        $previstas = in_array($status, [null, 'a_vencer', 'pendente'], true)
            ? $this->previsao->entre($usuario, $filtros['mes']->max($hoje->addDay()), $filtros['ate']->endOfMonth())
            : [];
        if ($filtros['cliente_id'] !== null) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
            $previstas = array_filter($previstas, fn (array $prevista) => $prevista['cliente_id'] === $filtros['cliente_id']);
        }

        // ponytail: a lista leve fica inteira na memória (até 36 meses); agregar no SQL se passar de dezenas de milhares de linhas.
        $leves = $consulta->get(['id', 'vencimento', 'status', 'valor_centavos'])
            ->map(fn (Cobranca $cobranca) => [
                'id' => $cobranca->id,
                'vencimento' => $cobranca->vencimento->toDateString(),
                'situacao' => $cobranca->situacao($hoje),
                'valor_centavos' => $cobranca->valor_centavos,
                'prevista' => null,
            ])
            ->concat(array_map(fn (array $prevista) => [
                'id' => $prevista['id'],
                'vencimento' => CarbonImmutable::createFromFormat('!d/m/Y', $prevista['vencimento'])->toDateString(),
                'situacao' => 'prevista',
                'valor_centavos' => $prevista['valor_centavos'],
                'prevista' => $prevista,
            ], array_values($previstas)))
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
        $completas = $usuario->cobrancas()->with(['cliente:id,nome', 'recibo', 'auditorias'])
            ->findMany($bloco['itens']->whereNull('prevista')->pluck('id'))
            ->keyBy('id');

        return [
            'linhas' => $bloco['itens']->map(fn (array $leve) => $leve['prevista'] ?? $completas[$leve['id']]->paraLista($hoje))->all(),
            'totais' => $totais,
            'total_geral' => ['quantidade' => $aReceber->count(), 'soma_centavos' => (int) $aReceber->sum('valor_centavos')],
            'pagina' => $bloco['pagina'],
        ];
    }
}
