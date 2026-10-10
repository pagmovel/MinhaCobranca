<?php

namespace App\Support;

use Illuminate\Support\Str;

/** BR Code estático do PIX com valor (padrão EMV do Banco Central). */
final class PixCopiaECola
{
    public static function gerar(string $chave, string $nome, string $cidade, int $valorCentavos, string $txid): string
    {
        $identificacao = substr((string) preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);

        $payload = self::campo('00', '01')
            .self::campo('26', self::campo('00', 'br.gov.bcb.pix').self::campo('01', $chave))
            .self::campo('52', '0000')
            .self::campo('53', '986')
            .self::campo('54', sprintf('%d.%02d', intdiv($valorCentavos, 100), $valorCentavos % 100))
            .self::campo('58', 'BR')
            .self::campo('59', self::texto($nome, 25))
            .self::campo('60', self::texto($cidade, 15))
            .self::campo('62', self::campo('05', $identificacao === '' ? '***' : $identificacao))
            .'6304';

        return $payload.self::crc16($payload);
    }

    /** CRC16-CCITT: polinômio 0x1021, valor inicial 0xFFFF. */
    public static function crc16(string $dados): string
    {
        $crc = 0xFFFF;

        foreach (str_split($dados) as $caractere) {
            $crc ^= ord($caractere) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id.sprintf('%02d', strlen($valor)).$valor;
    }

    /** Maiúsculas sem acento (o app do banco exibe esses campos), cortado no limite. */
    private static function texto(string $valor, int $limite): string
    {
        $limpo = (string) preg_replace('/[^A-Z0-9 ]/', '', strtoupper(Str::ascii($valor)));

        return rtrim(substr(trim($limpo), 0, $limite));
    }
}
