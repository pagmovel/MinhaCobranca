<?php

namespace App\Enums;

enum FamiliaModelo: string
{
    case Webiss = 'webiss';
    case Danfse = 'danfse';

    /** Versão usada nos recibos emitidos a partir de agora. Ao criar uma v2, trocar só aqui. */
    public function versaoAtual(): ModeloRecibo
    {
        return match ($this) {
            self::Webiss => ModeloRecibo::WebissV2,
            self::Danfse => ModeloRecibo::DanfseV2,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Webiss => 'Modelo prefeitura (WebISS)',
            self::Danfse => 'Modelo nacional (DANFSe)',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(
            fn (self $familia) => ['valor' => $familia->value, 'rotulo' => $familia->rotulo()],
            self::cases(),
        );
    }
}
