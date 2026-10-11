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
        // Primeiro (e único) modelo do usuário: vira padrão mesmo sem marcar o checkbox (spec §2: "exatamente um
        // padrão quando houver modelos" — achado da revisão, T24.9).
        $this->assertSame(['Aviso', 'Olá {cliente}!', true], [$modelo->nome, $modelo->texto, $modelo->padrao]);

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

    /**
     * Checkbox desmarcado: o navegador não manda a chave "padrao" (não "padrao=false") — essa é a pegadinha.
     * Mas o resultado não pode zerar o padrão (spec §2: "exatamente um padrão quando houver modelos"), então a
     * desmarcação do único padrão é recusada (achado da revisão, T24.9).
     */
    public function test_nao_e_possivel_zerar_o_padrao_desmarcando_o_unico_modelo(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $modelo = $this->usuario->modelosMensagem()->sole();

        $this->put("/perfil/mensagens/{$modelo->id}", ['nome' => 'Um', 'texto' => 'x']);

        $this->assertTrue($modelo->fresh()->padrao);
    }

    public function test_primeiro_modelo_cadastrado_vira_padrao_mesmo_sem_marcar(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Aviso', 'texto' => 'x']);

        $this->assertTrue($this->usuario->modelosMensagem()->sole()->padrao);
    }

    public function test_segundo_modelo_sem_marcar_nao_vira_padrao(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x']); // vira padrão por ser o primeiro
        $this->post('/perfil/mensagens', ['nome' => 'Dois', 'texto' => 'y']);

        $dois = $this->usuario->modelosMensagem()->where('nome', 'Dois')->sole();
        $this->assertFalse($dois->padrao);
    }

    public function test_excluir_o_padrao_promove_o_mais_antigo_remanescente(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x']); // vira padrão por ser o primeiro
        $this->post('/perfil/mensagens', ['nome' => 'Dois', 'texto' => 'y']);
        $um = $this->usuario->modelosMensagem()->where('nome', 'Um')->sole();
        $dois = $this->usuario->modelosMensagem()->where('nome', 'Dois')->sole();

        $this->delete("/perfil/mensagens/{$um->id}");

        $this->assertTrue($dois->fresh()->padrao);
    }

    public function test_excluir_modelo_que_nao_e_padrao_nao_promove_ninguem(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x']); // vira padrão por ser o primeiro
        $this->post('/perfil/mensagens', ['nome' => 'Dois', 'texto' => 'y']);
        $um = $this->usuario->modelosMensagem()->where('nome', 'Um')->sole();
        $dois = $this->usuario->modelosMensagem()->where('nome', 'Dois')->sole();

        $this->delete("/perfil/mensagens/{$dois->id}");

        $this->assertTrue($um->fresh()->padrao);
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
