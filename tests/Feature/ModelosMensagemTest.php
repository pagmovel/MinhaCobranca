<?php

namespace Tests\Feature;

use App\Models\ModeloMensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModelosMensagemTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    public function test_cadastra_edita_e_exclui_isolado_por_usuario(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Aviso', 'texto' => 'Olá {cliente}!'])
            ->assertRedirect()->assertSessionHas('sucesso', 'Modelo cadastrado.');
        $modelo = $this->usuario->modelosMensagem()->sole();
        $this->assertSame(['Aviso', 'Olá {cliente}!', false], [$modelo->nome, $modelo->texto, $modelo->padrao]);

        $this->put("/perfil/mensagens/{$modelo->id}", ['nome' => 'Aviso editado', 'texto' => 'Oi {cliente}!'])
            ->assertRedirect()->assertSessionHas('sucesso', 'Modelo atualizado.');
        $this->assertSame('Aviso editado', $modelo->fresh()->nome);

        $this->delete("/perfil/mensagens/{$modelo->id}")->assertRedirect()->assertSessionHas('sucesso', 'Modelo excluído.');
        $this->assertSame(0, $this->usuario->modelosMensagem()->count());
    }

    public function test_marcar_padrao_desmarca_o_anterior(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $um = $this->usuario->modelosMensagem()->sole();

        $this->post('/perfil/mensagens', ['nome' => 'Dois', 'texto' => 'y', 'padrao' => true]);
        $dois = $this->usuario->modelosMensagem()->where('nome', 'Dois')->sole();

        $this->assertSame([false, true], [$um->fresh()->padrao, $dois->fresh()->padrao]);

        $this->put("/perfil/mensagens/{$um->id}", ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $this->assertSame([true, false], [$um->fresh()->padrao, $dois->fresh()->padrao]);
    }

    public function test_desmarcar_o_padrao_funciona_mesmo_sem_mandar_outro(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $modelo = $this->usuario->modelosMensagem()->sole();

        // Checkbox desmarcado: o navegador não manda a chave "padrao" (não "padrao=false").
        $this->put("/perfil/mensagens/{$modelo->id}", ['nome' => 'Um', 'texto' => 'x']);

        $this->assertFalse($modelo->fresh()->padrao);
    }

    public function test_modelo_de_outro_usuario_da_404(): void
    {
        $alheio = ModeloMensagem::factory()->create();

        $this->put("/perfil/mensagens/{$alheio->id}", ['nome' => 'x', 'texto' => 'y'])->assertNotFound();
        $this->delete("/perfil/mensagens/{$alheio->id}")->assertNotFound();
        $this->assertNotNull($alheio->fresh());
    }

    public function test_tela_do_perfil_traz_os_modelos_do_usuario(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Aviso', 'texto' => 'Olá {cliente}!', 'padrao' => true]);

        $this->get('/perfil')->assertInertia(fn (Assert $pagina) => $pagina
            ->has('modelosMensagem', 1)
            ->where('modelosMensagem.0.nome', 'Aviso')
            ->where('modelosMensagem.0.padrao', true));
    }

    public function test_nome_e_texto_obrigatorios_e_texto_ate_2000(): void
    {
        $this->post('/perfil/mensagens', ['nome' => '', 'texto' => ''])->assertSessionHasErrors(['nome', 'texto']);
        $this->post('/perfil/mensagens', ['nome' => 'x', 'texto' => str_repeat('a', 2001)])->assertSessionHasErrors('texto');
    }
}
