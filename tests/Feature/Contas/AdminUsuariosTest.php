<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUsuariosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_quem_nao_e_admin_recebe_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/usuarios')->assertForbidden();
    }

    public function test_lista_mostra_pendentes_primeiro_e_conta_pendentes(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Aprovado']);
        User::factory()->pendente()->create(['name' => 'Zé Pendente']);

        $this->actingAs($admin)->get('/admin/usuarios')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Usuarios')
                ->missing('usuarios')
                ->where('filtros', ['q' => '', 'status' => '', 'pagina' => 1, 'por_pagina' => 25])
                ->where('pendentes', 1));
        $this->getJson('/admin/usuarios/dados')->assertOk()
            ->assertJsonCount(3, 'linhas')
            ->assertJsonPath('linhas.0.nome', 'Zé Pendente')
            ->assertJsonPath('linhas.0.status', 'pendente');
    }

    /** Pedido do usuário (2026-10-12): quem se cadastrou primeiro aparece primeiro, não por ordem alfabética. */
    public function test_ordem_e_por_cadastro_nao_por_nome(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Zeca Admin']);
        User::factory()->create(['name' => 'Ana Depois']);

        $nomes = array_column($this->actingAs($admin)->getJson('/admin/usuarios/dados')->assertOk()->json('linhas'), 'nome');

        $this->assertSame(['Zeca Admin', 'Ana Depois'], $nomes);
    }

    public function test_dados_pendentes_primeiro_com_busca_situacao_e_eu(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Ana Liberada', 'email' => 'ana.liberada@exemplo.test']);
        User::factory()->pendente()->create(['name' => 'Zé Pendente']);
        User::factory()->bloqueado()->create(['name' => 'Bia Bloqueada']);
        $nomes = fn (string $consulta = '') => array_column($this->actingAs($admin)->getJson('/admin/usuarios/dados'.$consulta)->assertOk()->json('linhas'), 'nome');

        $this->assertSame(['Zé Pendente', 'Admin', 'Ana Liberada', 'Bia Bloqueada'], $nomes());
        $this->assertSame(['Ana Liberada'], $nomes('?q=ANA.LIBERADA@'));
        $this->assertSame(['Ana Liberada'], $nomes('?q=liberada'));
        $this->assertSame(['Bia Bloqueada'], $nomes('?status=bloqueado'));
        $this->getJson('/admin/usuarios/dados')->assertJsonPath('linhas.1.eu', true)->assertJsonPath('linhas.0.eu', false);
        $this->getJson('/admin/usuarios/dados?status=talvez')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_dados_so_para_admin(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/admin/usuarios/dados')->assertForbidden();
    }

    public function test_admin_aprova_bloqueia_e_reativa(): void
    {
        $admin = User::factory()->admin()->create();
        $usuario = User::factory()->pendente()->create();

        $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}/aprovar")->assertRedirect();
        $this->assertSame(StatusUsuario::Aprovado, $usuario->fresh()->status);

        $this->post("/admin/usuarios/{$usuario->id}/bloquear")->assertRedirect();
        $this->assertSame(StatusUsuario::Bloqueado, $usuario->fresh()->status);

        $this->post("/admin/usuarios/{$usuario->id}/aprovar");
        $this->assertSame(StatusUsuario::Aprovado, $usuario->fresh()->status);
    }

    public function test_admin_nao_bloqueia_a_propria_conta(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/admin/usuarios/{$admin->id}/bloquear")
            ->assertSessionHas('erro', 'Você não pode bloquear a própria conta.');
        $this->assertSame(StatusUsuario::Aprovado, $admin->fresh()->status);
    }

    public function test_liberar_em_lote_aprova_pendentes_e_reativa_bloqueados(): void
    {
        $admin = User::factory()->admin()->create();
        $pendente = User::factory()->pendente()->create();
        $bloqueado = User::factory()->bloqueado()->create();

        $this->actingAs($admin)->post('/admin/usuarios/lote/aprovar', ['ids' => [$pendente->id, $bloqueado->id]])
            ->assertSessionHas('sucesso', '2 usuário(s) liberado(s).');

        $this->assertSame(StatusUsuario::Aprovado, $pendente->fresh()->status);
        $this->assertSame(StatusUsuario::Aprovado, $bloqueado->fresh()->status);
    }

    public function test_liberar_em_lote_ignora_quem_ja_esta_liberado_e_avisa(): void
    {
        $admin = User::factory()->admin()->create();
        $pendente = User::factory()->pendente()->create();
        $jaAprovado = User::factory()->create();

        $this->actingAs($admin)->post('/admin/usuarios/lote/aprovar', ['ids' => [$pendente->id, $jaAprovado->id]])
            ->assertSessionHas('sucesso', '1 usuário(s) liberado(s). 1 ignorado(s) (já estava liberado).');
    }

    public function test_liberar_em_lote_so_para_admin(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/usuarios/lote/aprovar', ['ids' => [1]])
            ->assertForbidden();
    }
}
