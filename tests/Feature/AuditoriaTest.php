<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00:00'));
        $this->usuario = User::factory()->comPerfilPronto()->create(['dias_antecedencia' => 10]);
    }

    private function paga(User $usuario, string $pagoEm = '2026-06-17'): Cobranca
    {
        $cobranca = Cobranca::factory()->for($usuario)->create(['vencimento' => '2026-05-17']);
        $cobranca->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => $pagoEm])->save();

        return $cobranca;
    }

    /** @return list<array{acao: string, alvo_tipo: string, alvo_id: int, resumo: string, motivo: ?string}> */
    private function registros(): array
    {
        return $this->usuario->auditorias()->orderBy('id')->get(['acao', 'alvo_tipo', 'alvo_id', 'resumo', 'motivo'])->toArray();
    }

    public function test_corrigir_pagamento_com_senha_e_motivo_fica_registrado(): void
    {
        $cobranca = $this->paga($this->usuario);

        $this->actingAs($this->usuario)->from('/cobrancas')->post("/cobrancas/{$cobranca->id}/corrigir-pagamento", [
            'pago_em' => '2026-05-17', 'senha' => 'password', 'motivo' => 'Data digitada errada',
        ])->assertRedirect('/cobrancas')->assertSessionHasNoErrors();

        $this->assertSame('2026-05-17', $cobranca->fresh()->pago_em->toDateString());
        $this->assertSame([[
            'acao' => 'pagamento.corrigido', 'alvo_tipo' => 'cobranca', 'alvo_id' => $cobranca->id,
            'resumo' => 'Pagamento corrigido (antes: 17/06/2026)', 'motivo' => 'Data digitada errada',
        ]], $this->registros());
        $auditoria = $this->usuario->auditorias()->sole();
        $this->assertSame([['pago_em' => '2026-06-17'], ['pago_em' => '2026-05-17'], '127.0.0.1'], [$auditoria->antes, $auditoria->depois, $auditoria->ip]);
    }

    public function test_desfazer_baixa_volta_a_pendente_e_fica_registrado(): void
    {
        $cobranca = $this->paga($this->usuario);

        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/desfazer-baixa", [
            'senha' => 'password', 'motivo' => 'Baixa na cobrança errada',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $cobranca->refresh();
        $this->assertSame([StatusCobranca::Pendente, null], [$cobranca->status, $cobranca->pago_em]);
        $this->assertSame(['baixa.desfeita', 'Baixa desfeita (estava paga em 17/06/2026)'], [$this->registros()[0]['acao'], $this->registros()[0]['resumo']]);
    }

    public function test_senha_errada_ou_sem_motivo_nada_muda(): void
    {
        $cobranca = $this->paga($this->usuario);
        $this->actingAs($this->usuario);

        $this->post("/cobrancas/{$cobranca->id}/corrigir-pagamento", ['pago_em' => '2026-05-17', 'senha' => 'errada', 'motivo' => 'x'])
            ->assertSessionHasErrors('senha');
        $this->post("/cobrancas/{$cobranca->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => ' '])
            ->assertSessionHasErrors('motivo');
        $this->post("/cobrancas/{$cobranca->id}/corrigir-pagamento", ['pago_em' => '2026-10-11', 'senha' => 'password', 'motivo' => 'x'])
            ->assertSessionHasErrors('pago_em');

        $cobranca->refresh();
        $this->assertSame([StatusCobranca::Paga, '2026-06-17'], [$cobranca->status, $cobranca->pago_em->toDateString()]);
        $this->assertSame([], $this->registros());
    }

    public function test_so_cobranca_paga_tem_o_pagamento_corrigido(): void
    {
        $pendente = Cobranca::factory()->for($this->usuario)->create();

        $this->actingAs($this->usuario)->post("/cobrancas/{$pendente->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'x'])
            ->assertSessionHas('erro');

        $this->assertSame(StatusCobranca::Pendente, $pendente->fresh()->status);
        $this->assertSame([], $this->registros());
    }

    public function test_tentativas_de_senha_sao_limitadas(): void
    {
        $cobranca = $this->paga($this->usuario);
        $this->actingAs($this->usuario);

        for ($tentativa = 1; $tentativa <= 6; $tentativa++) {
            $this->post("/cobrancas/{$cobranca->id}/desfazer-baixa", ['senha' => "errada{$tentativa}", 'motivo' => 'x'])->assertSessionHasErrors('senha');
        }

        $this->post("/cobrancas/{$cobranca->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'x'])->assertTooManyRequests();
        $this->assertSame(StatusCobranca::Paga, $cobranca->fresh()->status);
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        $propria = $this->paga($this->usuario);
        $this->actingAs($this->usuario)->post("/cobrancas/{$propria->id}/corrigir-pagamento", ['pago_em' => '2026-05-17', 'senha' => 'password', 'motivo' => 'x'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $alheia = $this->paga(User::factory()->create());
        $this->post("/cobrancas/{$alheia->id}/corrigir-pagamento", ['pago_em' => '2026-05-17', 'senha' => 'password', 'motivo' => 'x'])->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'x'])->assertNotFound();
        $this->assertSame('2026-06-17', $alheia->fresh()->pago_em->toDateString());
    }

    public function test_cancelar_e_editar_cobranca_ficam_registrados(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $produto = ProdutoServico::factory()->for($this->usuario)->create();
        $this->actingAs($this->usuario)->post('/cobrancas', [
            'cliente_id' => $cliente->id, 'descricao' => '', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-20', 'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '100,00', 'principal' => true]],
        ])->assertRedirect('/cobrancas');
        $cobranca = $this->usuario->cobrancas()->sole();

        $this->put("/cobrancas/{$cobranca->id}", [
            'cliente_id' => $cliente->id, 'descricao' => '', 'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-25', 'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '120,00', 'principal' => true]],
        ])->assertRedirect('/cobrancas');
        $this->post("/cobrancas/{$cobranca->id}/cancelar")->assertRedirect();

        $this->assertSame([
            ['cobranca.editada', 'Cobrança editada (antes: R$ 100,00, vence 20/10/2026)'],
            ['cobranca.cancelada', 'Cobrança cancelada (R$ 120,00, vence 25/10/2026)'],
        ], array_map(fn (array $registro) => [$registro['acao'], $registro['resumo']], $this->registros()));
    }

    public function test_encerrar_recomecar_e_corrigir_contrato_ficam_registrados(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $produto = ProdutoServico::factory()->for($this->usuario)->create();
        $dados = fn (array $extra = []) => array_merge([
            'cliente_id' => $cliente->id, 'descricao' => '', 'periodicidade' => 'mensal', 'modalidade' => 'prepago',
            'dia_vencimento' => 17, 'data_inicio' => '2026-10-10', 'ativo' => true,
            'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '100,00', 'principal' => true]],
        ], $extra);
        $this->actingAs($this->usuario)->post('/contratos', $dados())->assertRedirect('/contratos');
        $contrato = $this->usuario->contratos()->sole();
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $this->put("/contratos/{$contrato->id}", $dados(['data_inicio' => '2026-09-10', 'corrigir' => true]))->assertRedirect('/contratos');
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $this->post("/contratos/{$contrato->id}/recomecar", $dados(['data_inicio' => '2026-11-10']))->assertRedirect();
        $novo = $this->usuario->contratos()->whereKeyNot($contrato->id)->sole();
        $this->post("/contratos/{$novo->id}/encerrar", ['fim' => '2026-11-20'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame([
            ['contrato.corrigido', $contrato->id, 'Contrato corrigido (antes: início 10/10/2026, mensal, pré-pago)'],
            ['contrato.encerrado', $contrato->id, 'Encerramento agendado para 09/11/2026'],
            ['contrato.recomecado', $contrato->id, "Contrato recomeçado: novo contrato nº {$novo->id} a partir de 10/11/2026"],
            ['contrato.encerrado', $novo->id, 'Encerramento agendado para 20/11/2026'],
        ], array_map(fn (array $registro) => [$registro['acao'], $registro['alvo_id'], $registro['resumo']], $this->registros()));
    }

    public function test_historico_aparece_na_linha_da_cobranca(): void
    {
        $cobranca = $this->paga($this->usuario);
        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/corrigir-pagamento", [
            'pago_em' => '2026-05-17', 'senha' => 'password', 'motivo' => 'Data digitada errada',
        ]);

        $this->getJson('/cobrancas/dados?mes=2026-05')
            ->assertJsonPath('linhas.0.historico', ['10/10/2026 · Pagamento corrigido (antes: 17/06/2026)']);
    }

    public function test_tela_de_auditoria_lista_so_os_registros_do_usuario_no_periodo(): void
    {
        $cobranca = $this->paga($this->usuario);
        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'Baixa errada']);
        $outro = User::factory()->create();
        $alheia = $this->paga($outro);
        $this->actingAs($outro)->post("/cobrancas/{$alheia->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'Do outro']);

        $this->actingAs($this->usuario)->get('/auditoria')->assertInertia(fn (Assert $pagina) => $pagina
            ->component('Auditoria/Index')
            ->missing('registros')
            ->where('filtros', ['de' => '2026-09-10', 'ate' => '2026-10-10', 'pagina' => 1, 'por_pagina' => 25])
            ->where('padroes', ['de' => '2026-09-10', 'ate' => '2026-10-10']));

        $this->getJson('/auditoria/dados')->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.resumo', 'Baixa desfeita (estava paga em 17/06/2026)')
            ->assertJsonPath('linhas.0.motivo', 'Baixa errada')
            ->assertJsonPath('linhas.0.quando', '10/10/2026 10:00')
            ->assertJsonPath('linhas.0.link', '/cobrancas?mes=2026-05');

        $this->getJson('/auditoria/dados?de=2026-10-11&ate=2026-10-31')->assertOk()->assertJsonCount(0, 'linhas');
        $this->getJson('/auditoria/dados?de=2026-10-11&ate=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('ate');
    }

    public function test_ano_com_mais_de_quatro_digitos_e_recusado_e_nao_vira_outra_data(): void
    {
        // O campo de data do Chrome aceita até 6 dígitos no ano; a regra "date" lia 72026-08-01 como 2006-08-01.
        $this->actingAs($this->usuario)->getJson('/auditoria/dados?de=72026-08-01&ate=2026-10-11')
            ->assertUnprocessable()->assertJsonValidationErrors('de');
    }
}
