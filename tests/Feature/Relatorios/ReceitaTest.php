<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Support\Dinheiro;
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

    private function cobranca(Cliente $cliente, string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false, int $desconto = 0): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        if ($pagoEm !== null) {
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
            $cobranca->desconto_centavos = $desconto;
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

        $this->actingAs($this->usuario)->getJson('/relatorios/clientes/dados?de=2026-01&ate=2026-10')->assertOk()
            ->assertJsonCount(2, 'linhas')
            ->assertJsonPath('linhas.0', ['cliente' => 'Ana Contabilidade', 'recebido' => 40000, 'contratos' => 1, 'media' => 20000, 'vencidas' => 0, 'atraso' => 0])
            ->assertJsonPath('linhas.1', ['cliente' => 'Bruno Mecânica', 'recebido' => 0, 'contratos' => 0, 'media' => 0, 'vencidas' => 2, 'atraso' => 15])
            ->assertJsonPath('totais', ['cliente' => 'Total', 'recebido' => 40000, 'contratos' => 1, 'media' => null, 'vencidas' => 2, 'atraso' => null]);
    }

    /** Fase 26: recebido e média por cobrança paga usam o valor pago (valor − desconto). */
    public function test_receita_por_cliente_usa_o_valor_pago(): void
    {
        $ana = Cliente::factory()->for($this->usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($this->usuario)->create(['nome' => 'Bruno Mecânica']);
        $this->cobranca($ana, '2026-10-05', 10000, '2026-10-05', desconto: 1000);  // pago 90,00
        $this->cobranca($ana, '2026-10-10', 30000, '2026-10-11');                  // sem desconto: 300,00
        $this->cobranca($ana, '2026-09-10', 8000, '2026-09-12', desconto: 800);    // paga antes do período: fora
        $this->cobranca($bruno, '2026-10-06', 5000, '2026-10-06', desconto: 5000); // desconto integral: 0,00

        $this->actingAs($this->usuario)->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonCount(2, 'linhas')
            // Média: (9.000 + 30.000) / 2 = 19.500; com o valor cheio daria 20.000.
            ->assertJsonPath('linhas.0', ['cliente' => 'Ana Contabilidade', 'recebido' => 39000, 'contratos' => 0, 'media' => 19500, 'vencidas' => 0, 'atraso' => 0])
            ->assertJsonPath('linhas.1', ['cliente' => 'Bruno Mecânica', 'recebido' => 0, 'contratos' => 0, 'media' => 0, 'vencidas' => 0, 'atraso' => 0])
            ->assertJsonPath('totais.recebido', 39000);
    }

    /**
     * Pagas no mês de outubro, a Hospedagem e o Suporte numa cobrança (pago 135,00 de 150,00), uma cobrança sem itens
     * (pago 63,00 de 70,00) e, fora do período, uma paga em setembro com desconto e uma pendente.
     */
    private function cenarioComDescontosPorProduto(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        $suporte = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Suporte']);
        $comItens = function (string $vencimento, ?string $pagoEm, int $desconto, array $itens) use ($cliente) {
            $cobranca = $this->cobranca($cliente, $vencimento, array_sum(array_column($itens, 1)), $pagoEm, desconto: $desconto);
            foreach ($itens as [$produto, $total]) {
                $cobranca->itens()->create([
                    'produto_servico_id' => $produto->id, 'nome' => $produto->nome, 'unidade' => 'mês', 'quantidade' => '1.000',
                    'preco_unitario_centavos' => $total, 'total_centavos' => $total, 'principal' => true,
                ]);
            }
        };
        $comItens('2026-10-10', '2026-10-10', 1500, [[$hospedagem, 10000], [$suporte, 5000]]); // paga no período
        $this->cobranca($cliente, '2026-10-01', 7000, '2026-10-02', desconto: 700);             // sem itens
        $comItens('2026-09-15', '2026-09-20', 900, [[$hospedagem, 9000]]);                      // paga em setembro: fora
        $comItens('2026-10-20', null, 0, [[$hospedagem, 20000]]);                               // pendente: vence no período
    }

    public function test_receita_por_produto_mostra_os_descontos_e_o_total_bate_com_o_painel(): void
    {
        $this->cenarioComDescontosPorProduto();

        $resposta = $this->actingAs($this->usuario)->getJson('/relatorios/produtos/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonCount(4, 'linhas')
            // Os itens e "Sem item" ficam com o valor cheio; a linha negativa tira o que foi abatido nas baixas do período.
            ->assertJsonPath('linhas.0', ['item' => 'Hospedagem', 'recebido' => 10000, 'previsto' => 30000, 'contratos' => 0])
            ->assertJsonPath('linhas.1', ['item' => 'Suporte', 'recebido' => 5000, 'previsto' => 5000, 'contratos' => 0])
            ->assertJsonPath('linhas.2', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 7000, 'previsto' => 7000, 'contratos' => 0])
            ->assertJsonPath('linhas.3', ['item' => 'Descontos concedidos', 'recebido' => -2200, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('totais.recebido', 19800)
            ->assertJsonPath('totais.previsto', 42000);

        $this->get('/painel')->assertInertia(fn (Assert $page) => $page
            ->where('resumo.recebido', Dinheiro::formatar($resposta->json('totais.recebido'))));
    }

    public function test_linha_de_descontos_so_aparece_com_desconto_no_periodo(): void
    {
        $this->cenarioComDescontosPorProduto();
        $this->actingAs($this->usuario);

        // Em setembro só houve a baixa com desconto de 9,00; em agosto não houve nada.
        $this->getJson('/relatorios/produtos/dados?de=2026-09&ate=2026-09')->assertOk()
            ->assertJsonPath('linhas.1', ['item' => 'Descontos concedidos', 'recebido' => -900, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('totais.recebido', 8100);
        $this->getJson('/relatorios/produtos/dados?de=2026-08&ate=2026-08')->assertOk()->assertJsonCount(0, 'linhas');
    }

    public function test_csv_por_produto_mostra_o_desconto_como_numero_negativo(): void
    {
        $this->cenarioComDescontosPorProduto();

        $csv = $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-10&ate=2026-10&formato=csv')->streamedContent();

        // Número negativo que nós formatamos não é fórmula: não ganha o apóstrofo do texto livre.
        $this->assertStringContainsString("\n\"Descontos concedidos\";-22,00;0,00;0\n", $csv);
        $this->assertStringContainsString("\nTotal;198,00;420,00;\n", $csv);
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

        $this->actingAs($this->usuario)->getJson('/relatorios/produtos/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonCount(3, 'linhas')
                // Previsto: 30.000 emitidos + 2 previstas de 21/10 (contratos ativos, ainda não emitidas).
            ->assertJsonPath('linhas.0', ['item' => 'Hospedagem', 'recebido' => 10000, 'previsto' => 50000, 'contratos' => 2])
            ->assertJsonPath('linhas.1', ['item' => 'Suporte', 'recebido' => 5000, 'previsto' => 5000, 'contratos' => 0])
            ->assertJsonPath('linhas.2', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 7000, 'previsto' => 7000, 'contratos' => 0])
            ->assertJsonPath('totais.recebido', 22000);

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

        $this->actingAs($this->usuario)->getJson('/relatorios/produtos/dados?de=2026-11&ate=2026-11')->assertOk()
            ->assertJsonCount(2, 'linhas')
            ->assertJsonPath('linhas.0', ['item' => 'Hospedagem', 'recebido' => 0, 'previsto' => 10000, 'contratos' => 1])
            ->assertJsonPath('linhas.1', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 0, 'previsto' => 26060, 'contratos' => 0]);
    }

    public function test_csv_neutraliza_formula_no_nome_do_cliente(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create(['nome' => '=HYPERLINK("http://golpe.example")']);
        $this->cobranca($cliente, '2026-10-10', 10000, '2026-10-10');

        $csv = $this->actingAs($this->usuario)->get('/relatorios/clientes?de=2026-10&ate=2026-10&formato=csv')->streamedContent();

        $this->assertStringContainsString("\n\"'=HYPERLINK(\"\"http://golpe.example\"\")\";100,00;", $csv);
    }

    public function test_por_cliente_pagina_com_os_totais_do_relatorio_inteiro_e_csv_inteiro(): void
    {
        foreach (range(1, 30) as $numero) {
            $cliente = Cliente::factory()->for($this->usuario)->create(['nome' => sprintf('Cliente %02d', $numero)]);
            $this->cobranca($cliente, '2026-10-05', 1000, '2026-10-05');
        }
        $this->actingAs($this->usuario);

        $primeira = $this->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10&por_pagina=25')->assertOk();
        $segunda = $this->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10&por_pagina=25&pagina=2')->assertOk();

        $primeira->assertJsonPath('pagina.total', 30)->assertJsonCount(30, 'linhas'); // o bloco de 4 páginas cobre as 30
        $segunda->assertJsonPath('pagina.inicio', 2)->assertJsonCount(5, 'linhas');
        $this->assertSame($primeira->json('totais'), $segunda->json('totais'));
        $this->assertSame(30000, $primeira->json('totais.recebido'));
        $csv = $this->get('/relatorios/clientes?de=2026-10&ate=2026-10&formato=csv')->streamedContent();
        $this->assertSame(32, substr_count($csv, "\n")); // cabeçalho + 30 clientes + total
    }
}
