<?php

namespace Database\Factories;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Padrão: aprovado e com perfil completo — é o que a maioria dos testes precisa.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => StatusUsuario::Aprovado,
            'documento' => '52998224725', // CPF válido de exemplo (não é de pessoa real conhecida)
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'Aracaju',
            'uf' => 'SE',
            'cep' => '49000000',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function pendente(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StatusUsuario::Pendente]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StatusUsuario::Bloqueado]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['is_admin' => true]);
    }

    public function semPerfil(): static
    {
        return $this->state(fn (array $attributes) => array_fill_keys(
            ['documento', 'telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep'],
            null,
        ));
    }
}
