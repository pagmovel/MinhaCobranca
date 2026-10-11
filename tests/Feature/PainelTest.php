<?php

namespace Tests\Feature;

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

class PainelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
    }

    public function test_abrir_o_painel_gera_as_cobrancas_devidas(): void
    {
        $usuario = User::factory()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Painel')
                ->where('mes', 'Outubro de 2026')
                ->missing('paraEnviar')->missing('aguardando')->missing('recebidas'));
        $this->getJson('/painel/dados?lista=para_enviar')->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.recibo.numero', '2026/000001');
        $this->getJson('/painel/dados?lista=aguardando')->assertJsonCount(0, 'linhas');
        $this->getJson('/painel/dados?lista=recebidas')->assertJsonCount(0, 'linhas');
    }

    public function test_blocos_seguem_envio_e_pagamento(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $nova = fn (array $dados) => Cobranca::factory()->for($usuario)->for($cliente)->create($dados);

        $paraEnviar = $nova(['vencimento' => '2026-10-20']);
        $enviada = $nova(['vencimento' => '2026-10-15']);
        $enviada->enviado_em = now();
        $enviada->save();
        $paga = $nova(['vencimento' => '2026-10-05', 'valor_centavos' => 10000]);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-06';
        $paga->save();
        $cancelada = $nova(['vencimento' => '2026-10-07']);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();
        foreach ([$paraEnviar, $enviada, $paga, $cancelada] as $cobranca) {
            app(EmissorDeRecibos::class)->emitir($cobranca);
        }

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('resumo.previsto', '621,20')
                ->where('resumo.recebido', '100,00'));
        foreach (['para_enviar' => $paraEnviar, 'aguardando' => $enviada, 'recebidas' => $paga] as $lista => $cobranca) {
            $this->getJson("/painel/dados?lista={$lista}")->assertOk()
                ->assertJsonCount(1, 'linhas')
                ->assertJsonPath('linhas.0.id', $cobranca->id);
        }
    }

    public function test_perfil_incompleto_avisa_e_nao_gera(): void
    {
        $usuario = User::factory()->semPerfil()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('perfilCompleto', false));
        $this->getJson('/painel/dados?lista=para_enviar')->assertOk()->assertJsonCount(0, 'linhas');
    }

    public function test_dados_nao_geram_cobrancas(): void
    {
        $usuario = User::factory()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->getJson('/painel/dados?lista=para_enviar')->assertOk()->assertJsonCount(0, 'linhas');
        $this->assertSame(0, Cobranca::count());
    }

    public function test_contagem_e_soma_da_lista_inteira_em_blocos_de_10(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        foreach (range(1, 12) as $dia) {
            Cobranca::factory()->for($usuario)->for($cliente)->create([
                'vencimento' => sprintf('2026-10-%02d', $dia + 10), 'valor_centavos' => 1000, 'enviado_em' => now(),
            ]);
        }
        Cobranca::factory()->create(['vencimento' => '2026-10-20', 'valor_centavos' => 99900, 'enviado_em' => now()]); // de outro usuário

        $this->actingAs($usuario)->getJson('/painel/dados?lista=aguardando')->assertOk()
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 10, 'total' => 12])
            ->assertJsonPath('total_geral', ['quantidade' => 12, 'soma_centavos' => 12000])
            ->assertJsonCount(12, 'linhas'); // bloco de 4 páginas de 10
        $this->getJson('/painel/dados?lista=aguardando&pagina=9')->assertJsonPath('pagina.inicio', 2);
        $this->getJson('/painel/dados?lista=outra')->assertUnprocessable()->assertJsonValidationErrors('lista');
        $this->getJson('/painel/dados?lista=aguardando&por_pagina=100')->assertUnprocessable();
    }

    public function test_recebido_e_o_que_foi_pago_no_mes_qualquer_que_seja_o_vencimento(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $paga = function (string $vencimento, string $pagoEm, int $valor) use ($usuario, $cliente) {
            $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
            $cobranca->save();
        };
        $paga('2026-09-25', '2026-10-02', 10000); // venceu em setembro, pago em outubro: entra
        $paga('2026-10-20', '2026-10-05', 5000);  // adiantado: entra
        $paga('2026-10-03', '2026-09-30', 7000);  // pago em setembro: não entra

        $this->actingAs($usuario)->get('/painel')->assertInertia(fn (Assert $page) => $page
            ->where('resumo.recebido', '150,00')
            ->where('resumo.previsto', '120,00'));
    }
}
