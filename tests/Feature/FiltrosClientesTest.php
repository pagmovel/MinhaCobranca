<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FiltrosClientesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
    }

    private function cliente(array $dados): Cliente
    {
        return Cliente::factory()->for($this->usuario)->create($dados);
    }

    /** @return list<string> */
    private function nomes(string $url): array
    {
        return array_column($this->actingAs($this->usuario)->get($url)->assertOk()->inertiaProps('clientes'), 'nome');
    }

    public function test_pagina_de_25_em_25_mantendo_os_filtros(): void
    {
        foreach (range(1, 30) as $numero) {
            $this->cliente(['nome' => sprintf('Cliente %02d', $numero), 'cidade' => 'Araci']);
        }
        $this->cliente(['nome' => 'Cliente de Aracaju', 'cidade' => 'Aracaju']);

        $resposta = $this->actingAs($this->usuario)->get('/clientes?cidade=araci');

        $this->assertCount(25, $resposta->inertiaProps('clientes'));
        $this->assertSame(30, $resposta->inertiaProps('paginacao.total'));
        $this->assertStringContainsString('cidade=araci', $resposta->inertiaProps('paginacao.proxima'));
        $this->assertStringContainsString('page=2', $resposta->inertiaProps('paginacao.proxima'));
        $this->assertSame(
            ['Cliente 26', 'Cliente 27', 'Cliente 28', 'Cliente 29', 'Cliente 30'],
            $this->nomes('/clientes?cidade=araci&page=2'),
        );
    }

    public function test_filtra_por_nome_ou_documento_cidade_uf_e_modelo(): void
    {
        $this->cliente([
            'nome' => 'Padaria Pão Quente', 'documento' => Documento::cnpjComDv('998887770001'),
            'cidade' => 'Araci', 'uf' => 'BA', 'modelo' => FamiliaModelo::Webiss,
        ]);
        $this->cliente([
            'nome' => 'Mercado Bom Preço', 'documento' => Documento::cnpjComDv('445556660001'),
            'cidade' => 'Aracaju', 'uf' => 'SE', 'modelo' => FamiliaModelo::Danfse,
        ]);
        Cliente::factory()->create(['nome' => 'Padaria de outro usuário']);

        $this->assertSame(['Padaria Pão Quente'], $this->nomes('/clientes?q=PADARIA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?q=44.555'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?cidade=aracaju'));
        $this->assertSame(['Padaria Pão Quente'], $this->nomes('/clientes?uf=BA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?modelo=danfse'));
        $this->assertSame(['Mercado Bom Preço', 'Padaria Pão Quente'], $this->nomes('/clientes'));
    }

    public function test_com_e_sem_contrato_ativo(): void
    {
        $ativo = $this->cliente(['nome' => 'A com contrato ativo']);
        Contrato::factory()->for($this->usuario)->for($ativo)->create();
        $encerrado = $this->cliente(['nome' => 'B só com contrato encerrado']);
        Contrato::factory()->for($this->usuario)->for($encerrado)->create(['ativo' => false]);
        $this->cliente(['nome' => 'C sem contrato']);

        $this->assertSame(['A com contrato ativo'], $this->nomes('/clientes?contrato=com'));
        $this->assertSame(['B só com contrato encerrado', 'C sem contrato'], $this->nomes('/clientes?contrato=sem'));
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        $this->actingAs($this->usuario)->get('/clientes?uf=XX')->assertSessionHasErrors('uf');
        $this->get('/clientes?contrato=talvez')->assertSessionHasErrors('contrato');
    }
}
