<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Services\PrevisaoDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VencimentoEmDiaUtilTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_gerador_leva_o_vencimento_ao_proximo_dia_util_e_mantem_o_periodo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00:00'));
        $usuario = User::factory()->create(); // antecedência 10
        $contrato = Contrato::factory()->for($usuario)->create(); // desde 21/09, dia 21

        app(GeradorDeCobrancas::class)->gerarPara($usuario, CarbonImmutable::today());

        $novembro = $contrato->cobrancas()->whereDate('periodo_inicio', '2026-11-21')->with('recibo')->sole();
        $this->assertSame(
            ['2026-11-21', '2026-12-20', '2026-11-23'],
            [$novembro->periodo_inicio->toDateString(), $novembro->periodo_fim->toDateString(), $novembro->vencimento->toDateString()],
        );
        $this->assertSame('2026-11-23', $novembro->recibo->snapshot['servico']['vencimento']);
    }

    public function test_feriado_local_do_usuario_vale_para_a_previsao_e_o_de_outro_usuario_nao(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        Feriado::factory()->for($usuario)->create(['descricao' => 'Padroeira', 'dia' => 21, 'mes' => 12]);
        Feriado::factory()->create(['dia' => 21, 'mes' => 1]); // de outro usuário
        Contrato::factory()->for($usuario)->create();

        $previstas = app(PrevisaoDeCobrancas::class)->entre($usuario, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2027-01-31'));

        // 21/12/2026 (segunda) é feriado local → terça, 22/12; 21/01/2027 (quinta) continua.
        $this->assertSame(['22/12/2026', '21/01/2027'], array_column($previstas, 'vencimento'));
    }

    public function test_proximo_dia_util_para_o_aviso_da_avulsa(): void
    {
        $usuario = User::factory()->create();
        Feriado::factory()->for($usuario)->create(['descricao' => 'Padroeira', 'dia' => 14, 'mes' => 10]);

        $this->getJson('/dias-uteis/proximo?data=2026-10-10')->assertUnauthorized();

        $this->actingAs($usuario);
        $this->getJson('/dias-uteis/proximo?data=2026-10-10')
            ->assertExactJson(['util' => false, 'aviso' => 'Cai num sábado.', 'proximo' => '2026-10-13', 'proximo_rotulo' => 'terça, 13/10']);
        $this->getJson('/dias-uteis/proximo?data=2026-10-14')
            ->assertJson(['util' => false, 'aviso' => 'É feriado: Padroeira.', 'proximo' => '2026-10-15']);
        $this->getJson('/dias-uteis/proximo?data=2026-10-15')
            ->assertJson(['util' => true, 'aviso' => null, 'proximo' => '2026-10-15']);
        $this->getJson('/dias-uteis/proximo?data=72026-10-15')->assertUnprocessable()->assertJsonValidationErrors('data');
    }
}
