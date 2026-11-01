<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '10'])
            ->assertSessionHas('sucesso', '2 cobrança(s) baixada(s).');

        $this->assertSame([1000, 2476], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos]);
        $this->assertSame([9001, 22281], [$a->fresh()->valorPagoCentavos(), $b->fresh()->valorPagoCentavos()]);
        $this->assertSame(3476, $this->usuario->cobrancas()->sum('desconto_centavos'));
    }

    /** Tarefa 3b: em R$ o mesmo valor sai de cada cobrança (a soma gravada é a da prévia). */
    public function test_lote_aplica_o_mesmo_valor_em_reais_em_cada_cobranca(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento', 'desconto_tipo' => 'valor', 'desconto' => '20,00'])
            ->assertSessionHas('sucesso', '2 cobrança(s) baixada(s).');

        $this->assertSame([2000, 2000], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos]);
        $this->assertSame([8001, 22757], [$a->fresh()->valorPagoCentavos(), $b->fresh()->valorPagoCentavos()]);
        $this->assertSame(4000, $this->usuario->cobrancas()->sum('desconto_centavos'));
        $this->assertSame(
            [
                'Baixa em lote (R$ 100,01, desconto R$ 20,00, pago R$ 80,01, em 01/10/2026)',
                'Baixa em lote (R$ 247,57, desconto R$ 20,00, pago R$ 227,57, em 02/10/2026)',
            ],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('alvo_id')->pluck('resumo')->all(),
        );
    }

    /** O limite em R$ é a menor cobrança do lote: nenhuma fica negativa, e o lote recusado não grava nada. */
    public function test_lote_com_reais_acima_da_menor_pendente_e_recusado_sem_gravar_nada(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => '100,02'])
            ->assertSessionHasErrors(['desconto' => 'O desconto não pode passar do valor da menor cobrança (R$ 100,01).']);

        foreach ([$a, $b] as $cobranca) {
            $this->assertSame([StatusCobranca::Pendente, null, 0], [$cobranca->fresh()->status, $cobranca->fresh()->pago_em, $cobranca->fresh()->desconto_centavos]);
        }
        $this->assertSame(0, $this->usuario->auditorias()->count());
    }

    public function test_lote_com_reais_igual_a_menor_e_aceito_e_a_menor_fica_com_pago_zero(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento', 'desconto_tipo' => 'valor', 'desconto' => '100,01'])
            ->assertSessionHasNoErrors();

        $this->assertSame([0, 14756], [$a->fresh()->valorPagoCentavos(), $b->fresh()->valorPagoCentavos()]);
        $this->assertSame(
            [
                'Baixa em lote com desconto integral (100% = R$ 100,01, pago R$ 0,00, em 01/10/2026)',
                'Baixa em lote (R$ 247,57, desconto R$ 100,01, pago R$ 147,56, em 02/10/2026)',
            ],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('alvo_id')->pluck('resumo')->all(),
        );
    }

    /** A paga ou cancelada selecionada não entra na conta do limite nem recebe desconto. */
    public function test_limite_em_reais_ignora_a_selecionada_que_nao_esta_pendente(): void
    {
        $pendenteA = $this->cobranca(['valor_centavos' => 10001]);
        $pendenteB = $this->cobranca(['valor_centavos' => 24757]);
        $paga = $this->cobranca(['valor_centavos' => 1500]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01', 'desconto_centavos' => 300])->save();
        $cancelada = $this->cobranca(['valor_centavos' => 500]);
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$pendenteA->id, $pendenteB->id, $paga->id, $cancelada->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => '20'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', '2 cobrança(s) baixada(s). 2 ignorada(s) (não estavam pendentes).');

        $this->assertSame([2000, 2000, 300, 0], [$pendenteA->fresh()->desconto_centavos, $pendenteB->fresh()->desconto_centavos, $paga->fresh()->desconto_centavos, $cancelada->fresh()->desconto_centavos]);
        $this->assertSame('2026-10-01', $paga->fresh()->pago_em->toDateString());
    }

    /** Selecionadas que não são pendentes não deixam ninguém sem limite: sem pendente, não há o que baixar nem o que limitar. */
    public function test_lote_so_com_nao_pendentes_nao_grava_nem_da_erro_de_limite(): void
    {
        $paga = $this->cobranca(['valor_centavos' => 1500]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01'])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$paga->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => '99'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', '0 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame(0, $paga->fresh()->desconto_centavos);
    }

    public function test_lote_com_desconto_sem_tipo_ou_com_tipo_invalido_e_recusado(): void
    {
        $cobranca = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto' => '10'])
            ->assertSessionHasErrors(['desconto_tipo' => 'Escolha se o desconto é em % ou em R$.']);
        $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'abatimento', 'desconto' => '10'])
            ->assertSessionHasErrors(['desconto_tipo' => 'Tipo de desconto inválido.']);

        $this->assertSame([StatusCobranca::Pendente, 0], [$cobranca->fresh()->status, $cobranca->fresh()->desconto_centavos]);
    }

    public function test_lote_com_reais_invalido_e_recusado_sem_gravar_nada(): void
    {
        $cobranca = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        foreach (['abc', '-5', '1e3', '+5', '1,234,56', '10%'] as $entrada) {
            $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => $entrada])
                ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        }

        // Pedido montado fora da tela: o desconto como lista é erro de validação, não 500.
        $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => ['10']])
            ->assertSessionHasErrors('desconto');

        $this->assertSame([StatusCobranca::Pendente, 0], [$cobranca->fresh()->status, $cobranca->fresh()->desconto_centavos]);
    }

    public function test_lote_com_percentual_so_desconta_nas_pendentes_selecionadas(): void
    {
        $pendente = $this->cobranca(['valor_centavos' => 10000]);
        $naoSelecionada = $this->cobranca(['valor_centavos' => 10000]);
        $paga = $this->cobranca(['valor_centavos' => 10000]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01', 'desconto_centavos' => 300])->save();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$pendente->id, $paga->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '50'])
            ->assertSessionHas('sucesso', '1 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame([5000, 0, 300], [$pendente->fresh()->desconto_centavos, $naoSelecionada->fresh()->desconto_centavos, $paga->fresh()->desconto_centavos]);
        $this->assertSame(StatusCobranca::Pendente, $naoSelecionada->fresh()->status);
    }

    public function test_lote_sem_desconto_continua_sem_desconto(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 1000]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 2000]);
        $c = $this->cobranca(['vencimento' => '2026-10-03', 'valor_centavos' => 3000]);
        $d = $this->cobranca(['vencimento' => '2026-10-04', 'valor_centavos' => 4000]);

        $this->actingAs($this->usuario);
        $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id], 'modo' => 'vencimento'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$b->id], 'modo' => 'vencimento', 'desconto_tipo' => 'percentual', 'desconto' => ''])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id], 'modo' => 'vencimento', 'desconto_tipo' => 'percentual', 'desconto' => '0'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$d->id], 'modo' => 'vencimento', 'desconto_tipo' => 'valor', 'desconto' => ''])->assertSessionHasNoErrors();

        $this->assertSame([0, 0, 0, 0], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos, $c->fresh()->desconto_centavos, $d->fresh()->desconto_centavos]);
        $this->assertSame(
            ['Baixa em lote (R$ 10,00, paga em 01/10/2026)', 'Baixa em lote (R$ 20,00, paga em 02/10/2026)', 'Baixa em lote (R$ 30,00, paga em 03/10/2026)', 'Baixa em lote (R$ 40,00, paga em 04/10/2026)'],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('id')->pluck('resumo')->all(),
        );
    }

    public function test_lote_percentual_acima_de_100_ou_invalido_e_recusado(): void
    {
        $cobranca = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        foreach (['100,01', '101', '150'] as $percentual) {
            $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => $percentual])
                ->assertSessionHasErrors(['desconto' => 'Percentual inválido: use de 0 a 100, com até 2 casas decimais.']);
        }

        foreach (['1000', '-5', 'abc', '10,555', '10%'] as $percentual) {
            $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => $percentual])
                ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        }

        // Pedido montado fora da tela: o percentual como lista é erro de validação, não 500.
        $this->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => ['10']])
            ->assertSessionHasErrors('desconto');

        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);
        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
    }

    public function test_lote_aceita_100_por_cento_e_virgula_ou_ponto(): void
    {
        $a = $this->cobranca(['valor_centavos' => 10000]);
        $b = $this->cobranca(['valor_centavos' => 10000]);
        $c = $this->cobranca(['valor_centavos' => 10000]);
        $this->actingAs($this->usuario);

        $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '100'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$b->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '12,5'])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '12.5'])->assertSessionHasNoErrors();

        $this->assertSame([10000, 1250, 1250], [$a->fresh()->desconto_centavos, $b->fresh()->desconto_centavos, $c->fresh()->desconto_centavos]);
    }

    public function test_lote_audita_o_desconto_de_cada_cobranca(): void
    {
        $a = $this->cobranca(['vencimento' => '2026-10-01', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-10-02', 'valor_centavos' => 24757]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento', 'desconto_tipo' => 'percentual', 'desconto' => '10']);

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

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'vencimento', 'desconto_tipo' => 'percentual', 'desconto' => '100']);

        $this->assertSame(
            'Baixa em lote com desconto integral (100% = R$ 247,57, pago R$ 0,00, em 01/10/2026)',
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->sole()->resumo,
        );
    }

    /**
     * Fase 27, Tarefa 4. Cenário: o relógio em 01/11/2026 e cobranças vencidas em 05/01/2026 (300 dias de atraso).
     * Contrato A (cliente A): desconto 15%, multa 2%, juros 1% ao mês. Contrato B (outro cliente): multa R$ 5,00 e juros R$ 3,00 ao mês.
     *
     * @return array{0: Cobranca, 1: Cobranca} R$ 210,00 do contrato A e R$ 100,00 do contrato B
     */
    private function cenarioDeAtraso(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $contratoA = Contrato::factory()->comCondicoes()->create(['user_id' => $this->usuario->id]);
        $contratoB = Contrato::factory()->create(['user_id' => $this->usuario->id, 'multa_tipo' => 'valor', 'multa_valor' => 500, 'juros_tipo' => 'valor', 'juros_valor' => 300]);

        return [$this->cobrancaDoContrato($contratoA, 21000), $this->cobrancaDoContrato($contratoB, 10000)];
    }

    private function cobrancaDoContrato(Contrato $contrato, int $valor, string $vencimento = '2026-01-05'): Cobranca
    {
        return Cobranca::factory()->create([
            'user_id' => $this->usuario->id,
            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'periodo_inicio' => $vencimento, // o índice único é (contrato, início do período)
            'vencimento' => $vencimento,
            'valor_centavos' => $valor,
        ]);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} desconto, multa, juros e outros gravados */
    private function gravados(Cobranca $cobranca): array
    {
        $cobranca->refresh();

        return [$cobranca->desconto_centavos, $cobranca->multa_centavos, $cobranca->juros_centavos, $cobranca->outros_centavos];
    }

    /** @return array<string, array{0: array<string, string>}> o que a tela manda com os campos em branco, e o pedido sem eles */
    public static function camposEmBranco(): array
    {
        return [
            'ausentes' => [[]],
            'em branco' => [['desconto_tipo' => 'percentual', 'desconto' => '', 'multa_tipo' => 'percentual', 'multa' => '', 'juros_tipo' => 'percentual', 'juros' => '', 'outros' => '']],
        ];
    }

    /** @param  array<string, string>  $campos */
    #[DataProvider('camposEmBranco')]
    public function test_em_branco_usa_o_contrato_de_cada_cobranca(array $campos): void
    {
        [$a, $b] = $this->cenarioDeAtraso();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'hoje'] + $campos)
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso', '2 cobrança(s) baixada(s).');

        $this->assertSame([0, 420, 2100, 0], $this->gravados($a));
        $this->assertSame([0, 500, 3000, 0], $this->gravados($b));
        $this->assertSame([23520, 13500], [$a->valorPagoCentavos(), $b->valorPagoCentavos()]);
    }

    public function test_preenchido_vale_para_todas(): void
    {
        [$a, $b] = $this->cenarioDeAtraso();
        $avulsa = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 5000]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', [
            'ids' => [$a->id, $b->id, $avulsa->id], 'modo' => 'hoje',
            'desconto_tipo' => 'percentual', 'desconto' => '10',
            'multa_tipo' => 'percentual', 'multa' => '3',
            'juros_tipo' => 'valor', 'juros' => '1,00',
            'outros' => '5,00',
        ])->assertSessionHasNoErrors();

        // O digitado vence o contrato (a multa de 2% do A, o juros de R$ 3,00 do B) e vale até para a que não tem contrato.
        $this->assertSame([2100, 630, 1000, 500], $this->gravados($a));
        $this->assertSame([1000, 300, 1000, 500], $this->gravados($b));
        $this->assertSame([500, 150, 1000, 500], $this->gravados($avulsa));
    }

    /** Percentual é calculado em cada cobrança (arredondado por ela); R$ é o mesmo valor em todas. */
    public function test_percentual_calculado_por_cobranca_e_reais_igual_em_cada(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $a = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 10001]);
        $b = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 24757]);
        $c = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 10001]);
        $d = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 24757]);
        $this->actingAs($this->usuario);

        $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'hoje', 'multa_tipo' => 'percentual', 'multa' => '10', 'juros_tipo' => 'percentual', 'juros' => '1'])
            ->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id, $d->id], 'modo' => 'hoje', 'multa_tipo' => 'valor', 'multa' => '5,00'])
            ->assertSessionHasNoErrors();

        $this->assertSame([[0, 1000, 1000, 0], [0, 2476, 2476, 0]], [$this->gravados($a), $this->gravados($b)]);
        $this->assertSame([[0, 500, 0, 0], [0, 500, 0, 0]], [$this->gravados($c), $this->gravados($d)]);
        $this->assertSame(3476, $a->multa_centavos + $b->multa_centavos);
    }

    /** Zero digitado não é em branco: zera a regra do contrato em todas (inclusive o desconto da que ainda vai vencer). */
    public function test_zero_zera_em_todas(): void
    {
        [$vencida] = $this->cenarioDeAtraso();
        $contrato = $vencida->contrato;
        $aVencer = $this->cobrancaDoContrato($contrato, 21000, '2026-12-05');
        $controle = $this->cobrancaDoContrato($contrato, 21000, '2026-12-06');
        $this->actingAs($this->usuario);

        $this->post('/cobrancas/lote/pagar', ['ids' => [$vencida->id, $aVencer->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => '0', 'multa_tipo' => 'percentual', 'multa' => '0', 'juros_tipo' => 'valor', 'juros' => '0', 'outros' => '0'])
            ->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$controle->id], 'modo' => 'hoje', 'desconto_tipo' => 'percentual', 'desconto' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame([[0, 0, 0, 0], [0, 0, 0, 0]], [$this->gravados($vencida), $this->gravados($aVencer)]);
        $this->assertSame([3150, 0, 0, 0], $this->gravados($controle));
    }

    public function test_so_cobranca_em_atraso_recebe_acrescimo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $vencida = $this->cobranca(['vencimento' => '2026-01-05', 'valor_centavos' => 21000]);
        $venceHoje = $this->cobranca(['vencimento' => '2026-11-01', 'valor_centavos' => 21000]);
        $aVencer = $this->cobranca(['vencimento' => '2026-12-05', 'valor_centavos' => 21000]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', [
            'ids' => [$vencida->id, $venceHoje->id, $aVencer->id], 'modo' => 'hoje',
            'multa_tipo' => 'valor', 'multa' => '5,00', 'juros_tipo' => 'valor', 'juros' => '3,00', 'outros' => '5,00',
        ])->assertSessionHasNoErrors();

        $this->assertSame([0, 500, 3000, 500], $this->gravados($vencida));
        $this->assertSame([[0, 0, 0, 0], [0, 0, 0, 0]], [$this->gravados($venceHoje), $this->gravados($aVencer)]);
        $this->assertSame([21000, 21000], [$venceHoje->valorPagoCentavos(), $aVencer->valorPagoCentavos()]);
    }

    /** Ponto 5 da revisão: com "usar a data de vencimento" a vencida é baixada no vencimento, sem multa nem juros (nem os digitados). */
    public function test_modo_vencimento_nao_gera_acrescimo(): void
    {
        [$a, $b] = $this->cenarioDeAtraso();

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => 'vencimento'])->assertSessionHasNoErrors();
        $this->assertSame('2026-01-05', $a->fresh()->pago_em->toDateString());
        $this->assertSame([3150, 0, 0, 0], $this->gravados($a));
        $this->assertSame([0, 0, 0, 0], $this->gravados($b));

        [$c] = $this->cenarioDeAtraso();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$c->id], 'modo' => 'vencimento', 'multa_tipo' => 'percentual', 'multa' => '5', 'juros_tipo' => 'percentual', 'juros' => '5', 'outros' => '9,00'])
            ->assertSessionHasNoErrors();
        $this->assertSame([3150, 0, 0, 0], $this->gravados($c));
    }

    /** Ponto 5 da revisão: a que ainda vai vencer é baixada com a data de hoje e recebe o desconto do contrato. */
    public function test_modo_vencimento_a_vencer_recebe_desconto_do_contrato(): void
    {
        [$vencida] = $this->cenarioDeAtraso();
        $aVencer = $this->cobrancaDoContrato($vencida->contrato, 21000, '2026-12-05');

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$vencida->id, $aVencer->id], 'modo' => 'vencimento'])
            ->assertSessionHasNoErrors();

        $aVencer->refresh();
        $vencida->refresh();
        $this->assertSame(['2026-11-01', 3150, 17850], [$aVencer->pago_em->toDateString(), $aVencer->desconto_centavos, $aVencer->valorPagoCentavos()]);
        $this->assertSame(['2026-01-05', 3150, 17850], [$vencida->pago_em->toDateString(), $vencida->desconto_centavos, $vencida->valorPagoCentavos()]);
    }

    /** @return array<string, array{0: array<string, string>, 1: string, 2: string}> campos, o campo do erro e a mensagem */
    public static function acrescimosInvalidos(): array
    {
        $percentual = 'Percentual inválido: use de 0 a 100, com até 2 casas decimais.';
        $reais = 'Valor inválido: use reais, como 15,00, de no máximo R$ 99.999.999,99.';

        return [
            'multa acima de 100%' => [['multa_tipo' => 'percentual', 'multa' => '150'], 'multa', $percentual],
            'multa com 3 casas' => [['multa_tipo' => 'percentual', 'multa' => '2,555'], 'multa', $percentual],
            'multa em reais com texto' => [['multa_tipo' => 'valor', 'multa' => 'abc'], 'multa', $reais],
            'multa em reais acima do teto' => [['multa_tipo' => 'valor', 'multa' => '100.000.000,00'], 'multa', $reais],
            'juros acima de 100%' => [['juros_tipo' => 'percentual', 'juros' => '101'], 'juros', $percentual],
            'juros em reais negativo' => [['juros_tipo' => 'valor', 'juros' => '-5'], 'juros', $reais],
            'outros com texto' => [['outros' => 'abc'], 'outros', $reais],
            'multa sem tipo' => [['multa' => '2'], 'multa_tipo', 'Escolha se a multa é em % ou em R$.'],
            'juros com tipo inválido' => [['juros_tipo' => 'taxa', 'juros' => '2'], 'juros_tipo', 'Tipo de juros inválido.'],
        ];
    }

    /**
     * Qualquer valor inválido recusa o lote inteiro, mesmo ao lado de um valor bom, e até com a data de vencimento (sem atraso):
     * nada é baixado nem auditado.
     *
     * @param  array<string, string>  $invalido
     */
    #[DataProvider('acrescimosInvalidos')]
    public function test_multa_invalida_recusa_o_lote_sem_gravar_nada(array $invalido, string $campo, string $mensagem): void
    {
        [$a, $b] = $this->cenarioDeAtraso();
        $this->actingAs($this->usuario);

        foreach (['hoje', 'vencimento'] as $modo) {
            $this->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id], 'modo' => $modo, 'desconto_tipo' => 'percentual', 'desconto' => '5'] + $invalido)
                ->assertSessionHasErrors([$campo => $mensagem]);
        }

        foreach ([$a, $b] as $cobranca) {
            $this->assertSame([StatusCobranca::Pendente, null, 0, 0, 0, 0], [$cobranca->fresh()->status, $cobranca->fresh()->pago_em, ...$this->gravados($cobranca)]);
        }
        $this->assertSame(0, $this->usuario->auditorias()->count());
    }

    public function test_lote_sem_regra_fica_igual(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $semRegra = Contrato::factory()->create(['user_id' => $this->usuario->id]);
        $deContrato = $this->cobrancaDoContrato($semRegra, 1000);
        $avulsa = $this->cobranca(['vencimento' => '2026-01-06', 'valor_centavos' => 2000]);
        $semDados = $this->cobranca(['vencimento' => '2026-01-07', 'valor_centavos' => 3000]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', [
            'ids' => [$deContrato->id, $avulsa->id], 'modo' => 'hoje',
            'desconto_tipo' => 'percentual', 'desconto' => '', 'multa_tipo' => 'percentual', 'multa' => '', 'juros_tipo' => 'percentual', 'juros' => '', 'outros' => '',
        ])->assertSessionHasNoErrors();
        $this->post('/cobrancas/lote/pagar', ['ids' => [$semDados->id], 'modo' => 'hoje'])->assertSessionHasNoErrors();

        foreach ([$deContrato, $avulsa, $semDados] as $cobranca) {
            $this->assertSame([0, 0, 0, 0], $this->gravados($cobranca));
            $this->assertSame($cobranca->valor_centavos, $cobranca->valorPagoCentavos());
        }
        $this->assertSame(
            ['Baixa em lote (R$ 10,00, paga em 01/11/2026)', 'Baixa em lote (R$ 20,00, paga em 01/11/2026)', 'Baixa em lote (R$ 30,00, paga em 01/11/2026)'],
            $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('alvo_id')->pluck('resumo')->all(),
        );
    }

    /** O texto da spec, em cada cobrança do lote: só os acréscimos maiores que zero, o desconto sempre. */
    public function test_auditoria_de_cada_cobranca_do_lote_traz_os_acrescimos(): void
    {
        [$a, $b] = $this->cenarioDeAtraso();
        $semAcrescimo = $this->cobrancaDoContrato(Contrato::factory()->create(['user_id' => $this->usuario->id]), 7000, '2026-12-05');

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id, $semAcrescimo->id], 'modo' => 'hoje', 'outros' => '5,00'])
            ->assertSessionHasNoErrors();

        $resumos = $this->usuario->auditorias()->where('acao', 'cobranca.paga')->orderBy('alvo_id')->pluck('resumo')->all();
        $this->assertSame(
            [
                'Baixa registrada (R$ 210,00, multa R$ 4,20, juros R$ 21,00, outros R$ 5,00, desconto R$ 0,00, pago R$ 240,20, em 01/11/2026)',
                'Baixa registrada (R$ 100,00, multa R$ 5,00, juros R$ 30,00, outros R$ 5,00, desconto R$ 0,00, pago R$ 140,00, em 01/11/2026)',
                'Baixa em lote (R$ 70,00, paga em 01/11/2026)',
            ],
            $resumos,
        );
    }

    /** O desconto em R$ do contrato, numa cobrança de valor menor (proporcional), fica no valor: nunca negativo, e audita como integral. */
    public function test_desconto_do_contrato_em_reais_acima_do_valor_fica_no_valor(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $contrato = Contrato::factory()->create(['user_id' => $this->usuario->id, 'desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade_valor' => 30000]);
        $cobranca = $this->cobrancaDoContrato($contrato, 20000, '2026-12-05');

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$cobranca->id], 'modo' => 'vencimento'])->assertSessionHasNoErrors();

        $this->assertSame([20000, 0, 0, 0], $this->gravados($cobranca));
        $this->assertSame(0, $cobranca->valorPagoCentavos());
        $this->assertSame('Baixa em lote com desconto integral (100% = R$ 200,00, pago R$ 0,00, em 01/11/2026)', $this->usuario->auditorias()->sole()->resumo);
    }

    /** A soma que a prévia da tela mostra (JS: previaDoLote) é a gravada: mesmos números de usarValoresDaBaixa.test.js. */
    public function test_soma_gravada_do_lote_bate_com_a_previa_da_tela(): void
    {
        [$a, $b] = $this->cenarioDeAtraso();
        $c = $this->cobrancaDoContrato($a->contrato, 21000, '2026-12-05');

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$a->id, $b->id, $c->id], 'modo' => 'hoje'])->assertSessionHasNoErrors();

        $this->assertSame([23520, 13500, 17850], [$a->refresh()->valorPagoCentavos(), $b->refresh()->valorPagoCentavos(), $c->refresh()->valorPagoCentavos()]);
        $this->assertSame(54870, $this->usuario->cobrancas()->get()->sum(fn (Cobranca $cobranca) => $cobranca->valorPagoCentavos()));
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

    /** A de outro usuário, menor que o desconto em R$, não entra na conta do limite nem é alterada. */
    public function test_limite_em_reais_ignora_a_cobranca_de_outro_usuario(): void
    {
        $propria = $this->cobranca(['valor_centavos' => 24757]);
        $alheia = Cobranca::factory()->create(['valor_centavos' => 500]);

        $this->actingAs($this->usuario)->post('/cobrancas/lote/pagar', ['ids' => [$propria->id, $alheia->id], 'modo' => 'hoje', 'desconto_tipo' => 'valor', 'desconto' => '20'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', '1 cobrança(s) baixada(s). 1 ignorada(s) (não estavam pendentes).');

        $this->assertSame([2000, 0], [$propria->fresh()->desconto_centavos, $alheia->fresh()->desconto_centavos]);
        $this->assertSame([StatusCobranca::Paga, StatusCobranca::Pendente], [$propria->fresh()->status, $alheia->fresh()->status]);
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
