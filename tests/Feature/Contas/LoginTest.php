<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_usuario_aprovado_entra_e_vai_para_o_painel(): void
    {
        $usuario = User::factory()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertRedirect('/painel');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_entra_com_o_email_escrito_em_maiusculas(): void
    {
        $usuario = User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'segredo123']);

        $this->post('/entrar', ['email' => 'Ana@Exemplo.com', 'password' => 'segredo123'])
            ->assertRedirect('/painel');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_senha_errada_mostra_mensagem_clara(): void
    {
        $usuario = User::factory()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->assertGuest();
    }

    public function test_conta_pendente_nao_entra(): void
    {
        $usuario = User::factory()->pendente()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertSessionHasErrors(['email' => 'Seu cadastro aguarda liberação do administrador.']);
        $this->assertGuest();
    }

    public function test_conta_bloqueada_nao_entra(): void
    {
        $usuario = User::factory()->bloqueado()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertSessionHasErrors(['email' => 'Seu acesso foi bloqueado. Fale com o administrador.']);
        $this->assertGuest();
    }

    public function test_bloquear_derruba_quem_ja_esta_logado(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get('/painel')->assertOk();

        $usuario->status = StatusUsuario::Bloqueado;
        $usuario->save();

        $this->get('/painel')
            ->assertRedirect('/entrar')
            ->assertSessionHas('erro', 'Seu acesso foi bloqueado. Fale com o administrador.');
        $this->assertGuest();
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/painel')->assertRedirect('/entrar');
        $this->get('/')->assertRedirect('/painel');
    }

    public function test_logado_que_abre_entrar_vai_para_o_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/entrar')->assertRedirect('/painel');
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->create())->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
    }

    public function test_muitas_tentativas_do_mesmo_email_respondem_429(): void
    {
        $usuario = User::factory()->create(['password' => 'segredo123']);

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->post('/entrar', ['email' => $usuario->email, 'password' => 'errada']);
        }

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])->assertStatus(429);
        $this->assertGuest();
    }
}
