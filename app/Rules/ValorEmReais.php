<?php

namespace App\Rules;

use App\Support\Dinheiro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValorEmReais implements ValidationRule
{
    private const MAXIMO_CENTAVOS = 9_999_999_999; // R$ 99.999.999,99

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $centavos = is_string($value) ? Dinheiro::paraCentavos($value) : null;

        if ($centavos === null || $centavos < 1 || $centavos > self::MAXIMO_CENTAVOS) {
            $fail('Informe um valor válido, como 260,60.');
        }
    }
}
