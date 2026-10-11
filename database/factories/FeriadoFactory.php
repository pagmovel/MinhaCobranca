<?php

namespace Database\Factories;

use App\Models\Feriado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feriado>
 */
class FeriadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'descricao' => 'Independência da Bahia',
            'tipo' => 'anual',
            'dia' => 2,
            'mes' => 7,
            'data' => null,
        ];
    }
}
