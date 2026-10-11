<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Cadastro rápido em modal: quem está lançando um contrato ou cobrança cadastra o que falta sem sair da tela.
 * Os endpoints de sempre respondem JSON quando o pedido aceita JSON; validação e isolamento continuam os mesmos.
 */
class CadastroRapidoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    public function test_produto_novo_volta_no_formato_da_autosugestao(): void
    {
        $this->postJson('/produtos-servicos', [
            'nome' => 'Consultoria', 'tipo' => 'servico', 'unidade' => 'hora', 'preco' => '150,00',
        ])->assertCreated()->assertExactJson([
            'id' => $this->usuario->produtosServicos()->sole()->id, 'nome' => 'Consultoria', 'unidade' => 'hora', 'preco' => '150,00',
        ]);
    }

    public function test_produto_com_dados_invalidos_responde_422_com_os_campos(): void
    {
        ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Consultoria']);

        $this->postJson('/produtos-servicos', ['nome' => 'Consultoria', 'tipo' => 'servico', 'unidade' => '', 'preco' => 'abc'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nome', 'unidade', 'preco']);
        $this->assertSame(1, $this->usuario->produtosServicos()->count());
    }

    public function test_cliente_novo_volta_no_formato_da_autosugestao(): void
    {
        $this->postJson('/clientes', ['nome' => 'Padaria Pão Quente', 'documento' => '11.222.333/0001-81', 'modelo' => 'webiss'])
            ->assertCreated()->assertExactJson([
                'id' => $this->usuario->clientes()->sole()->id, 'nome' => 'Padaria Pão Quente', 'documento' => '11.222.333/0001-81',
            ]);

        $this->postJson('/clientes', ['nome' => 'Outra', 'documento' => '11222333000181', 'modelo' => 'webiss'])
            ->assertUnprocessable()->assertJsonValidationErrors('documento');
    }

    public function test_chave_pix_nova_volta_no_formato_das_opcoes(): void
    {
        $resposta = $this->postJson('/perfil/chaves-pix', ['tipo' => 'email', 'chave' => 'Financeiro@Exemplo.com', 'apelido' => 'Conta PJ'])
            ->assertCreated();

        $chave = $this->usuario->chavesPix()->sole();
        $resposta->assertExactJson(['id' => $chave->id, 'rotulo' => $chave->rotulo()]);
    }

    public function test_formulario_tradicional_continua_redirecionando(): void
    {
        $this->post('/produtos-servicos', ['nome' => 'Hospedagem', 'tipo' => 'servico', 'unidade' => 'mês', 'preco' => '89,90'])
            ->assertRedirect('/produtos-servicos');
        $this->post('/clientes', ['nome' => 'Ana', 'documento' => '529.982.247-25', 'modelo' => 'webiss'])
            ->assertRedirect('/clientes');
    }

    public function test_formularios_de_lancamento_trazem_as_opcoes_do_cadastro_rapido(): void
    {
        $this->usuario->forceFill(['modelo_padrao' => 'danfse'])->save();

        foreach (['/contratos/create', '/cobrancas/create'] as $url) {
            $this->get($url)->assertInertia(fn (Assert $page) => $page
                ->where('cadastroRapido.modeloPadrao', 'danfse')
                ->has('cadastroRapido.modelos', 2)
                ->has('cadastroRapido.ufs', 27));
        }
    }

    public function test_tipos_de_chave_pix_chegam_a_toda_tela_com_forma_de_pagamento(): void
    {
        foreach (['/painel', '/cobrancas', '/contratos/create'] as $url) {
            $this->get($url)->assertInertia(fn (Assert $page) => $page->has('tiposChavePix', 5)->where('tiposChavePix.0.valor', 'cpf'));
        }
    }

    public function test_formulario_abre_mesmo_sem_cliente_cadastrado(): void
    {
        foreach (['/contratos/create', '/cobrancas/create'] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('temClientes', false));
        }
    }

    public function test_busca_vazia_mostra_os_primeiros_ao_focar(): void
    {
        foreach (range(1, 12) as $numero) {
            ProdutoServico::factory()->for($this->usuario)->create(['nome' => sprintf('Item %02d', $numero)]);
            Cliente::factory()->for($this->usuario)->create(['nome' => sprintf('Cliente %02d', $numero)]);
        }
        ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Aaa inativo', 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Aaa de outro usuário']);

        $this->getJson('/produtos-servicos/busca?q=')->assertJsonCount(10)->assertJsonPath('0.nome', 'Item 01');
        $this->getJson('/produtos-servicos/busca')->assertJsonCount(10);
        $this->getJson('/clientes/busca?q=')->assertJsonCount(10)->assertJsonPath('0.nome', 'Cliente 01');
        $this->getJson('/produtos-servicos/busca?q=i')->assertExactJson([]); // 1 letra continua sem busca
    }
}
