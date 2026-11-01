<?php

namespace App\Enums;

/**
 * Layout congelado de um recibo. Ajuste visual depois de recibos emitidos = nova versão
 * (ex.: WebissV3 = 'webiss-v3') com novo template; a versão antiga continua renderizando.
 * Exceção consciente da Fase 26: o v2 substituiu o v1 (sem desconto sai idêntico) e a migração
 * `troca_modelo_dos_recibos_para_v2` passou os recibos emitidos para o v2.
 */
enum ModeloRecibo: string
{
    case WebissV2 = 'webiss-v2';
    case DanfseV2 = 'danfse-v2';

    public function view(): string
    {
        return 'recibos.pdf.'.$this->value;
    }
}
