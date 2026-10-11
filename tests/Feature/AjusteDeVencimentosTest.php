<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\AjusteDeVencimentos;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AjusteDeVencimentosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->contrato = Contrato::factory()->for($this->usuario)->create();
    }

    /** Cobrança do contrato com recibo, gravada com o vencimento dado (como as geradas antes da regra nova). */
    private function cobranca(string $vencimento, array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(array_merge([
            'contrato_id' => $this->contrato->id,
            'vencimento' => $vencimento,
            'periodo_inicio' => $vencimento,
            'periodo_fim' => CarbonImmutable::parse($vencimento)->addMonthNoOverflow()->subDay()->toDateString(),
        ], $extra));
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca;
    }

    public function test_previa_lista_so_as_de_contrato_pendentes_e_nao_enviadas_em_dia_nao_util(): void
    {
        $sabado = $this->cobranca('2026-10-24', ['forma_pagamento' => 'boleto', 'linha_digitavel' => str_repeat('1', 47)]);
        $this->cobranca('2026-10-26'); // segunda: dia útil
        $this->cobranca('2026-10-31')->forceFill(['enviado_em' => now()])->save(); // sábado, enviada
        $this->cobranca('2026-11-07')->forceFill(['status' => 'paga', 'pago_em' => '2026-10-15'])->save(); // sábado, paga
        $this->cobranca('2026-11-14')->forceFill(['status' => 'cancelada'])->save(); // sábado, cancelada
        Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(['vencimento' => '2026-11-21']); // avulsa (D2)

        $this->actingAs($this->usuario)->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('vencimentosParaAjustar', 1)
            ->where('vencimentosParaAjustar.0', [
                'id' => $sabado->id,
                'cliente' => $this->contrato->cliente->exibicao(),
                'vencimento' => '24/10/2026',
                'motivo' => 'sábado',
                'novo_vencimento' => 'segunda, 26/10/2026',
                'tem_boleto' => true,
            ]));
    }

    public function test_ajusta_so_as_escolhidas_que_ainda_precisam_com_o_mesmo_numero_de_recibo(): void
    {
        $sabado = $this->cobranca('2026-10-24');
        $domingo = $this->cobranca('2026-11-01');
        $enviada = $this->cobranca('2026-10-31');
        $enviada->forceFill(['enviado_em' => now()])->save();
        $alheia = Cobranca::factory()->create(['vencimento' => '2026-10-24']);
        $numero = $sabado->recibo->numero;

        $this->actingAs($this->usuario)
            ->post('/perfil/feriados/ajustar', ['ids' => [$sabado->id, $enviada->id, $alheia->id]])
            ->assertSessionHas('sucesso', '1 cobrança ajustada.');

        $sabado = $sabado->fresh()->load('recibo');
        $this->assertSame(
            ['2026-10-26', '2026-10-26', $numero, '2026-10-24'],
            [$sabado->vencimento->toDateString(), $sabado->recibo->snapshot['servico']['vencimento'], $sabado->recibo->numero, $sabado->periodo_inicio->toDateString()],
        );
        $this->assertSame('2026-11-01', $domingo->fresh()->vencimento->toDateString(), 'fora da escolha: não muda');
        $this->assertSame('2026-10-31', $enviada->fresh()->vencimento->toDateString());
        $this->assertSame('2026-10-24', $alheia->fresh()->vencimento->toDateString());
    }

    public function test_feriado_local_novo_reaparece_na_previa_e_ajustar_de_novo_nao_muda_nada(): void
    {
        $quarta = $this->cobranca('2026-10-21');
        $this->actingAs($this->usuario)->get('/perfil')->assertInertia(fn (Assert $page) => $page->has('vencimentosParaAjustar', 0));

        $this->post('/perfil/feriados', ['descricao' => 'Padroeira', 'tipo' => 'anual', 'dia' => '21', 'mes' => '10']);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->where('vencimentosParaAjustar.0.motivo', 'Padroeira')
            ->where('vencimentosParaAjustar.0.novo_vencimento', 'quinta, 22/10/2026'));

        $this->post('/perfil/feriados/ajustar', ['ids' => [$quarta->id]])->assertSessionHas('sucesso', '1 cobrança ajustada.');
        $this->post('/perfil/feriados/ajustar', ['ids' => [$quarta->id]])->assertSessionHas('sucesso', 'Nenhuma cobrança precisava de ajuste.');
        $this->assertSame('2026-10-22', $quarta->fresh()->vencimento->toDateString());
    }

    public function test_cobranca_de_contrato_de_outro_usuario_nao_aparece_na_previa_nem_muda(): void
    {
        // De contrato, pendente, não enviada e num sábado: só não entra porque é de outro prestador.
        $outroContrato = Contrato::factory()->create();
        $alheia = Cobranca::factory()->for($outroContrato->user)->for($outroContrato->cliente)->create([
            'contrato_id' => $outroContrato->id, 'vencimento' => '2026-10-24', 'periodo_inicio' => '2026-10-24', 'periodo_fim' => '2026-11-23',
        ]);

        $this->actingAs($this->usuario)->get('/perfil')->assertInertia(fn (Assert $page) => $page->has('vencimentosParaAjustar', 0));
        $this->post('/perfil/feriados/ajustar', ['ids' => [$alheia->id]])
            ->assertSessionHas('sucesso', 'Nenhuma cobrança precisava de ajuste.');

        $this->assertSame('2026-10-24', $alheia->fresh()->vencimento->toDateString());
    }

    public function test_ajuste_registra_cada_cobranca_na_auditoria(): void
    {
        $sabado = $this->cobranca('2026-10-24');

        $this->actingAs($this->usuario)->post('/perfil/feriados/ajustar', ['ids' => [$sabado->id]])->assertSessionHas('sucesso');

        $registro = $this->usuario->auditorias()->sole();
        $this->assertSame(
            ['cobranca.vencimento_ajustado', 'cobranca', $sabado->id, 'Vencimento ajustado de 24/10/2026 (sábado) para 26/10/2026', ['vencimento' => '2026-10-24'], ['vencimento' => '2026-10-26']],
            [$registro->acao, $registro->alvo_tipo, $registro->alvo_id, $registro->resumo, $registro->antes, $registro->depois],
        );
    }

    public function test_cada_cobranca_a_mais_no_ajuste_custa_no_maximo_quatro_consultas(): void
    {
        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        $this->actingAs($this->usuario);

        $uma = $this->cobranca('2026-10-24');
        $consultas = 0;
        $this->post('/perfil/feriados/ajustar', ['ids' => [$uma->id]])->assertSessionHas('sucesso', '1 cobrança ajustada.');
        $comUma = $consultas;

        $tres = [$this->cobranca('2026-10-31'), $this->cobranca('2026-11-07'), $this->cobranca('2026-11-14')];
        $consultas = 0;
        $this->post('/perfil/feriados/ajustar', ['ids' => array_map(fn (Cobranca $cobranca) => $cobranca->id, $tres)])
            ->assertSessionHas('sucesso', '3 cobranças ajustadas.');

        // Atualizar a cobrança, gravar a auditoria, ler os itens e gravar o recibo; prestador e cliente uma vez por lote.
        $this->assertLessThanOrEqual(2 * 4, $consultas - $comUma);
    }

    public function test_painel_avisa_quantas_cobrancas_em_aberto_vencem_em_dia_nao_util(): void
    {
        $this->actingAs($this->usuario)->get('/painel')->assertInertia(fn (Assert $page) => $page->where('vencimentosEmDiaNaoUtil', 0));

        $this->cobranca('2026-10-24');
        $this->cobranca('2026-10-31');

        $this->get('/painel')->assertInertia(fn (Assert $page) => $page->where('vencimentosEmDiaNaoUtil', 2));
    }

    public function test_quantas_bate_com_a_previa_sem_montar_cliente_e_recibo(): void
    {
        $this->cobranca('2026-10-24', ['forma_pagamento' => 'boleto', 'linha_digitavel' => str_repeat('1', 47)]);
        $this->cobranca('2026-10-31');
        $this->cobranca('2026-10-26'); // segunda: dia útil, não entra

        $ajuste = app(AjusteDeVencimentos::class);

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        $quantas = $ajuste->quantas($this->usuario);
        $consultasDeQuantas = $consultas;

        $consultas = 0;
        $previa = $ajuste->previa($this->usuario);
        $consultasDaPrevia = $consultas;

        $this->assertSame(2, $quantas);
        $this->assertSame(count($previa), $quantas);
        $this->assertLessThan($consultasDaPrevia, $consultasDeQuantas, 'quantas() não deve montar cliente/recibo só para contar');
    }

    public function test_pedido_sem_ids_ou_com_id_nao_numerico_e_recusado(): void
    {
        $this->actingAs($this->usuario)->post('/perfil/feriados/ajustar', [])->assertSessionHasErrors('ids');
        $this->post('/perfil/feriados/ajustar', ['ids' => ['abc']])->assertSessionHasErrors('ids.0');
    }
}
