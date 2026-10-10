<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDePagamento;
use Illuminate\Foundation\Http\FormRequest;

class PagamentoRequest extends FormRequest
{
    use RegrasDePagamento;

    /** Cobrança de outro usuário vira 404 antes da validação (o erro não pode revelar que ela existe). */
    public function authorize(): bool
    {
        abort_unless($this->user()->cobrancas()->whereKey($this->route('cobranca'))->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
    }

    public function rules(): array
    {
        return $this->regrasDePagamento(true);
    }

    public function dados(): array
    {
        return $this->dadosDePagamento($this->validated() + ['linha_digitavel' => null]);
    }
}
