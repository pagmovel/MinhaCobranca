<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limita linhas para o texto caber na caixa de descrição do recibo A4. */
final class LinhasMaximas implements ValidationRule
{
    public function __construct(private int $maximo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (count(preg_split('/\R/', rtrim((string) $value))) > $this->maximo) {
            $fail("Use no máximo {$this->maximo} linhas.");
        }
    }
}
