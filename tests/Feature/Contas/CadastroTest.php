<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Bruno Lima',
            'email' => 'bruno@exemplo.com',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ], $extra);
    }

    public function test_pagina_de_cadastro_renderiza(): void
    {
        $this->get('/cadastro')->assertInertia(fn (Assert $page) => $page->component('Auth/Cadastro'));
    }

    public function test_cadastro_cria_conta_pendente_sem_entrar(): void
    {
        $this->post('/cadastro', $this->dados())
            ->assertRedirect('/entrar')
            ->assertSessionHas('sucesso', 'Cadastro recebido. Você poderá entrar assim que o administrador liberar seu acesso.');

        $this->assertGuest();
        $usuario = User::where('email', 'bruno@exemplo.com')->firstOrFail();
        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
    }

    public function test_email_ja_cadastrado_e_recusado(): void
    {
        User::factory()->create(['email' => 'bruno@exemplo.com']);

        $this->post('/cadastro', $this->dados())
            ->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);
    }

    public function test_email_e_guardado_em_minusculas_e_nao_duplica_por_maiusculas(): void
    {
        $this->post('/cadastro', $this->dados(['email' => ' Bruno@Exemplo.COM ']))->assertRedirect('/entrar');
        $this->assertSame('bruno@exemplo.com', User::firstOrFail()->email);

        $this->post('/cadastro', $this->dados(['email' => 'BRUNO@exemplo.com']))
            ->assertSessionHasErrors(['email' => 'Este e-mail já está cadastrado.']);
        $this->assertSame(1, User::count());
    }

    public function test_senha_curta_ou_sem_confirmacao_e_recusada(): void
    {
        $this->post('/cadastro', $this->dados(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors(['password' => 'O campo senha deve ter pelo menos 8 caracteres.']);
        $this->post('/cadastro', $this->dados(['password_confirmation' => 'outra-senha']))
            ->assertSessionHasErrors(['password' => 'A confirmação de senha não confere.']);
    }
}
