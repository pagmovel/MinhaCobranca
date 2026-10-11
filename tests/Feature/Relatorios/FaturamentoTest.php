<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaturamentoTest extends TestCase
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

    private function cobranca(string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        if ($pagoEm !== null) {
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
        }
        if ($cancelada) {
            $cobranca->status = StatusCobranca::Cancelada;
        }
        $cobranca->save();

        return $cobranca;
    }

    public function test_previsto_recebido_e_atraso_por_mes(): void
    {
        $this->cobranca('2026-08-10', 10000, '2026-09-05');  // atrasada no fim de agosto; recebida em setembro
        $this->cobranca('2026-09-10', 20000);                // em aberto desde setembro
        $this->cobranca('2026-09-20', 5000, cancelada: true); // cancelada: fora de tudo
        $this->cobranca('2026-10-10', 30000, '2026-10-10');  // paga em dia
        $this->cobranca('2026-11-10', 40000, '2026-10-12');  // de novembro, paga adiantada em outubro
        Cobranca::factory()->create(['vencimento' => '2026-09-10', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-08&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Relatorios/Ver')
                ->where('tipo', 'faturamento')
                ->has('tabela.linhas', 4)
                ->where('tabela.linhas.0', ['mes' => '08/2026', 'previsto' => 10000, 'nao_emitido' => 0, 'recebido' => 0, 'inadimplencia' => 10000])
                ->where('tabela.linhas.1', ['mes' => '09/2026', 'previsto' => 20000, 'nao_emitido' => 0, 'recebido' => 10000, 'inadimplencia' => 20000])
                ->where('tabela.linhas.2', ['mes' => '10/2026', 'previsto' => 30000, 'nao_emitido' => 0, 'recebido' => 70000, 'inadimplencia' => 20000])
                ->where('tabela.linhas.3', ['mes' => '11/2026', 'previsto' => 40000, 'nao_emitido' => 0, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.totais', ['mes' => 'Total', 'previsto' => 100000, 'nao_emitido' => 0, 'recebido' => 80000, 'inadimplencia' => null]));
    }

    public function test_previsto_inclui_o_que_os_contratos_ainda_vao_emitir(): void
    {
        // Desde 21/09, dia 21, R$ 260,60, nada gerado (o relatório não gera): 21/09 já passou e fica de fora.
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $this->cobranca('2026-11-10', 10000);

        $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-09&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tabela.linhas.0', ['mes' => '09/2026', 'previsto' => 0, 'nao_emitido' => 0, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.linhas.1', ['mes' => '10/2026', 'previsto' => 26060, 'nao_emitido' => 26060, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.linhas.2', ['mes' => '11/2026', 'previsto' => 36060, 'nao_emitido' => 26060, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.totais.nao_emitido', 52120)
                ->where('tabela.colunas.2.rotulo', 'Ainda não emitido'));
    }

    public function test_periodo_padrao_e_os_ultimos_12_meses(): void
    {
        $this->actingAs($this->usuario)->get('/relatorios/faturamento')->assertInertia(fn (Assert $page) => $page
            ->has('tabela.linhas', 12)
            ->where('tabela.linhas.0.mes', '11/2025')
            ->where('tabela.linhas.11.mes', '10/2026')
            ->where('filtros', ['de' => '2025-11', 'ate' => '2026-10']));
    }

    public function test_periodo_invalido_e_tipo_desconhecido(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/relatorios/faturamento?de=2026-10&ate=2026-01')
            ->assertSessionHasErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->get('/relatorios/faturamento?de=2020-01&ate=2026-10')
            ->assertSessionHasErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/relatorios/faturamento?de=outubro')->assertSessionHasErrors('de');
        $this->get('/relatorios/inexistente')->assertNotFound();
    }

    public function test_csv_para_o_excel_em_portugues(): void
    {
        $this->cobranca('2026-10-10', 123456, '2026-10-10');

        $resposta = $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-10&ate=2026-10&formato=csv');

        $resposta->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('relatorio-faturamento-2026-10-a-2026-10.csv');
        $this->assertSame(
            "\xEF\xBB\xBF"
            ."Mês;Previsto;\"Ainda não emitido\";Recebido;\"Em atraso no fim do mês\"\n"
            ."10/2026;1.234,56;0,00;1.234,56;0,00\n"
            ."Total;1.234,56;0,00;1.234,56;\n",
            $resposta->streamedContent(),
        );
    }

    public function test_menu_de_relatorios_e_visitante(): void
    {
        $this->actingAs($this->usuario)->get('/relatorios')->assertInertia(fn (Assert $page) => $page->component('Relatorios/Index'));

        auth()->logout();
        $this->get('/relatorios/faturamento')->assertRedirect('/entrar');
    }
}
