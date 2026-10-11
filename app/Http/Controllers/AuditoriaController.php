<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Support\Paginacao;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    /** Ações sensíveis do usuário (padrão: últimos 30 dias), mais recentes primeiro. */
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
        ]);
        $de = CarbonImmutable::parse($filtros['de'] ?? $hoje->subDays(30))->startOfDay();
        $ate = CarbonImmutable::parse($filtros['ate'] ?? $hoje)->endOfDay();

        $pagina = $usuario->auditorias()->whereBetween('created_at', [$de, $ate])
            ->orderByDesc('id')->paginate(25)->withQueryString();

        // Uma consulta para os links das cobranças (a lista abre no mês do vencimento).
        $vencimentos = $usuario->cobrancas()
            ->whereIn('id', collect($pagina->items())->where('alvo_tipo', 'cobranca')->pluck('alvo_id'))
            ->pluck('vencimento', 'id');

        return Inertia::render('Auditoria/Index', [
            'registros' => collect($pagina->items())->map(fn (Auditoria $registro) => [
                'id' => $registro->id,
                'quando' => $registro->created_at->format('d/m/Y H:i'),
                'resumo' => $registro->resumo,
                'motivo' => $registro->motivo,
                'link' => match (true) {
                    $registro->alvo_tipo === 'contrato' => "/contratos/{$registro->alvo_id}/edit",
                    isset($vencimentos[$registro->alvo_id]) => '/cobrancas?mes='.CarbonImmutable::parse($vencimentos[$registro->alvo_id])->format('Y-m'),
                    default => null,
                },
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => ['de' => $de->toDateString(), 'ate' => $ate->toDateString()],
        ]);
    }
}
