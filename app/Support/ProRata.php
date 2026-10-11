<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Cobrança proporcional aos dias usados de um período (só inteiros, meio para cima). */
final class ProRata
{
    /** Quantidade em milésimos × usados ÷ dias; nunca zera (um item cobrado não some do recibo). */
    public static function quantidade(int $milesimos, int $usados, int $dias): int
    {
        return max(1, intdiv(2 * $milesimos * $usados + $dias, 2 * $dias));
    }

    /** @return array{0: int, 1: int} dias usados (início até o fim, inclusive) e dias do período (inclusive) */
    public static function dias(Periodo $periodo, CarbonImmutable $fim): array
    {
        return [
            (int) $periodo->inicio->diffInDays($fim) + 1,
            (int) $periodo->inicio->diffInDays($periodo->fim) + 1,
        ];
    }

    /** Cobrança antiga sem itens: o valor × usados ÷ dias. */
    public static function valor(int $centavos, int $usados, int $dias): int
    {
        return intdiv(2 * $centavos * $usados + $dias, 2 * $dias);
    }
}
