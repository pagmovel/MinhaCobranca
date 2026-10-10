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

        $this->actingAs($this->usuario)->get('/cobrancas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cobrancas/Index')
                ->where('filtros.mes', '2026-10')
                ->has('cobrancas', 1)
                ->where('cobrancas.0.id', $doMes->id));
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
        $this->get('/cobrancas?mes=2026-11&status=paga')
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1)->where('cobrancas.0.id', $paga->id));
        $this->get("/cobrancas?mes=2026-11&cliente_id={$outroCliente->id}")
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1)->where('cobrancas.0.id', $deOutro->id));
        $this->get('/cobrancas?mes=novembro')->assertSessionHasErrors('mes');
    }

    public function test_abrir_a_lista_gera_as_cobrancas_devidas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_inicio' => '2026-10-05', 'dia_vencimento' => 5]);

        $this->actingAs($this->usuario)->get('/cobrancas')
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1));
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
            ->assertInertia(fn (Assert $page) => $page->component('Cobrancas/Form')->where('cobranca', null)->has('clientes', 1));
        $this->get("/cobrancas/{$editavel->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('cobranca.id', $editavel->id)->where('cobranca.valor', '260,60'));
        $this->get("/cobrancas/{$enviada->id}/edit")
            ->assertRedirect('/cobrancas')
            ->assertSessionHas('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        $this->get("/cobrancas/{$alheia->id}/edit")->assertNotFound();
    }
}
