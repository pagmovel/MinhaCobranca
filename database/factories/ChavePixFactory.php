<?php

namespace Database\Factories;

use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChavePix> */
class ChavePixFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo' => TipoChavePix::Email,
            'chave' => fake()->unique()->safeEmail(),
            'apelido' => null,
        ];
    }
}
