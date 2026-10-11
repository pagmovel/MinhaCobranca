<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
    private function nomes(string $consulta = ''): array
    {
        return array_column($this->actingAs($this->usuario)->getJson('/clientes/dados'.$consulta)->assertOk()->json('linhas'), 'nome');
    }

    public function test_bloco_de_paginas_mantendo_os_filtros(): void
    {
        foreach (range(1, 30) as $numero) {
            $this->cliente(['nome' => sprintf('Cliente %02d', $numero), 'cidade' => 'Araci']);
        }
        $this->cliente(['nome' => 'Cliente de Aracaju', 'cidade' => 'Aracaju']);

        $resposta = $this->actingAs($this->usuario)->getJson('/clientes/dados?cidade=araci&por_pagina=25&pagina=2')->assertOk();

        $resposta->assertJsonPath('pagina', ['inicio' => 2, 'por_pagina' => 25, 'total' => 30]);
        $this->assertSame(['Cliente 26', 'Cliente 27', 'Cliente 28', 'Cliente 29', 'Cliente 30'], array_column($resposta->json('linhas'), 'nome'));
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

        $this->assertSame(['Padaria Pão Quente'], $this->nomes('?q=PADARIA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('?q=44.555'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('?cidade=aracaju'));
        $this->assertSame(['Padaria Pão Quente'], $this->nomes('?uf=BA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('?modelo=danfse'));
        $this->assertSame(['Mercado Bom Preço', 'Padaria Pão Quente'], $this->nomes());
    }

    public function test_com_e_sem_contrato_ativo(): void
    {
        $ativo = $this->cliente(['nome' => 'A com contrato ativo']);
        Contrato::factory()->for($this->usuario)->for($ativo)->create();
        $encerrado = $this->cliente(['nome' => 'B só com contrato encerrado']);
        Contrato::factory()->for($this->usuario)->for($encerrado)->create(['ativo' => false]);
        $this->cliente(['nome' => 'C sem contrato']);

        $this->assertSame(['A com contrato ativo'], $this->nomes('?contrato=com'));
        $this->assertSame(['B só com contrato encerrado', 'C sem contrato'], $this->nomes('?contrato=sem'));
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        $this->actingAs($this->usuario)->get('/clientes?uf=XX')->assertSessionHasErrors('uf');
        $this->get('/clientes?contrato=talvez')->assertSessionHasErrors('contrato');
        $this->getJson('/clientes/dados?uf=XX')->assertUnprocessable()->assertJsonValidationErrors('uf');
    }

    public function test_dados_trazem_contratos_ativos_e_documento_formatado(): void
    {
        $cliente = $this->cliente(['nome' => 'Padaria', 'documento' => Documento::cnpjComDv('998887770001')]);
        Contrato::factory()->for($this->usuario)->for($cliente)->count(2)->create();
        Contrato::factory()->for($this->usuario)->for($cliente)->create(['ativo' => false]);

        $this->actingAs($this->usuario)->getJson('/clientes/dados')->assertOk()
            ->assertJsonPath('linhas.0.contratos_ativos', 2)
            ->assertJsonPath('linhas.0.documento', Documento::formatar(Documento::cnpjComDv('998887770001')));
    }

    public function test_pagina_abre_so_com_os_filtros(): void
    {
        $this->cliente(['nome' => 'Padaria']);

        $this->actingAs($this->usuario)->get('/clientes?uf=BA&pagina=2&page=7')->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Clientes/Index')
                ->missing('clientes')
                ->where('filtros', ['q' => '', 'cidade' => '', 'uf' => 'BA', 'modelo' => '', 'contrato' => '', 'pagina' => 2, 'por_pagina' => 25]));
    }
}
