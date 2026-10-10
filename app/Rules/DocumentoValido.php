<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class DocumentoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Documento::valido(is_string($value) ? $value : null)) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }
}
