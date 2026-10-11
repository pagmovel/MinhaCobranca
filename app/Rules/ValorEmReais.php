<?php

namespace App\Rules;

use App\Support\Dinheiro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValorEmReais implements ValidationRule
{
    public const MAXIMO_CENTAVOS = 9_999_999_999; // R$ 99.999.999,99

    /** Preço de item do catálogo pode ser zero (item incluso, brinde); valor de cobrança não. */
    public function __construct(private bool $aceitaZero = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $centavos = is_string($value) ? Dinheiro::paraCentavos($value) : null;
        $minimo = $this->aceitaZero ? 0 : 1;

        if ($centavos === null || $centavos < $minimo || $centavos > self::MAXIMO_CENTAVOS) {
            $fail('Informe um valor válido, como 260,60.');
        }
    }
}
