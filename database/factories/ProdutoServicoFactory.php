<?php

namespace Database\Factories;

use App\Enums\TipoItem;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdutoServico>
 */
class ProdutoServicoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => 'Serviço '.fake()->unique()->numberBetween(1, 9_999_999),
            'tipo' => TipoItem::Servico,
            'unidade' => 'mês',
            'preco_centavos' => 26060,
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'ativo' => true,
        ];
    }
}
