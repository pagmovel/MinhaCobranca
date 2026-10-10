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
                ->has('usuarios', 3)
                ->where('usuarios.0.nome', 'Zé Pendente')
                ->where('usuarios.0.status', 'pendente')
                ->where('pendentes', 1));
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
}
