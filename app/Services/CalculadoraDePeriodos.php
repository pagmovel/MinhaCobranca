<?php

namespace App\Services;

use App\Enums\Periodicidade;
use App\Support\Periodo;
use Carbon\CarbonImmutable;

final class CalculadoraDePeriodos
{
    /** Trava de segurança: 100 anos de períodos mensais. */
    private const LIMITE = 1200;

    public function periodo(CarbonImmutable $dataInicio, Periodicidade $periodicidade, int $diaVencimento, int $indice): Periodo
    {
        // Sempre a partir da data original: 31/01 → 28/02 → 31/03, sem escorregar para o dia 28.
        $inicio = $dataInicio->startOfDay()->addMonthsNoOverflow($indice * $periodicidade->meses());
        $fim = $dataInicio->startOfDay()->addMonthsNoOverflow(($indice + 1) * $periodicidade->meses())->subDay();
        $mes = $inicio->startOfMonth();

        return new Periodo($inicio, $fim, $mes->setDay(min($diaVencimento, $mes->daysInMonth)));
    }

    /** @return list<Periodo> */
    public function devidos(
        CarbonImmutable $dataInicio,
        ?CarbonImmutable $dataFim,
        Periodicidade $periodicidade,
        int $diaVencimento,
        int $diasAntecedencia,
        CarbonImmutable $hoje,
    ): array {
        $devidos = [];

        for ($indice = 0; $indice < self::LIMITE; $indice++) {
            $periodo = $this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice);

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
