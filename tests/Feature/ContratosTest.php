<?php

namespace Tests\Feature;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Enums\StatusCobranca;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Support\CondicoesDePagamento;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ComItens;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    private function dados(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'itens' => $this->itensPara($cliente),
            'periodicidade' => 'mensal',
            'modalidade' => 'prepago',
            'dia_vencimento' => 21,
            'data_inicio' => '2026-09-21',
            'data_fim' => null,
            'ativo' => true,
        ], $extra);
    }

    /** @return array{0: User, 1: Cliente} */
    private function usuarioComCliente(): array
    {
        $usuario = User::factory()->comPerfilPronto()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    public function test_cria_contrato_convertendo_o_valor(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente))->assertRedirect('/contratos');

        $contrato = $usuario->contratos()->firstOrFail();
        $this->assertSame(26060, $contrato->valor_centavos);
        $this->assertSame(Periodicidade::Mensal, $contrato->periodicidade);
        $this->assertSame('2026-09-21', $contrato->data_inicio->toDateString());
        $this->assertTrue($contrato->ativo);
    }

    public function test_lista_com_cliente_e_valor_formatado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        Contrato::factory()->for($usuario)->for($cliente)->create(['valor_centavos' => 26060]);

        $this->actingAs($usuario)->get('/contratos')
            ->assertInertia(fn (Assert $page) => $page->component('Contratos/Index'));
        $this->getJson('/contratos/dados')->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.cliente', $cliente->nome)
            ->assertJsonPath('linhas.0.valor', '260,60');
    }

    public function test_cliente_de_outro_usuario_e_recusado(): void
    {
        [$usuario] = $this->usuarioComCliente();
        $alheio = Cliente::factory()->create();
        // $this->dados($alheio) cria o produto do catálogo de $alheio (dono do cliente informado), não do
        // próprio $usuario; sem um produto seu, o catálogo dele ficaria incompleto e o middleware de progresso
        // bloquearia antes da validação de dono do cliente, que é o que este teste quer provar.
        ProdutoServico::factory()->for($usuario)->create();

        $this->actingAs($usuario)->post('/contratos', $this->dados($alheio))
            ->assertSessionHasErrors(['cliente_id' => 'O valor escolhido para cliente é inválido.']);
    }

    public function test_descricao_longa_demais_e_recusada(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat("linha\n", 26)]))
            ->assertSessionHasErrors(['descricao' => 'Use no máximo 25 linhas.']);
        // Linha comprida ocupa várias linhas na caixa do recibo: conta pelo que aparece no PDF.
        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat('palavra ', 60)."\n".str_repeat("linha\n", 21)]))
            ->assertSessionHasErrors(['descricao' => 'Use no máximo 25 linhas.']);
        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat('a', 2001)]))
            ->assertSessionHasErrors(['descricao' => 'O campo descrição deve ter no máximo 2000 caracteres.']);
    }

    public function test_datas_dia_de_vencimento_e_valor_invalidos(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['data_fim' => '2026-09-01']))->assertSessionHasErrors('data_fim');
        $this->post('/contratos', $this->dados($cliente, ['dia_vencimento' => 0]))
            ->assertSessionHasErrors(['dia_vencimento' => 'O campo dia do vencimento deve estar entre 1 e 31.']);
        $this->post('/contratos', $this->dados($cliente, ['dia_vencimento' => 32]))->assertSessionHasErrors('dia_vencimento');
        $this->post('/contratos', $this->dados($cliente, ['itens' => $this->itensPara($cliente, '0,00')]))
            ->assertSessionHasErrors(['itens' => 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.']);
    }

    public function test_encerra_contrato_e_isola_contrato_alheio(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $alheio = Contrato::factory()->create();

        $this->actingAs($usuario)->put("/contratos/{$contrato->id}", $this->dados($cliente, ['ativo' => false]))
            ->assertRedirect('/contratos');
        $this->assertFalse($contrato->fresh()->ativo);

        $this->get("/contratos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/contratos/{$alheio->id}", $this->dados($cliente))->assertNotFound();
    }

    public function test_contrato_pos_pago_gera_cobranca_que_vence_depois_do_periodo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        [$usuario, $cliente] = $this->usuarioComCliente();
        $usuario->update(['dias_antecedencia' => 10]);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            'modalidade' => 'pospago', 'data_inicio' => '2026-10-01', 'dia_vencimento' => 10,
        ]))->assertRedirect('/contratos');

        app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::today());

        $cobranca = $usuario->cobrancas()->sole();
        $this->assertSame(
            ['2026-10-01', '2026-10-31', '2026-11-10'],
            [$cobranca->periodo_inicio->toDateString(), $cobranca->periodo_fim->toDateString(), $cobranca->vencimento->toDateString()],
        );
    }

    public function test_modalidade_invalida_e_recusada_e_nao_muda_com_cobrancas(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $this->post('/contratos', $this->dados($cliente, ['modalidade' => 'quinzenal']))->assertSessionHasErrors('modalidade');

        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        Cobranca::factory()->for($usuario)->for($cliente)->create(['contrato_id' => $contrato->id]);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, ['modalidade' => 'pospago']))
            ->assertSessionHasErrors(['data_inicio' => 'Confirme a correção: as cobranças pendentes deste contrato serão canceladas.']);
        $this->assertSame(Modalidade::Prepago, $contrato->fresh()->modalidade);
    }

    /** @return list<mixed> as seis colunas das condições, na ordem em que o contrato as guarda */
    private function condicoesGravadas(Contrato $contrato): array
    {
        return array_values($contrato->fresh()->only(Contrato::CONDICOES));
    }

    public function test_grava_condicoes_em_percentual_e_em_reais(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '2,5',
            'multa_tipo' => 'valor', 'multa' => '15,00',
            'juros_tipo' => 'percentual', 'juros' => '1',
        ]))->assertRedirect('/contratos')->assertSessionHasNoErrors();

        $this->assertSame(['percentual', 250, 'valor', 1500, 'percentual', 100], $this->condicoesGravadas($usuario->contratos()->sole()));
    }

    public function test_contrato_sem_condicoes_continua_valido(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        // Sem os campos (formulário antigo) e com os campos em branco (como a tela os manda).
        $this->post('/contratos', $this->dados($cliente))->assertRedirect('/contratos')->assertSessionHasNoErrors();
        $this->post('/contratos', $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '',
            'multa_tipo' => 'percentual', 'multa' => '',
            'juros_tipo' => 'valor', 'juros' => '',
        ]))->assertRedirect('/contratos')->assertSessionHasNoErrors();

        $this->assertSame(2, $usuario->contratos()->count());
        foreach ($usuario->contratos as $contrato) {
            $this->assertSame([null, null, null, null, null, null], $this->condicoesGravadas($contrato));
        }
    }

    public function test_zero_vira_sem_regra(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->comCondicoes()->create();

        $this->actingAs($usuario)->put("/contratos/{$contrato->id}", $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '0',
            'multa_tipo' => 'valor', 'multa' => '0,00',
            'juros_tipo' => 'percentual', 'juros' => '0,0',
        ]))->assertRedirect('/contratos')->assertSessionHasNoErrors();

        $this->assertSame([null, null, null, null, null, null], $this->condicoesGravadas($contrato));
    }

    public function test_percentual_acima_de_100_e_recusado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $mensagem = 'Informe um percentual de 0 a 100, com até 2 casas decimais.';

        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'percentual', 'multa' => '100,01']))
            ->assertSessionHasErrors(['multa' => $mensagem]);
        $this->post('/contratos', $this->dados($cliente, ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '101']))
            ->assertSessionHasErrors(['desconto_pontualidade' => $mensagem]);
        $this->post('/contratos', $this->dados($cliente, ['juros_tipo' => 'percentual', 'juros' => '1,234']))
            ->assertSessionHasErrors(['juros' => $mensagem]);
        $this->assertSame(0, $usuario->contratos()->count());

        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'percentual', 'multa' => '100']))
            ->assertRedirect('/contratos')->assertSessionHasNoErrors();
        $this->assertSame(10000, $usuario->contratos()->sole()->multa_valor);
    }

    public function test_desconto_em_reais_acima_do_valor_do_contrato_e_recusado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade' => '260,61']))
            ->assertSessionHasErrors(['desconto_pontualidade' => 'O desconto não pode passar do valor do contrato (R$ 260,60).']);
        $this->assertSame(0, $usuario->contratos()->count());

        // O valor do contrato (R$ 260,60) é o limite, inclusive; a multa em reais não tem teto.
        $this->post('/contratos', $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade' => '260,60',
            'multa_tipo' => 'valor', 'multa' => '999,00',
        ]))->assertRedirect('/contratos')->assertSessionHasNoErrors();
        $this->assertSame(['valor', 26060, 'valor', 99900, null, null], $this->condicoesGravadas($usuario->contratos()->sole()));
    }

    public function test_multa_em_reais_acima_do_maximo_e_recusada(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $mensagem = 'Informe um valor em reais, como 15,00, de no máximo R$ 99.999.999,99.';

        // Um centavo acima do teto dos preços e o valor enorme que ainda cabe no inteiro do PHP: o mesmo erro, no campo.
        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'valor', 'multa' => '100.000.000,00']))
            ->assertSessionHasErrors(['multa' => $mensagem]);
        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'valor', 'multa' => '9999999999999999,99']))
            ->assertSessionHasErrors(['multa' => $mensagem]);
        $this->assertSame(0, $usuario->contratos()->count());

        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'valor', 'multa' => '99.999.999,99']))
            ->assertRedirect('/contratos')->assertSessionHasNoErrors();
        $this->assertSame(9_999_999_999, $usuario->contratos()->sole()->multa_valor);
    }

    public function test_juros_em_reais_acima_do_maximo_e_recusada(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $mensagem = 'Informe um valor em reais, como 15,00, de no máximo R$ 99.999.999,99.';

        $this->post('/contratos', $this->dados($cliente, ['juros_tipo' => 'valor', 'juros' => '100.000.000,00']))
            ->assertSessionHasErrors(['juros' => $mensagem]);
        $this->post('/contratos', $this->dados($cliente, ['juros_tipo' => 'valor', 'juros' => '9999999999999999,99']))
            ->assertSessionHasErrors(['juros' => $mensagem]);
        $this->assertSame(0, $usuario->contratos()->count());

        // No teto, a conta de juros com atraso continua em inteiro (antes do teto, o valor gravado dava TypeError no intdiv).
        $this->post('/contratos', $this->dados($cliente, ['juros_tipo' => 'valor', 'juros' => '99.999.999,99']))
            ->assertRedirect('/contratos')->assertSessionHasNoErrors();
        $contrato = $usuario->contratos()->sole();
        $this->assertSame(9_999_999_999, $contrato->juros_valor);
        $this->assertSame(3333333333, CondicoesDePagamento::jurosCentavos(10000, 'valor', $contrato->juros_valor, 10));
    }

    public function test_formato_ou_tipo_invalido_das_condicoes_e_recusado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'valor', 'multa' => 'abc']))
            ->assertSessionHasErrors(['multa' => 'Informe um valor em reais, como 15,00, de no máximo R$ 99.999.999,99.']);
        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'dobro', 'multa' => '2']))
            ->assertSessionHasErrors('multa_tipo');
        $this->post('/contratos', $this->dados($cliente, ['juros' => '1']))
            ->assertSessionHasErrors(['juros_tipo' => 'Escolha se os juros são em % ou em R$.']);
        // Lista no lugar do texto: erro de validação, não 500.
        $this->post('/contratos', $this->dados($cliente, ['multa_tipo' => 'percentual', 'multa' => ['2']]))
            ->assertSessionHasErrors('multa');
        $this->assertSame(0, $usuario->contratos()->count());
    }

    public function test_mudar_condicoes_audita_antes_e_depois(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->comCondicoes()->create();

        $this->actingAs($usuario)->put("/contratos/{$contrato->id}", $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '10',
            'multa_tipo' => 'valor', 'multa' => '5,00',
            'juros_tipo' => 'percentual', 'juros' => '',
        ]))->assertRedirect('/contratos');

        $registro = Auditoria::where('acao', 'contrato.condicoes')->sole();
        $this->assertSame([$usuario->id, 'contrato', $contrato->id], [$registro->user_id, $registro->alvo_tipo, $registro->alvo_id]);
        $this->assertSame(
            'Condições de pagamento alteradas (antes: desconto até o vencimento 15%, multa 2%, juros 1% ao mês; agora: desconto até o vencimento 10%, multa R$ 5,00)',
            $registro->resumo,
        );
        $this->assertSame([
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1500,
            'multa_tipo' => 'percentual', 'multa_valor' => 200,
            'juros_tipo' => 'percentual', 'juros_valor' => 100,
        ], $registro->antes);
        $this->assertSame([
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1000,
            'multa_tipo' => 'valor', 'multa_valor' => 500,
            'juros_tipo' => null, 'juros_valor' => null,
        ], $registro->depois);
    }

    public function test_salvar_sem_mudar_condicoes_nao_audita(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->comCondicoes()->create();
        $this->actingAs($usuario);

        // As mesmas condições que a tela devolve (15%, 2%, 1%) e, depois, um pedido sem os campos: nada muda.
        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '15',
            'multa_tipo' => 'percentual', 'multa' => '2',
            'juros_tipo' => 'percentual', 'juros' => '1',
        ]))->assertRedirect('/contratos');
        $this->put("/contratos/{$contrato->id}", $this->dados($cliente))->assertRedirect('/contratos');

        $this->assertSame(0, Auditoria::where('acao', 'contrato.condicoes')->count());
        $this->assertSame(['percentual', 1500, 'percentual', 200, 'percentual', 100], $this->condicoesGravadas($contrato));
    }

    public function test_mudar_so_as_condicoes_nao_pede_correcao_nem_mexe_nas_cobrancas(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $pendente = Cobranca::factory()->for($usuario)->for($cliente)->create(['contrato_id' => $contrato->id]);
        $paga = Cobranca::factory()->for($usuario)->for($cliente)->create([
            'contrato_id' => $contrato->id, 'periodo_inicio' => '2026-09-01', 'periodo_fim' => '2026-09-30',
            'vencimento' => '2026-09-10', 'status' => StatusCobranca::Paga, 'pago_em' => '2026-09-10',
        ]);

        // Contrato com cobranças e uma paga: mudar o início seria recusado, mas condição não muda período nem valor.
        $this->actingAs($usuario)->put("/contratos/{$contrato->id}", $this->dados($cliente, [
            'desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade' => '10',
            'multa_tipo' => 'percentual', 'multa' => '2',
        ]))->assertRedirect('/contratos')->assertSessionHasNoErrors();

        $this->assertSame(['percentual', 1000, 'percentual', 200, null, null], $this->condicoesGravadas($contrato));
        $this->assertSame([$contrato->id, StatusCobranca::Pendente, 26060], [$pendente->fresh()->contrato_id, $pendente->fresh()->status, $pendente->fresh()->valor_centavos]);
        $this->assertSame([$contrato->id, StatusCobranca::Paga, 26060], [$paga->fresh()->contrato_id, $paga->fresh()->status, $paga->fresh()->valor_centavos]);
        $this->assertSame(0, Auditoria::where('acao', 'contrato.corrigido')->count());
        $this->assertSame(1, Auditoria::where('acao', 'contrato.condicoes')->count());
    }

    public function test_outro_usuario_nao_ve_condicoes(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->comCondicoes()->create([
            'multa_tipo' => 'valor', 'multa_valor' => 1500,
        ]);
        $semCondicoes = Contrato::factory()->for($usuario)->for($cliente)->create();
        $alheio = Contrato::factory()->comCondicoes()->create();

        // A tela de edição devolve as condições como se digitam; só depois vale provar o 404 do contrato alheio.
        $this->actingAs($usuario)->get("/contratos/{$contrato->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Contratos/Form')
            ->where('contrato.desconto_pontualidade_tipo', 'percentual')->where('contrato.desconto_pontualidade', '15')
            ->where('contrato.multa_tipo', 'valor')->where('contrato.multa', '15,00')
            ->where('contrato.juros_tipo', 'percentual')->where('contrato.juros', '1'));
        $this->get("/contratos/{$semCondicoes->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('contrato.desconto_pontualidade_tipo', 'percentual')->where('contrato.desconto_pontualidade', '')
            ->where('contrato.multa', '')->where('contrato.juros', ''));

        $this->get("/contratos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/contratos/{$alheio->id}", $this->dados($cliente, ['multa_tipo' => 'valor', 'multa' => '9,00']))->assertNotFound();
        $this->assertSame(['percentual', 1500, 'percentual', 200, 'percentual', 100], $this->condicoesGravadas($alheio));
    }
}
