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

    private function cobranca(string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false, int $desconto = 0, int $multa = 0, int $juros = 0, int $outros = 0): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        if ($pagoEm !== null) {
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
            $cobranca->desconto_centavos = $desconto;
            $cobranca->multa_centavos = $multa;
            $cobranca->juros_centavos = $juros;
            $cobranca->outros_centavos = $outros;
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

        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2026-08&ate=2026-11')->assertOk()
            ->assertJsonCount(4, 'linhas')
            ->assertJsonPath('linhas.0', ['mes' => '08/2026', 'previsto' => 10000, 'nao_emitido' => 0, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 10000])
            ->assertJsonPath('linhas.1', ['mes' => '09/2026', 'previsto' => 20000, 'nao_emitido' => 0, 'recebido' => 10000, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 20000])
            ->assertJsonPath('linhas.2', ['mes' => '10/2026', 'previsto' => 30000, 'nao_emitido' => 0, 'recebido' => 70000, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 20000])
            ->assertJsonPath('linhas.3', ['mes' => '11/2026', 'previsto' => 40000, 'nao_emitido' => 0, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('totais', ['mes' => 'Total', 'previsto' => 100000, 'nao_emitido' => 0, 'recebido' => 80000, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => null]);
    }

    public function test_previsto_inclui_o_que_os_contratos_ainda_vao_emitir(): void
    {
        // Desde 21/09, dia 21, R$ 260,60, nada gerado (o relatório não gera): 21/09 já passou e fica de fora.
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $this->cobranca('2026-11-10', 10000);

        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2026-09&ate=2026-11')->assertOk()
            ->assertJsonPath('linhas.0', ['mes' => '09/2026', 'previsto' => 0, 'nao_emitido' => 0, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('linhas.1', ['mes' => '10/2026', 'previsto' => 26060, 'nao_emitido' => 26060, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('linhas.2', ['mes' => '11/2026', 'previsto' => 36060, 'nao_emitido' => 26060, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('totais.nao_emitido', 52120)
            ->assertJsonPath('colunas.2.rotulo', 'Ainda não emitido');
    }

    /** Fase 26: recebido é o valor pago e os descontos aparecem ao lado; previsto e atraso seguem com o valor cheio. */
    public function test_recebido_e_o_valor_pago_e_os_descontos_ficam_em_coluna_propria(): void
    {
        $this->cobranca('2026-08-10', 24757, '2026-09-05', desconto: 2476); // paga com atraso, em setembro: pago 222,81
        $this->cobranca('2026-10-10', 10000, '2026-10-10', desconto: 10000); // desconto integral: recebido 0,00
        $this->cobranca('2026-10-12', 5000, '2026-10-12');                   // sem desconto, como antes da fase
        $this->cobranca('2026-07-20', 7000, '2026-07-25', desconto: 700);    // paga em julho, antes do período: não entra

        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2026-08&ate=2026-10')->assertOk()
            ->assertJsonPath('colunas.4', ['chave' => 'descontos', 'rotulo' => 'Descontos', 'tipo' => 'dinheiro', 'soma' => true])
            ->assertJsonPath('colunas.5', ['chave' => 'acrescimos', 'rotulo' => 'Acréscimos', 'tipo' => 'dinheiro', 'soma' => true])
            ->assertJsonPath('colunas.6.chave', 'inadimplencia')
            ->assertJsonPath('linhas.0', ['mes' => '08/2026', 'previsto' => 24757, 'nao_emitido' => 0, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 24757])
            ->assertJsonPath('linhas.1', ['mes' => '09/2026', 'previsto' => 0, 'nao_emitido' => 0, 'recebido' => 22281, 'descontos' => 2476, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('linhas.2', ['mes' => '10/2026', 'previsto' => 15000, 'nao_emitido' => 0, 'recebido' => 5000, 'descontos' => 10000, 'acrescimos' => 0, 'inadimplencia' => 0])
            ->assertJsonPath('totais', ['mes' => 'Total', 'previsto' => 39757, 'nao_emitido' => 0, 'recebido' => 27281, 'descontos' => 12476, 'acrescimos' => 0, 'inadimplencia' => null]);
    }

    /**
     * Fase 27: o recebido é o valor pago, com multa, juros e outros; a coluna Acréscimos soma só esses três; previsto e
     * inadimplência seguem com o valor cheio (a paga com acréscimos continua devendo o valor cheio no fim de agosto).
     */
    public function test_recebido_inclui_os_acrescimos_e_a_coluna_acrescimos_vem_depois_dos_descontos(): void
    {
        $this->cobranca('2026-08-10', 24757, '2026-09-05', multa: 495, juros: 1234, outros: 500);  // paga em setembro: pago 269,86
        $this->cobranca('2026-09-10', 8000);                                                        // pendente: não entra no recebido
        $this->cobranca('2026-10-10', 10000, '2026-10-12', desconto: 1000, multa: 200);             // desconto e acréscimo juntos: pago 92,00
        $this->cobranca('2026-10-12', 5000, '2026-10-12');                                          // sem nada, como antes
        $this->cobranca('2026-07-20', 7000, '2026-07-25', multa: 140, juros: 90, outros: 70);       // paga em julho, antes do período: não entra

        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2026-08&ate=2026-10')->assertOk()
            ->assertJsonPath('colunas.4.chave', 'descontos')
            ->assertJsonPath('colunas.5', ['chave' => 'acrescimos', 'rotulo' => 'Acréscimos', 'tipo' => 'dinheiro', 'soma' => true])
            ->assertJsonPath('colunas.6.chave', 'inadimplencia')
            ->assertJsonPath('linhas.0', ['mes' => '08/2026', 'previsto' => 24757, 'nao_emitido' => 0, 'recebido' => 0, 'descontos' => 0, 'acrescimos' => 0, 'inadimplencia' => 24757])
            ->assertJsonPath('linhas.1', ['mes' => '09/2026', 'previsto' => 8000, 'nao_emitido' => 0, 'recebido' => 26986, 'descontos' => 0, 'acrescimos' => 2229, 'inadimplencia' => 8000])
            ->assertJsonPath('linhas.2', ['mes' => '10/2026', 'previsto' => 15000, 'nao_emitido' => 0, 'recebido' => 14200, 'descontos' => 1000, 'acrescimos' => 200, 'inadimplencia' => 8000])
            ->assertJsonPath('totais', ['mes' => 'Total', 'previsto' => 47757, 'nao_emitido' => 0, 'recebido' => 41186, 'descontos' => 1000, 'acrescimos' => 2429, 'inadimplencia' => null]);
    }

    /** Pedido do Marcos (2026-11-01): "outros" vale em qualquer data; pago no vencimento, entra no Recebido e em Acréscimos. */
    public function test_outros_pago_em_dia_entra_no_recebido_e_em_acrescimos(): void
    {
        $cobranca = $this->cobranca('2026-10-10', 10000);
        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-10', 'outros' => '5,00'])->assertSessionHas('sucesso');

        $this->getJson('/relatorios/faturamento/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonPath('linhas.0', ['mes' => '10/2026', 'previsto' => 10000, 'nao_emitido' => 0, 'recebido' => 10500, 'descontos' => 0, 'acrescimos' => 500, 'inadimplencia' => 0]);
    }

    public function test_periodo_padrao_e_os_ultimos_12_meses(): void
    {
        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados')->assertOk()
            ->assertJsonCount(12, 'linhas')
            ->assertJsonPath('linhas.0.mes', '11/2025')
            ->assertJsonPath('linhas.11.mes', '10/2026')
            ->assertJsonPath('filtros', ['de' => '2025-11', 'ate' => '2026-10', 'pagina' => 1, 'por_pagina' => 25]);
    }

    public function test_periodo_invalido_e_tipo_desconhecido(): void
    {
        $this->actingAs($this->usuario);

        $this->getJson('/relatorios/faturamento/dados?de=2026-10&ate=2026-01')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->getJson('/relatorios/faturamento/dados?de=2020-01&ate=2026-10')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/relatorios/faturamento?de=outubro')->assertSessionHasErrors('de');
        $this->get('/relatorios/inexistente')->assertNotFound();
        $this->getJson('/relatorios/inexistente/dados')->assertNotFound();
    }

    public function test_faturamento_nao_pagina_e_a_pagina_abre_sem_a_tabela(): void
    {
        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2024-05&ate=2026-10&por_pagina=25')->assertOk()
            ->assertJsonCount(30, 'linhas')
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 30, 'total' => 30]);

        $this->get('/relatorios/faturamento?de=2026-01&ate=2026-10')->assertInertia(fn (Assert $page) => $page
            ->component('Relatorios/Ver')
            ->missing('tabela')
            ->where('filtros', ['de' => '2026-01', 'ate' => '2026-10', 'pagina' => 1, 'por_pagina' => 25]));
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
            ."Mês;Previsto;\"Ainda não emitido\";Recebido;Descontos;Acréscimos;\"Em atraso no fim do mês\"\n"
            ."10/2026;1.234,56;0,00;1.234,56;0,00;0,00;0,00\n"
            ."Total;1.234,56;0,00;1.234,56;0,00;0,00;\n",
            $resposta->streamedContent(),
        );
    }

    /** Fase 27: o CSV acompanha a coluna Acréscimos e o recebido com multa, juros e outros. */
    public function test_csv_com_acrescimos_byte_a_byte(): void
    {
        $this->cobranca('2026-10-01', 100000, '2026-10-10', desconto: 5000, multa: 2000, juros: 1500, outros: 500); // pago 1.000,00 − 50,00 + 40,00 = 990,00

        $resposta = $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-10&ate=2026-10&formato=csv');

        $this->assertSame(
            "\xEF\xBB\xBF"
            ."Mês;Previsto;\"Ainda não emitido\";Recebido;Descontos;Acréscimos;\"Em atraso no fim do mês\"\n"
            ."10/2026;1.000,00;0,00;990,00;50,00;40,00;0,00\n"
            ."Total;1.000,00;0,00;990,00;50,00;40,00;\n",
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
