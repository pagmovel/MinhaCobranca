<?php

namespace App\Support;

use TypeError;

/**
 * Regra única do desconto na baixa. A tela só exibe a mesma conta (resources/js/desconto.js):
 * mudou aqui, muda lá, e os casos de teste dos dois lados são os mesmos.
 */
final class Desconto
{
    public const PERCENTUAL = 'percentual';

    public const VALOR = 'valor';

    /**
     * Desconto em centavos para o que a pessoa digitou: vazio é 0, formato inválido é null.
     * Não limita ao valor — quem chama confere 0 ≤ desconto ≤ valor.
     */
    public static function centavos(int $valorCentavos, string $tipo, ?string $entrada): ?int
    {
        $texto = trim((string) $entrada);

        if ($texto === '') {
            return 0;
        }

        if ($tipo === self::VALOR) {
            try {
                return Dinheiro::paraCentavos($texto);
            } catch (TypeError) {
                // Número gigante estoura o inteiro dentro de Dinheiro::paraCentavos: é entrada inválida.
                return null;
            }
        }

        $pontosBase = $tipo === self::PERCENTUAL ? self::pontosBase($texto) : null;

        // Pontos-base = % × 100; meio centavo arredonda para cima, tudo em inteiros.
        return $pontosBase === null ? null : intdiv($valorCentavos * $pontosBase + 5000, 10000);
    }

    /** Percentual da baixa em lote: formato certo e de 0 a 100 (vazio vale 0). */
    public static function percentualValido(?string $entrada): bool
    {
        $texto = trim((string) $entrada);
        $pontosBase = self::pontosBase($texto);

        return $texto === '' || ($pontosBase !== null && $pontosBase <= 10000);
    }

    /** Texto da auditoria: "10% = R$ 24,76" (percentual, sempre com vírgula e sem zeros à direita) ou "R$ 24,76". */
    public static function rotulo(int $valorCentavos, int $descontoCentavos, string $tipo, ?string $entrada): string
    {
        $reais = 'R$ '.Dinheiro::formatar($descontoCentavos);
        $pontosBase = $tipo === self::PERCENTUAL ? self::pontosBase(trim((string) $entrada)) : null;

        if ($pontosBase === null) {
            return $reais;
        }

        $decimais = rtrim(str_pad((string) ($pontosBase % 100), 2, '0', STR_PAD_LEFT), '0');

        return intdiv($pontosBase, 100).($decimais === '' ? '' : ','.$decimais).'% = '.$reais;
    }

    /** "12,5" ou "12.5" → 1250; até 3 dígitos inteiros e 2 decimais, sem sinal. */
    private static function pontosBase(string $texto): ?int
    {
        if (! preg_match('/^(\d{1,3})(?:[.,](\d{1,2}))?$/', $texto, $partes)) {
            return null;
        }

        return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '', 2, '0');
    }
}
