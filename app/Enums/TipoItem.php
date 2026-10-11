<?php

namespace App\Enums;

enum TipoItem: string
{
    case Servico = 'servico';
    case Produto = 'produto';

    public function rotulo(): string
    {
        return match ($this) {
            self::Servico => 'Serviço',
            self::Produto => 'Produto',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }
}
