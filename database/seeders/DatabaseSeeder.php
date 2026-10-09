<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Sem usuário de teste: a factory cria conta aprovada com senha conhecida ("password").
     */
    public function run(): void
    {
        $this->call(PerfilInicialSeeder::class);
    }
}
