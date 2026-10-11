<?php

namespace App\Enums;

/** Quando o modelo deve ser pré-selecionado automaticamente na janela de aviso — mesmos valores de Cobranca::situacao(). */
enum TipoAvisoModelo: string
{
    case AVencer = 'a_vencer';
    case VenceHoje = 'vence_hoje';
    case Vencida = 'vencida';

    public function rotulo(): string
    {
        return match ($this) {
            self::AVencer => 'A vencer',
            self::VenceHoje => 'Vencendo (vence hoje)',
            self::Vencida => 'Vencida',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }
}
