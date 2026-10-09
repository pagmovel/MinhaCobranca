<?php

namespace App\Support;

final class Dinheiro
{
    /**
     * Converte o que a pessoa digitou ("1.234,56", "260,6", "R$ 260") em centavos.
     * Retorna null quando não dá para interpretar sem chutar.
     */
    public static function paraCentavos(?string $valor): ?int
    {
        $texto = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $valor));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',')) {
            // Formato brasileiro: ponto é milhar, vírgula é decimal.
            $texto = str_replace(',', '.', str_replace('.', '', $texto));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            // "1.234" sem vírgula: ponto de milhar.
            $texto = str_replace('.', '', $texto);
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
            return null;
        }

        return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '0', 2, '0');
    }

    public static function formatar(int $centavos): string
    {
        return number_format(intdiv($centavos, 100), 0, ',', '.')
            .','.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
