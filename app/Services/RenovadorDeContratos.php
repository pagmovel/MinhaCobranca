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
     * O que volta ao renovar — mesma lista para a prévia e para a execução.
     *
     * @return list<array{acao: 'reabrir'|'proporcional_cheio'|'aviso_avulsa', cobranca: Cobranca}>
     */
    public function plano(Contrato $contrato): array
    {
        $fimAntigo = $contrato->data_fim;
        $acoes = [];

        $canceladasDepois = $contrato->cobrancas()
            ->where('status', StatusCobranca::Cancelada)
            ->whereDate('periodo_inicio', '>', $fimAntigo)
            ->get();
        foreach ($canceladasDepois as $cobranca) {
            $acoes[] = ['acao' => 'reabrir', 'cobranca' => $cobranca];
        }

        $fronteira = $this->cobrancaDeFronteira($contrato, $fimAntigo);

        if ($fronteira !== null) {
            $acoes[] = match (true) {
                // Sem proporcional: o período de fronteira foi só cancelado (não truncado) ao encerrar.
                $contrato->ultimo_periodo === 'nao_cobrar' => ['acao' => 'reabrir', 'cobranca' => $fronteira],
                // Proporcional e já enviada: o encerramento criou uma avulsa e cancelou esta. Só avisa.
                $fronteira->status === StatusCobranca::Cancelada => ['acao' => 'aviso_avulsa', 'cobranca' => $fronteira],
                default => ['acao' => 'proporcional_cheio', 'cobranca' => $fronteira],
            };
        }

        usort($acoes, fn (array $a, array $b) => $a['cobranca']->periodo_inicio <=> $b['cobranca']->periodo_inicio);

        return $acoes;
    }

    /** Tudo ou nada: reabre o que o agendamento tinha fechado e volta a gerar no ritmo normal. */
    public function renovar(Contrato $contrato, ?CarbonImmutable $novaData): void
    {
        DB::transaction(function () use ($contrato, $novaData) {
            foreach ($this->plano($contrato) as $acao) {
                match ($acao['acao']) {
                    // `status` não é Fillable (App\Models\Cobranca): mudança sempre por atribuição direta, como em EncerradorDeContratos::cancelar().
                    'reabrir' => $this->reabrir($acao['cobranca']),
                    'proporcional_cheio' => $this->desfazerProporcional($contrato, $acao['cobranca']),
                    'aviso_avulsa' => null, // fora do automático: a prévia só avisa (decisão do usuário, spec seção 5).
                };
            }

            $contrato->update(['data_fim' => $novaData, 'ultimo_periodo' => 'inteiro']);
        });
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

    /** Período cheio (sem truncar pelo fim antigo) que começa em `$periodoInicio`, pela periodicidade do contrato. */
    private function periodoCompletoDe(Contrato $contrato, CarbonImmutable $periodoInicio): Periodo
    {
        $periodos = $this->calculadora->devidos(
            $contrato->data_inicio, null, $contrato->periodicidade, $contrato->dia_vencimento, 0,
            $periodoInicio->addYears(1), $contrato->modalidade,
        );

        return collect($periodos)->first(fn (Periodo $periodo) => $periodo->inicio->isSameDay($periodoInicio));
    }
}
