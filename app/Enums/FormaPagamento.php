<?php

namespace App\Enums;

enum FormaPagamento: string
{
    case Pix = 'pix';
    case Boleto = 'boleto';
    case Nenhuma = 'nenhuma';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Boleto => 'Boleto bancário',
            self::Nenhuma => 'Não informar',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $forma) => ['valor' => $forma->value, 'rotulo' => $forma->rotulo()], self::cases());
    }
}
