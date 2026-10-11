<?php

namespace App\Support;

/** Contas dos itens de contrato e cobrança: só inteiros (quantidade em milésimos, preço em centavos). */
final class ItensDeVenda
{
    /** "1,5", "1.5", "2" ou 2 → milésimos (1500, 2000). Null se não for número com até 3 casas. */
    public static function milesimos(string|int|float $quantidade): ?int
    {
        $texto = str_replace(',', '.', trim((string) $quantidade));

        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $texto, $partes)) {
            return null;
        }

        return (int) $partes[1] * 1000 + (int) str_pad($partes[2] ?? '0', 3, '0');
    }

    /** Milésimos → "1.500" (coluna decimal(10,3)). */
    public static function quantidade(int $milesimos): string
    {
        return intdiv($milesimos, 1000).'.'.str_pad((string) ($milesimos % 1000), 3, '0', STR_PAD_LEFT);
    }

    /** Milésimos → "1,5", "2", "0,033" (como a pessoa digita). */
    public static function formatarQuantidade(int $milesimos): string
    {
        $decimais = rtrim(str_pad((string) ($milesimos % 1000), 3, '0', STR_PAD_LEFT), '0');

        return intdiv($milesimos, 1000).($decimais === '' ? '' : ','.$decimais);
    }

    /** Quantidade × preço em centavos, meio centavo para cima. */
    public static function total(int $milesimos, int $precoCentavos): int
    {
        return intdiv($milesimos * $precoCentavos + 500, 1000);
    }

    /**
     * Item principal: o primeiro marcado; sem marcado, o de maior total (empate: o primeiro).
     *
     * @param  list<array{total_centavos: int, principal: bool}>  $itens
     */
    public static function indicePrincipal(array $itens): int
    {
        foreach ($itens as $indice => $item) {
            if ($item['principal']) {
                return $indice;
            }
        }

        $maior = 0;
        foreach ($itens as $indice => $item) {
            if ($item['total_centavos'] > $itens[$maior]['total_centavos']) {
                $maior = $indice;
            }
        }

        return $maior;
    }
}
