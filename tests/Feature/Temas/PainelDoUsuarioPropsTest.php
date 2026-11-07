<?php

namespace Tests\Feature\Temas;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** O painel do usuário lê tudo de `auth.user`: nome, e-mail e tema, e nunca a senha nem o hash. */
class PainelDoUsuarioPropsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_props_trazem_nome_email_e_tema_sem_senha(): void
    {
        $usuario = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@exemplo.test']);

        // Sem `etc()`: qualquer outra chave em `auth.user` (a senha, o hash, o token) derruba o teste.
        $this->actingAs($usuario)->get('/painel')
            ->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->has('auth.user', fn (Assert $dados) => $dados
                    ->where('id', $usuario->id)
                    ->where('name', 'Ana Souza')
                    ->where('email', 'ana@exemplo.test')
                    ->where('is_admin', false)
                    ->where('tema', $usuario->tema->value)));
    }
}
