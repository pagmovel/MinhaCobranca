<?php

namespace App\Http\Requests\Concerns;

use App\Enums\FormaPagamento;
use Illuminate\Validation\Rule;

/** Forma de pagamento: igual no contrato, na cobrança e na troca depois de enviada. */
trait RegrasDePagamento
{
    protected function prepararPagamento(): void
    {
        $this->mergeIfMissing(['forma_pagamento' => FormaPagamento::Nenhuma->value]);

        if ($this->has('linha_digitavel')) {
            $linha = (string) preg_replace('/\D/', '', (string) $this->input('linha_digitavel'));
            $this->merge(['linha_digitavel' => $linha === '' ? null : $linha]);
        }
    }

    protected function regrasDePagamento(bool $comLinhaDigitavel): array
    {
        $regras = [
            'forma_pagamento' => ['required', Rule::enum(FormaPagamento::class)],
            'chave_pix_id' => [
                'nullable',
                'required_if:forma_pagamento,pix',
                Rule::exists('chaves_pix', 'id')->where('user_id', $this->user()->id),
            ],
        ];

        if ($comLinhaDigitavel) {
            $regras['linha_digitavel'] = ['nullable', 'regex:/^(\d{47}|\d{48})$/'];
        }

        return $regras;
    }

    /** Chave só com PIX; linha digitável só com boleto. */
    protected function dadosDePagamento(array $validados): array
    {
        $forma = FormaPagamento::from($validados['forma_pagamento']);
        $dados = [
            'forma_pagamento' => $forma,
            'chave_pix_id' => $forma === FormaPagamento::Pix ? $validados['chave_pix_id'] : null,
        ];

        if (array_key_exists('linha_digitavel', $validados)) {
            $dados['linha_digitavel'] = $forma === FormaPagamento::Boleto ? $validados['linha_digitavel'] : null;
        }

        return $dados;
    }
}
