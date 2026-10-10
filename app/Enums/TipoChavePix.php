<?php

namespace App\Enums;

use App\Support\Documento;

enum TipoChavePix: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Email = 'email';
    case Telefone = 'telefone';
    case Aleatoria = 'aleatoria';

    public function rotulo(): string
    {
        return match ($this) {
            self::Cpf => 'CPF',
            self::Cnpj => 'CNPJ',
            self::Email => 'E-mail',
            self::Telefone => 'Telefone',
            self::Aleatoria => 'Chave aleatória',
        };
    }

    /** A chave como o DICT a conhece; null quando não serve para o tipo. */
    public function normalizar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return match ($this) {
            self::Cpf => self::documento($valor, 11),
            // Manual Operacional do DICT 8.5, seção 1: CNPJ alfanumérico, 14 caracteres, sem pontos ou traços.
            self::Cnpj => self::documento($valor, 14),
            self::Email => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($valor) <= 77 ? mb_strtolower($valor) : null,
            self::Telefone => self::telefone($valor),
            self::Aleatoria => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1 ? mb_strtolower($valor) : null,
        };
    }

    public function formatar(string $chave): string
    {
        return match ($this) {
            self::Cpf, self::Cnpj => Documento::formatar($chave),
            self::Telefone => (string) preg_replace('/^\+55(\d{2})(\d{5})(\d{4})$/', '+55 ($1) $2-$3', $chave),
            default => $chave,
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }

    private static function documento(string $valor, int $tamanho): ?string
    {
        $documento = Documento::normalizar($valor);

        return strlen($documento) === $tamanho && Documento::valido($documento) ? $documento : null;
    }

    private static function telefone(string $valor): ?string
    {
        $digitos = (string) preg_replace('/\D/', '', $valor);

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        // DICT: só telefone celular, padrão E.164 (+55 + DDD + 9 dígitos começando com 9).
        return strlen($digitos) === 11 && $digitos[2] === '9' ? '+55'.$digitos : null;
    }
}
