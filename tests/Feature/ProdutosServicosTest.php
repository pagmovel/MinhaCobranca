<?php

namespace Tests\Feature;

use App\Enums\TipoItem;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProdutosServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Hospedagem de site',
            'tipo' => 'servico',
            'unidade' => 'mês',
            'preco' => '89,90',
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'ativo' => true,
        ], $extra);
    }

    public function test_cria_item_convertendo_o_preco(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post('/produtos-servicos', $this->dados())->assertRedirect('/produtos-servicos');

        $item = $usuario->produtosServicos()->sole();
        $this->assertSame(8990, $item->preco_centavos);
        $this->assertSame(TipoItem::Servico, $item->tipo);
        $this->assertSame('mês', $item->unidade);
        $this->assertTrue($item->ativo);
    }

    public function test_preco_zero_e_aceito_mas_negativo_e_texto_nao(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/produtos-servicos', $this->dados(['preco' => '0,00']))->assertRedirect('/produtos-servicos');
        $this->assertSame(0, $usuario->produtosServicos()->sole()->preco_centavos);

        $this->post('/produtos-servicos', $this->dados(['nome' => 'Outro', 'preco' => '-5,00']))
            ->assertSessionHasErrors(['preco' => 'Informe um valor válido, como 260,60.']);
        $this->post('/produtos-servicos', $this->dados(['nome' => 'Outro', 'preco' => 'abc']))
            ->assertSessionHasErrors('preco');
    }

    public function test_nome_repetido_so_conta_no_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem de site']);
        ProdutoServico::factory()->create(['nome' => 'Suporte mensal']);
        $this->actingAs($usuario);

        $this->post('/produtos-servicos', $this->dados())
            ->assertSessionHasErrors(['nome' => 'Este nome já está cadastrado.']);
        $this->post('/produtos-servicos', $this->dados(['nome' => 'Suporte mensal']))
            ->assertRedirect('/produtos-servicos');
    }

    public function test_lista_filtra_e_pagina_so_os_itens_do_usuario(): void
    {
        $usuario = User::factory()->create();
        ProdutoServico::factory()->for($usuario)->count(26)
            ->sequence(fn ($sequencia) => ['nome' => sprintf('Serviço %02d', $sequencia->index)])
            ->create();
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Caneta 50%', 'tipo' => TipoItem::Produto, 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Serviço de outro']);
        $this->actingAs($usuario);

        $this->get('/produtos-servicos')->assertInertia(fn (Assert $page) => $page
            ->component('ProdutosServicos/Index')
            ->has('itens', 25)
            ->where('itens.0.nome', 'Caneta 50%')
            ->where('paginacao.total', 27)
            ->where('paginacao.ultima', 2));
        $this->get('/produtos-servicos?page=2')->assertInertia(fn (Assert $page) => $page->has('itens', 2));
        $this->get('/produtos-servicos?tipo=produto&situacao=inativos')
            ->assertInertia(fn (Assert $page) => $page->has('itens', 1)->where('itens.0.nome', 'Caneta 50%'));
        // % digitado é texto, não curinga.
        $this->get('/produtos-servicos?nome=50%25')
            ->assertInertia(fn (Assert $page) => $page->has('itens', 1)->where('filtros.nome', '50%'));
        $this->get('/produtos-servicos?situacao=todos')->assertSessionHasErrors('situacao');
    }

    public function test_busca_traz_so_os_ativos_do_usuario(): void
    {
        $usuario = User::factory()->create();
        $ativo = ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem de site', 'preco_centavos' => 8990]);
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem antiga', 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Hospedagem de outro']);
        $this->actingAs($usuario);

        $this->getJson('/produtos-servicos/busca?q=HOSPED')->assertExactJson([
            ['id' => $ativo->id, 'nome' => 'Hospedagem de site', 'unidade' => 'mês', 'preco' => '89,90'],
        ]);
        $this->getJson('/produtos-servicos/busca?q=h')->assertExactJson([]);
    }

    public function test_edita_desativa_exclui_e_isola_item_alheio(): void
    {
        $usuario = User::factory()->create();
        $item = ProdutoServico::factory()->for($usuario)->create();
        $semUso = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->get("/produtos-servicos/{$item->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->component('ProdutosServicos/Form')->where('item.preco', '260,60'));
        $this->put("/produtos-servicos/{$item->id}", $this->dados(['ativo' => false]))->assertRedirect('/produtos-servicos');
        $this->assertFalse($item->fresh()->ativo);
        $this->delete("/produtos-servicos/{$semUso->id}")->assertRedirect('/produtos-servicos');
        $this->assertModelMissing($semUso);

        $this->get("/produtos-servicos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/produtos-servicos/{$alheio->id}", $this->dados(['nome' => '']))->assertNotFound();
        $this->delete("/produtos-servicos/{$alheio->id}")->assertNotFound();
        $this->assertModelExists($alheio);
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/produtos-servicos')->assertRedirect('/entrar');
        $this->getJson('/produtos-servicos/busca?q=ho')->assertUnauthorized();
    }
}
