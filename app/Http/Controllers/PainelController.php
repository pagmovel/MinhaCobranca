<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Services\AjusteDeVencimentos;
use App\Services\GeradorDeCobrancas;
use App\Support\BlocoDePaginas;
use App\Support\CondicoesDePagamento;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request, GeradorDeCobrancas $gerador, AjusteDeVencimentos $ajuste): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();

        // Geração "preguiçosa": quem abre o painel vê o que já devia existir.
        $gerador->gerarPara($usuario, $hoje);

        $inicio = $hoje->startOfMonth();
        $fim = $hoje->endOfMonth();
        $doMes = fn () => $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->whereBetween('vencimento', [$inicio, $fim]);

        // As três listas vêm por ajax de /painel/dados; só a página gera.
        return Inertia::render('Painel', [
            'mes' => Str::ucfirst($hoje->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'perfilCompleto' => $usuario->perfilCompleto(),
            // P2 (decisão do usuário, 2026-10-11): o aviso leva à prévia do ajuste, na seção Feriados do perfil.
            'vencimentosEmDiaNaoUtil' => $ajuste->quantas($usuario),
            'contratosParaRenovar' => $usuario->contratos()->where('ativo', true)->whereNotNull('data_fim')
                ->whereBetween('data_fim', [$hoje, $hoje->addDays($usuario->dias_aviso_contrato)])->count(),
            'diasAntecedencia' => $usuario->dias_antecedencia,
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'diasAviso' => $usuario->dias_aviso,
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
            'prestadorNome' => $usuario->nome_fantasia ?: $usuario->name,
            'resumo' => [
                'previsto' => Dinheiro::formatar((int) $doMes()->sum('valor_centavos')),
                // Regime de caixa (D1): o que entrou no mês, qualquer que seja o vencimento — o valor pago, já sem o desconto.
                'recebido' => Dinheiro::formatar((int) $usuario->cobrancas()->where('status', StatusCobranca::Paga)
                    ->whereBetween('pago_em', [$inicio, $fim])->sum(DB::raw(Cobranca::VALOR_PAGO_SQL))),
            ],
        ]);
    }

    /** Uma lista do painel (JSON, só leitura — a geração preguiçosa fica na página): bloco de páginas, contagem e soma. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'lista' => ['required', Rule::in(['para_enviar', 'aguardando', 'recebidas', 'vencendo'])],
            ...BlocoDePaginas::regras([10, 25, 50]),
        ]);
        $hoje = CarbonImmutable::today();

        if ($filtros['lista'] === 'vencendo') {
            return $this->dadosDeVencendo($request, $hoje, (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 10));
        }

        $consulta = $request->user()->cobrancas();

        match ($filtros['lista']) {
            'para_enviar' => $consulta->where('status', StatusCobranca::Pendente)->whereNull('enviado_em'),
            'aguardando' => $consulta->where('status', StatusCobranca::Pendente)->whereNotNull('enviado_em'),
            'recebidas' => $consulta->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$hoje->startOfMonth(), $hoje->endOfMonth()]),
        };
        // Soma antes de ordenar: no PostgreSQL, sum() com ORDER BY de coluna é erro.
        $soma = (int) $consulta->toBase()->sum($filtros['lista'] === 'recebidas' ? DB::raw(Cobranca::VALOR_PAGO_SQL) : 'valor_centavos');
        if ($filtros['lista'] === 'recebidas') {
            $consulta->orderByDesc('pago_em')->orderByDesc('id');
        } else {
            $consulta->orderBy('vencimento')->orderBy('id');
        }

        $bloco = BlocoDePaginas::de($consulta->with(['cliente:id,nome,apelido', 'recibo', 'contrato']), (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 10));

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje))->all(),
            'total_geral' => ['quantidade' => $bloco['pagina']['total'], 'soma_centavos' => $soma],
            'pagina' => $bloco['pagina'],
        ]);
    }

    /**
     * Linha do tempo do mês (JSON, só leitura): os pontos do mês corrente e, fora dos pontos, o que atravessa meses
     * (vencidas e anteriores). Ponto = pendente com vencimento no mês ou paga com pagamento no mês (no dia do pagamento);
     * cancelada nunca entra. `para_avisar` usa o mesmo conjunto do bloco "Vencendo" (de hoje a "dias_aviso" dias), só as
     * sem aviso; `para_enviar` é o total da lista "Recibos a enviar" (todas as pendentes sem envio, de qualquer mês).
     */
    public function mes(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $inicio = $hoje->startOfMonth();
        $fim = $hoje->endOfMonth();
        $pendentes = fn () => $usuario->cobrancas()->where('status', StatusCobranca::Pendente);
        $vencidas = fn () => $pendentes()->where('vencimento', '<', $hoje);

        $pontos = $usuario->cobrancas()
            ->select(['id', 'cliente_id', 'status', 'vencimento', 'pago_em', 'enviado_em', 'valor_centavos', 'desconto_centavos', 'multa_centavos', 'juros_centavos', 'outros_centavos'])
            ->where(fn ($consulta) => $consulta
                ->where(fn ($pendente) => $pendente->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$inicio, $fim]))
                ->orWhere(fn ($paga) => $paga->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$inicio, $fim])))
            ->with('cliente:id,nome,apelido')
            ->orderBy('id')
            ->get();

        $diasUteis = $usuario->diasUteis();
        $feriados = [];
        $fimDeSemana = [];
        for ($dia = $inicio; $dia->lte($fim); $dia = $dia->addDay()) {
            if (in_array($dia->dayOfWeek, [0, 6], true)) {
                $fimDeSemana[] = $dia->day;
            } elseif (($motivo = $diasUteis->motivo($dia)) !== null) {
                $feriados[$dia->day] = $motivo;
            }
        }

        return response()->json([
            'mes' => $hoje->format('Y-m'),
            'hoje' => $hoje->day,
            'dias' => $hoje->daysInMonth,
            // Objeto (`{}` quando vazio), não lista: o front indexa por dia.
            'feriados' => (object) $feriados,
            'fim_de_semana' => $fimDeSemana,
            'pontos' => $pontos->map(fn (Cobranca $cobranca) => [
                'id' => $cobranca->id,
                'cliente' => $cobranca->cliente->exibicao(),
                'valor_centavos' => $cobranca->valor_centavos,
                'vencimento_dia' => $cobranca->vencimento->day,
                'etapa' => match (true) {
                    $cobranca->status === StatusCobranca::Paga => 'recebida',
                    $cobranca->enviado_em !== null => 'aguardando',
                    default => 'enviar',
                },
                'situacao' => $cobranca->situacao($hoje),
                'pago_dia' => $cobranca->pago_em?->day,
                'pago_centavos' => $cobranca->status === StatusCobranca::Paga ? $cobranca->valorPagoCentavos() : null,
            ])->all(),
            'vencidas' => $this->somar($vencidas()) + [
                'itens' => $vencidas()->select(['id', 'cliente_id', 'vencimento', 'valor_centavos'])->with('cliente:id,nome,apelido')
                    ->orderBy('vencimento')->orderBy('id')->limit(3)->get()
                    ->map(fn (Cobranca $cobranca) => [
                        'id' => $cobranca->id,
                        'cliente' => $cobranca->cliente->exibicao(),
                        'valor_centavos' => $cobranca->valor_centavos,
                        'dias_de_atraso' => CondicoesDePagamento::diasDeAtraso($cobranca->vencimento, $hoje),
                    ])->all(),
            ],
            'hoje_resumo' => [
                'vence_hoje' => $this->somar($pendentes()->where('vencimento', $hoje)),
                'para_avisar' => $pendentes()->whereBetween('vencimento', [$hoje, $hoje->addDays($usuario->dias_aviso)])->whereNull('avisado_em')->count(),
                'para_enviar' => $pendentes()->whereNull('enviado_em')->count(),
            ],
            'anteriores' => $this->somar($pendentes()->where('vencimento', '<', $inicio)),
        ]);
    }

    /** A linha de uma cobrança, a mesma das listas, para o ponto clicado abrir as ações. Cobrança alheia: 404. */
    public function cobranca(Request $request, int $cobranca): JsonResponse
    {
        $registro = $request->user()->cobrancas()->with(['cliente', 'recibo', 'contrato'])->findOrFail($cobranca);

        return response()->json($registro->paraLista(CarbonImmutable::today()));
    }

    /** @return array{quantidade: int, soma_centavos: int} */
    private function somar(HasMany $consulta): array
    {
        $totais = $consulta->toBase()->selectRaw('count(*) as quantidade, coalesce(sum(valor_centavos), 0) as soma_centavos')->first();

        return ['quantidade' => (int) $totais->quantidade, 'soma_centavos' => (int) $totais->soma_centavos];
    }

    /** Pendentes vencendo nos próximos "dias_aviso" dias, agrupadas por cliente (a forma rompe o padrão 1 linha = 1 cobrança). */
    private function dadosDeVencendo(Request $request, CarbonImmutable $hoje, int $pagina, int $porPagina): JsonResponse
    {
        $usuario = $request->user();
        $fim = $hoje->addDays($usuario->dias_aviso);

        $grupos = $usuario->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereBetween('vencimento', [$hoje, $fim])
            ->with('cliente:id,nome,apelido')
            ->orderBy('vencimento')
            ->get()
            ->groupBy('cliente_id')
            ->map(function (Collection $cobrancas) {
                $centavos = (int) $cobrancas->sum('valor_centavos');

                return [
                    'cliente_id' => $cobrancas->first()->cliente_id,
                    'cliente' => $cobrancas->first()->cliente->exibicao(),
                    'quantidade' => $cobrancas->count(),
                    'vencimentos' => $cobrancas->pluck('vencimento')->unique()->sort()->map(fn ($data) => $data->format('d/m/Y'))->values()->all(),
                    'total_centavos' => $centavos,
                    'total' => Dinheiro::formatar($centavos),
                    'avisado_em' => $cobrancas->pluck('avisado_em')->filter()->sort()->last()?->format('d/m'),
                    'ids' => $cobrancas->pluck('id')->all(),
                ];
            })
            ->values();

        $bloco = BlocoDePaginas::de($grupos, $pagina, $porPagina);

        return response()->json([
            'linhas' => $bloco['itens'],
            'total_geral' => ['quantidade' => $grupos->count(), 'soma_centavos' => (int) $grupos->sum('total_centavos')],
            'pagina' => $bloco['pagina'],
        ]);
    }
}
