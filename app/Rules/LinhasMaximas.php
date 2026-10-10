<?php

namespace App\Rules;

use App\Support\LinhasDoRecibo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limita as linhas do recibo (as digitadas e as que uma linha comprida ocupa) para caber na caixa A4. */
final class LinhasMaximas implements ValidationRule
{
    public function __construct(private int $maximo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (count(LinhasDoRecibo::quebrar((string) $value)) > $this->maximo) {
            $fail("Use no máximo {$this->maximo} linhas.");
        }
    }
}
