<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDeItens;
use App\Rules\AnoValido;
use Illuminate\Foundation\Http\FormRequest;

/** Só o que muda na renovação: a partir de quando e os itens (preço pode ser renegociado, não o do catálogo). */
class RenovarContratoVencidoRequest extends FormRequest
{
    use RegrasDeItens;

    /** Contrato de outro usuário vira 404 (o erro não pode revelar que ele existe). */
    public function authorize(): bool
    {
        abort_if(! $this->user()->contratos()->whereKey($this->route('contrato'))->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararItens();
    }

    public function rules(): array
    {
        return [
            'data_inicio' => ['required', 'date_format:Y-m-d', new AnoValido],
            ...$this->regrasDeItens(),
        ];
    }
}
