<?php

namespace App\Enums;

/**
 * Layout congelado de um recibo. Ajuste visual depois de recibos emitidos = nova versão
 * (ex.: WebissV2 = 'webiss-v2') com novo template; a versão antiga continua renderizando.
 */
enum ModeloRecibo: string
{
    case WebissV1 = 'webiss-v1';
    case DanfseV1 = 'danfse-v1';

    public function view(): string
    {
        return 'recibos.pdf.'.$this->value;
    }
}
