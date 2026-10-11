<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceitaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
    }

    private function cobranca(Cliente $cliente, string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)
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

    public function test_receita_por_cliente(): void
    {
        $ana = Cliente::factory()->for($this->usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($this->usuario)->create(['nome' => 'Bruno Mecânica']);
        $carla = Cliente::factory()->for($this->usuario)->create(['nome' => 'Carla Antiga']);
        Contrato::factory()->for($this->usuario)->for($ana)->create();
        Contrato::factory()->for($this->usuario)->for($ana)->create(['ativo' => false]);
        $this->cobranca($ana, '2026-09-10', 10000, '2026-09-10');
        $this->cobranca($ana, '2026-10-10', 30000, '2026-10-11');
        $this->cobranca($bruno, '2026-10-05', 4000); // 10 dias de atraso
        $this->cobranca($bruno, '2026-09-25', 6000); // 20 dias de atraso
        $this->cobranca($carla, '2025-05-10', 9000, '2025-05-10'); // paga antes do período: fora
        Cobranca::factory()->create(['vencimento' => '2026-09-01', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($this->usuario)->get('/relatorios/clientes?de=2026-01&ate=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 2)
                ->where('tabela.linhas.0', ['cliente' => 'Ana Contabilidade', 'recebido' => 40000, 'contratos' => 1, 'media' => 20000, 'vencidas' => 0, 'atraso' => 0])
                ->where('tabela.linhas.1', ['cliente' => 'Bruno Mecânica', 'recebido' => 0, 'contratos' => 0, 'media' => 0, 'vencidas' => 2, 'atraso' => 15])
                ->where('tabela.totais', ['cliente' => 'Total', 'recebido' => 40000, 'contratos' => 1, 'media' => null, 'vencidas' => 2, 'atraso' => null]));
    }

    public function test_receita_por_produto_bate_com_o_total_pago(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        $suporte = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Suporte']);
        foreach ([true, true, false] as $ativo) {
            Contrato::factory()->for($this->usuario)->for($cliente)->create(['ativo' => $ativo])->itens()->create([
                'produto_servico_id' => $hospedagem->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 10000, 'principal' => true, 'ordem' => 0,
            ]);
        }
        $comItens = function (string $vencimento, ?string $pagoEm, array $itens, bool $cancelada = false) use ($cliente) {
            $cobranca = $this->cobranca($cliente, $vencimento, array_sum(array_column($itens, 1)), $pagoEm, $cancelada);
            foreach ($itens as [$produto, $total]) {
                $cobranca->itens()->create([
                    'produto_servico_id' => $produto->id, 'nome' => $produto->nome, 'unidade' => 'mês', 'quantidade' => '1.000',
                    'preco_unitario_centavos' => $total, 'total_centavos' => $total, 'principal' => true,
                ]);
            }
        };
        $comItens('2026-10-10', '2026-10-10', [[$hospedagem, 10000], [$suporte, 5000]]); // paga no período
        $comItens('2026-10-20', null, [[$hospedagem, 20000]]);                            // vence no período
        $comItens('2026-10-12', null, [[$suporte, 7000]], cancelada: true);               // cancelada: fora
        $this->cobranca($cliente, '2026-10-01', 7000, '2026-10-02');                      // anterior ao catálogo

        $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-10&ate=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 3)
                // Previsto: 30.000 emitidos + 2 previstas de 21/10 (contratos ativos, ainda não emitidas).
                ->where('tabela.linhas.0', ['item' => 'Hospedagem', 'recebido' => 10000, 'previsto' => 50000, 'contratos' => 2])
                ->where('tabela.linhas.1', ['item' => 'Suporte', 'recebido' => 5000, 'previsto' => 5000, 'contratos' => 0])
                ->where('tabela.linhas.2', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 7000, 'previsto' => 7000, 'contratos' => 0])
                ->where('tabela.totais.recebido', 22000));

        // Bate com a lista de cobranças: tudo o que foi pago no período.
        $this->assertSame(22000, (int) $this->usuario->cobrancas()->where('status', 'paga')->sum('valor_centavos'));
    }

    public function test_previsto_por_produto_inclui_as_previstas(): void
    {
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        Contrato::factory()->for($this->usuario)->create()->itens()->create([
            'produto_servico_id' => $hospedagem->id, 'quantidade' => '2.000', 'preco_unitario_centavos' => 5000, 'principal' => true, 'ordem' => 0,
        ]);
        Contrato::factory()->for($this->usuario)->create(); // sem itens, R$ 260,60

        $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-11&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 2)
                ->where('tabela.linhas.0', ['item' => 'Hospedagem', 'recebido' => 0, 'previsto' => 10000, 'contratos' => 1])
                ->where('tabela.linhas.1', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 0, 'previsto' => 26060, 'contratos' => 0]));
    }

    public function test_csv_neutraliza_formula_no_nome_do_cliente(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create(['nome' => '=HYPERLINK("http://golpe.example")']);
        $this->cobranca($cliente, '2026-10-10', 10000, '2026-10-10');

        $csv = $this->actingAs($this->usuario)->get('/relatorios/clientes?de=2026-10&ate=2026-10&formato=csv')->streamedContent();

        $this->assertStringContainsString("\n\"'=HYPERLINK(\"\"http://golpe.example\"\")\";100,00;", $csv);
    }
}
