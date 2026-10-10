<?php

namespace Database\Factories;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        $raiz = str_pad((string) fake()->unique()->numberBetween(1, 99_999_999), 8, '0', STR_PAD_LEFT);

        return [
            'user_id' => User::factory(),
            'nome' => fake()->company(),
            'documento' => Documento::cnpjComDv($raiz.'0001'),
            'telefone' => '(75) 3333-0000',
            'email' => fake()->safeEmail(),
            'logradouro' => 'Rua Virgílio Oliveira Lima',
            'numero' => '185',
            'bairro' => 'Centro',
            'cidade' => 'Araci',
            'uf' => 'BA',
            'cep' => '48760000',
            'modelo' => FamiliaModelo::Webiss,
        ];
    }
}
