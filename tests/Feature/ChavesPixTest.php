<?php

namespace Tests\Feature;

use App\Models\ChavePix;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChavesPixTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cadastra_chave_normalizada_e_lista_no_perfil(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post('/perfil/chaves-pix', ['tipo' => 'telefone', 'chave' => '(79) 99999-0000', 'apelido' => 'Celular'])
            ->assertSessionHas('sucesso', 'Chave PIX cadastrada.');

        $this->assertSame('+5579999990000', $usuario->chavesPix()->sole()->chave);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('chavesPix', 1)
            ->where('chavesPix.0.chave', '+55 (79) 99999-0000')
            ->where('chavesPix.0.apelido', 'Celular'));
    }

    public function test_chave_invalida_para_o_tipo_e_recusada(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/perfil/chaves-pix', ['tipo' => 'cpf', 'chave' => '111.111.111-11'])
            ->assertSessionHasErrors(['chave' => 'Informe uma chave válida para o tipo escolhido.']);

        $this->assertSame(0, ChavePix::count());
    }

    public function test_chave_repetida_so_e_recusada_para_o_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create(['tipo' => 'email', 'chave' => 'a@exemplo.com']);

        $this->actingAs($usuario)->post('/perfil/chaves-pix', ['tipo' => 'email', 'chave' => 'A@Exemplo.com'])
            ->assertSessionHasErrors(['chave' => 'Esta chave já está cadastrada.']);
        $this->actingAs(User::factory()->create())->post('/perfil/chaves-pix', ['tipo' => 'email', 'chave' => 'a@exemplo.com'])
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso');
    }

    public function test_edita_e_exclui_so_a_propria_chave(): void
    {
        $usuario = User::factory()->create();
        $minha = ChavePix::factory()->for($usuario)->create();
        $alheia = ChavePix::factory()->create();
        $this->actingAs($usuario);

        $this->put("/perfil/chaves-pix/{$minha->id}", ['tipo' => 'cpf', 'chave' => '52998224725', 'apelido' => 'Pessoal'])
            ->assertSessionHas('sucesso', 'Chave PIX atualizada.');
        $this->assertSame('52998224725', $minha->fresh()->chave);

        $this->put("/perfil/chaves-pix/{$alheia->id}", ['tipo' => 'cpf', 'chave' => '52998224725'])->assertNotFound();
        $this->delete("/perfil/chaves-pix/{$alheia->id}")->assertNotFound();

        $this->delete("/perfil/chaves-pix/{$minha->id}")->assertSessionHas('sucesso', 'Chave PIX excluída.');
        $this->assertNull($minha->fresh());
    }
}
