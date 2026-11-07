<?php

namespace Tests\Feature\Temas;

use App\Enums\TemaVisual;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompartilhamentoDoTemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Usuário que já existia antes dos temas: apresentação resolvida e a novidade ainda pendente. */
    private function usuarioAntigo(array $extra = []): User
    {
        $usuario = User::factory()->create($extra);
        $usuario->forceFill(['apresentacao_vista' => true, 'novidade_temas_vista' => false])->save();

        return $usuario;
    }

    public function test_html_traz_data_tema_do_usuario(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertOk()
            ->assertSee('<html lang="pt-BR" data-tema="'.$usuario->tema->value.'"', false);
    }

    public function test_visitante_recebe_talao(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('data-tema="talao"', false);
    }

    public function test_tema_corrompido_no_banco_vira_talao(): void
    {
        $usuario = User::factory()->create();
        DB::table('users')->where('id', $usuario->id)->update(['tema' => 'tema-removido']);

        $this->actingAs($usuario)->get('/painel')
            ->assertOk()
            ->assertSee('data-tema="talao"', false)
            ->assertInertia(fn (Assert $pagina) => $pagina->where('auth.user.tema', 'talao'));
    }

    public function test_props_trazem_o_tema_e_a_lista_sem_expor_outras_colunas(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->where('auth.user', [
                    'id' => $usuario->id, 'name' => $usuario->name, 'email' => $usuario->email,
                    'is_admin' => false, 'tema' => 'talao',
                ])
                ->where('temas', TemaVisual::paraOFront()));
    }

    public function test_visitante_nao_recebe_usuario_nos_props(): void
    {
        $this->get('/entrar')->assertInertia(fn (Assert $pagina) => $pagina
            ->where('auth.user', null)
            ->where('mostrarNovidadeTemas', false));
    }

    public function test_novidade_nao_aparece_com_um_unico_tema(): void
    {
        // Enquanto o enum tiver um caso só, não há o que escolher e o popup não abre.
        $esperado = count(TemaVisual::cases()) > 1;

        $this->actingAs($this->usuarioAntigo())->get('/painel')
            ->assertInertia(fn (Assert $pagina) => $pagina->where('mostrarNovidadeTemas', $esperado));
    }

    public function test_novidade_ja_vista_nao_volta(): void
    {
        $usuario = $this->usuarioAntigo();
        $usuario->forceFill(['novidade_temas_vista' => true])->save();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $pagina) => $pagina->where('mostrarNovidadeTemas', false));
    }

    public function test_novidade_nunca_aparece_por_cima_da_apresentacao_inicial(): void
    {
        $usuario = $this->usuarioAntigo();
        $usuario->forceFill(['apresentacao_vista' => false])->save(); // perfil incompleto: a apresentação vai abrir

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->where('mostrarApresentacao', true)
                ->where('mostrarNovidadeTemas', false));
    }

    public function test_usuario_novo_nao_ve_a_novidade(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel')
            ->assertInertia(fn (Assert $pagina) => $pagina->where('mostrarNovidadeTemas', false));
    }
}
