<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProgressoDeConfiguracaoMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bloco_perfil_incompleto_impede_criar_produto_e_cliente(): void
    {
        $usuario = User::factory()->create(); // sem chave PIX nem modelo: perfil incompleto

        $this->actingAs($usuario)->get('/produtos-servicos/create')
            ->assertRedirect('/painel')
            ->assertSessionHas('erro', 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.');
        $this->post('/produtos-servicos', ['nome' => 'X', 'tipo' => 'servico', 'unidade' => 'un', 'preco' => '1,00'])
            ->assertRedirect('/painel');
        $this->assertSame(0, $usuario->produtosServicos()->count());

        $this->get('/clientes/create')->assertRedirect('/painel');
        $this->post('/clientes', ['nome' => 'Y', 'modelo' => 'webiss'])->assertRedirect('/painel');
        $this->assertSame(0, $usuario->clientes()->count());
    }

    public function test_bloco_perfil_completo_libera_criar_produto_e_cliente(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();

        $this->actingAs($usuario)->get('/produtos-servicos/create')->assertOk();
        $this->post('/produtos-servicos', ['nome' => 'X', 'tipo' => 'servico', 'unidade' => 'un', 'preco' => '1,00'])
            ->assertRedirect('/produtos-servicos');
        $this->get('/clientes/create')->assertOk();
        $this->post('/clientes', ['nome' => 'Y', 'modelo' => 'webiss'])->assertRedirect('/clientes');
    }

    /** Protege quem já usa o sistema: dados antigos continuam abertos mesmo com o progresso incompleto. */
    public function test_usuario_retroativo_continua_vendo_e_editando_o_que_ja_existe(): void
    {
        $usuario = User::factory()->create(); // perfil incompleto (sem chave/modelo), igual a uma conta antiga
        $cliente = Cliente::factory()->for($usuario)->create();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->get('/clientes')->assertOk();
        $this->get("/clientes/{$cliente->id}/edit")->assertOk();
        $this->put("/clientes/{$cliente->id}", ['nome' => 'Novo nome', 'modelo' => 'webiss'])->assertRedirect('/clientes');

        $this->get('/produtos-servicos')->assertOk();
        $this->get("/produtos-servicos/{$produto->id}/edit")->assertOk();

        $this->get('/contratos')->assertOk();
        $this->get("/contratos/{$contrato->id}/edit")->assertOk();

        $this->get('/cobrancas')->assertOk();
    }
}
