<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TelasCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function cobranca(array $dados = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create($dados);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca;
    }

    public function test_lista_do_mes_atual_so_do_usuario(): void
    {
        $doMes = $this->cobranca(['vencimento' => '2026-10-15']);
        $this->cobranca(['vencimento' => '2026-11-15']);
        Cobranca::factory()->create(['vencimento' => '2026-10-15']);

        // A página abre só com os filtros; a lista vem do JSON.
        $this->actingAs($this->usuario)->get('/cobrancas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cobrancas/Index')
                ->missing('cobrancas')
                ->where('filtros.mes', '2026-10')
                ->where('filtros.pagina', 1)
                ->where('filtros.por_pagina', 25)
                ->where('padroes', ['mes' => '2026-10', 'ate' => '2026-10', 'status' => '', 'cliente_id' => '', 'contrato_id' => '', 'canceladas' => false]));
        $this->getJson('/cobrancas/dados')->assertJsonCount(1, 'linhas')->assertJsonPath('linhas.0.id', $doMes->id);
    }

    /** Spec §4: "A linha mostra 'Avisado em dd/mm'" — não só no bloco do Painel (achado da revisão, T24.9). */
    public function test_lista_mostra_avisado_em(): void
    {
        $avisada = $this->cobranca();
        $avisada->avisado_em = '2026-10-08 09:00:00';
        $avisada->save();
        $naoAvisada = $this->cobranca(['vencimento' => '2026-10-16']);

        $this->actingAs($this->usuario)->getJson('/cobrancas/dados')->assertJson(['linhas' => [
            ['id' => $avisada->id, 'avisado_em' => '08/10'],
            ['id' => $naoAvisada->id, 'avisado_em' => null],
        ]]);
    }

    public function test_filtros_de_mes_situacao_e_cliente(): void
    {
        $outroCliente = Cliente::factory()->for($this->usuario)->create();
        $paga = $this->cobranca(['vencimento' => '2026-11-05']);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-01';
        $paga->save();
        $this->cobranca(['vencimento' => '2026-11-06']);
        $deOutro = Cobranca::factory()->for($this->usuario)->for($outroCliente)->create(['vencimento' => '2026-11-07']);

        $this->actingAs($this->usuario);
        $this->getJson('/cobrancas/dados?mes=2026-11&status=paga')
            ->assertJsonCount(1, 'linhas')->assertJsonPath('linhas.0.id', $paga->id);
        $this->getJson("/cobrancas/dados?mes=2026-11&cliente_id={$outroCliente->id}")
            ->assertJsonCount(1, 'linhas')->assertJsonPath('linhas.0.id', $deOutro->id);
        $this->get('/cobrancas?mes=novembro')->assertSessionHasErrors('mes');
    }

    public function test_pagina_mostra_o_contrato_filtrado(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['valor_centavos' => 16257, 'data_inicio' => '2026-09-01', 'ativo' => false]);

        $this->actingAs($this->usuario)->get("/cobrancas?contrato_id={$contrato->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.contrato_id', $contrato->id)
                ->where('contratoFiltro.valor', '162,57')
                ->where('contratoFiltro.data_inicio', '01/09/2026'));
    }

    public function test_abrir_a_lista_gera_as_cobrancas_devidas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_inicio' => '2026-10-05', 'dia_vencimento' => 5]);

        $this->actingAs($this->usuario)->get('/cobrancas')->assertOk(); // a geração roda ao abrir a página
        $this->getJson('/cobrancas/dados')->assertJsonCount(1, 'linhas');
        $this->assertSame(1, $this->usuario->cobrancas()->count());
    }

    public function test_formularios_de_avulsa_e_edicao(): void
    {
        $editavel = $this->cobranca();
        $enviada = $this->cobranca();
        $enviada->enviado_em = now();
        $enviada->save();
        $alheia = Cobranca::factory()->create();

        $this->actingAs($this->usuario);
        $this->get('/cobrancas/create')
            ->assertInertia(fn (Assert $page) => $page->component('Cobrancas/Form')->where('cobranca', null)->where('clienteAtual', null));
        $this->get("/cobrancas/{$editavel->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('cobranca.id', $editavel->id)->where('cobranca.valor', '260,60')->where('clienteAtual.id', $this->cliente->id));
        $this->get("/cobrancas/{$enviada->id}/edit")
            ->assertRedirect('/cobrancas')
            ->assertSessionHas('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        $this->get("/cobrancas/{$alheia->id}/edit")->assertNotFound();
    }
}
