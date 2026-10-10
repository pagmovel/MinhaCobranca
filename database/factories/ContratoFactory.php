<?php

namespace Database\Factories;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cliente_id' => fn (array $atributos) => Cliente::factory()->create(['user_id' => $atributos['user_id']])->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'valor_centavos' => 26060,
            'periodicidade' => Periodicidade::Mensal,
            'modalidade' => Modalidade::Prepago,
            'dia_vencimento' => 21,
            'data_inicio' => '2026-09-21',
            'data_fim' => null,
            'ativo' => true,
        ];
    }
}
