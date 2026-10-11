<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class Paginacao
{
    /** @return array{atual: int, ultima: int, total: int, anterior: ?string, proxima: ?string} */
    public static function dados(LengthAwarePaginator $pagina): array
    {
        return [
            'atual' => $pagina->currentPage(),
            'ultima' => $pagina->lastPage(),
            'total' => $pagina->total(),
            'anterior' => $pagina->previousPageUrl(),
            'proxima' => $pagina->nextPageUrl(),
        ];
    }
}
