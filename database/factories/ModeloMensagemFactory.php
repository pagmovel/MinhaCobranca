<?php

namespace Database\Factories;

use App\Models\ModeloMensagem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeloMensagem>
 */
class ModeloMensagemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => 'Aviso padrão',
            'texto' => 'Olá {cliente}! Passando para avisar: {itens}. Vencimento: {vencimento}. Total: {total}.'."\n\n".'{prestador}',
            'padrao' => false,
        ];
    }
}
