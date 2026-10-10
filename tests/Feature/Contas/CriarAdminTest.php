<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CriarAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cria_admin_aprovado_com_senha(): void
    {
        $this->artisan('app:criar-admin', ['email' => 'admin@exemplo.com', '--nome' => 'Admin'])
            ->expectsQuestion('Senha', 'segredo123')
            ->expectsQuestion('Confirme a senha', 'segredo123')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@exemplo.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertSame(StatusUsuario::Aprovado, $admin->status);
        $this->assertTrue(Hash::check('segredo123', $admin->password));
    }

    public function test_promove_usuario_existente_sem_pedir_senha(): void
    {
        $usuario = User::factory()->pendente()->create(['email' => 'ana@exemplo.com']);

        $this->artisan('app:criar-admin', ['email' => 'ana@exemplo.com'])->assertExitCode(0);

        $usuario->refresh();
        $this->assertTrue($usuario->is_admin);
        $this->assertSame(StatusUsuario::Aprovado, $usuario->status);
    }

    public function test_senhas_diferentes_nao_criam_nada(): void
    {
        $this->artisan('app:criar-admin', ['email' => 'admin@exemplo.com'])
            ->expectsQuestion('Senha', 'segredo123')
            ->expectsQuestion('Confirme a senha', 'outra-senha')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'admin@exemplo.com']);
    }
}
