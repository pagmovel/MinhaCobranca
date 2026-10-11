<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Services\AjusteDeVencimentos;
use App\Services\GeradorDeCobrancas;
use App\Support\BlocoDePaginas;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'vencimentosEmDiaNaoUtil' => count($ajuste->previa($usuario)),
            'diasAntecedencia' => $usuario->dias_antecedencia,
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'resumo' => [
                'previsto' => Dinheiro::formatar((int) $doMes()->sum('valor_centavos')),
                // Regime de caixa (D1): o que entrou no mês, qualquer que seja o vencimento.
                'recebido' => Dinheiro::formatar((int) $usuario->cobrancas()->where('status', StatusCobranca::Paga)
                    ->whereBetween('pago_em', [$inicio, $fim])->sum('valor_centavos')),
            ],
        ]);
    }

    /** Uma lista do painel (JSON, só leitura — a geração preguiçosa fica na página): bloco de páginas, contagem e soma. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'lista' => ['required', Rule::in(['para_enviar', 'aguardando', 'recebidas'])],
            ...BlocoDePaginas::regras([10, 25, 50]),
        ]);
        $hoje = CarbonImmutable::today();
        $consulta = $request->user()->cobrancas();

        match ($filtros['lista']) {
            'para_enviar' => $consulta->where('status', StatusCobranca::Pendente)->whereNull('enviado_em'),
            'aguardando' => $consulta->where('status', StatusCobranca::Pendente)->whereNotNull('enviado_em'),
            'recebidas' => $consulta->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$hoje->startOfMonth(), $hoje->endOfMonth()]),
        };
        // Soma antes de ordenar: no PostgreSQL, sum() com ORDER BY de coluna é erro.
        $soma = (int) $consulta->toBase()->sum('valor_centavos');
        if ($filtros['lista'] === 'recebidas') {
            $consulta->orderByDesc('pago_em')->orderByDesc('id');
        } else {
            $consulta->orderBy('vencimento')->orderBy('id');
        }

        $bloco = BlocoDePaginas::de($consulta->with(['cliente:id,nome,apelido', 'recibo']), (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 10));

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje))->all(),
            'total_geral' => ['quantidade' => $bloco['pagina']['total'], 'soma_centavos' => $soma],
            'pagina' => $bloco['pagina'],
        ]);
    }
}
