<?php

namespace Tests\Feature\Temas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Feriado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * `GET /painel/mes` (Tarefa 5.1): os pontos da linha do tempo e os resumos de vencidas, de hoje e dos meses anteriores.
 * "Hoje" é sexta, 09/10/2026; 12/10 é Nossa Senhora Aparecida (segunda).
 */
class PainelMesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();
    }

    /** @param array<string, mixed> $dados */
    private function cobranca(array $dados = [], ?User $dono = null): Cobranca
    {
        $dono ??= $this->usuario;

        return Cobranca::factory()->for($dono)->for(Cliente::factory()->for($dono))->create($dados + ['vencimento' => '2026-10-20']);
    }

    /** @return array<string, mixed> */
    private function paga(string $pagoEm, string $vencimento = '2026-10-20'): array
    {
        return ['status' => StatusCobranca::Paga, 'pago_em' => $pagoEm, 'vencimento' => $vencimento];
    }

    /** @return array<string, mixed> */
    private function mes(): array
    {
        return $this->actingAs($this->usuario)->getJson('/painel/mes')->assertOk()->json();
    }

    /** @param list<Cobranca> $cobrancas */
    private function ids(array $cobrancas): array
    {
        $ids = array_map(fn (Cobranca $cobranca) => $cobranca->id, $cobrancas);
        sort($ids);

        return $ids;
    }

    /** @param array<string, mixed> $resposta */
    private function idsDosPontos(array $resposta): array
    {
        $ids = array_column($resposta['pontos'], 'id');
        sort($ids);

        return $ids;
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/painel/mes')->assertRedirect('/entrar');
    }

    public function test_devolve_o_mes_e_a_forma_dos_dados(): void
    {
        $this->cobranca(['valor_centavos' => 15000]);

        $this->actingAs($this->usuario)->getJson('/painel/mes')->assertOk()
            ->assertJsonPath('mes', '2026-10')
            ->assertJsonPath('hoje', 9)
            ->assertJsonPath('dias', 31)
            ->assertJsonStructure([
                'feriados', 'fim_de_semana',
                'pontos' => [['id', 'cliente', 'valor_centavos', 'vencimento_dia', 'etapa', 'situacao', 'pago_dia', 'pago_centavos']],
                'vencidas' => ['quantidade', 'soma_centavos', 'itens'],
                'hoje_resumo' => ['vence_hoje' => ['quantidade', 'soma_centavos'], 'para_avisar', 'para_enviar'],
                'anteriores' => ['quantidade', 'soma_centavos'],
            ]);
    }

    public function test_so_traz_cobrancas_do_usuario_logado(): void
    {
        $outro = User::factory()->create();
        $noMes = $this->cobranca(['vencimento' => '2026-10-20']);
        $deHoje = $this->cobranca(['vencimento' => '2026-10-09', 'valor_centavos' => 2000]);
        $vencida = $this->cobranca(['vencimento' => '2026-09-15', 'valor_centavos' => 1000]);

        // O outro usuário tem de tudo: ponto, vencida de mês anterior, vence hoje, a avisar, a enviar e paga no mês.
        $this->cobranca(['vencimento' => '2026-10-21', 'valor_centavos' => 7700], $outro);
        $this->cobranca(['vencimento' => '2026-08-10', 'valor_centavos' => 7700], $outro);
        $this->cobranca(['vencimento' => '2026-10-09', 'valor_centavos' => 7700], $outro);
        $this->cobranca(['vencimento' => '2026-10-11', 'valor_centavos' => 7700], $outro);
        $this->cobranca($this->paga('2026-10-05'), $outro);

        $resposta = $this->mes();

        $this->assertSame($this->ids([$noMes, $deHoje]), $this->idsDosPontos($resposta));
        $this->assertSame(1, $resposta['vencidas']['quantidade']);
        $this->assertSame(1000, $resposta['vencidas']['soma_centavos']);
        $this->assertSame([$vencida->id], array_column($resposta['vencidas']['itens'], 'id'));
        $this->assertSame(['quantidade' => 1, 'soma_centavos' => 1000], $resposta['anteriores']);
        $this->assertSame(['quantidade' => 1, 'soma_centavos' => 2000], $resposta['hoje_resumo']['vence_hoje']);
        $this->assertSame(1, $resposta['hoje_resumo']['para_avisar']);
        $this->assertSame(3, $resposta['hoje_resumo']['para_enviar']);
    }

    public function test_vencida_de_mes_anterior_entra_em_vencidas_e_anteriores(): void
    {
        $this->cobranca(['vencimento' => '2026-08-01', 'valor_centavos' => 2000]);
        $this->cobranca(['vencimento' => '2026-09-20', 'valor_centavos' => 1000]);
        $this->cobranca(['vencimento' => '2026-10-05', 'valor_centavos' => 4000]);
        $this->cobranca(['vencimento' => '2026-10-07', 'valor_centavos' => 8000]);
        // Não são pendentes: nem vencidas nem anteriores.
        $this->cobranca($this->paga('2026-09-10', '2026-09-05'));
        $this->cobranca(['vencimento' => '2026-09-05', 'status' => StatusCobranca::Cancelada]);

        $resposta = $this->mes();

        $this->assertSame(['quantidade' => 4, 'soma_centavos' => 15000], array_intersect_key($resposta['vencidas'], ['quantidade' => 0, 'soma_centavos' => 0]));
        $this->assertSame(['quantidade' => 2, 'soma_centavos' => 3000], $resposta['anteriores']);
        // As 3 mais antigas, da mais antiga para a mais nova, com os dias de atraso de hoje (09/10).
        $this->assertSame([69, 19, 4], array_column($resposta['vencidas']['itens'], 'dias_de_atraso'));
        $this->assertSame([2000, 1000, 4000], array_column($resposta['vencidas']['itens'], 'valor_centavos'));
        $this->assertSame(['id', 'cliente', 'valor_centavos', 'dias_de_atraso'], array_keys($resposta['vencidas']['itens'][0]));
        // As de setembro e agosto não são ponto do mês; as de outubro são, e vencidas.
        $this->assertSame([5, 7], array_column($resposta['pontos'], 'vencimento_dia'));
        $this->assertSame(['vencida', 'vencida'], array_column($resposta['pontos'], 'situacao'));
    }

    public function test_pago_fora_do_mes_nao_entra_nos_pontos(): void
    {
        $this->cobranca($this->paga('2026-09-30', '2026-10-20'));
        $this->cobranca($this->paga('2026-11-02', '2026-10-20'));
        $this->cobranca(['vencimento' => '2026-11-01']);
        $this->cobranca(['vencimento' => '2026-10-15', 'status' => StatusCobranca::Cancelada]);
        // Os limites do mês entram: dia 1 e dia 31, pendente ou paga.
        $inicio = $this->cobranca(['vencimento' => '2026-10-01']);
        $fim = $this->cobranca(['vencimento' => '2026-10-31']);
        $pagaNoInicio = $this->cobranca($this->paga('2026-10-01', '2026-09-25'));
        $pagaNoFim = $this->cobranca($this->paga('2026-10-31', '2026-09-25'));

        $this->assertSame($this->ids([$inicio, $fim, $pagaNoInicio, $pagaNoFim]), $this->idsDosPontos($this->mes()));
    }

    public function test_pago_no_mes_usa_o_dia_do_pagamento(): void
    {
        $paga = $this->cobranca($this->paga('2026-10-08', '2026-10-05') + [
            'valor_centavos' => 10000, 'desconto_centavos' => 500, 'multa_centavos' => 100, 'juros_centavos' => 50, 'outros_centavos' => 25,
        ]);
        $pendente = $this->cobranca(['vencimento' => '2026-10-20', 'valor_centavos' => 3000]);

        $pontos = collect($this->mes()['pontos'])->keyBy('id');

        $this->assertEquals(
            ['vencimento_dia' => 5, 'pago_dia' => 8, 'etapa' => 'recebida', 'situacao' => 'paga', 'valor_centavos' => 10000, 'pago_centavos' => 9675],
            collect($pontos[$paga->id])->only(['vencimento_dia', 'pago_dia', 'etapa', 'situacao', 'valor_centavos', 'pago_centavos'])->all(),
        );
        $this->assertNull($pontos[$pendente->id]['pago_dia']);
        $this->assertNull($pontos[$pendente->id]['pago_centavos']);
        $this->assertSame('a_vencer', $pontos[$pendente->id]['situacao']);
    }

    public function test_etapas_batem_com_as_listas_do_painel(): void
    {
        $this->cobranca(['vencimento' => '2026-10-12']);
        $this->cobranca(['vencimento' => '2026-10-20']);
        $this->cobranca(['vencimento' => '2026-09-02']);
        $this->cobranca(['vencimento' => '2026-10-14', 'enviado_em' => '2026-10-02 09:00:00']);
        $this->cobranca(['vencimento' => '2026-10-25', 'enviado_em' => '2026-10-03 09:00:00']);
        $this->cobranca($this->paga('2026-10-03', '2026-10-01') + ['desconto_centavos' => 300]);
        $this->cobranca($this->paga('2026-10-06', '2026-09-20') + ['multa_centavos' => 450]);
        $this->cobranca($this->paga('2026-09-29', '2026-09-20'));

        $resposta = $this->mes();
        $etapas = array_count_values(array_column($resposta['pontos'], 'etapa'));
        $lista = fn (string $nome) => $this->getJson("/painel/dados?lista={$nome}")->assertOk()->json('total_geral');

        // "Recibos a enviar" atravessa meses; a pendente de setembro está nele e não é ponto do mês.
        $paraEnviar = $lista('para_enviar');
        $this->assertSame($paraEnviar['quantidade'], $resposta['hoje_resumo']['para_enviar']);
        $this->assertSame($paraEnviar['quantidade'] - $resposta['anteriores']['quantidade'], $etapas['enviar']);
        $this->assertSame($lista('aguardando')['quantidade'], $etapas['aguardando']);
        $recebidas = $lista('recebidas');
        $this->assertSame($recebidas['quantidade'], $etapas['recebida']);
        $this->assertSame($recebidas['soma_centavos'], array_sum(array_column(array_filter($resposta['pontos'], fn (array $ponto) => $ponto['etapa'] === 'recebida'), 'pago_centavos')));
    }

    /** O "Previsto" do Talão (servidor) e o da Régua e do Almanaque (soma dos pontos, no navegador) são o mesmo número. */
    public function test_previsto_do_painel_e_a_soma_dos_pontos_do_mes(): void
    {
        $this->cobranca(['valor_centavos' => 10000]);                                                                       // a enviar
        $this->cobranca(['valor_centavos' => 4000, 'enviado_em' => now()]);                                                 // aguardando
        $this->cobranca(['valor_centavos' => 21000, 'desconto_centavos' => 2000] + $this->paga('2026-10-02', '2026-10-03')); // paga: 190,00
        $this->cobranca(['valor_centavos' => 5000] + $this->paga('2026-10-04', '2026-09-25'));                              // venceu em setembro, pagou em outubro
        $this->cobranca(['valor_centavos' => 7000] + $this->paga('2026-09-20', '2026-10-15'));                              // pagou em setembro: fora
        $this->cobranca(['valor_centavos' => 9000, 'status' => StatusCobranca::Cancelada]);                                 // cancelada: fora

        $somaDosPontos = array_sum(array_map(
            fn (array $ponto) => $ponto['etapa'] === 'recebida' ? $ponto['pago_centavos'] : $ponto['valor_centavos'],
            $this->mes()['pontos'],
        ));

        $this->assertSame(38000, $somaDosPontos);
        $this->actingAs($this->usuario)->get('/painel')->assertInertia(fn (Assert $page) => $page->where('resumo.previsto', '380,00'));
    }

    public function test_resumo_de_hoje(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $this->cobranca(['vencimento' => '2026-10-09', 'valor_centavos' => 3000]);
        $this->cobranca(['vencimento' => '2026-10-09', 'valor_centavos' => 500, 'enviado_em' => '2026-10-08 09:00:00']);
        $this->cobranca(['vencimento' => '2026-10-12', 'avisado_em' => '2026-10-08 09:00:00']); // já avisada
        $this->cobranca(['vencimento' => '2026-10-14']); // último dia da janela de 5 dias
        $this->cobranca(['vencimento' => '2026-10-15']); // fora da janela
        $this->cobranca(['vencimento' => '2026-10-09', 'status' => StatusCobranca::Cancelada]);
        $this->cobranca($this->paga('2026-10-09', '2026-10-09'));

        $resumo = $this->mes()['hoje_resumo'];

        $this->assertSame(['quantidade' => 2, 'soma_centavos' => 3500], $resumo['vence_hoje']);
        $this->assertSame(3, $resumo['para_avisar']); // as duas de hoje e a do dia 14
        $this->assertSame(4, $resumo['para_enviar']); // as pendentes sem envio: todas, menos a enviada
    }

    public function test_marca_feriado_e_fim_de_semana(): void
    {
        Feriado::factory()->for($this->usuario)->create(['descricao' => 'Aniversário da cidade', 'tipo' => 'anual', 'dia' => 20, 'mes' => 10]);
        Feriado::factory()->for($this->usuario)->create(['descricao' => 'Outro mês', 'tipo' => 'anual', 'dia' => 20, 'mes' => 11]);
        Feriado::factory()->for(User::factory())->create(['descricao' => 'Do outro usuário', 'tipo' => 'anual', 'dia' => 22, 'mes' => 10]);

        $resposta = $this->mes();

        // Feriado em fim de semana não aparece em `feriados`: o dia já está em `fim_de_semana`.
        $this->assertSame([12 => 'Nossa Senhora Aparecida', 20 => 'Aniversário da cidade'], $this->deObjeto($resposta['feriados']));
        $this->assertSame([3, 4, 10, 11, 17, 18, 24, 25, 31], $resposta['fim_de_semana']);

        // Mês sem feriado: objeto vazio (`{}`), não lista vazia.
        $this->travelTo(CarbonImmutable::parse('2026-08-10 10:00:00'));
        $agosto = $this->actingAs($this->usuario)->getJson('/painel/mes')->assertOk();
        $this->assertStringContainsString('"feriados":{}', $agosto->getContent());
        $this->assertSame([1, 2, 8, 9, 15, 16, 22, 23, 29, 30], $agosto->json('fim_de_semana'));
    }

    /** @return array<int, string> */
    private function deObjeto(mixed $feriados): array
    {
        return collect($feriados)->mapWithKeys(fn (string $nome, $dia) => [(int) $dia => $nome])->all();
    }

    public function test_numero_de_consultas_nao_cresce_com_as_cobrancas(): void
    {
        $this->cobrancasDeTodosOsTipos(1);
        $this->mes(); // aquece: a primeira chamada não conta

        $antes = $this->consultas();
        $this->cobrancasDeTodosOsTipos(9);
        $depois = $this->consultas();

        $this->assertSame($antes, $depois, 'o número de consultas cresceu com as cobranças');
    }

    private function cobrancasDeTodosOsTipos(int $vezes): void
    {
        for ($i = 0; $i < $vezes; $i++) {
            $this->cobranca(['vencimento' => '2026-10-20']);
            $this->cobranca(['vencimento' => '2026-10-14', 'enviado_em' => '2026-10-02 09:00:00']);
            $this->cobranca(['vencimento' => '2026-10-09']);
            $this->cobranca(['vencimento' => '2026-10-02']);
            $this->cobranca(['vencimento' => '2026-09-02']);
            $this->cobranca($this->paga('2026-10-05', '2026-10-01'));
            $this->cobranca($this->paga('2026-09-05', '2026-09-01'));
            $this->cobranca(['vencimento' => '2026-10-10', 'status' => StatusCobranca::Cancelada]);
        }
    }

    private function consultas(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->mes();
        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
