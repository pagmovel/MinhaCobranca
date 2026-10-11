<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\Modalidade;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Services\PrevisaoDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrevisaoDeCobrancasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
    }

    /** @return list<array<string, mixed>> */
    private function prever(string $de, string $ate): array
    {
        return app(PrevisaoDeCobrancas::class)->entre($this->usuario, CarbonImmutable::parse($de), CarbonImmutable::parse($ate)->endOfDay());
    }

    /** Mensal pré-pago desde 01/08, vence dia 20, um item de R$ 247,57 (o do exemplo aprovado da T17.9). */
    private function contratoComItem(array $extra = []): Contrato
    {
        $contrato = Contrato::factory()->for($this->usuario)->create(array_merge([
            'data_inicio' => '2026-08-01', 'dia_vencimento' => 20, 'valor_centavos' => 24757,
        ], $extra));
        $produto = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem de site']);
        $contrato->itens()->create([
            'produto_servico_id' => $produto->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 24757, 'principal' => true, 'ordem' => 0,
        ]);

        return $contrato;
    }

    public function test_contrato_sem_fim_preve_cada_vencimento_do_intervalo(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->create(); // desde 21/09, dia 21, R$ 260,60, sem itens

        $previstas = $this->prever('2026-10-01', '2027-01-31');

        $this->assertSame(['21/10/2026', '21/11/2026', '21/12/2026', '21/01/2027'], array_column($previstas, 'vencimento'));
        $this->assertSame([
            'id' => "prevista-{$contrato->id}-2026-10-21",
            'contrato_id' => $contrato->id,
            'cliente_id' => $contrato->cliente_id,
            'cliente' => $contrato->cliente->nome,
            'periodo' => '21/10/2026 a 20/11/2026',
            'vencimento' => '21/10/2026',
            'valor' => '260,60',
            'valor_centavos' => 26060,
            'situacao' => 'prevista',
            'itens' => [],
            'recibo' => null,
            'historico' => [],
            'pago_em' => null,
            'enviado' => false,
            'editavel' => false,
        ], $previstas[0]);
    }

    public function test_periodo_ja_emitido_fica_de_fora_mesmo_cancelado(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->create();
        Cobranca::factory()->for($this->usuario)->for($contrato->cliente)->create([
            'contrato_id' => $contrato->id, 'periodo_inicio' => '2026-11-21', 'periodo_fim' => '2026-12-20',
            'vencimento' => '2026-11-21', 'status' => StatusCobranca::Cancelada,
        ]);

        $this->assertSame(['21/10/2026', '21/12/2026'], array_column($this->prever('2026-10-01', '2026-12-31'), 'vencimento'));
    }

    public function test_respeita_o_cobrar_a_partir_de(): void
    {
        Contrato::factory()->for($this->usuario)->create(['cobrar_a_partir_de' => '2026-12-01']);

        $this->assertSame(['21/12/2026', '21/01/2027'], array_column($this->prever('2026-10-01', '2027-01-31'), 'vencimento'));
    }

    public function test_ultimo_periodo_inteiro_nao_cobrar_ou_proporcional(): void
    {
        $esperados = [
            'inteiro' => [['01/10/2026 a 31/10/2026', 24757, [24757]]],
            'nao_cobrar' => [],
            // Exemplo aprovado (T17.9): 18 de 31 dias de 1 × R$ 247,57 = R$ 143,84 (quantidade 0,581).
            'proporcional' => [['01/10/2026 a 18/10/2026', 14384, [14384]]],
        ];

        foreach ($esperados as $ultimo => $esperado) {
            $this->usuario = User::factory()->create();
            $this->contratoComItem(['data_fim' => '2026-10-18', 'ultimo_periodo' => $ultimo]);

            $previstas = $this->prever('2026-10-01', '2026-12-31');

            $this->assertSame($esperado, array_map(
                fn (array $prevista) => [$prevista['periodo'], $prevista['valor_centavos'], array_column($prevista['itens'], 'total_centavos')],
                $previstas,
            ), $ultimo);
        }
    }

    public function test_pos_pago_vence_depois_do_periodo(): void
    {
        Contrato::factory()->for($this->usuario)->create(['modalidade' => Modalidade::Pospago, 'data_inicio' => '2026-09-01', 'dia_vencimento' => 10]);

        $this->assertSame(
            [['01/09/2026 a 30/09/2026', '10/10/2026'], ['01/10/2026 a 31/10/2026', '10/11/2026']],
            array_map(fn (array $prevista) => [$prevista['periodo'], $prevista['vencimento']], $this->prever('2026-10-01', '2026-11-30')),
        );
    }

    public function test_so_contratos_ativos_do_proprio_usuario(): void
    {
        Contrato::factory()->for($this->usuario)->create(['ativo' => false]);
        Contrato::factory()->create(); // de outro usuário

        $this->assertSame([], $this->prever('2026-10-01', '2026-12-31'));
    }

    public function test_reajuste_muda_o_valor_previsto(): void
    {
        $contrato = $this->contratoComItem();
        $this->assertSame([24757], array_column($this->prever('2026-11-01', '2026-11-30'), 'valor_centavos'));

        $contrato->itens()->update(['preco_unitario_centavos' => 26000]);
        $contrato->update(['valor_centavos' => 26000]);

        $this->assertSame([[26000, [26000]]], array_map(
            fn (array $prevista) => [$prevista['valor_centavos'], array_column($prevista['itens'], 'total_centavos')],
            $this->prever('2026-11-01', '2026-11-30'),
        ));
    }

    public function test_previsao_bate_com_o_que_o_gerador_cria(): void
    {
        $contrato = $this->contratoComItem(['data_inicio' => '2026-10-01', 'data_fim' => '2026-12-18', 'ultimo_periodo' => 'proporcional']);
        $previstas = array_map(
            fn (array $prevista) => [$prevista['periodo'], $prevista['vencimento'], $prevista['valor_centavos']],
            $this->prever('2026-10-01', '2026-12-31'),
        );

        // Anda o relógio até depois do último vencimento e deixa o gerador criar as cobranças de verdade.
        $this->travelTo(CarbonImmutable::parse('2026-12-31 10:00:00'));
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $geradas = $contrato->cobrancas()->orderBy('vencimento')->get()->map(fn (Cobranca $cobranca) => [
            $cobranca->periodo_inicio->format('d/m/Y').' a '.$cobranca->periodo_fim->format('d/m/Y'),
            $cobranca->vencimento->format('d/m/Y'),
            $cobranca->valor_centavos,
        ])->all();

        $this->assertCount(3, $previstas);
        $this->assertSame($previstas, $geradas);
        $this->assertSame([], $this->prever('2026-10-01', '2026-12-31'), 'depois de gerar, nada fica previsto');
    }

    public function test_uma_consulta_so_para_as_cobrancas_existentes_de_todos_os_contratos(): void
    {
        $this->contratoComItem();
        Contrato::factory()->for($this->usuario)->count(2)->create(['data_inicio' => '2026-08-01']);
        $consultas = 0;
        DB::listen(function ($consulta) use (&$consultas) {
            if (str_contains($consulta->sql, 'periodo_inicio')) {
                $consultas++;
            }
        });

        $this->prever('2026-11-01', '2027-01-31');

        $this->assertSame(1, $consultas);
    }
}
