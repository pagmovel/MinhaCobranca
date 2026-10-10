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
                ->has('paraEnviar', 1)
                ->where('paraEnviar.0.recibo.numero', '2026/000001')
                ->has('aguardando', 0)
                ->has('recebidas', 0));
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
                ->has('paraEnviar', 1)->where('paraEnviar.0.id', $paraEnviar->id)
                ->has('aguardando', 1)->where('aguardando.0.id', $enviada->id)
                ->has('recebidas', 1)->where('recebidas.0.id', $paga->id)
                ->where('resumo.previsto', '621,20')
                ->where('resumo.recebido', '100,00'));
    }

    public function test_perfil_incompleto_avisa_e_nao_gera(): void
    {
        $usuario = User::factory()->semPerfil()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('perfilCompleto', false)
                ->has('paraEnviar', 0));
    }
}
