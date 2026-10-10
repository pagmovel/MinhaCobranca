<?php

namespace App\Enums;

enum Modalidade: string
{
    case Prepago = 'prepago';
    case Pospago = 'pospago';

    public function rotulo(): string
    {
        return match ($this) {
            self::Prepago => 'Pré-pago',
            self::Pospago => 'Pós-pago',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $modalidade) => ['valor' => $modalidade->value, 'rotulo' => $modalidade->rotulo()], self::cases());
    }
}
