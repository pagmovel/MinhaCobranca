<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
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
        $this->actingAs($this->usuario)->get($url)->assertOk(); // a página gera as cobranças devidas
        $cobrancas = $this->getJson(str_replace('/cobrancas', '/cobrancas/dados', $url))->assertOk()->json('linhas');

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

    /**
     * "Próximos N dias": pendentes de amanhã (16/10) até hoje + N, de qualquer mês, com as bordas (7 e 15 dias) dentro e
     * o dia seguinte à borda fora; hoje, pagas e canceladas ficam de fora.
     */
    public function test_proximos_dias_pegam_so_as_pendentes_da_janela_em_qualquer_mes(): void
    {
        $cliente = $this->usuario->clientes()->firstOrFail();
        foreach ([['2026-10-16', 800], ['2026-10-22', 900], ['2026-10-23', 1100], ['2026-10-30', 1200], ['2026-10-31', 1300]] as [$vencimento, $valor]) {
            Cobranca::factory()->for($this->usuario)->for($cliente)->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        }
        Cobranca::factory()->for($this->usuario)->for($cliente)->create(['vencimento' => '2026-10-17', 'valor_centavos' => 1400, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-15']);

        foreach (['', '&mes=2026-08', '&mes=2026-12'] as $periodo) {
            $this->assertSame(['8,00', '9,00'], $this->valores('/cobrancas?status=proximos_7'.$periodo), "7 dias{$periodo}");
            $this->assertSame(['8,00', '9,00', '11,00', '6,00', '12,00'], $this->valores('/cobrancas?status=proximos_15'.$periodo), "15 dias{$periodo}");
        }
        $abas = $this->getJson('/cobrancas/dados?status=vencida')->assertOk()->json('abas');
        $this->assertContains(['chave' => 'proximos_7', 'quantidade' => 2, 'soma_centavos' => 1700], $abas);
        $this->assertContains(['chave' => 'proximos_15', 'quantidade' => 5, 'soma_centavos' => 4600], $abas);
    }

    public function test_todas_deixa_canceladas_de_fora_salvo_quando_pedidas(): void
    {
        $this->assertSame(['2,00', '3,00', '4,00', '6,00'], $this->valores('/cobrancas'));
        $this->assertSame(['2,00', '3,00', '4,00', '5,00', '6,00'], $this->valores('/cobrancas?canceladas=1'));
        $this->assertSame(['5,00'], $this->valores('/cobrancas?status=cancelada'));
        $this->assertTrue($this->get('/cobrancas?canceladas=1')->inertiaProps('filtros.canceladas'));
        $this->assertFalse($this->get('/cobrancas')->inertiaProps('filtros.canceladas'));
    }

    public function test_filtros_antigos_continuam(): void
    {
        $this->assertSame(['2,00', '3,00', '6,00'], $this->valores('/cobrancas?status=pendente'));
        $this->assertSame(['4,00'], $this->valores('/cobrancas?status=paga'));
        $this->get('/cobrancas?status=talvez')->assertSessionHasErrors('status');
    }

    /**
     * T17.16: as abas somam vencida (1,00+2,00) + vence_hoje (3,00) + a_vencer (6,00, só este mês) + paga (4,00);
     * cancelada (5,00) fica fora de "todas", como no rodapé.
     */
    public function test_abas_mostram_contagem_e_soma_do_conjunto_inteiro(): void
    {
        $esperado = [
            ['chave' => 'todas', 'quantidade' => 5, 'soma_centavos' => 1600],
            ['chave' => 'vencida', 'quantidade' => 2, 'soma_centavos' => 300],
            ['chave' => 'vence_hoje', 'quantidade' => 1, 'soma_centavos' => 300],
            ['chave' => 'proximos_7', 'quantidade' => 0, 'soma_centavos' => 0],
            ['chave' => 'proximos_15', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'a_vencer', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'paga', 'quantidade' => 1, 'soma_centavos' => 400],
        ];
        $abas = fn (string $consulta) => $this->actingAs($this->usuario)->getJson('/cobrancas/dados'.$consulta)->assertOk()->json('abas');

        $this->assertSame($esperado, $abas(''));
        // Estar numa aba (ex.: só vencidas na listagem) não pode "zerar" a contagem das outras.
        $this->assertSame($esperado, $abas('?status=vencida'));
        $this->assertSame($esperado, $abas('?status=paga'));
    }

    /**
     * Fase 26: "Pagas" soma o valor pago (valor − desconto) na aba, no total da situação e no rodapé; as outras
     * situações seguem com o valor cheio, e a paga sem desconto sai igual a antes.
     */
    public function test_pagas_somam_o_valor_pago_e_as_demais_o_valor_cheio(): void
    {
        $this->usuario->cobrancas()->where('status', StatusCobranca::Paga)->firstOrFail()
            ->forceFill(['desconto_centavos' => 100])->save(); // 4,00 com 1,00 de desconto: pago 3,00
        $semDesconto = Cobranca::factory()->for($this->usuario)->for($this->usuario->clientes()->firstOrFail())
            ->create(['vencimento' => '2026-10-20', 'valor_centavos' => 500]);
        $semDesconto->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-16'])->save();

        $dados = $this->actingAs($this->usuario)->getJson('/cobrancas/dados')->assertOk();

        $dados->assertJsonPath('abas', [
            ['chave' => 'todas', 'quantidade' => 6, 'soma_centavos' => 2000],
            ['chave' => 'vencida', 'quantidade' => 2, 'soma_centavos' => 300],
            ['chave' => 'vence_hoje', 'quantidade' => 1, 'soma_centavos' => 300],
            ['chave' => 'proximos_7', 'quantidade' => 0, 'soma_centavos' => 0],
            ['chave' => 'proximos_15', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'a_vencer', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'paga', 'quantidade' => 2, 'soma_centavos' => 800],
        ]);
        // Sem filtro de situação a lista é só do mês: a vencida de setembro (1,00) não entra nos totais, só nas abas.
        $dados->assertJsonPath('totais', [
            ['situacao' => 'vencida', 'quantidade' => 1, 'soma_centavos' => 200],
            ['situacao' => 'vence_hoje', 'quantidade' => 1, 'soma_centavos' => 300],
            ['situacao' => 'a_vencer', 'quantidade' => 1, 'soma_centavos' => 600],
            ['situacao' => 'paga', 'quantidade' => 2, 'soma_centavos' => 800],
        ]);
        $dados->assertJsonPath('total_geral', ['quantidade' => 5, 'soma_centavos' => 1900]);
    }

    /**
     * Fase 27: o valor pago soma também multa, juros e outros; a pendente com regra no contrato (que hoje valeria mais
     * ou menos) segue com o valor cheio nas abas, nos totais e no rodapé.
     */
    public function test_pagas_somam_o_valor_pago_com_acrescimos_e_as_pendentes_com_regra_o_valor_cheio(): void
    {
        $cliente = $this->usuario->clientes()->firstOrFail();
        $this->usuario->cobrancas()->where('status', StatusCobranca::Paga)->firstOrFail()
            ->forceFill(['multa_centavos' => 50, 'juros_centavos' => 30, 'outros_centavos' => 20])->save(); // 4,00 + 1,00 = pago 5,00
        $comDesconto = Cobranca::factory()->for($this->usuario)->for($cliente)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 1000]);
        $comDesconto->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-16', 'desconto_centavos' => 200])->save(); // pago 8,00
        $contrato = Contrato::factory()->comCondicoes()->for($this->usuario)->for($cliente)->create(['ativo' => false]);
        Cobranca::factory()->for($this->usuario)->for($cliente)->create(['contrato_id' => $contrato->id, 'vencimento' => '2026-10-05', 'valor_centavos' => 1000]); // vencida: hoje valeria mais

        $dados = $this->actingAs($this->usuario)->getJson('/cobrancas/dados')->assertOk();

        $dados->assertJsonPath('abas', [
            ['chave' => 'todas', 'quantidade' => 7, 'soma_centavos' => 3500],
            ['chave' => 'vencida', 'quantidade' => 3, 'soma_centavos' => 1300],
            ['chave' => 'vence_hoje', 'quantidade' => 1, 'soma_centavos' => 300],
            ['chave' => 'proximos_7', 'quantidade' => 0, 'soma_centavos' => 0],
            ['chave' => 'proximos_15', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'a_vencer', 'quantidade' => 1, 'soma_centavos' => 600],
            ['chave' => 'paga', 'quantidade' => 2, 'soma_centavos' => 1300],
        ]);
        $dados->assertJsonPath('totais', [
            ['situacao' => 'vencida', 'quantidade' => 2, 'soma_centavos' => 1200],
            ['situacao' => 'vence_hoje', 'quantidade' => 1, 'soma_centavos' => 300],
            ['situacao' => 'a_vencer', 'quantidade' => 1, 'soma_centavos' => 600],
            ['situacao' => 'paga', 'quantidade' => 2, 'soma_centavos' => 1300],
        ]);
        $dados->assertJsonPath('total_geral', ['quantidade' => 6, 'soma_centavos' => 3400]);
    }
}
