<?php

namespace App\Services;

final class ValorPorExtenso
{
    private const UNIDADES = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez',
        'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    private const DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    private const CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    /** [singular, plural] por classe: unidades, milhar, milhão, bilhão. */
    private const CLASSES = [['', ''], ['mil', 'mil'], ['milhão', 'milhões'], ['bilhão', 'bilhões']];

    public static function converter(int $centavos): string
    {
        if ($centavos === 0) {
            return 'zero reais';
        }

        $reais = intdiv($centavos, 100);
        $resto = $centavos % 100;
        $partes = [];

        if ($reais > 0) {
            $partes[] = self::inteiro($reais).self::moeda($reais);
        }

        if ($resto > 0) {
            $partes[] = self::ate999($resto).($resto === 1 ? ' centavo' : ' centavos');
        }

        return implode(' e ', $partes);
    }

    private static function moeda(int $reais): string
    {
        if ($reais === 1) {
            return ' real';
        }

        // "um milhão de reais", mas "um milhão e quinhentos mil reais".
        return $reais % 1_000_000 === 0 ? ' de reais' : ' reais';
    }

    private static function inteiro(int $numero): string
    {
        $grupos = [];
        for ($classe = 0; $numero > 0; $classe++) {
            $grupos[$classe] = $numero % 1000;
            $numero = intdiv($numero, 1000);
        }

        $trechos = [];
        for ($classe = count($grupos) - 1; $classe >= 0; $classe--) {
            $grupo = $grupos[$classe];
            if ($grupo === 0) {
                continue;
            }

            $texto = ($classe === 1 && $grupo === 1)
                ? 'mil'
                : self::ate999($grupo).($classe > 0 ? ' '.self::CLASSES[$classe][$grupo === 1 ? 0 : 1] : '');

            $trechos[] = ['texto' => $texto, 'valor' => $grupo];
        }

        $saida = '';
        foreach ($trechos as $indice => $trecho) {
            if ($indice === 0) {
                $saida = $trecho['texto'];

                continue;
            }

            // "e" antes do último grupo quando ele é menor que cem ou centena exata: "mil e dez", "mil e duzentos".
            $ultimo = $indice === count($trechos) - 1;
            $comE = $ultimo && ($trecho['valor'] < 100 || $trecho['valor'] % 100 === 0);
            $saida .= ($comE ? ' e ' : ' ').$trecho['texto'];
        }

        return $saida;
    }

    private static function ate999(int $numero): string
    {
        if ($numero === 100) {
            return 'cem';
        }

        $partes = [];
        $centena = intdiv($numero, 100);
        $resto = $numero % 100;

        if ($centena > 0) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = $resto < 20
                ? self::UNIDADES[$resto]
                : self::DEZENAS[intdiv($resto, 10)].($resto % 10 ? ' e '.self::UNIDADES[$resto % 10] : '');
        }

        return implode(' e ', $partes);
    }
}
