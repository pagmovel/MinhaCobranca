<?php

namespace App\Http\Requests;

use App\Enums\TemaVisual;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TemaRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tema' => ['required', Rule::enum(TemaVisual::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'tema.required' => 'Escolha um tema.',
            'tema.enum' => 'Esse tema não existe. Escolha um da lista.',
        ];
    }
}
