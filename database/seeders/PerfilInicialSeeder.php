<?php

namespace Database\Seeders;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Cria (ou atualiza) o administrador com o perfil de prestador do dono do sistema.
 *
 * Os dados ficam em database/seeders/dados/perfil-inicial.json, fora do git (CPF, endereço e
 * hash da senha são dados pessoais). Sem o arquivo, a seed não faz nada.
 */
class PerfilInicialSeeder extends Seeder
{
    public function __construct(private ?string $arquivo = null) {}

    public function run(): void
    {
        $caminho = $this->arquivo ?? database_path('seeders/dados/perfil-inicial.json');

        if (! is_file($caminho)) {
            $this->command?->warn("Arquivo de dados não encontrado: {$caminho}. Nada foi criado.");

            return;
        }

        $this->semear(json_decode((string) file_get_contents($caminho), true, flags: JSON_THROW_ON_ERROR));
    }

    /** Idempotente: identifica o usuário pelo e-mail. */
    public function semear(array $dados): User
    {
        $usuario = User::firstOrNew(['email' => $dados['email']]);
        $usuario->fill(Arr::except($dados, ['email', 'password_hash']));
        // Hash já pronto: o cast "hashed" grava como está e a senha atual continua valendo.
        $usuario->password = $dados['password_hash'];
        $usuario->is_admin = true;
        $usuario->status = StatusUsuario::Aprovado;
        $usuario->save();

        return $usuario;
    }
}
