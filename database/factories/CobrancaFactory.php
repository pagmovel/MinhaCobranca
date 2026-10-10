<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cobranca>
 */
class CobrancaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cliente_id' => fn (array $atributos) => Cliente::factory()->create(['user_id' => $atributos['user_id']])->id,
            'contrato_id' => null,
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-10',
            'valor_centavos' => 26060,
            'descricao' => 'Serviço avulso',
        ];
    }
}
