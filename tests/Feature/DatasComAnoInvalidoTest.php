<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * O campo de data do Chrome deixa digitar até 6 dígitos no ano, e a regra "date" lia 72026-08-01 como 2006-08-01:
 * um erro de digitação virava outra data gravada, sem aviso. Todo formulário que grava data recusa o ano assim.
 */
class DatasComAnoInvalidoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ANO_INVALIDO = '72026-08-01';

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    public function test_baixa_e_correcao_do_pagamento(): void
    {
        $pendente = Cobranca::factory()->for($this->usuario)->create();
        $this->post("/cobrancas/{$pendente->id}/pagar", ['pago_em' => self::ANO_INVALIDO])->assertSessionHasErrors('pago_em');
        $this->assertSame(StatusCobranca::Pendente, $pendente->fresh()->status);

        $paga = Cobranca::factory()->for($this->usuario)->create();
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01'])->save();
        $this->post("/cobrancas/{$paga->id}/corrigir-pagamento", ['pago_em' => self::ANO_INVALIDO, 'senha' => 'password', 'motivo' => 'x'])
            ->assertSessionHasErrors('pago_em');
        $this->assertSame('2026-10-01', $paga->fresh()->pago_em->toDateString());
    }

    public function test_cobranca_avulsa(): void
    {
        $this->post('/cobrancas', ['periodo_inicio' => self::ANO_INVALIDO, 'periodo_fim' => self::ANO_INVALIDO, 'vencimento' => self::ANO_INVALIDO])
            ->assertSessionHasErrors(['periodo_inicio', 'vencimento']);
    }

    public function test_contrato_inicio_retroativos_e_encerramento(): void
    {
        $this->post('/contratos', ['data_inicio' => self::ANO_INVALIDO])->assertSessionHasErrors('data_inicio');
        $this->getJson('/contratos/retroativos?periodicidade=mensal&data_inicio='.self::ANO_INVALIDO)
            ->assertUnprocessable()->assertJsonValidationErrors('data_inicio');

        $contrato = Contrato::factory()->for($this->usuario)->create();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => self::ANO_INVALIDO])->assertSessionHasErrors('fim');
        $this->assertNull($contrato->fresh()->data_fim);
    }
}
