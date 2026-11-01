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

    /** Fase 26: o percentual vale para todas, mas cada cobrança arredonda a sua (a soma gravada é a da prévia). */
    public function test_lote_aplica_percentual_em_cada_cobranca(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'hoje', 'desconto_percentual' => '10'])
            ->assertSessionHas('sucesso', '2 cobrança(s) baixada(s).');

        $this->assertSame([1000, 2476], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos]);
        $this->assertSame([9001, 22281], [$a->fresh()->valorPagoCentavos(), $b->fresh()->valorPagoCentavos()]);
        $this->assertSame(3476, $this->usuario->cobrancas()->sum('desconto_centavos'));
    }

    public function test_lote_com_percentual_so_desconta_nas_pendentes_selecionadas(): void
    {
        $pendente = $this->cobranca(['valor_centavos' => 10000]);
        $naoSelecionada = $this->cobranca(['valor_centavos' => 10000]);
        $paga = $this->cobranca(['valor_centavos' => 10000]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01', 'desconto_centavos' => 300])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$pendente->id, $paga->id], 'modo' => 'hoje', 'desconto_percentual' => '50'])
            ->assertSessionHas('sucesso', '1 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame([5000, 0, 300], [$pendente->fresh()->desconto_centavos, $naoSelecionada->fresh()->desconto_centavos, $paga->fresh()->desconto_centavos]);
        $this->assertSame(StatusCobranca::Pendente, $naoSelecionada->fresh()->status);
    }

    public function test_lote_sem_percentual_continua_sem_desconto(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 1000]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 2000]);
        $c = $this->cobranca(['vencimento' => '2026-10-03', 'valor_centavos' => 3000]);

        $this->actingAs($this->usuario);
        $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id], 'modo' => 'vencimento'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$b->id], 'modo' => 'vencimento', 'desconto_percentual' => ''])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id], 'modo' => 'vencimento', 'desconto_percentual' => '0'])->assertSessionHasNoErrors();

        $this->assertSame([0, 0, 0], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos, $c->fresh()->desconto_centavos]);
        $this->assertSame(
            ['Baixa em lote (R$ 10,00, paga em 01/10/2026)', 'Baixa em lote (R$ 20,00, paga em 02/10/2026)', 'Baixa em lote (R$ 30,00, paga em 03/10/2026)'],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('id')->pluck('resumo')->all(),
        );
    }

    public function test_lote_percentual_acima_de_100_ou_invalido_e_recusado(): void
    {
        $cobranca = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        foreach (['100,01', '101', '150', '1000', '-5', 'abc', '10,555', '10%'] as $percentual) {
            $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_percentual' => $percentual])
                ->assertSessionHasErrors('desconto_percentual');
        }

        // Pedido montado fora da tela: o percentual como lista é erro de validação, não 500.
        $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_percentual' => ['10']])
            ->assertSessionHasErrors('desconto_percentual');

        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);
        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
    }

    public function test_lote_aceita_100_por_cento_e_virgula_ou_ponto(): void
    {
        $a = $this->cobranca(['valor_centavos' => 10000]);
        $b = $this->cobranca(['valor_centavos' => 10000]);
        $c = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id], 'modo' => 'hoje', 'desconto_percentual' => '100'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$b->id], 'modo' => 'hoje', 'desconto_percentual' => '12,5'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id], 'modo' => 'hoje', 'desconto_percentual' => '12.5'])->assertSessionHasNoErrors();

        $this->assertSame([10000, 1250, 1250], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos, $c->fresh()->desconto_centavos]);
    }

    public function test_lote_audita_o_desconto_de_cada_cobranca(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento', 'desconto_percentual' => '10']);

        $this->assertSame(
            [
                'Baixa em lote (R$ 100,01, desconto 10% = R$ 10,00, pago R$ 90,01, em 01/10/2026)',
                'Baixa em lote (R$ 247,57, desconto 10% = R$ 24,76, pago R$ 222,81, em 02/10/2026)',
            ],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('alvo_id')->pluck('resumo')->all(),
        );
    }

    public function test_lote_com_100_por_cento_audita_como_desconto_integral(): void
    {
        $cobranca = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'vencimento', 'desconto_percentual' => '100']);

        $this->assertSame(
            'Baixa em lote com desconto integral (100% = R$ 247,57, pago R$ 0,00, em 01/10/2026)',
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->sole()->resumo,
        );
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
