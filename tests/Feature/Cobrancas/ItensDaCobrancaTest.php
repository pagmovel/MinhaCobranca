<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItensDaCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create(['dias_antecedencia' => 10]);
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function produto(array $extra = []): ProdutoServico
    {
        return ProdutoServico::factory()->for($this->usuario)->create($extra);
    }

    private function item(ProdutoServico $produto, string $quantidade, string $preco, bool $principal = false): array
    {
        return ['produto_servico_id' => $produto->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => $principal];
    }

    private function avulsa(array $itens): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'descricao' => 'Manutenção extra do site',
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-20',
            'itens' => $itens,
        ];
    }

    private function contrato(array $itens): array
    {
        return [
            'cliente_id' => $this->cliente->id, 'descricao' => 'Hospedagem', 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
            'itens' => $itens,
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: bool}> */
    private function copias(Cobranca $cobranca): array
    {
        return $cobranca->itens()->get()->map(fn (CobrancaItem $item) => [
            $item->nome, $item->unidade, $item->quantidade, $item->preco_unitario_centavos, $item->total_centavos, $item->principal,
        ])->all();
    }

    public function test_avulsa_copia_os_itens_e_o_valor_e_a_soma(): void
    {
        $site = $this->produto(['nome' => 'Manutenção de site', 'unidade' => 'hora', 'codigo_servico' => '0107 - Suporte']);
        $dominio = $this->produto(['nome' => 'Registro de domínio', 'unidade' => 'un', 'codigo_servico' => '0103 - Hospedagem']);

        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([
            $this->item($site, '2,5', '80,00', principal: true),
            $this->item($dominio, '1', '40,00'),
        ]))->assertRedirect('/cobrancas');

        $cobranca = $this->usuario->cobrancas()->with('recibo')->sole();
        $this->assertSame(20000 + 4000, $cobranca->valor_centavos);
        $this->assertSame('0107 - Suporte', $cobranca->codigo_servico);
        $this->assertSame(24000, $cobranca->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame([
            ['Manutenção de site', 'hora', '2.500', 8000, 20000, true],
            ['Registro de domínio', 'un', '1.000', 4000, 4000, false],
        ], $this->copias($cobranca));
    }

    public function test_geracao_copia_os_itens_do_contrato_e_mudancas_depois_nao_alteram_a_cobranca(): void
    {
        $hospedagem = $this->produto(['nome' => 'Hospedagem']);
        $this->actingAs($this->usuario)->post('/contratos', $this->contrato([$this->item($hospedagem, '1', '89,90')]))
            ->assertRedirect('/contratos');
        $contrato = $this->usuario->contratos()->sole();

        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $cobranca = $contrato->cobrancas()->sole();
        $this->assertSame(8990, $cobranca->valor_centavos);
        $this->assertSame([['Hospedagem', 'mês', '1.000', 8990, 8990, true]], $this->copias($cobranca));
        $this->assertSame($hospedagem->id, $cobranca->itens()->sole()->produto_servico_id);

        // Renomear o item e reajustar o contrato valem só para as próximas cobranças.
        $hospedagem->update(['nome' => 'Hospedagem premium']);
        $this->put("/contratos/{$contrato->id}", $this->contrato([$this->item($hospedagem, '1', '120,00')]))
            ->assertRedirect('/contratos');

        $this->assertSame([['Hospedagem', 'mês', '1.000', 8990, 8990, true]], $this->copias($cobranca));
        $this->assertSame(8990, $cobranca->fresh()->valor_centavos);
    }

    public function test_contrato_antigo_sem_itens_gera_cobranca_sem_itens(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['data_inicio' => '2026-10-01', 'dia_vencimento' => 10]);

        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $cobranca = $contrato->cobrancas()->sole();
        $this->assertSame(26060, $cobranca->valor_centavos);
        $this->assertSame(0, $cobranca->itens()->count());
    }

    public function test_editar_nao_enviada_troca_os_itens_e_mantem_o_numero(): void
    {
        $antigo = $this->produto();
        $novo = $this->produto(['nome' => 'Consultoria', 'unidade' => 'hora']);
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($antigo, '1', '150,00')]));
        $cobranca = $this->usuario->cobrancas()->sole();

        $this->put("/cobrancas/{$cobranca->id}", $this->avulsa([$this->item($novo, '3', '100,00')]))
            ->assertRedirect('/cobrancas');

        $cobranca->refresh()->load('recibo');
        $this->assertSame(30000, $cobranca->valor_centavos);
        $this->assertSame([['Consultoria', 'hora', '3.000', 10000, 30000, true]], $this->copias($cobranca));
        $this->assertSame(1, $cobranca->recibo->numero);

        $this->get("/cobrancas/{$cobranca->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('cobranca.itens.0.nome', 'Consultoria')
            ->where('cobranca.itens.0.quantidade', '3')
            ->where('cobranca.itens.0.preco', '100,00')
            ->where('cobranca.itens.0.principal', true));
    }

    public function test_item_usado_so_em_cobranca_nao_e_excluido(): void
    {
        $produto = $this->produto();
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($produto, '1', '10,00')]));

        $this->delete("/produtos-servicos/{$produto->id}")
            ->assertSessionHas('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        $this->assertModelExists($produto);
    }

    public function test_cobranca_alheia_da_404_antes_da_validacao(): void
    {
        $produto = $this->produto();
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($produto, '1', '10,00')]));
        $propria = $this->usuario->cobrancas()->sole();
        $alheia = Cobranca::factory()->create();

        $this->put("/cobrancas/{$propria->id}", $this->avulsa([$this->item($produto, '2', '10,00')]))->assertRedirect('/cobrancas');
        $this->put("/cobrancas/{$alheia->id}", $this->avulsa([]))->assertNotFound();
    }

    public function test_descricao_e_opcional_e_o_recibo_lista_os_itens_com_a_conta(): void
    {
        $suporte = $this->produto(['nome' => 'Suporte técnico', 'unidade' => 'hora']);
        $hospedagem = $this->produto(['nome' => 'Hospedagem de site', 'unidade' => 'mês']);

        $this->actingAs($this->usuario)->post('/cobrancas', ['descricao' => ''] + $this->avulsa([
            $this->item($suporte, '1,5', '120,00', principal: true),
            $this->item($hospedagem, '1', '247,57'),
        ]))->assertRedirect('/cobrancas');

        $cobranca = $this->usuario->cobrancas()->with('recibo')->sole();
        $this->assertSame('', $cobranca->descricao);
        $servico = app(RenderizadorDeRecibo::class)->dados($cobranca->recibo, CarbonImmutable::now())['servico'];
        $this->assertSame([
            'Suporte técnico: 1,5 hora × R$ 120,00 = R$ 180,00',
            'Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57',
        ], $servico['linhas']);
        $this->assertSame('Suporte técnico: 1,5 hora × R$ 120,00 = R$ 180,00; Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57', $servico['descricao_em_linha']);
    }

    public function test_descricao_complementar_sai_depois_dos_itens(): void
    {
        $hospedagem = $this->produto(['nome' => 'Hospedagem de site', 'unidade' => 'mês']);

        $this->actingAs($this->usuario)->post('/cobrancas', ['descricao' => "Domínio aplbaraci.com.br\nReferente a outubro"] + $this->avulsa([
            $this->item($hospedagem, '1', '247,57'),
        ]))->assertRedirect('/cobrancas');

        $recibo = $this->usuario->cobrancas()->with('recibo')->sole()->recibo;
        $this->assertSame(
            ['Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57', '', 'Domínio aplbaraci.com.br', 'Referente a outubro'],
            app(RenderizadorDeRecibo::class)->dados($recibo, CarbonImmutable::now())['servico']['linhas'],
        );
    }

    public function test_cobranca_gerada_do_contrato_sem_descricao_lista_os_itens(): void
    {
        $hospedagem = $this->produto(['nome' => 'Hospedagem de site', 'unidade' => 'mês']);
        $this->actingAs($this->usuario)->post('/contratos', ['descricao' => null] + $this->contrato([$this->item($hospedagem, '1', '89,90')]))
            ->assertRedirect('/contratos');

        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $recibo = $this->usuario->contratos()->sole()->cobrancas()->sole()->recibo;
        $this->assertSame(['Hospedagem de site: 1 mês × R$ 89,90 = R$ 89,90'], app(RenderizadorDeRecibo::class)->dados($recibo, CarbonImmutable::now())['servico']['linhas']);
    }

    public function test_itens_e_descricao_que_passam_de_25_linhas_sao_recusados(): void
    {
        $itens = [];
        foreach (range(1, 23) as $numero) {
            $itens[] = $this->item($this->produto(['nome' => "Item {$numero}"]), '1', '10,00');
        }

        // 23 itens + linha em branco + 2 linhas de descrição = 26
        $this->actingAs($this->usuario)->post('/cobrancas', ['descricao' => "linha 1\nlinha 2"] + $this->avulsa($itens))
            ->assertSessionHasErrors(['descricao' => 'Itens e descrição passam de 25 linhas no recibo. Encurte a descrição ou junte itens.']);
        $this->assertSame(0, $this->usuario->cobrancas()->count());

        $this->post('/cobrancas', ['descricao' => 'linha 1'] + $this->avulsa($itens))->assertRedirect('/cobrancas'); // 25: cabe
    }
}
