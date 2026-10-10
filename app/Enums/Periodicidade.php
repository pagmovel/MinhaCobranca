<?php

namespace App\Enums;

enum Periodicidade: string
{
    case Mensal = 'mensal';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';

    public function meses(): int
    {
        return match ($this) {
            self::Mensal => 1,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
        };
    }

    public function rotulo(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $p) => ['valor' => $p->value, 'rotulo' => $p->rotulo()], self::cases());
    }
}
