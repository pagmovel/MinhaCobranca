<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** T17.19: seleção de várias cobranças e ação em lote (baixar, cancelar), com prévia na tela e auditoria por item. */
class AcoesEmLoteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function cobranca(array $extra = []): Cobranca
    {
        return Cobranca::factory()->for($this->usuario)->for($this->cliente)->create($extra);
    }

    public function test_baixa_em_lote_com_data_de_hoje_para_todas(): void
    {
        $vencida = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 1000]);
        $aVencer = $this->cobranca(['vencimento' => '2026-10-20', 'valor_centavos' => 2000]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$vencida->id, $aVencer->id], 'modo' => 'hoje'])
            ->assertSessionHas('sucesso', '2 cobrança(s) baixada(s).');

        $this->assertSame(['paga', '2026-10-13'], [$vencida->fresh()->status->value, $vencida->fresh()->pago_em->toDateString()]);
        $this->assertSame(['paga', '2026-10-13'], [$aVencer->fresh()->status->value, $aVencer->fresh()->pago_em->toDateString()]);
    }

    /** Vencimento futuro vira hoje (decisão do usuário, 2026-10-12): nunca grava pago_em no futuro. */
    public function test_baixa_em_lote_com_data_de_vencimento_usa_hoje_para_a_que_ainda_nao_venceu(): void
    {
        $vencida = $this->cobranca(['vencimento' => '2026-10-01']);
        $aVencer = $this->cobranca(['vencimento' => '2026-10-20']);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$vencida->id, $aVencer->id], 'modo' => 'vencimento']);

        $this->assertSame('2026-10-01', $vencida->fresh()->pago_em->toDateString());
        $this->assertSame('2026-10-13', $aVencer->fresh()->pago_em->toDateString());
    }

    public function test_baixa_em_lote_ignora_as_que_nao_estao_pendentes_e_avisa(): void
    {
        $pendente = $this->cobranca();
        $paga = $this->cobranca();
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01'])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$pendente->id, $paga->id], 'modo' => 'hoje'])
            ->assertSessionHas('sucesso', '1 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame('2026-10-01', $paga->fresh()->pago_em->toDateString());
    }

    public function test_baixa_em_lote_registra_auditoria_por_cobranca(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 1000]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 2000]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento']);

        $registros = $this->usuario->auditorias()->orderBy('alvo_id')->get(['acao', 'alvo_id']);
        $this->assertSame([['acao' => 'cobranca.paga', 'alvo_id' => $a->id], ['acao' => 'cobranca.paga', 'alvo_id' => $b->id]],
            $registros->map(fn ($r) => ['acao' => $r->acao, 'alvo_id' => $r->alvo_id])->all());
    }

    public function test_cancelar_em_lote_so_as_pendentes_e_registra_auditoria(): void
    {
        $pendente = $this->cobranca();
        $cancelada = $this->cobranca();
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/cancelar', ['ids' => [$pendente->id, $cancelada->id]])
            ->assertSessionHas('sucesso', '1 cobrança(s) cancelada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame(StatusCobranca::Cancelada, $pendente->fresh()->status);
        $this->assertSame('cobranca.cancelada', $this->usuario->auditorias()->sole()->acao);
    }

    public function test_so_cobrancas_do_proprio_usuario(): void
    {
        $alheia = Cobranca::factory()->create();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$alheia->id], 'modo' => 'hoje'])
            ->assertSessionHas('sucesso', '0 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame(StatusCobranca::Pendente, $alheia->fresh()->status);
    }

    public function test_pagar_e_marcar_enviada_registram_auditoria(): void
    {
        $cobranca = $this->cobranca(['vencimento' => '2026-10-01']);
        $this->actingAs($this->usuario);

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-13']);
        $this->post("/cobrancas/{$cobranca->id}/enviada");

        $acoes = $this->usuario->auditorias()->orderBy('id')->pluck('acao')->all();
        $this->assertSame(['cobranca.paga', 'cobranca.enviada'], $acoes);
    }
}
