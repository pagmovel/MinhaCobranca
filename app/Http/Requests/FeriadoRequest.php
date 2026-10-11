<?php

namespace App\Http\Requests;

use App\Models\Feriado;
use App\Rules\AnoValido;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FeriadoRequest extends FormRequest
{
    public const LIMITE = 100;

    /** Feriado de outro usuário vira 404 antes da validação (padrão de `ChavePixRequest`). */
    public function authorize(): bool
    {
        $feriado = $this->route('feriado');

        abort_if($feriado !== null && ! $this->user()->feriados()->whereKey($feriado)->exists(), 404);

        return true;
    }

    /** Só ficam os campos do tipo escolhido: trocar de tipo não deixa resto gravado. */
    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo');

        $this->merge([
            'dia' => $tipo === 'anual' ? $this->input('dia') : null,
            'mes' => $tipo === 'anual' ? $this->input('mes') : null,
            'data' => $tipo === 'unico' ? $this->input('data') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:60'],
            'tipo' => ['required', Rule::in(Feriado::TIPOS)],
            'dia' => ['nullable', 'required_if:tipo,anual', 'integer', 'between:1,31'],
            'mes' => ['nullable', 'required_if:tipo,anual', 'integer', 'between:1,12'],
            'data' => ['nullable', 'required_if:tipo,unico', 'date_format:Y-m-d', new AnoValido],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            // 2024 é bissexto: 29/02 passa (acontece nos anos bissextos); 31/02 e 31/04 não existem.
            if ($this->input('tipo') === 'anual' && ! $validator->errors()->hasAny(['dia', 'mes'])
                && ! checkdate((int) $this->input('mes'), (int) $this->input('dia'), 2024)) {
                $validator->errors()->add('dia', 'Este dia não existe no mês escolhido.');
            }

            // Uma lista local que cobrisse todos os dias deixaria o calendário sem dia útil (`DiasUteis` para com erro).
            if ($this->route('feriado') === null && $this->user()->feriados()->count() >= self::LIMITE) {
                $validator->errors()->add('descricao', 'Limite de '.self::LIMITE.' feriados locais. Exclua um que não vale mais antes de incluir outro.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'descricao.required' => 'Informe o nome do feriado.',
            'dia.required_if' => 'Informe o dia.',
            'mes.required_if' => 'Escolha o mês.',
            'data.required_if' => 'Informe a data.',
        ];
    }
}
