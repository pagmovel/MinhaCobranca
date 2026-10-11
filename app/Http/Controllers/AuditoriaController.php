<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Rules\AnoValido;
use App\Support\BlocoDePaginas;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    /** Ações sensíveis do usuário. A lista vem por ajax de /auditoria/dados; a página só abre a tela com os filtros. */
    public function index(Request $request): Response
    {
        return Inertia::render('Auditoria/Index', [
            'filtros' => $this->filtros($request),
            'padroes' => $this->padroes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): padrão últimos 30 dias, mais recentes primeiro. */
    public function dados(Request $request): JsonResponse
    {
        $request->validate(['ate' => ['nullable', 'date_format:Y-m-d', new AnoValido, 'after_or_equal:de']]);
        $filtros = $this->filtros($request);

        $bloco = BlocoDePaginas::de(
            $request->user()->auditorias()
                ->whereBetween('created_at', [CarbonImmutable::parse($filtros['de'])->startOfDay(), CarbonImmutable::parse($filtros['ate'])->endOfDay()])
                ->orderByDesc('id'),
            $filtros['pagina'],
            $filtros['por_pagina'],
        );

        // Uma consulta para os links das cobranças do bloco (a lista abre no mês do vencimento).
        $vencimentos = $request->user()->cobrancas()
            ->whereIn('id', $bloco['itens']->where('alvo_tipo', 'cobranca')->pluck('alvo_id'))
            ->pluck('vencimento', 'id');

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Auditoria $registro) => [
                'id' => $registro->id,
                'quando' => $registro->created_at->format('d/m/Y H:i'),
                'resumo' => $registro->resumo,
                'motivo' => $registro->motivo,
                'link' => match (true) {
                    $registro->alvo_tipo === 'contrato' => "/contratos/{$registro->alvo_id}/edit",
                    isset($vencimentos[$registro->alvo_id]) => '/cobrancas?mes='.CarbonImmutable::parse($vencimentos[$registro->alvo_id])->format('Y-m'),
                    default => null,
                },
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /** @return array{de: string, ate: string} */
    private function padroes(): array
    {
        $hoje = CarbonImmutable::today();

        return ['de' => $hoje->subDays(30)->toDateString(), 'ate' => $hoje->toDateString()];
    }

    /**
     * Filtros já no formato da tela. Aqui só o formato: a ordem das datas é conferida no JSON (422 embaixo do "Até").
     *
     * @return array{de: string, ate: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            // date_format, não "date": "date" lia o ano de 5 dígitos do campo do Chrome (72026-08-01) como 2006-08-01.
            'de' => ['nullable', 'date_format:Y-m-d', new AnoValido],
            'ate' => ['nullable', 'date_format:Y-m-d', new AnoValido],
            ...BlocoDePaginas::regras(),
        ]);
        $padroes = $this->padroes();

        return [
            'de' => isset($validados['de']) ? CarbonImmutable::parse($validados['de'])->toDateString() : $padroes['de'],
            'ate' => isset($validados['ate']) ? CarbonImmutable::parse($validados['ate'])->toDateString() : $padroes['ate'],
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
}
