<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FiltroSituacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $nova = fn (string $vencimento, int $valor, ?StatusCobranca $status = null) => tap(
            Cobranca::factory()->for($this->usuario)->for($cliente)->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]),
            function (Cobranca $cobranca) use ($status) {
                if ($status) {
                    $cobranca->status = $status;
                    $cobranca->pago_em = $status === StatusCobranca::Paga ? '2026-10-15' : null;
                    $cobranca->save();
                }
            },
        );

        $nova('2026-09-20', 100);                          // vencida no mês passado
        $nova('2026-10-05', 200);                          // vencida neste mês
        $nova('2026-10-15', 300);                          // vence hoje
        $nova('2026-10-15', 400, StatusCobranca::Paga);    // venceria hoje, mas já foi paga
        $nova('2026-10-15', 500, StatusCobranca::Cancelada);
        $nova('2026-10-28', 600);                          // a vencer neste mês
        $nova('2026-11-10', 700);                          // a vencer no mês que vem
        Cobranca::factory()->create(['vencimento' => '2026-10-15', 'valor_centavos' => 999]); // de outro usuário
    }

    /** @return list<string> */
    private function valores(string $url): array
    {
        $cobrancas = $this->actingAs($this->usuario)->get($url)->assertOk()->inertiaProps('cobrancas');

        return array_map(fn (array $cobranca) => $cobranca['valor'], $cobrancas);
    }

    public function test_vencendo_hoje_e_vencidas_valem_para_qualquer_mes(): void
    {
        $this->assertSame(['3,00'], $this->valores('/cobrancas?status=vence_hoje'));
        $this->assertSame(['3,00'], $this->valores('/cobrancas?status=vence_hoje&mes=2026-08'));
        $this->assertSame(['1,00', '2,00'], $this->valores('/cobrancas?status=vencida'));
        $this->assertSame(['1,00', '2,00'], $this->valores('/cobrancas?status=vencida&mes=2026-12'));
    }

    public function test_a_vencer_respeita_o_mes(): void
    {
        $this->assertSame(['6,00'], $this->valores('/cobrancas?status=a_vencer'));
        $this->assertSame(['7,00'], $this->valores('/cobrancas?status=a_vencer&mes=2026-11'));
    }

    public function test_filtros_antigos_continuam(): void
    {
        $this->assertSame(['2,00', '3,00', '6,00'], $this->valores('/cobrancas?status=pendente'));
        $this->assertSame(['4,00'], $this->valores('/cobrancas?status=paga'));
        $this->get('/cobrancas?status=talvez')->assertSessionHasErrors('status');
    }
}
