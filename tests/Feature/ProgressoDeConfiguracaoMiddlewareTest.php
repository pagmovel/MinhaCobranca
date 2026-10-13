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

    public function test_bloco_liberado_incompleto_impede_criar_contrato_e_cobranca(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create(); // perfil ok, catálogo vazio
        $cliente = Cliente::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/contratos/create')
            ->assertRedirect('/painel')
            ->assertSessionHas('erro', 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.');
        $this->get('/cobrancas/create')->assertRedirect('/painel');

        // Sem produto real: o middleware bloqueia antes de qualquer validação do corpo, então o payload não
        // precisa (e não deve) referenciar um produto de verdade — criar um aqui completaria o catálogo e o
        // POST passaria a ser legitimamente permitido, o que não é o que este teste quer provar.
        $this->post('/contratos', [
            'cliente_id' => $cliente->id, 'itens' => [['produto_servico_id' => 999999, 'quantidade' => '1', 'preco' => '10,00', 'principal' => true]],
            'periodicidade' => 'mensal', 'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ])->assertRedirect('/painel');
        $this->assertSame(0, $usuario->contratos()->count());
    }

    public function test_bloco_liberado_completo_libera_criar_contrato_e_cobranca(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        ProdutoServico::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/contratos/create')->assertOk();
        $this->get('/cobrancas/create')->assertOk();
    }

    /** recomecar/renovar/renovar-vencido criam contrato novo por baixo — também exigem "liberado" (Review Focus 2). */
    public function test_bloco_liberado_incompleto_impede_recomecar_renovar_e_renovar_vencido(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario)->post('/contratos', [
            'cliente_id' => $cliente->id,
            'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '10,00', 'principal' => true]],
            'periodicidade' => 'mensal', 'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ])->assertRedirect('/contratos');
        $contrato = $usuario->contratos()->sole();

        // Simula o progresso voltando a ficar incompleto depois do contrato já existir, pra provar que as 3
        // ações (que criam contrato novo por baixo) também passam pelo gate — não só o "store" original.
        $usuario->chavesPix()->delete();

        $this->post("/contratos/{$contrato->id}/recomecar", [])->assertRedirect('/painel');
        $this->post("/contratos/{$contrato->id}/renovar", [])->assertRedirect('/painel');
        $this->post("/contratos/{$contrato->id}/renovar-vencido", [])->assertRedirect('/painel');
    }
}
