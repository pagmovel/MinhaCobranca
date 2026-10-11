<?php

namespace App\Http\Controllers;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Services\GeradorDeCobrancas;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();

        // Geração "preguiçosa": quem abre o painel vê o que já devia existir.
        $gerador->gerarPara($usuario, $hoje);

        $inicio = $hoje->startOfMonth();
        $fim = $hoje->endOfMonth();
        $comRelacoes = fn () => $usuario->cobrancas()->with(['cliente:id,nome', 'recibo']);
        $doMes = fn () => $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->whereBetween('vencimento', [$inicio, $fim]);
        $paraLista = fn (Cobranca $cobranca) => $cobranca->paraLista($hoje);

        $pendentes = $comRelacoes()->where('status', StatusCobranca::Pendente)->orderBy('vencimento')->get();

        return Inertia::render('Painel', [
            'mes' => Str::ucfirst($hoje->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'perfilCompleto' => $usuario->perfilCompleto(),
            'diasAntecedencia' => $usuario->dias_antecedencia,
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'resumo' => [
                'previsto' => Dinheiro::formatar((int) $doMes()->sum('valor_centavos')),
                // Regime de caixa (D1): o que entrou no mês, qualquer que seja o vencimento.
                'recebido' => Dinheiro::formatar((int) $usuario->cobrancas()->where('status', StatusCobranca::Paga)
                    ->whereBetween('pago_em', [$inicio, $fim])->sum('valor_centavos')),
            ],
            'paraEnviar' => $pendentes->whereNull('enviado_em')->values()->map($paraLista),
            'aguardando' => $pendentes->whereNotNull('enviado_em')->values()->map($paraLista),
            'recebidas' => $comRelacoes()->where('status', StatusCobranca::Paga)
                ->whereBetween('pago_em', [$inicio, $fim])->orderByDesc('pago_em')->get()->map($paraLista),
        ]);
    }
}
