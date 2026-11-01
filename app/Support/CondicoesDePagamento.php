<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Regra única do desconto por pontualidade, da multa e dos juros do contrato. A tela só exibe a mesma conta
 * (resources/js/condicoes.js): mudou aqui, muda lá, e os casos de teste dos dois lados são os mesmos.
 *
 * A regra é sempre em inteiros: percentual em pontos-base (2% = 200) ou R$ em centavos, conforme o tipo
 * ({@see Desconto::PERCENTUAL} e {@see Desconto::VALOR}). Sem tipo ou sem valor, não há regra e a conta dá 0.
 * A base é sempre o valor original da cobrança.
 */
final class CondicoesDePagamento
{
    /**
     * Dias corridos do vencimento até a data (0 se não passou). Só a data de calendário de cada objeto conta,
     * no fuso dele: a hora do dia e o horário de verão não entram na conta.
     */
    public static function diasDeAtraso(CarbonInterface $vencimento, CarbonInterface $data): int
    {
        $de = CarbonImmutable::parse($vencimento->toDateString(), 'UTC');
        $ate = CarbonImmutable::parse($data->toDateString(), 'UTC');

        return $ate->lte($de) ? 0 : (int) $de->diffInDays($ate);
    }

    /** Multa de uma vez só: percentual do valor ou o próprio valor em R$. */
    public static function multaCentavos(int $valor, ?string $tipo, ?int $regra): int
    {
        return self::aplicar($valor, $tipo, $regra);
    }

    /** Juros simples ao mês, proporcionais aos dias (mês de 30 dias), meio centavo para cima. */
    public static function jurosCentavos(int $valor, ?string $tipo, ?int $regra, int $dias): int
    {
        if ($regra === null || $regra <= 0 || $dias <= 0) {
            return 0;
        }

        return match ($tipo) {
            Desconto::PERCENTUAL => intdiv($valor * $regra * $dias + 150000, 300000),
            Desconto::VALOR => intdiv($regra * $dias + 15, 30),
            default => 0,
        };
    }

    /** Desconto por pontualidade, limitado ao valor (um contrato reajustado pode ficar abaixo do desconto em R$). */
    public static function descontoCentavos(int $valor, ?string $tipo, ?int $regra): int
    {
        return min($valor, self::aplicar($valor, $tipo, $regra));
    }

    /**
     * O que se digitou no contrato virou regra: vazio ou 0 é 0 (sem regra); formato inválido ou percentual acima
     * de 100% é null. Percentual vira pontos-base; R$, centavos.
     */
    public static function regraDaEntrada(string $tipo, ?string $entrada): ?int
    {
        $texto = trim((string) $entrada);

        if ($texto === '') {
            return 0;
        }

        if ($tipo === Desconto::PERCENTUAL) {
            $pontosBase = Desconto::pontosBase($texto);

            return $pontosBase !== null && $pontosBase <= 10000 ? $pontosBase : null;
        }

        return $tipo === Desconto::VALOR ? Desconto::centavos(0, $tipo, $texto) : null;
    }

    /** A regra como se digita no campo: 1500 percentual → "15", 250 → "2,5", 1500 R$ → "15,00"; sem regra, "". */
    public static function entradaDaRegra(?string $tipo, ?int $regra): string
    {
        if ($regra === null || $regra <= 0) {
            return '';
        }

        return match ($tipo) {
            Desconto::PERCENTUAL => Desconto::textoDoPercentual($regra),
            Desconto::VALOR => Dinheiro::formatar($regra),
            default => '',
        };
    }

    /** Multa e desconto são a mesma conta do desconto da baixa, aplicada à regra gravada no contrato. */
    private static function aplicar(int $valor, ?string $tipo, ?int $regra): int
    {
        if ($tipo === null || $regra === null || $regra <= 0) {
            return 0;
        }

        return Desconto::centavos($valor, $tipo, self::entradaDaRegra($tipo, $regra)) ?? 0;
    }
}
