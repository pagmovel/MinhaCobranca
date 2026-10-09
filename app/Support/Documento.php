<?php

namespace App\Support;

final class Documento
{
    private const PESOS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const PESOS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** Só letras e dígitos, em maiúsculas (o CNPJ pode ter letras desde jul/2026). */
    public static function normalizar(?string $valor): string
    {
        return (string) preg_replace('/[^0-9A-Z]/', '', mb_strtoupper((string) $valor));
    }

    public static function valido(?string $valor): bool
    {
        $documento = self::normalizar($valor);

        if (preg_match('/^\d{11}$/', $documento)) {
            return self::cpfValido($documento);
        }

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $documento)) {
            return ! preg_match('/^(\d)\1{13}$/', $documento)
                && self::cnpjComDv(substr($documento, 0, 12)) === $documento;
        }

        return false;
    }

    public static function formatar(?string $valor): string
    {
        $documento = self::normalizar($valor);

        return match (strlen($documento)) {
            0 => '-',
            11 => (string) preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $documento),
            14 => (string) preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $documento),
            default => $documento,
        };
    }

    /** Completa os 12 caracteres-base com os dois dígitos verificadores. */
    public static function cnpjComDv(string $base): string
    {
        $base = self::normalizar($base);
        $dv1 = self::dvCnpj($base, self::PESOS_DV1);

        return $base.$dv1.self::dvCnpj($base.$dv1, self::PESOS_DV2);
    }

    private static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }
            if ((int) $cpf[$posicao] !== ($soma * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    /** Módulo 11; cada caractere vale ASCII − 48 (regra da Receita para o CNPJ alfanumérico). */
    private static function dvCnpj(string $parcial, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (ord($parcial[$i]) - 48) * $peso;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
