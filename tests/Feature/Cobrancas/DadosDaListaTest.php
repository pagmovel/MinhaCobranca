<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DadosDaListaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    /** Cobranças avulsas de outubro, uma por dia a partir de 16/10 (todas "a vencer"), de R$ 1,00, 2,00, 3,00… */
    private function avulsas(int $quantidade): void
    {
        for ($dia = 0; $dia < $quantidade; $dia++) {
            Cobranca::factory()->for($this->usuario)->for($this->cliente)->create([
                'vencimento' => CarbonImmutable::parse('2026-10-16')->addDays(intdiv($dia, 4))->toDateString(),
                'valor_centavos' => ($dia + 1) * 100,
            ]);
        }
    }

    private function dados(string $consulta = ''): TestResponse
    {
        return $this->actingAs($this->usuario)->getJson('/cobrancas/dados'.$consulta);
    }

    public function test_devolve_um_bloco_de_quatro_paginas_a_partir_da_pedida(): void
    {
        $this->avulsas(60);

        $this->dados('?por_pagina=25')->assertOk()
            ->assertJsonCount(60, 'linhas') // 4 páginas de 25 = 100, mas só existem 60
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 25, 'total' => 60]);

        $resposta = $this->dados('?por_pagina=25&pagina=2')->assertOk();
        $resposta->assertJsonCount(35, 'linhas')->assertJsonPath('pagina.inicio', 2);
        $this->assertSame('26,00', $resposta->json('linhas.0.valor'));
    }

    public function test_pagina_alem_da_ultima_devolve_a_ultima(): void
    {
        $this->avulsas(30);

        $this->dados('?por_pagina=25&pagina=9')->assertOk()
            ->assertJsonPath('pagina.inicio', 2)
            ->assertJsonCount(5, 'linhas');
        $this->dados('?por_pagina=25&pagina=1')->assertJsonPath('pagina.inicio', 1);
    }

    public function test_por_pagina_e_pagina_sao_validados(): void
    {
        $this->dados('?por_pagina=30')->assertUnprocessable()->assertJsonValidationErrors('por_pagina');
        $this->dados('?pagina=0')->assertUnprocessable()->assertJsonValidationErrors('pagina');
    }

    public function test_totais_por_situacao_somam_o_resultado_inteiro_sem_canceladas_no_total(): void
    {
        $this->avulsas(30); // R$ 1,00 + … + 30,00 = R$ 465,00, todas a vencer
        $paga = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-05', 'valor_centavos' => 1000]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05'])->save();
        Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-10', 'valor_centavos' => 2000]); // vencida
        $cancelada = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-11', 'valor_centavos' => 4000]);
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();

        $this->dados('?canceladas=1&por_pagina=25')->assertOk()
            ->assertJsonCount(33, 'linhas')
            ->assertJsonPath('totais', [
                ['situacao' => 'vencida', 'quantidade' => 1, 'soma_centavos' => 2000],
                ['situacao' => 'a_vencer', 'quantidade' => 30, 'soma_centavos' => 46500],
                ['situacao' => 'paga', 'quantidade' => 1, 'soma_centavos' => 1000],
                ['situacao' => 'cancelada', 'quantidade' => 1, 'soma_centavos' => 4000],
            ])
            ->assertJsonPath('total_geral', ['quantidade' => 32, 'soma_centavos' => 49500]);

        // A página 2 mostra só uma parte, mas os totais continuam sendo do resultado inteiro.
        $this->dados('?canceladas=1&por_pagina=25&pagina=2')->assertJsonPath('total_geral.soma_centavos', 49500);
    }

    public function test_previstas_entram_mescladas_e_somadas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(); // dia 21, R$ 260,60, nada gerado aqui

        $resposta = $this->dados('?mes=2026-10&ate=2026-12')->assertOk();

        $this->assertSame(['prevista', 'prevista', 'prevista'], array_column($resposta->json('linhas'), 'situacao'));
        $resposta->assertJsonPath('totais', [['situacao' => 'prevista', 'quantidade' => 3, 'soma_centavos' => 78180]]);
    }

    public function test_linha_real_traz_valor_em_centavos_e_filtro_de_cliente_vale(): void
    {
        $this->avulsas(1);
        $outro = Cliente::factory()->for($this->usuario)->create();
        Cobranca::factory()->for($this->usuario)->for($outro)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 999]);

        $this->dados("?cliente_id={$outro->id}")->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.valor_centavos', 999)
            ->assertJsonPath('filtros.cliente_id', $outro->id);
    }

    public function test_so_dados_do_proprio_usuario_e_nada_gravado(): void
    {
        // Antes do actingAs (ele vale até o fim do teste): sem sessão, o JSON não responde.
        $this->getJson('/cobrancas/dados')->assertUnauthorized();
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        Cobranca::factory()->create(['vencimento' => '2026-10-20']); // de outro usuário
        $antes = [Cobranca::count(), Recibo::count()];

        $this->dados('?mes=2026-09&ate=2026-12')->assertOk()->assertJsonPath('pagina.total', 3); // 3 previstas, sem a alheia

        $this->assertSame($antes, [Cobranca::count(), Recibo::count()]);
    }

    public function test_periodo_invalido_responde_422_com_a_mensagem(): void
    {
        $this->dados('?mes=2026-10&ate=2026-09')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->dados('?mes=2026-01&ate=2029-01')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->dados('?ate=outubro')->assertUnprocessable()->assertJsonValidationErrors('ate');
    }
}
