<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContasAReceberTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pendentes_por_cliente_em_faixas_de_vencimento(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        $ana = Cliente::factory()->for($usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($usuario)->create(['nome' => 'Bruno Mecânica']);
        $pendente = fn (Cliente $cliente, string $vencimento, int $valor) => Cobranca::factory()->for($usuario)->for($cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);

        $pendente($ana, '2026-10-05', 10000); // vencida há 10 dias
        $pendente($ana, '2026-09-15', 5000);  // vencida há 30 dias
        $pendente($ana, '2026-10-15', 2000);  // vence hoje: até 30 dias
        $pendente($ana, '2026-12-01', 3000);  // 47 dias
        $pendente($bruno, '2026-12-20', 1000); // 66 dias
        $pendente($bruno, '2027-01-20', 4000); // 97 dias
        $paga = $pendente($bruno, '2026-10-01', 8000);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-01';
        $paga->save();
        $cancelada = $pendente($bruno, '2026-10-02', 9000);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();
        Cobranca::factory()->create(['vencimento' => '2026-10-01', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($usuario)->get('/relatorios/a-receber')->assertInertia(fn (Assert $page) => $page
            ->has('tabela.linhas', 2)
            ->where('tabela.linhas.0', ['cliente' => 'Ana Contabilidade', 'vencidas' => 15000, 'atraso' => 30, 'ate30' => 2000, 'ate60' => 3000, 'ate90' => 0, 'depois' => 0])
            ->where('tabela.linhas.1', ['cliente' => 'Bruno Mecânica', 'vencidas' => 0, 'atraso' => 0, 'ate30' => 0, 'ate60' => 0, 'ate90' => 1000, 'depois' => 4000])
            ->where('tabela.totais', ['cliente' => 'Total', 'vencidas' => 15000, 'atraso' => null, 'ate30' => 2000, 'ate60' => 3000, 'ate90' => 1000, 'depois' => 4000]));
    }
}
