<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Ano entre 1900 e 2100 no começo da data ("Y-m-d" ou "Y-m"), o mesmo intervalo do `anoCompleto()` do JS. Vai junto do
 * `date_format`: o ano 0000 passa nele e o PostgreSQL recusa a data (erro 500); o SQLite dos testes aceita.
 */
final class AnoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Formato (inclusive lista em `?mes[]=`, que viraria "Array to string conversion" = 500) é com o `date_format`.
        if (! is_string($value) || preg_match('/^(\d{4})-/', $value, $partes) !== 1) {
            return;
        }

        $ano = (int) $partes[1];

        if ($ano < 1900 || $ano > 2100) {
            $fail('Use um ano entre 1900 e 2100.');
        }
    }
}
