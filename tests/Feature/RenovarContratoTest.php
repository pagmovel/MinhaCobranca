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

class RenovarContratoTest extends TestCase
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

    public function test_renovar_reabre_cancelamentos_e_devolve_a_cobranca_de_fronteira_ao_valor_cheio(): void
    {
        $contrato = $this->contratoComCobrancas();
        $numeroDeOutubro = $this->cobrancaDe($contrato, '2026-10-01')->recibo->numero;

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertRedirect();
        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $this->assertSame(['2026-10-18', 14384], [$outubro->periodo_fim->toDateString(), $outubro->valor_centavos]);
        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-11-01')->status);

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()
            ->assertJsonCount(2, 'acoes')
            ->assertJsonPath('acoes.0.acao', 'proporcional_cheio')
            ->assertJsonPath('acoes.1.acao', 'reabrir');

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertRedirect("/contratos/{$contrato->id}/edit")->assertSessionHas('sucesso', 'Contrato renovado.');

        $contrato->refresh();
        $this->assertSame([true, null, 'inteiro'], [$contrato->ativo, $contrato->data_fim, $contrato->ultimo_periodo]);

        $outubro->refresh();
        $this->assertSame(['2026-10-31', 24757, 'Domínio exemplo.com.br'], [
            $outubro->periodo_fim->toDateString(), $outubro->valor_centavos, $outubro->descricao,
        ]);
        $this->assertSame($numeroDeOutubro, $outubro->recibo->numero);
        $this->assertSame(24757, $outubro->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-11-01')->status);
    }

    public function test_renovar_com_data_de_fim_nova(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'cancelar'])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-12-31'])->assertRedirect();

        $contrato->refresh();
        $this->assertSame(['2026-12-31', 'inteiro'], [$contrato->data_fim->toDateString(), $contrato->ultimo_periodo]);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-10-01')->status);
    }

    public function test_renovar_recusa_data_de_fim_hoje_ou_no_passado(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-10-15'])->assertSessionHasErrors('data_fim');
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-10-14'])->assertSessionHasErrors('data_fim');

        $this->assertSame('2026-10-18', $contrato->fresh()->data_fim->toDateString());
    }

    public function test_renovar_so_vale_para_contrato_com_encerramento_agendado(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');

        // Fim hoje: encerra na hora (ativo passa a false), também não tem o que renovar.
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-15'])->assertRedirect();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');
    }

    public function test_renovar_duas_vezes_a_segunda_nao_tem_mais_encerramento_agendado(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');
    }

    public function test_renovacao_sem_cobranca_de_fronteira_materializada_nao_erra(): void
    {
        // Fim bem no futuro: com dias_antecedencia=40, a cobrança de fronteira ainda não foi gerada.
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2062-10-15'])->assertRedirect();

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()->assertJsonCount(0, 'acoes');
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();
        $contrato->refresh();
        $this->assertSame([true, null, 'inteiro'], [$contrato->ativo, $contrato->data_fim, $contrato->ultimo_periodo]);
    }

    public function test_contrato_de_outro_usuario_da_404(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        $outro = User::factory()->create();
        $this->actingAs($outro);
        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertNotFound();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertNotFound();
        $this->assertSame('2026-10-18', $contrato->fresh()->data_fim->toDateString());
    }

    /** Caso difícil da spec (seção 1/5): proporcional já enviada vira avulsa — renovar não toca nela, só avisa. */
    public function test_renovar_nao_toca_automaticamente_a_cobranca_proporcional_ja_enviada(): void
    {
        $contrato = $this->contratoComCobrancas();
        $outubroOriginal = $this->cobrancaDe($contrato, '2026-10-01');
        $outubroOriginal->forceFill(['enviado_em' => now()])->save();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertRedirect();
        $outubroOriginal->refresh();
        $this->assertSame(StatusCobranca::Cancelada, $outubroOriginal->status);
        $avulsa = $this->usuario->cobrancas()->whereNull('contrato_id')->sole();

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()
            ->assertJsonCount(2, 'acoes')
            ->assertJsonPath('acoes.0.acao', 'aviso_avulsa')
            ->assertJsonPath('acoes.1.acao', 'reabrir');

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();

        $this->assertSame(StatusCobranca::Cancelada, $outubroOriginal->fresh()->status);
        $this->assertSame(StatusCobranca::Pendente, $avulsa->fresh()->status);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-11-01')->status);
        $this->assertSame(['inteiro', null], [$contrato->fresh()->ultimo_periodo, $contrato->fresh()->data_fim]);
    }
}
