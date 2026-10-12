<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Contrato com fim já passado (marcado direto no formulário, sem passar por "Encerrar"): T17.11 não cobre este caso. */
class RenovarContratoVencidoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    private ProdutoServico $hospedagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-13 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
        $this->hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['preco_centavos' => 26060]);
    }

    /** Vencido: ativo ainda true (nunca passou pelo fluxo de encerrar), fim em 30/09 — hoje é 13/10. */
    private function contratoVencido(array $extra = []): Contrato
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(array_merge(['data_inicio' => '2026-09-01', 'data_fim' => '2026-09-30'], $extra));
        $contrato->itens()->create([
            'produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1.000',
            'preco_unitario_centavos' => 24757, 'principal' => true, 'ordem' => 0,
        ]);

        return $contrato;
    }

    private function renovar(Contrato $contrato, array $dados = []): TestResponse
    {
        return $this->post("/contratos/{$contrato->id}/renovar-vencido", array_merge([
            'data_inicio' => '2026-10-01',
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '300,00', 'principal' => true]],
        ], $dados));
    }

    public function test_cria_contrato_novo_com_itens_ajustados_e_encerra_o_antigo(): void
    {
        $antigo = $this->contratoVencido();

        $resposta = $this->actingAs($this->usuario)->renovar($antigo);

        $antigo->refresh();
        $this->assertFalse($antigo->ativo);
        $this->assertSame('2026-09-30', $antigo->data_fim->toDateString());

        $novo = $this->usuario->contratos()->where('id', '!=', $antigo->id)->sole();
        $resposta->assertRedirect("/contratos/{$novo->id}/edit");
        $this->assertTrue($novo->ativo);
        $this->assertNull($novo->data_fim);
        $this->assertSame('2026-10-01', $novo->data_inicio->toDateString());
        $this->assertSame($antigo->cliente_id, $novo->cliente_id);
        $this->assertSame($antigo->periodicidade, $novo->periodicidade);
        $this->assertSame($antigo->modalidade, $novo->modalidade);
        $this->assertSame($antigo->dia_vencimento, $novo->dia_vencimento);

        // Preço renegociado na renovação, não o do catálogo (26060) nem o do contrato antigo (24757).
        $item = $novo->itens()->sole();
        $this->assertSame(30000, $item->preco_unitario_centavos);
        $this->assertTrue($item->principal);
    }

    public function test_recusa_quando_o_contrato_ainda_nao_venceu(): void
    {
        $futuro = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['data_inicio' => '2026-09-01', 'data_fim' => '2026-12-31']);
        $futuro->itens()->create([
            'produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1.000',
            'preco_unitario_centavos' => 24757, 'principal' => true, 'ordem' => 0,
        ]);

        $this->actingAs($this->usuario)->renovar($futuro)
            ->assertSessionHas('erro', 'Este contrato ainda não venceu.');
        $this->assertSame(1, $this->usuario->contratos()->count());
    }

    public function test_recusa_contrato_sem_fim_marcado(): void
    {
        $semFim = Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_fim' => null]);

        $this->actingAs($this->usuario)->renovar($semFim)
            ->assertSessionHas('erro', 'Este contrato ainda não venceu.');
    }

    public function test_recusa_contrato_ja_encerrado(): void
    {
        $encerrado = $this->contratoVencido(['ativo' => false]);

        $this->actingAs($this->usuario)->renovar($encerrado)
            ->assertSessionHas('erro', 'Este contrato já está encerrado.');
    }

    public function test_data_de_inicio_precisa_ser_depois_do_fim_antigo(): void
    {
        $antigo = $this->contratoVencido();

        $this->actingAs($this->usuario)->renovar($antigo, ['data_inicio' => '2026-09-30'])
            ->assertSessionHasErrors('data_inicio');
        $this->assertSame(1, $this->usuario->contratos()->count());
    }

    public function test_itens_sao_obrigatorios(): void
    {
        $antigo = $this->contratoVencido();

        $this->actingAs($this->usuario)->renovar($antigo, ['itens' => []])
            ->assertSessionHasErrors('itens');
    }

    public function test_tela_de_edicao_marca_vencido_e_nao_marca_um_contrato_futuro(): void
    {
        $vencido = $this->contratoVencido();
        $futuro = Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_fim' => '2026-12-31']);

        $this->actingAs($this->usuario);
        $this->get("/contratos/{$vencido->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('contrato.vencido', true));
        $this->get("/contratos/{$futuro->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('contrato.vencido', false));
    }

    /** Pedido do usuário (2026-10-12): os campos do formulário principal já sugerem o próximo período, não as
     * datas (vencidas) do contrato antigo — editáveis depois, sem travar nada. */
    public function test_sugestao_de_renovacao_replica_a_duracao_da_vigencia_anterior(): void
    {
        $vencido = $this->contratoVencido();

        $this->actingAs($this->usuario)->get("/contratos/{$vencido->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('contrato.sugestao_renovacao.data_inicio', '2026-10-13')
                ->where('contrato.sugestao_renovacao.data_fim', '2026-11-12'));
    }

    /** Trimestral: a vigência anterior durou 3 meses (1 trimestre) — a sugestão replica 1 trimestre, não 3 meses corridos. */
    public function test_sugestao_de_renovacao_conta_em_periodos_da_periodicidade(): void
    {
        $trimestral = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['periodicidade' => 'trimestral', 'data_inicio' => '2026-01-01', 'data_fim' => '2026-03-31', 'ativo' => true]);

        $this->actingAs($this->usuario)->get("/contratos/{$trimestral->id}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('contrato.sugestao_renovacao.data_inicio', '2026-10-13')
                ->where('contrato.sugestao_renovacao.data_fim', '2027-01-12'));
    }

    public function test_sem_sugestao_de_renovacao_quando_nao_esta_vencido(): void
    {
        $futuro = Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_fim' => '2026-12-31']);

        $this->actingAs($this->usuario)->get("/contratos/{$futuro->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('contrato.sugestao_renovacao', null));
    }

    public function test_contrato_de_outro_usuario_da_404(): void
    {
        $alheio = $this->contratoVencido();
        $this->actingAs(User::factory()->create());

        $this->renovar($alheio)->assertNotFound();
    }
}
