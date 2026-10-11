<?php

namespace App\Support;

final class Busca
{
    /** Padrão para `lower(coluna) like ? escape '\'`: o texto digitado vale literalmente (% e _ não são curinga). */
    public static function contem(string $termo): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($termo))).'%';
    }
}
