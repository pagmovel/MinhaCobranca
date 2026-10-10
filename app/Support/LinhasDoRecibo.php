<?php

namespace App\Support;

/**
 * Quebra a descrição nas linhas que aparecem na caixa do recibo.
 *
 * O dompdf ignora line-height nas linhas que ele mesmo quebra (saem a ~10,7pt em fonte de 7pt), o que
 * sobrepunha texto no WebISS. Quebrando aqui, cada linha cabe sem quebra automática e a validação
 * (`LinhasMaximas`) conta exatamente as linhas que vão para o PDF.
 */
final class LinhasDoRecibo
{
    /** Caracteres por linha na caixa do WebISS (7pt DejaVu): medidos 137–146; folga para maiúsculas. */
    public const LARGURA = 110;

    /** @return list<string> */
    public static function quebrar(string $texto): array
    {
        $linhas = [];

        foreach (preg_split('/\R/u', rtrim($texto)) as $digitada) {
            $atual = '';
            foreach (preg_split('/ +/u', trim($digitada)) as $palavra) {
                foreach (mb_str_split($palavra, self::LARGURA) ?: [''] as $pedaco) {
                    $junto = $atual === '' ? $pedaco : "{$atual} {$pedaco}";
                    if (mb_strlen($junto) <= self::LARGURA) {
                        $atual = $junto;

                        continue;
                    }
                    $linhas[] = $atual;
                    $atual = $pedaco;
                }
            }
            $linhas[] = $atual;
        }

        return $linhas;
    }
}
