<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FiltrosContratosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
    }

    /** @return list<int> */
    private function ids(string $consulta = ''): array
    {
        return array_column($this->actingAs($this->usuario)->getJson('/contratos/dados'.$consulta)->assertOk()->json('linhas'), 'id');
    }

    public function test_bloco_de_paginas_de_25(): void
    {
        Contrato::factory()->for($this->usuario)->count(26)->create();

        $this->actingAs($this->usuario)->getJson('/contratos/dados?por_pagina=25&pagina=2')->assertOk()
            ->assertJsonPath('pagina', ['inicio' => 2, 'por_pagina' => 25, 'total' => 26])
            ->assertJsonCount(1, 'linhas');
    }

    public function test_totais_dos_ativos_por_periodicidade_e_encerrados(): void
    {
        Contrato::factory()->for($this->usuario)->count(2)->create(['periodicidade' => 'mensal', 'valor_centavos' => 10000]);
        Contrato::factory()->for($this->usuario)->create(['periodicidade' => 'trimestral', 'valor_centavos' => 30000]);
        Contrato::factory()->for($this->usuario)->create(['periodicidade' => 'mensal', 'valor_centavos' => 99999, 'ativo' => false]);
        Contrato::factory()->create(['periodicidade' => 'mensal']); // de outro usuário

        $this->actingAs($this->usuario)->getJson('/contratos/dados')->assertOk()
            ->assertJsonPath('totais.ativos', [
                ['periodicidade' => 'mensal', 'quantidade' => 2, 'soma_centavos' => 20000],
                ['periodicidade' => 'trimestral', 'quantidade' => 1, 'soma_centavos' => 30000],
            ])
            ->assertJsonPath('totais.encerrados', 1)
            ->assertJsonPath('pagina.total', 4);
    }

    public function test_situacao_ativo_encerra_em_e_encerrado(): void
    {
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-01-01']);
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-02-01', 'data_fim' => '2026-12-31']);
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-03-01', 'data_fim' => '2026-06-30', 'ativo' => false]);

        $linhas = $this->actingAs($this->usuario)->getJson('/contratos/dados')->json('linhas');

        $this->assertSame(['ativo', 'encerra', 'encerrado'], array_column($linhas, 'situacao'));
        $this->assertSame([null, '31/12/2026', '30/06/2026'], array_column($linhas, 'fim'));
    }

    public function test_pagina_traz_o_produto_do_filtro_mesmo_inativo(): void
    {
        $produto = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Domínio antigo', 'ativo' => false]);

        $this->actingAs($this->usuario)->get("/contratos?produto_servico_id={$produto->id}")->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Contratos/Index')
                ->missing('contratos')
                ->missing('produtos')
                ->where('produtoFiltro', ['id' => $produto->id, 'nome' => 'Domínio antigo']));
    }

    public function test_filtra_por_cliente_situacao_modalidade_forma_e_produto(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $produto = ProdutoServico::factory()->for($this->usuario)->create();
        $doCliente = Contrato::factory()->for($this->usuario)->for($cliente)->create();
        $encerrado = Contrato::factory()->for($this->usuario)->create(['ativo' => false]);
        $posPago = Contrato::factory()->for($this->usuario)->create(['modalidade' => Modalidade::Pospago]);
        $comPix = Contrato::factory()->for($this->usuario)->create([
            'forma_pagamento' => FormaPagamento::Pix,
            'chave_pix_id' => ChavePix::factory()->for($this->usuario)->create()->id,
        ]);
        $comItem = Contrato::factory()->for($this->usuario)->create();
        $comItem->itens()->create([
            'produto_servico_id' => $produto->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 26060,
            'principal' => true, 'ordem' => 0,
        ]);
        Contrato::factory()->create();

        $this->assertSame([$doCliente->id], $this->ids("?cliente_id={$cliente->id}"));
        $this->assertSame([$encerrado->id], $this->ids('?situacao=encerrados'));
        $this->assertCount(4, $this->ids('?situacao=ativos'));
        $this->assertSame([$posPago->id], $this->ids('?modalidade=pospago'));
        $this->assertSame([$comPix->id], $this->ids('?forma_pagamento=pix'));
        $this->assertSame([$comItem->id], $this->ids("?produto_servico_id={$produto->id}"));
        $this->assertCount(5, $this->ids());
    }

    public function test_cliente_alheio_nao_traz_nada_e_filtro_invalido_e_recusado(): void
    {
        $alheio = Contrato::factory()->create();
        Contrato::factory()->for($this->usuario)->create();

        $this->assertSame([], $this->ids("?cliente_id={$alheio->cliente_id}"));
        $this->actingAs($this->usuario)->get('/contratos?situacao=todos')->assertSessionHasErrors('situacao');
        $this->getJson('/contratos/dados?situacao=talvez')->assertUnprocessable()->assertJsonValidationErrors('situacao');
    }
}
