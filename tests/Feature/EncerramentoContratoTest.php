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

class EncerramentoContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    private ProdutoServico $hospedagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        // Antecedência de 40 dias: novembro (vence 20/11) já foi gerada em 15/10.
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

    public function test_exemplo_aprovado_vencida_continua_em_curso_proporcional_e_seguinte_cancelada(): void
    {
        $contrato = $this->contratoComCobrancas();
        $agosto = $this->cobrancaDe($contrato, '2026-08-01');
        $agosto->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-08-20'])->save();
        $numeroDeOutubro = $this->cobrancaDe($contrato, '2026-10-01')->recibo->numero;

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])
            ->assertRedirect("/contratos/{$contrato->id}/edit");

        $this->assertSame(StatusCobranca::Paga, $agosto->fresh()->status);
        $setembro = $this->cobrancaDe($contrato, '2026-09-01');
        $this->assertSame(StatusCobranca::Pendente, $setembro->status);
        $this->assertSame(24757, $setembro->valor_centavos);

        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $this->assertSame(StatusCobranca::Pendente, $outubro->status);
        $this->assertSame(14384, $outubro->valor_centavos);
        $this->assertSame('2026-10-18', $outubro->periodo_fim->toDateString());
        $this->assertSame('2026-10-20', $outubro->vencimento->toDateString());
        $this->assertSame(['0.581', 14384], [$outubro->itens()->sole()->quantidade, $outubro->itens()->sole()->total_centavos]);
        $this->assertSame("Domínio exemplo.com.br\nProporcional: 18 de 31 dias (01/10/2026 a 18/10/2026)", $outubro->descricao);
        $this->assertSame($numeroDeOutubro, $outubro->recibo->numero);
        $this->assertSame(14384, $outubro->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame('2026-10-18', $outubro->recibo->snapshot['servico']['periodo_fim']);

        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-11-01')->status);

        // Fim no futuro (18/10 > hoje 15/10): agendado — continua ativo até lá, já com as cobranças acertadas.
        $contrato->refresh();
        $this->assertSame([true, '2026-10-18', 'proporcional'], [$contrato->ativo, $contrato->data_fim->toDateString(), $contrato->ultimo_periodo]);
    }

    public function test_em_curso_ja_enviada_cancela_a_original_e_emite_avulsa_proporcional(): void
    {
        $contrato = $this->contratoComCobrancas();
        $original = $this->cobrancaDe($contrato, '2026-10-01');
        $original->forceFill(['enviado_em' => now()])->save();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])
            ->assertRedirect();

        $this->assertSame(StatusCobranca::Cancelada, $original->fresh()->status);
        $nova = $this->usuario->cobrancas()->whereNull('contrato_id')->with('recibo')->sole();
        $this->assertSame([$this->cliente->id, 14384, '2026-10-01', '2026-10-18', '2026-10-20'], [
            $nova->cliente_id, $nova->valor_centavos, $nova->periodo_inicio->toDateString(),
            $nova->periodo_fim->toDateString(), $nova->vencimento->toDateString(),
        ]);
        $this->assertSame('0.581', $nova->itens()->sole()->quantidade);
        $this->assertNotSame($original->recibo->numero, $nova->recibo->numero);
        $this->assertNull($nova->enviado_em);
    }

    public function test_escolher_cancelar_o_periodo_em_curso(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'cancelar'])->assertRedirect();

        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-10-01')->status);
        $this->assertSame(24757, $this->cobrancaDe($contrato, '2026-10-01')->valor_centavos);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-09-01')->status);
    }

    public function test_pos_pago_encerrado_hoje_emite_os_periodos_ate_o_fim_que_ainda_nao_sairam(): void
    {
        $this->usuario->forceFill(['dias_antecedencia' => 0])->save();
        // Pós-pago desde 01/08, dia 25: agosto (vence 25/09) já saiu; setembro (vence 25/10) ainda não.
        $contrato = $this->contratoComCobrancas(['modalidade' => 'pospago', 'dia_vencimento' => 25]);
        $this->assertSame(1, $contrato->cobrancas()->count());

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-15', 'em_curso' => 'proporcional'])->assertRedirect();

        $setembro = $this->cobrancaDe($contrato, '2026-09-01');
        $this->assertSame([24757, '2026-09-30', '2026-10-25'], [$setembro->valor_centavos, $setembro->periodo_fim->toDateString(), $setembro->vencimento->toDateString()]);
        $this->assertNotNull($setembro->recibo);

        // Outubro: 15 de 31 dias → 0,484 × 247,57 = 119,82.
        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $this->assertSame([11982, '2026-10-15', '2026-11-25'], [$outubro->valor_centavos, $outubro->periodo_fim->toDateString(), $outubro->vencimento->toDateString()]);
        $this->assertSame(11982, $outubro->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame(3, $contrato->cobrancas()->count());
        $this->assertFalse($contrato->fresh()->ativo);
    }

    public function test_previa_mostra_o_que_acontece_sem_gravar_nada(): void
    {
        $contrato = $this->contratoComCobrancas();
        $antes = $contrato->cobrancas()->orderBy('id')->get(['status', 'valor_centavos', 'periodo_fim'])->toArray();

        $resposta = $this->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-10-18&em_curso=proporcional")->assertOk();

        $this->assertSame(['manter', 'manter', 'proporcional', 'cancelar'], array_column($resposta->json('acoes'), 'acao'));
        $this->assertSame(['247,57', '247,57', '143,84', '247,57'], array_column($resposta->json('acoes'), 'valor'));
        $this->assertSame(true, $resposta->json('tem_em_curso'));
        $this->assertSame($antes, $contrato->cobrancas()->orderBy('id')->get(['status', 'valor_centavos', 'periodo_fim'])->toArray());
        $this->assertTrue($contrato->fresh()->ativo);
    }

    public function test_fim_antes_do_inicio_e_recusado(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-07-31', 'em_curso' => 'proporcional'])
            ->assertSessionHasErrors('fim');
        $this->assertTrue($contrato->fresh()->ativo);
    }

    public function test_fim_no_futuro_agenda_o_encerramento_sem_emitir_nada_adiantado(): void
    {
        $contrato = $this->contratoComCobrancas();

        $previa = $this->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-12-15")->json();
        $this->assertSame(['manter', 'manter', 'manter', 'manter', 'proporcional'], array_column($previa['acoes'], 'acao'));
        $this->assertSame([false, false, false, false, true], array_column($previa['acoes'], 'agendada'));
        $this->assertTrue($previa['tem_em_curso']);

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-12-15', 'em_curso' => 'proporcional'])->assertRedirect();

        $contrato->refresh();
        $this->assertSame([true, '2026-12-15', 'proporcional'], [$contrato->ativo, $contrato->data_fim->toDateString(), $contrato->ultimo_periodo]);
        $this->assertSame(4, $contrato->cobrancas()->count());

        // Dezembro sai no ritmo de sempre (vence 20/12, antecedência 40 → 10/11), proporcional: 15 de 31 dias
        // → 0,484 × 247,57 = 119,82. Janeiro não sai.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:00'));
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $dezembro = $this->cobrancaDe($contrato, '2026-12-01');
        $this->assertSame([11982, '2026-12-15', '0.484'], [$dezembro->valor_centavos, $dezembro->periodo_fim->toDateString(), $dezembro->itens()->sole()->quantidade]);
        $this->assertSame(11982, $dezembro->recibo->snapshot['servico']['valor_centavos']);

        $this->travelTo(CarbonImmutable::parse('2027-03-01 10:00:00'));
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $this->assertSame(5, $contrato->cobrancas()->count());
    }

    public function test_encerramento_agendado_nao_e_reagendado(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        // Outubro já ficou proporcional (18 dias): encerrar de novo reduziria a quantidade já reduzida.
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-10'])->assertSessionHas('erro');
        $this->post("/contratos/{$contrato->id}/recomecar", $this->dadosDoContrato(['data_inicio' => '2026-10-11']))->assertSessionHas('erro');
        $this->assertSame(1, $this->usuario->contratos()->count());

        $this->assertSame('2026-10-18', $contrato->fresh()->data_fim->toDateString());
        $this->assertSame(14384, $this->cobrancaDe($contrato, '2026-10-01')->valor_centavos);
    }

    public function test_fim_no_futuro_sem_cobrar_o_ultimo_periodo(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-12-15', 'em_curso' => 'cancelar'])->assertRedirect();

        $this->travelTo(CarbonImmutable::parse('2026-11-10 10:00:00'));
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $this->assertSame(4, $contrato->cobrancas()->count());
    }

    public function test_fim_distante_nao_tem_limite_e_nao_emite_nada_agora(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2062-10-15'])->assertRedirect();

        $this->assertSame('2062-10-15', $contrato->fresh()->data_fim->toDateString());
        $this->assertSame(4, $contrato->cobrancas()->count());
    }

    public function test_recibo_proporcional_sai_sem_a_linha_digitavel_do_boleto_cheio(): void
    {
        $contrato = $this->contratoComCobrancas();
        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $outubro->forceFill(['forma_pagamento' => 'boleto', 'linha_digitavel' => str_repeat('1', 47)])->save();

        $previa = $this->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-10-18")->json('acoes');
        $this->assertSame([false, false, true, false], array_column($previa, 'boleto_novo'));

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertRedirect();

        $outubro->refresh();
        $this->assertSame([14384, null], [$outubro->valor_centavos, $outubro->linha_digitavel]);
    }

    public function test_vencida_de_periodo_que_comeca_depois_do_fim_e_cancelada(): void
    {
        // Pré-pago desde 15/08 com vencimento no dia 10: o período 15/10–14/11 venceu em 10/10, antes de começar.
        $contrato = $this->contratoComCobrancas(['data_inicio' => '2026-08-15', 'dia_vencimento' => 10]);

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-14', 'em_curso' => 'proporcional'])->assertRedirect();

        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-09-15')->status);
        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-10-15')->status);
        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-11-15')->status);
    }

    public function test_escolha_do_periodo_em_curso_so_aparece_quando_muda_algo(): void
    {
        $contrato = $this->contratoComCobrancas();

        // Setembro contém 18/09, mas está vencida: continua inteira, então não há o que escolher.
        $this->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-09-18")->assertOk()->assertJsonPath('tem_em_curso', false);
    }

    public function test_cobranca_antiga_sem_itens_fica_proporcional_pelo_valor(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['data_inicio' => '2026-10-01', 'dia_vencimento' => 20, 'valor_centavos' => 26060]);
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $this->actingAs($this->usuario)->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        // 260,60 × 18 ÷ 31 = 151,316… → 151,32.
        $this->assertSame(15132, $this->cobrancaDe($contrato, '2026-10-01')->valor_centavos);
    }

    public function test_contrato_ja_encerrado_nao_e_encerrado_de_novo_e_aba_antiga_nao_o_reativa(): void
    {
        $contrato = $this->contratoComCobrancas();
        // Fim hoje: encerra na hora.
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-15'])->assertRedirect();
        $this->assertFalse($contrato->fresh()->ativo);

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-20'])->assertSessionHas('erro');
        $this->assertSame('2026-10-15', $contrato->fresh()->data_fim->toDateString());

        // Formulário aberto antes do encerramento (ativo marcado, sem data de fim) salvo depois.
        $this->put("/contratos/{$contrato->id}", $this->dadosDoContrato(['data_fim' => '']))->assertRedirect('/contratos');
        $contrato->refresh();
        $this->assertSame([false, '2026-10-15'], [$contrato->ativo, $contrato->data_fim->toDateString()]);

        $this->put("/contratos/{$contrato->id}", $this->dadosDoContrato(['data_fim' => '', 'reativar' => true]))->assertRedirect('/contratos');
        $contrato->refresh();
        $this->assertSame([true, null], [$contrato->ativo, $contrato->data_fim]);
    }

    public function test_contrato_de_outro_usuario_da_404(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-10-18")->assertOk();

        $outro = User::factory()->create();
        $this->actingAs($outro)->getJson("/contratos/{$contrato->id}/encerramento?fim=2026-10-18")->assertNotFound();
        $this->actingAs($outro)->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertNotFound();
        $this->actingAs($outro)->post("/contratos/{$contrato->id}/recomecar", $this->dadosDoContrato(['data_inicio' => '2026-10-19']))->assertNotFound();
        $this->assertTrue($contrato->fresh()->ativo);
    }

    public function test_recomecar_encerra_na_vespera_e_cria_contrato_novo_com_os_dados_do_formulario(): void
    {
        $contrato = $this->contratoComCobrancas();

        $resposta = $this->post("/contratos/{$contrato->id}/recomecar", $this->dadosDoContrato([
            'data_inicio' => '2026-10-19', 'em_curso' => 'proporcional',
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '300,00', 'principal' => true]],
        ]));

        $novo = $this->usuario->contratos()->whereKeyNot($contrato->id)->sole();
        $resposta->assertRedirect("/contratos/{$novo->id}/edit");
        $this->assertSame(['2026-10-19', 30000, true], [$novo->data_inicio->toDateString(), $novo->valor_centavos, $novo->ativo]);
        $this->assertSame(1, $novo->itens()->count());

        $contrato->refresh();
        $this->assertSame([true, '2026-10-18'], [$contrato->ativo, $contrato->data_fim->toDateString()]);
        $this->assertSame(14384, $this->cobrancaDe($contrato, '2026-10-01')->valor_centavos);
    }

    public function test_recomecar_com_inicio_igual_ou_anterior_e_recusado(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/recomecar", $this->dadosDoContrato(['data_inicio' => '2026-08-01', 'periodicidade' => 'trimestral']))
            ->assertSessionHasErrors('data_inicio');

        $this->assertSame(1, $this->usuario->contratos()->count());
        $this->assertTrue($contrato->fresh()->ativo);
    }

    public function test_formulario_informa_se_o_contrato_ja_tem_cobrancas(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->get("/contratos/{$contrato->id}/edit")
            ->assertInertia(fn ($pagina) => $pagina->where('contrato.tem_cobrancas', true));
    }
}
