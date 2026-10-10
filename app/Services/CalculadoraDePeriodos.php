<?php

namespace App\Services;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Support\Periodo;
use Carbon\CarbonImmutable;

final class CalculadoraDePeriodos
{
    /** Trava de segurança: 100 anos de períodos mensais. */
    private const LIMITE = 1200;

    public function periodo(
        CarbonImmutable $dataInicio,
        Periodicidade $periodicidade,
        int $diaVencimento,
        int $indice,
        Modalidade $modalidade = Modalidade::Prepago,
    ): Periodo {
        // Sempre a partir da data original: 31/01 → 28/02 → 31/03, sem escorregar para o dia 28.
        $inicio = $dataInicio->startOfDay()->addMonthsNoOverflow($indice * $periodicidade->meses());
        $fim = $dataInicio->startOfDay()->addMonthsNoOverflow(($indice + 1) * $periodicidade->meses())->subDay();

        $vencimento = $modalidade === Modalidade::Pospago
            ? $this->primeiroDiaDepois($fim, $diaVencimento)
            : $this->diaNoMes($inicio, $diaVencimento);

        return new Periodo($inicio, $fim, $vencimento);
    }

    /** O dia escolhido no mês da data, limitado ao último dia do mês. */
    private function diaNoMes(CarbonImmutable $data, int $dia): CarbonImmutable
    {
        $mes = $data->startOfMonth();

        return $mes->setDay(min($dia, $mes->daysInMonth));
    }

    /** Pós-pago: primeira data com o dia escolhido estritamente depois do fim do período. */
    private function primeiroDiaDepois(CarbonImmutable $fim, int $dia): CarbonImmutable
    {
        $candidato = $this->diaNoMes($fim, $dia);

        return $candidato->gt($fim) ? $candidato : $this->diaNoMes($fim->startOfMonth()->addMonthNoOverflow(), $dia);
    }

    /** @return list<Periodo> */
    public function devidos(
        CarbonImmutable $dataInicio,
        ?CarbonImmutable $dataFim,
        Periodicidade $periodicidade,
        int $diaVencimento,
        int $diasAntecedencia,
        CarbonImmutable $hoje,
        Modalidade $modalidade = Modalidade::Prepago,
    ): array {
        $devidos = [];

        for ($indice = 0; $indice < self::LIMITE; $indice++) {
            $periodo = $this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice, $modalidade);

            if ($dataFim !== null && $periodo->inicio->gt($dataFim)) {
                break;
            }

            if ($periodo->vencimento->subDays($diasAntecedencia)->gt($hoje->startOfDay())) {
                break;
            }

            $devidos[] = $periodo;
        }

        return $devidos;
    }
}
