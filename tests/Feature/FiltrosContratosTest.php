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
    private function ids(string $url): array
    {
        return array_column($this->actingAs($this->usuario)->get($url)->assertOk()->inertiaProps('contratos'), 'id');
    }

    public function test_pagina_de_25_em_25(): void
    {
        Contrato::factory()->for($this->usuario)->count(26)->create();

        $resposta = $this->actingAs($this->usuario)->get('/contratos');

        $this->assertCount(25, $resposta->inertiaProps('contratos'));
        $this->assertSame(2, $resposta->inertiaProps('paginacao.ultima'));
        $this->assertCount(1, $this->ids('/contratos?page=2'));
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

        $this->assertSame([$doCliente->id], $this->ids("/contratos?cliente_id={$cliente->id}"));
        $this->assertSame([$encerrado->id], $this->ids('/contratos?situacao=encerrados'));
        $this->assertCount(4, $this->ids('/contratos?situacao=ativos'));
        $this->assertSame([$posPago->id], $this->ids('/contratos?modalidade=pospago'));
        $this->assertSame([$comPix->id], $this->ids('/contratos?forma_pagamento=pix'));
        $this->assertSame([$comItem->id], $this->ids("/contratos?produto_servico_id={$produto->id}"));
        $this->assertCount(5, $this->ids('/contratos'));
    }

    public function test_cliente_alheio_nao_traz_nada_e_filtro_invalido_e_recusado(): void
    {
        $alheio = Contrato::factory()->create();
        Contrato::factory()->for($this->usuario)->create();

        $this->assertSame([], $this->ids("/contratos?cliente_id={$alheio->cliente_id}"));
        $this->actingAs($this->usuario)->get('/contratos?situacao=todos')->assertSessionHasErrors('situacao');
    }
}
