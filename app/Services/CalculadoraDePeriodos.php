<?php

namespace App\Services;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Support\DiasUteis;
use App\Support\Periodo;
use Carbon\CarbonImmutable;

final class CalculadoraDePeriodos
{
    /** Trava de segurança: 100 anos de períodos mensais. */
    private const LIMITE = 1200;

    /** Sem calendário (padrão), o vencimento sai como sempre; com ele, vai para o próximo dia útil (Fase 23). */
    private ?DiasUteis $diasUteis = null;

    /** Cópia desta calculadora com o calendário do usuário; a original não muda. */
    public function comDiasUteis(DiasUteis $diasUteis): self
    {
        $copia = clone $this;
        $copia->diasUteis = $diasUteis;

        return $copia;
    }

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

        // Só o vencimento muda: início e fim do período (e o índice único do contrato) continuam.
        $vencimento = $this->diasUteis?->proximoDiaUtil($vencimento) ?? $vencimento;

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

    /**
     * Períodos que terminaram antes de hoje (os "anteriores ao atual") e o início do primeiro que não terminou.
     *
     * @return array{quantidade: int, de: ?CarbonImmutable, ate: ?CarbonImmutable, a_partir_de: CarbonImmutable}
     */
    public function anteriores(CarbonImmutable $dataInicio, Periodicidade $periodicidade, CarbonImmutable $hoje): array
    {
        $indice = 0;

        while ($indice < self::LIMITE && $this->periodo($dataInicio, $periodicidade, 1, $indice)->fim->lt($hoje->startOfDay())) {
            $indice++;
        }

        return [
            'quantidade' => $indice,
            'de' => $indice > 0 ? $dataInicio->startOfDay() : null,
            'ate' => $indice > 0 ? $this->periodo($dataInicio, $periodicidade, 1, $indice - 1)->fim : null,
            'a_partir_de' => $this->periodo($dataInicio, $periodicidade, 1, $indice)->inicio,
        ];
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

    /**
     * Períodos com vencimento entre `$de` e `$ate` (dias inteiros), sem passar do fim do contrato: o período que contém
     * o fim entra; o que começa depois dele, não. Usado pela previsão de cobranças.
     *
     * @return list<Periodo>
     */
    public function entre(
        CarbonImmutable $dataInicio,
        ?CarbonImmutable $dataFim,
        Periodicidade $periodicidade,
        int $diaVencimento,
        Modalidade $modalidade,
        CarbonImmutable $de,
        CarbonImmutable $ate,
    ): array {
        $periodos = [];

        for ($indice = 0; $indice < self::LIMITE; $indice++) {
            $periodo = $this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice, $modalidade);

            if (($dataFim !== null && $periodo->inicio->gt($dataFim)) || $periodo->vencimento->gt($ate->startOfDay())) {
                break;
            }

            if ($periodo->vencimento->gte($de->startOfDay())) {
                $periodos[] = $periodo;
            }
        }

        return $periodos;
    }
}
