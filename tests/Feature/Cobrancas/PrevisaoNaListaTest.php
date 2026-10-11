<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrevisaoNaListaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        // Antecedência 10: abrir a lista gera 21/09 e 21/10; de novembro em diante só há previstas.
        $this->usuario = User::factory()->create();
        $this->contrato = Contrato::factory()->for($this->usuario)->create(); // desde 21/09, dia 21, R$ 260,60
    }

    /** @return list<array{0: string, 1: string}> vencimento e situação de cada linha */
    private function linhas(string $consulta): array
    {
        $this->actingAs($this->usuario)->get('/cobrancas'.$consulta)->assertOk(); // a página gera as cobranças devidas

        return array_map(
            fn (array $linha) => [$linha['vencimento'], $linha['situacao']],
            $this->getJson('/cobrancas/dados'.$consulta)->assertOk()->json('linhas'),
        );
    }

    public function test_ate_comeca_igual_ao_mes(): void
    {
        $this->actingAs($this->usuario);

        $this->assertSame(['2026-10', '2026-10'], [
            $this->get('/cobrancas')->inertiaProps('filtros.mes'),
            $this->get('/cobrancas')->inertiaProps('filtros.ate'),
        ]);
        $this->assertSame('2026-11', $this->get('/cobrancas?mes=2026-11')->inertiaProps('filtros.ate'));
        // Um mês só: 21/10 já é cobrança de verdade e não sobra prevista em outubro.
        $this->assertSame([['21/10/2026', 'a_vencer']], $this->linhas('?mes=2026-10'));
    }

    public function test_previstas_entram_mescladas_por_vencimento(): void
    {
        Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(['vencimento' => '2026-11-10']);

        $this->assertSame([
            ['21/10/2026', 'a_vencer'],
            ['10/11/2026', 'a_vencer'],
            ['23/11/2026', 'prevista'], // 21/11 é sábado (Fase 23)
            ['21/12/2026', 'prevista'],
        ], $this->linhas('?mes=2026-10&ate=2026-12'));
    }

    public function test_previstas_so_em_todas_a_vencer_e_pendentes(): void
    {
        $comPrevistas = [['21/10/2026', 'a_vencer'], ['23/11/2026', 'prevista']];

        $this->assertSame($comPrevistas, $this->linhas('?mes=2026-10&ate=2026-11&status=a_vencer'));
        $this->assertSame($comPrevistas, $this->linhas('?mes=2026-10&ate=2026-11&status=pendente'));
        foreach (['paga', 'cancelada', 'vencida', 'vence_hoje'] as $status) {
            $this->assertNotContains('prevista', array_column($this->linhas("?mes=2026-10&ate=2026-11&status={$status}"), 1), $status);
        }
    }

    public function test_filtro_de_cliente_vale_para_as_previstas(): void
    {
        $outro = Cliente::factory()->for($this->usuario)->create();
        Contrato::factory()->for($this->usuario)->for($outro)->create(['dia_vencimento' => 5, 'data_inicio' => '2026-09-05']);

        $this->assertSame([['23/11/2026', 'prevista']], $this->linhas("?mes=2026-11&cliente_id={$this->contrato->cliente_id}"));
        $this->assertSame([['05/11/2026', 'prevista']], $this->linhas("?mes=2026-11&cliente_id={$outro->id}"));
    }

    public function test_so_previstas_depois_de_hoje(): void
    {
        // Perfil incompleto: a geração não cria nada. Mesmo assim, 21/09 (já passou) não aparece como prevista.
        $this->usuario = User::factory()->create(['cep' => null]);
        Contrato::factory()->for($this->usuario)->create();

        $this->assertSame([['21/10/2026', 'prevista']], $this->linhas('?mes=2026-09&ate=2026-10'));
    }

    public function test_ate_antes_do_mes_ou_mais_de_36_meses_e_recusado_no_json_sem_derrubar_a_pagina(): void
    {
        $this->actingAs($this->usuario);

        // Link com período ruim abre a tela; o erro vem do JSON e aparece embaixo do "Até".
        $this->get('/cobrancas?mes=2026-10&ate=2026-09')->assertOk()->assertInertia(fn ($page) => $page->where('filtros.ate', '2026-09'));
        $this->getJson('/cobrancas/dados?mes=2026-10&ate=2026-09')->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->getJson('/cobrancas/dados?mes=2026-01&ate=2029-01')->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/cobrancas?ate=outubro')->assertSessionHasErrors('ate');
        $this->getJson('/cobrancas/dados?mes=2026-01&ate=2028-12')->assertOk();
    }

    public function test_previsao_nao_grava_nada(): void
    {
        $this->actingAs($this->usuario)->get('/cobrancas')->assertOk(); // a geração normal acontece aqui
        $antes = [Cobranca::count(), Recibo::count()];

        $linhas = $this->linhas('?mes=2026-10&ate=2029-09');

        $this->assertCount(36, $linhas); // 21/10/2026 de verdade + 35 previstas
        $this->assertSame($antes, [Cobranca::count(), Recibo::count()]);
    }
}
