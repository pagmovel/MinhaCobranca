<?php

namespace App\Services;

use App\Models\Recibo;

final class NumeradorDeRecibos
{
    /** Chamar dentro da transação da emissão; o índice único (user_id, ano, numero) é a garantia final. */
    public function proximo(int $userId, int $ano): int
    {
        return (int) Recibo::where('user_id', $userId)->where('ano', $ano)->max('numero') + 1;
    }
}
