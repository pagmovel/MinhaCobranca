<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CorrecaoDoInicioTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    private ProdutoServico $hospedagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00'));
        $this->usuario = User::factory()->create(['dias_antecedencia' => 10]);
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
        $this->hospedagem = ProdutoServico::factory()->for($this->usuario)->create();
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id, 'descricao' => '', 'periodicidade' => 'mensal', 'modalidade' => 'prepago',
            'dia_vencimento' => 17, 'data_inicio' => '2026-10-10', 'ativo' => true,
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '247,57', 'principal' => true]],
        ], $extra);
    }

    private function gerar(): void
    {
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
    }

    /** O caso relatado: criado com início 10/10/2026 em vez de 17/01/2026; a cobrança gerada já foi cancelada. */
    private function contratoComInicioErrado(): Contrato
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dados())->assertRedirect('/contratos');
        $this->gerar();

        return $this->usuario->contratos()->sole();
    }

    public function test_corrige_o_inicio_sem_gerar_os_periodos_anteriores(): void
    {
        $contrato = $this->contratoComInicioErrado();
        $errada = $contrato->cobrancas()->sole();
        $this->post("/cobrancas/{$errada->id}/cancelar")->assertRedirect();

        $this->put("/contratos/{$contrato->id}", $this->dados(['data_inicio' => '2026-01-17', 'corrigir' => true, 'gerar_retroativas' => false]))
            ->assertRedirect('/contratos')->assertSessionHasNoErrors();

        $contrato->refresh();
        $this->assertSame(['2026-01-17', '2026-09-17'], [$contrato->data_inicio->toDateString(), $contrato->cobrar_a_partir_de->toDateString()]);
        // A cancelada deixa de ficar ligada ao contrato (não bloqueia período novo) e continua cancelada.
        $errada->refresh();
        $this->assertSame([null, StatusCobranca::Cancelada], [$errada->contrato_id, $errada->status]);

        // Período em curso (17/09–16/10) e outubro (vence 17/10, antecedência 10).
        $this->gerar();
        $this->assertSame(['2026-09-17', '2026-10-17'], $contrato->cobrancas()->orderBy('periodo_inicio')->get()
            ->map(fn ($cobranca) => $cobranca->periodo_inicio->toDateString())->all());
    }

    public function test_corrige_o_inicio_gerando_os_periodos_anteriores(): void
    {
        $contrato = $this->contratoComInicioErrado();

        $this->put("/contratos/{$contrato->id}", $this->dados(['data_inicio' => '2026-01-17', 'corrigir' => true, 'gerar_retroativas' => true]))
            ->assertRedirect('/contratos');

        $this->assertNull($contrato->fresh()->cobrar_a_partir_de);
        $this->gerar();
        $this->assertSame(10, $contrato->cobrancas()->count());
    }

    public function test_corrigir_cancela_as_pendentes_do_contrato(): void
    {
        $contrato = $this->contratoComInicioErrado();
        $pendente = $contrato->cobrancas()->sole();

        $this->put("/contratos/{$contrato->id}", $this->dados(['data_inicio' => '2026-01-17', 'corrigir' => true, 'gerar_retroativas' => false]))
            ->assertRedirect('/contratos');

        $pendente->refresh();
        $this->assertSame([StatusCobranca::Cancelada, null], [$pendente->status, $pendente->contrato_id]);
    }

    public function test_sem_confirmar_a_correcao_nada_muda(): void
    {
        $contrato = $this->contratoComInicioErrado();

        $this->put("/contratos/{$contrato->id}", $this->dados(['data_inicio' => '2026-01-17']))->assertSessionHasErrors('data_inicio');

        $this->assertSame('2026-10-10', $contrato->fresh()->data_inicio->toDateString());
        $this->assertSame(StatusCobranca::Pendente, $contrato->cobrancas()->sole()->status);
    }

    public function test_com_cobranca_paga_a_correcao_e_recusada(): void
    {
        $contrato = $this->contratoComInicioErrado();
        $contrato->cobrancas()->sole()->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-10'])->save();

        $this->put("/contratos/{$contrato->id}", $this->dados(['data_inicio' => '2026-01-17', 'corrigir' => true]))
            ->assertSessionHasErrors('data_inicio');

        $this->assertSame('2026-10-10', $contrato->fresh()->data_inicio->toDateString());
    }

    public function test_criar_contrato_com_inicio_no_passado_sem_os_periodos_anteriores(): void
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dados(['data_inicio' => '2026-01-17', 'gerar_retroativas' => false]))
            ->assertRedirect('/contratos');

        $contrato = $this->usuario->contratos()->sole();
        $this->assertSame('2026-09-17', $contrato->cobrar_a_partir_de->toDateString());
        $this->gerar();
        $this->assertSame(2, $contrato->cobrancas()->count());
    }

    public function test_sem_a_escolha_gera_desde_o_inicio_como_antes(): void
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dados(['data_inicio' => '2026-01-17']))->assertRedirect('/contratos');

        $this->assertNull($this->usuario->contratos()->sole()->cobrar_a_partir_de);
    }

    public function test_informa_os_periodos_anteriores_ao_atual(): void
    {
        $this->actingAs($this->usuario)->getJson('/contratos/retroativos?data_inicio=2026-01-17&periodicidade=mensal')
            ->assertOk()->assertExactJson([
                'quantidade' => 8,
                'de' => '17/01/2026',
                'ate' => '16/09/2026',
                'a_partir_de' => '17/09/2026',
            ]);

        $this->getJson('/contratos/retroativos?data_inicio=2026-10-01&periodicidade=mensal')
            ->assertOk()->assertJsonPath('quantidade', 0);
    }

    public function test_formulario_informa_pagas_e_pendentes_para_a_previa(): void
    {
        $contrato = $this->contratoComInicioErrado();

        $this->get("/contratos/{$contrato->id}/edit")->assertInertia(fn ($pagina) => $pagina
            ->where('contrato.tem_pagas', false)
            ->where('contrato.cobrancas_pendentes.0.periodo', '10/10/2026 a 09/11/2026')
            ->where('contrato.cobrancas_pendentes.0.valor', '247,57'));
    }

    public function test_encerrar_nao_emite_periodos_antes_do_cobrar_a_partir_de(): void
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dados(['data_inicio' => '2026-01-17', 'gerar_retroativas' => false]));
        $contrato = $this->usuario->contratos()->sole();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-10', 'em_curso' => 'proporcional'])->assertRedirect();

        $this->assertSame(['2026-09-17'], $contrato->cobrancas()->get()->map(fn ($cobranca) => $cobranca->periodo_inicio->toDateString())->all());
    }
}
