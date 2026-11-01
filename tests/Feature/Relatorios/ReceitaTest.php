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
use Illuminate\Support\Facades\DB;
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

    private function cobranca(Cliente $cliente, string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false, int $desconto = 0, int $multa = 0, int $juros = 0, int $outros = 0): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)
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

    /** Fase 27: recebido e média por cobrança paga incluem multa, juros e outros acréscimos. */
    public function test_receita_por_cliente_inclui_os_acrescimos(): void
    {
        $ana = Cliente::factory()->for($this->usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($this->usuario)->create(['nome' => 'Bruno Mecânica']);
        $this->cobranca($ana, '2026-09-05', 10000, '2026-10-05', desconto: 1000, multa: 200, juros: 300, outros: 500); // pago 100,00
        $this->cobranca($ana, '2026-10-10', 30000, '2026-10-11');                                                      // sem nada: 300,00
        $this->cobranca($ana, '2026-08-10', 8000, '2026-08-12', multa: 160);                                           // paga antes do período: fora
        $this->cobranca($bruno, '2026-09-06', 5000, '2026-10-06', juros: 250);                                         // pago 52,50

        $this->actingAs($this->usuario)->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonCount(2, 'linhas')
            // Média: (10.000 + 30.000) / 2 = 20.000; com o valor cheio e sem acréscimos seria 19.500.
            ->assertJsonPath('linhas.0', ['cliente' => 'Ana Contabilidade', 'recebido' => 40000, 'contratos' => 0, 'media' => 20000, 'vencidas' => 0, 'atraso' => 0])
            ->assertJsonPath('linhas.1', ['cliente' => 'Bruno Mecânica', 'recebido' => 5250, 'contratos' => 0, 'media' => 5250, 'vencidas' => 0, 'atraso' => 0])
            ->assertJsonPath('totais.recebido', 45250);
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

    /**
     * Fase 27. Pagas em outubro: Hospedagem e Suporte numa cobrança (desconto 15,00, multa 4,00, juros 7,00, outros 5,00 → pago 151,00),
     * uma sem itens (desconto 7,00, multa 1,40 → pago 64,40) e uma só de Hospedagem com multa de 0,80 (pago 40,80), todas vencidas em
     * setembro. Fora do recebido de outubro: uma paga em setembro (vencida em agosto) com juros de 0,90 e uma pendente que vence em outubro.
     */
    private function cenarioComAcrescimosPorProduto(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        $suporte = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Suporte']);
        $comItens = function (string $vencimento, ?string $pagoEm, array $itens, int $desconto = 0, int $multa = 0, int $juros = 0, int $outros = 0) use ($cliente) {
            $cobranca = $this->cobranca($cliente, $vencimento, array_sum(array_column($itens, 1)), $pagoEm, desconto: $desconto, multa: $multa, juros: $juros, outros: $outros);
            foreach ($itens as [$produto, $total]) {
                $cobranca->itens()->create([
                    'produto_servico_id' => $produto->id, 'nome' => $produto->nome, 'unidade' => 'mês', 'quantidade' => '1.000',
                    'preco_unitario_centavos' => $total, 'total_centavos' => $total, 'principal' => true,
                ]);
            }
        };
        $comItens('2026-09-10', '2026-10-10', [[$hospedagem, 10000], [$suporte, 5000]], desconto: 1500, multa: 400, juros: 700, outros: 500);
        $this->cobranca($cliente, '2026-09-20', 7000, '2026-10-02', desconto: 700, multa: 140);
        $comItens('2026-09-25', '2026-10-12', [[$hospedagem, 4000]], multa: 80);
        $comItens('2026-08-15', '2026-09-20', [[$hospedagem, 9000]], juros: 90);
        $comItens('2026-10-20', null, [[$hospedagem, 20000]]);
    }

    public function test_receita_por_produto_mostra_multas_juros_e_outros_e_o_total_bate_com_o_painel(): void
    {
        $this->cenarioComAcrescimosPorProduto();

        $resposta = $this->actingAs($this->usuario)->getJson('/relatorios/produtos/dados?de=2026-10&ate=2026-10')->assertOk()
            ->assertJsonCount(7, 'linhas')
            // Itens e "Sem item" com o valor cheio; depois os acréscimos (positivos) e, por último, os descontos (negativo).
            ->assertJsonPath('linhas.0', ['item' => 'Hospedagem', 'recebido' => 14000, 'previsto' => 20000, 'contratos' => 0])
            ->assertJsonPath('linhas.1', ['item' => 'Suporte', 'recebido' => 5000, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('linhas.2', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 7000, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('linhas.3', ['item' => 'Multas recebidas', 'recebido' => 620, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('linhas.4', ['item' => 'Juros recebidos', 'recebido' => 700, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('linhas.5', ['item' => 'Outros acréscimos', 'recebido' => 500, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('linhas.6', ['item' => 'Descontos concedidos', 'recebido' => -2200, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('totais.recebido', 25620)
            ->assertJsonPath('totais.previsto', 20000);

        // 151,00 + 64,40 + 40,80: o mesmo valor pago que o painel soma no mês.
        $this->assertSame(25620, (int) $this->usuario->cobrancas()->where('status', 'paga')->whereBetween('pago_em', ['2026-10-01', '2026-10-31'])->sum(DB::raw(Cobranca::VALOR_PAGO_SQL)));
        $this->get('/painel')->assertInertia(fn (Assert $page) => $page
            ->where('resumo.recebido', Dinheiro::formatar($resposta->json('totais.recebido'))));
    }

    public function test_linhas_de_acrescimos_so_aparecem_quando_ha_valor_no_periodo(): void
    {
        $this->cenarioComAcrescimosPorProduto();
        $this->actingAs($this->usuario);

        // Em setembro só houve juros (0,90): sem linha de multas, de outros acréscimos nem de descontos.
        $resposta = $this->getJson('/relatorios/produtos/dados?de=2026-09&ate=2026-09')->assertOk()
            ->assertJsonPath('linhas.3', ['item' => 'Juros recebidos', 'recebido' => 90, 'previsto' => 0, 'contratos' => 0])
            ->assertJsonPath('totais.recebido', 9090);
        $this->assertSame(['Hospedagem', 'Suporte', 'Sem item (cobranças anteriores ao catálogo)', 'Juros recebidos'], array_column($resposta->json('linhas'), 'item'));
        $this->getJson('/relatorios/produtos/dados?de=2026-07&ate=2026-07')->assertOk()->assertJsonCount(0, 'linhas');
    }

    public function test_csv_por_produto_acompanha_as_linhas_de_acrescimos(): void
    {
        $this->cenarioComAcrescimosPorProduto();

        $csv = $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-10&ate=2026-10&formato=csv')->streamedContent();

        $this->assertStringContainsString("\n\"Multas recebidas\";6,20;0,00;0\n\"Juros recebidos\";7,00;0,00;0\n\"Outros acréscimos\";5,00;0,00;0\n\"Descontos concedidos\";-22,00;0,00;0\n", $csv);
        $this->assertStringContainsString("\nTotal;256,20;200,00;\n", $csv);
    }

    /** PostgreSQL recusa `sum()`/`count()` com ORDER BY de coluna e o SQLite dos testes aceita: nenhuma consulta de relatório pode juntar os dois. */
    public function test_nenhuma_consulta_dos_relatorios_ordena_junto_de_um_agregado(): void
    {
        $this->cenarioComAcrescimosPorProduto();
        $this->actingAs($this->usuario);
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas) {
            $consultas[] = $consulta->sql;
        });

        foreach (['faturamento', 'clientes', 'produtos', 'a-receber'] as $tipo) {
            $this->getJson("/relatorios/{$tipo}/dados?de=2026-09&ate=2026-10")->assertOk();
        }

        $comAgregado = array_filter($consultas, fn (string $sql) => preg_match('/\b(sum|count|avg)\s*\(/i', $sql) === 1);
        $this->assertNotEmpty($comAgregado);
        foreach ($comAgregado as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('order by', $sql);
        }
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
