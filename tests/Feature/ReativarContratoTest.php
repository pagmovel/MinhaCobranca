<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReativarContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    private ProdutoServico $hospedagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create(['dias_antecedencia' => 40]);
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
        $this->hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem de site']);
    }

    private function dadosDoContrato(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id, 'descricao' => 'Domínio exemplo.com.br', 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 20, 'data_inicio' => '2026-08-01', 'ativo' => true,
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '247,57', 'principal' => true]],
        ], $extra);
    }

    /** Contrato mensal pré-pago desde 01/08 com as cobranças geradas até hoje (ago, set, out e nov). */
    private function contratoComCobrancas(array $extra = []): Contrato
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dadosDoContrato($extra))->assertRedirect('/contratos');
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        return $this->usuario->contratos()->sole();
    }

    private function cobrancaDe(Contrato $contrato, string $inicio): Cobranca
    {
        return $contrato->cobrancas()->whereDate('periodo_inicio', $inicio)->with('recibo')->sole();
    }

    /** Fim no passado (<= hoje): o contrato encerra de fato (ativo vira false), não só agendado. */
    private function encerrarDeFato(Contrato $contrato, string $fim = '2026-10-10', string $emCurso = 'proporcional'): void
    {
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => $fim, 'em_curso' => $emCurso])->assertRedirect();
        $this->assertFalse($contrato->fresh()->ativo);
    }

    public function test_reativar_desfaz_cancelamentos_restaura_a_fronteira_e_ainda_edita_o_contrato(): void
    {
        $contrato = $this->contratoComCobrancas();
        $numeroDeOutubro = $this->cobrancaDe($contrato, '2026-10-01')->recibo->numero;
        $this->encerrarDeFato($contrato);

        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $this->assertSame(['2026-10-10', StatusCobranca::Pendente], [$outubro->periodo_fim->toDateString(), $outubro->status]);
        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-11-01')->status);

        $this->getJson("/contratos/{$contrato->id}/reativacao")->assertOk()
            ->assertJsonCount(2, 'acoes')
            ->assertJsonPath('acoes.0.acao', 'proporcional_cheio')
            ->assertJsonPath('acoes.1.acao', 'reabrir');

        $dados = $this->dadosDoContrato([
            'data_fim' => '', 'reativar' => true,
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '300,00', 'principal' => true]],
        ]);
        $this->put("/contratos/{$contrato->id}", $dados)->assertRedirect('/contratos');

        $contrato->refresh();
        $this->assertSame([true, null, 'inteiro', 30000], [$contrato->ativo, $contrato->data_fim, $contrato->ultimo_periodo, $contrato->valor_centavos]);

        $outubro->refresh();
        $this->assertSame(['2026-10-31', 24757], [$outubro->periodo_fim->toDateString(), $outubro->valor_centavos]);
        $this->assertSame($numeroDeOutubro, $outubro->recibo->numero);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-11-01')->status);
    }

    public function test_reativacao_recusa_contrato_ainda_ativo(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->getJson("/contratos/{$contrato->id}/reativacao")->assertOk()->assertJsonCount(0, 'acoes');
    }

    public function test_reativacao_de_outro_usuario_da_404(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->encerrarDeFato($contrato);

        $outro = User::factory()->create();
        $this->actingAs($outro)->getJson("/contratos/{$contrato->id}/reativacao")->assertNotFound();
    }

    public function test_reativar_grava_auditoria(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->encerrarDeFato($contrato);

        $this->put("/contratos/{$contrato->id}", $this->dadosDoContrato(['data_fim' => '2026-12-31', 'reativar' => true]))
            ->assertRedirect('/contratos');

        $registro = $this->usuario->auditorias()->where('acao', 'contrato.reativado')->sole();
        $this->assertSame(
            ['Reativado (estava encerrado em 10/10/2026): novo fim em 31/12/2026', ['data_fim' => '2026-10-10'], ['data_fim' => '2026-12-31']],
            [$registro->resumo, $registro->antes, $registro->depois],
        );
    }

    /** Avulsa não paga: reativar também resolve sozinho, igual ao Renovar dedicado. */
    public function test_reativar_cancela_avulsa_nao_paga_e_restaura_a_original(): void
    {
        $contrato = $this->contratoComCobrancas();
        $outubroOriginal = $this->cobrancaDe($contrato, '2026-10-01');
        $outubroOriginal->forceFill(['enviado_em' => now()])->save();
        $this->encerrarDeFato($contrato);
        $avulsa = $this->usuario->cobrancas()->whereNull('contrato_id')->sole();

        $this->put("/contratos/{$contrato->id}", $this->dadosDoContrato(['data_fim' => '', 'reativar' => true]))
            ->assertRedirect('/contratos');

        $this->assertSame(StatusCobranca::Pendente, $outubroOriginal->fresh()->status);
        $this->assertSame(StatusCobranca::Cancelada, $avulsa->fresh()->status);
    }

    /** Avulsa já paga: dinheiro recebido não é desfeito sozinho — reativa, mas a avulsa paga fica intocada. */
    public function test_reativar_nao_mexe_quando_a_avulsa_proporcional_ja_foi_paga(): void
    {
        $contrato = $this->contratoComCobrancas();
        $outubroOriginal = $this->cobrancaDe($contrato, '2026-10-01');
        $outubroOriginal->forceFill(['enviado_em' => now()])->save();
        $this->encerrarDeFato($contrato);
        $avulsa = $this->usuario->cobrancas()->whereNull('contrato_id')->sole();
        $this->post("/cobrancas/{$avulsa->id}/pagar", ['pago_em' => '2026-10-15'])->assertRedirect();

        $this->put("/contratos/{$contrato->id}", $this->dadosDoContrato(['data_fim' => '', 'reativar' => true]))
            ->assertRedirect('/contratos');

        $this->assertTrue($contrato->fresh()->ativo);
        $this->assertSame(StatusCobranca::Cancelada, $outubroOriginal->fresh()->status);
        $this->assertSame(StatusCobranca::Paga, $avulsa->fresh()->status);
    }
}
