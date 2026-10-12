<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz um encerramento agendado (T17.11): reabre as cobranças que o agendamento tinha cancelado e devolve a
 * cobrança de fronteira ao valor cheio, deixando o contrato de novo com `ultimo_periodo = 'inteiro'` e uma nova
 * data de fim (ou sem fim).
 */
final class RenovadorDeContratos
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private GeradorDeCobrancas $gerador,
        private EmissorDeRecibos $emissor,
    ) {}

    /**
     * O que volta ao renovar — mesma lista para a prévia e para a execução. `$novaData` (só na execução, a prévia
     * roda antes de a pessoa escolher) limita a reabertura ao que cabe no contrato renovado: cancelada que começa
     * depois da nova data_fim fica cancelada (T17.11, achado da revisão final).
     *
     * @return list<array{acao: 'reabrir'|'proporcional_cheio'|'aviso_avulsa'|'desfazer_avulsa', cobranca: Cobranca, avulsa?: Cobranca}>
     */
    public function plano(Contrato $contrato, ?CarbonImmutable $novaData = null): array
    {
        $fimAntigo = $contrato->data_fim;
        $acoes = [];

        $canceladasDepois = $contrato->cobrancas()
            ->where('status', StatusCobranca::Cancelada)
            ->whereDate('periodo_inicio', '>', $fimAntigo)
            ->when($novaData !== null, fn ($query) => $query->whereDate('periodo_inicio', '<=', $novaData))
            ->get();
        foreach ($canceladasDepois as $cobranca) {
            $acoes[] = ['acao' => 'reabrir', 'cobranca' => $cobranca];
        }

        $fronteira = $this->cobrancaDeFronteira($contrato, $fimAntigo);
        $acaoDaFronteira = $fronteira === null ? null : $this->acaoDaFronteira($contrato, $fronteira);
        if ($acaoDaFronteira !== null) {
            $acoes[] = $acaoDaFronteira;
        }

        usort($acoes, fn (array $a, array $b) => $a['cobranca']->periodo_inicio <=> $b['cobranca']->periodo_inicio);

        return $acoes;
    }

    /**
     * O que fazer com a cobrança de fronteira — `null` quando o encerramento não mexeu nela (ficou cheia porque o
     * fim caiu no último dia do período, ou porque estava vencida/vence hoje: `EncerradorDeContratos` manteve).
     *
     * @return ?array{acao: 'reabrir'|'proporcional_cheio'|'aviso_avulsa'|'desfazer_avulsa', cobranca: Cobranca, avulsa?: Cobranca}
     */
    private function acaoDaFronteira(Contrato $contrato, Cobranca $fronteira): ?array
    {
        if ($fronteira->status === StatusCobranca::Cancelada) {
            // Nao_cobrar truncando, ou cancelamento manual na fronteira: reabre, sem mais nada.
            if ($contrato->ultimo_periodo !== 'proporcional') {
                return ['acao' => 'reabrir', 'cobranca' => $fronteira];
            }

            // Proporcional e já enviada: o encerramento criou uma avulsa e cancelou esta. Dinheiro já pago na
            // avulsa não é desfeito sozinho (decisão do usuário, 2026-10-12) — nesse caso só avisa; senão, a
            // avulsa é cancelada e a original (nunca truncada) volta a pendente.
            $avulsa = $this->avulsaSubstituta($contrato, $fronteira);

            return $avulsa === null || $avulsa->status === StatusCobranca::Paga
                ? ['acao' => 'aviso_avulsa', 'cobranca' => $fronteira]
                : ['acao' => 'desfazer_avulsa', 'cobranca' => $fronteira, 'avulsa' => $avulsa];
        }

        // Pendente: só "proporcional_cheio" se de fato foi truncada (o período natural é maior que o gravado).
        $periodoCompleto = $this->periodoCompletoDe($contrato, $fronteira->periodo_inicio);
        $foiTruncada = $periodoCompleto !== null && $fronteira->periodo_fim->lt($periodoCompleto->fim);

        return $foiTruncada ? ['acao' => 'proporcional_cheio', 'cobranca' => $fronteira] : null;
    }

    /** Tudo ou nada: reabre o que o agendamento tinha fechado e volta a gerar no ritmo normal. */
    public function renovar(Contrato $contrato, ?CarbonImmutable $novaData): void
    {
        DB::transaction(function () use ($contrato, $novaData) {
            $this->desfazerEncerramento($contrato, $novaData);
            $contrato->update(['data_fim' => $novaData, 'ultimo_periodo' => 'inteiro']);
        });
    }

    /**
     * Só a parte de desfazer (reabrir/restaurar cobranças) — sem tocar em `data_fim`/`ultimo_periodo` do contrato
     * nem abrir transação própria, para o chamador poder combinar com outras mudanças na mesma transação (T17.11:
     * usado também pelo "Reativar contrato" num contrato já encerrado de fato, junto com o resto do `update()`).
     */
    public function desfazerEncerramento(Contrato $contrato, ?CarbonImmutable $novaData): void
    {
        foreach ($this->plano($contrato, $novaData) as $acao) {
            match ($acao['acao']) {
                // `status` não é Fillable (App\Models\Cobranca): mudança sempre por atribuição direta, como em EncerradorDeContratos::cancelar().
                'reabrir' => $this->reabrir($acao['cobranca']),
                'proporcional_cheio' => $this->desfazerProporcional($contrato, $acao['cobranca']),
                'desfazer_avulsa' => $this->desfazerAvulsa($acao['cobranca'], $acao['avulsa']),
                'aviso_avulsa' => null, // avulsa já paga: dinheiro recebido não é desfeito sozinho, só avisa.
            };
        }
    }

    /** A cobrança (pendente ou cancelada) cujo período contém a data de fim antiga. */
    private function cobrancaDeFronteira(Contrato $contrato, CarbonImmutable $fimAntigo): ?Cobranca
    {
        return $contrato->cobrancas()
            ->whereIn('status', [StatusCobranca::Pendente, StatusCobranca::Cancelada])
            ->whereDate('periodo_inicio', '<=', $fimAntigo)
            ->whereDate('periodo_fim', '>=', $fimAntigo)
            ->with('itens')
            ->first();
    }

    private function reabrir(Cobranca $cobranca): void
    {
        $cobranca->status = StatusCobranca::Pendente;
        $cobranca->save();
    }

    private function desfazerProporcional(Contrato $contrato, Cobranca $cobranca): void
    {
        $periodo = $this->periodoCompletoDe($contrato, $cobranca->periodo_inicio);
        $this->gerador->desfazerProporcional($cobranca, $contrato, $periodo);
        $this->emissor->refazer($cobranca->recibo()->firstOrFail());
    }

    /**
     * A avulsa (`contrato_id` nulo) que `EncerradorDeContratos::proporcional()` criou no lugar da fronteira já
     * enviada: mesmo cliente, mesmo início de período — não há FK de volta para a original (ponytail: heurística
     * por cliente + início do período, suficiente aqui porque a avulsa nasce sempre assim nesse fluxo).
     */
    private function avulsaSubstituta(Contrato $contrato, Cobranca $fronteira): ?Cobranca
    {
        return $contrato->user->cobrancas()
            ->whereNull('contrato_id')
            ->where('cliente_id', $fronteira->cliente_id)
            ->whereDate('periodo_inicio', $fronteira->periodo_inicio)
            ->latest('id')
            ->first();
    }

    /** Desfaz a troca por avulsa: cancela a avulsa (nunca paga, nesse ponto) e reabre a original — que nunca foi truncada. */
    private function desfazerAvulsa(Cobranca $original, Cobranca $avulsa): void
    {
        $avulsa->status = StatusCobranca::Cancelada;
        $avulsa->save();
        $this->reabrir($original);
    }

    /** Período cheio (sem truncar pelo fim antigo) que começa em `$periodoInicio`, pela periodicidade do contrato. */
    private function periodoCompletoDe(Contrato $contrato, CarbonImmutable $periodoInicio): ?Periodo
    {
        $periodos = $this->calculadora->devidos(
            $contrato->data_inicio, null, $contrato->periodicidade, $contrato->dia_vencimento, 0,
            $periodoInicio->addYears(1), $contrato->modalidade,
        );

        return collect($periodos)->first(fn (Periodo $periodo) => $periodo->inicio->isSameDay($periodoInicio));
    }
}
