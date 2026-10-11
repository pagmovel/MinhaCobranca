<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Bloco de páginas das listas por ajax (Fase 19): o navegador guarda o bloco e troca de página sem pedir de novo. */
final class BlocoDePaginas
{
    public const PAGINAS = 4;

    /**
     * Regras de `pagina` e `por_pagina` dos `/dados`.
     *
     * @param  list<int>  $porPagina
     * @return array<string, list<mixed>>
     */
    public static function regras(array $porPagina = [25, 50, 100]): array
    {
        return [
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in($porPagina)],
        ];
    }

    /**
     * Fatia de `PAGINAS` páginas a partir de `$pagina`; além da última, devolve a última (`pagina.inicio` diz qual).
     * Consulta: conta e traz só o bloco. Coleção (cobranças, que mesclam previstas; relatórios): fatia na memória.
     *
     * @return array{itens: Collection, pagina: array{inicio: int, por_pagina: int, total: int}}
     */
    public static function de(Builder|Relation|Collection $fonte, int $pagina, int $porPagina): array
    {
        // getCountForPagination tira o ORDER BY: no PostgreSQL, count(*) com ORDER BY de coluna é erro.
        $total = $fonte instanceof Collection ? $fonte->count() : $fonte->toBase()->getCountForPagination();
        $inicio = min(max(1, $pagina), max(1, (int) ceil($total / $porPagina)));
        $pular = ($inicio - 1) * $porPagina;
        $quantos = self::PAGINAS * $porPagina;

        return [
            'itens' => $fonte instanceof Collection
                ? $fonte->slice($pular, $quantos)->values()
                : $fonte->offset($pular)->limit($quantos)->get(),
            'pagina' => ['inicio' => $inicio, 'por_pagina' => $porPagina, 'total' => $total],
        ];
    }
}
