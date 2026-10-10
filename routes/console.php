<?php

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:criar-admin {email} {--nome=Administrador}', function (string $email) {
    $usuario = User::where('email', $email)->first();

    if (! $usuario) {
        $senha = (string) $this->secret('Senha');

        if (mb_strlen($senha) < 8) {
            $this->error('A senha precisa ter pelo menos 8 caracteres.');

            return 1;
        }

        if ($senha !== (string) $this->secret('Confirme a senha')) {
            $this->error('As senhas não conferem. Nada foi criado.');

            return 1;
        }

        $usuario = User::create(['name' => $this->option('nome'), 'email' => $email, 'password' => $senha]);
    }

    $usuario->is_admin = true;
    $usuario->status = StatusUsuario::Aprovado;
    $usuario->save();

    $this->info("Administrador pronto: {$usuario->email}");

    return 0;
})->purpose('Cria ou promove um usuário a administrador com acesso liberado');
