<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProrrogarCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: User, 1: Cliente} */
    private function prestadorComCliente(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    private function cobrancaComRecibo(User $usuario, Cliente $cliente, array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create($extra);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca->fresh('recibo');
    }

    public function test_prorroga_o_vencimento_e_refaz_o_recibo(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $numero = $cobranca->recibo->numero;

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');

        $cobranca->refresh();
        $this->assertSame('2026-10-20', $cobranca->vencimento->toDateString());
        $this->assertSame($numero, $cobranca->recibo->numero);
        $this->assertSame('2026-10-20', $cobranca->recibo->snapshot['servico']['vencimento']);
    }

    public function test_recusa_data_igual_ou_anterior_ao_vencimento_atual(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $this->actingAs($usuario);

        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-10'])->assertSessionHasErrors('vencimento');
        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-09'])->assertSessionHasErrors('vencimento');

        $this->assertSame('2026-10-10', $cobranca->fresh()->vencimento->toDateString());
    }

    public function test_so_pendente_pode_ser_prorrogada(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09']);

        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('erro', 'Só cobranças pendentes podem ter o vencimento prorrogado.');
    }

    public function test_vale_para_cobranca_de_contrato_enviada_ou_nao(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, [
            'contrato_id' => $contrato->id, 'vencimento' => '2026-10-10', 'enviado_em' => now(),
        ]);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');
        $this->assertSame('2026-10-20', $cobranca->fresh()->vencimento->toDateString());
    }

    public function test_grava_auditoria_com_data_antes_e_depois(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20']);

        $registro = $usuario->auditorias()->sole();
        $this->assertSame(
            ['cobranca.vencimento_prorrogado', 'Vencimento prorrogado de 10/10/2026 para 20/10/2026', ['vencimento' => '2026-10-10'], ['vencimento' => '2026-10-20']],
            [$registro->acao, $registro->resumo, $registro->antes, $registro->depois],
        );
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        [$usuario] = $this->prestadorComCliente();
        $alheia = Cobranca::factory()->create(['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$alheia->id}/prorrogar", ['vencimento' => '2026-10-20'])->assertNotFound();
    }

    /** `Cobranca::factory()->create()` sem passar por `EmissorDeRecibos` não gera recibo — o caminho raro, mas previsto na spec. */
    public function test_prorroga_sem_recibo_nao_erra(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create(['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');

        $this->assertSame('2026-10-20', $cobranca->fresh()->vencimento->toDateString());
    }
}
