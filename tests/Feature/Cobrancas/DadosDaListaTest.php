<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DadosDaListaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    /** Cobranças avulsas de outubro, uma por dia a partir de 16/10 (todas "a vencer"), de R$ 1,00, 2,00, 3,00… */
    private function avulsas(int $quantidade): void
    {
        for ($dia = 0; $dia < $quantidade; $dia++) {
            Cobranca::factory()->for($this->usuario)->for($this->cliente)->create([
                'vencimento' => CarbonImmutable::parse('2026-10-16')->addDays(intdiv($dia, 4))->toDateString(),
                'valor_centavos' => ($dia + 1) * 100,
            ]);
        }
    }

    private function dados(string $consulta = ''): TestResponse
    {
        return $this->actingAs($this->usuario)->getJson('/cobrancas/dados'.$consulta);
    }

    public function test_devolve_um_bloco_de_quatro_paginas_a_partir_da_pedida(): void
    {
        $this->avulsas(60);

        $this->dados('?por_pagina=25')->assertOk()
            ->assertJsonCount(60, 'linhas') // 4 páginas de 25 = 100, mas só existem 60
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 25, 'total' => 60]);

        $resposta = $this->dados('?por_pagina=25&pagina=2')->assertOk();
        $resposta->assertJsonCount(35, 'linhas')->assertJsonPath('pagina.inicio', 2);
        $this->assertSame('26,00', $resposta->json('linhas.0.valor'));
    }

    public function test_pagina_alem_da_ultima_devolve_a_ultima(): void
    {
        $this->avulsas(30);

        $this->dados('?por_pagina=25&pagina=9')->assertOk()
            ->assertJsonPath('pagina.inicio', 2)
            ->assertJsonCount(5, 'linhas');
        $this->dados('?por_pagina=25&pagina=1')->assertJsonPath('pagina.inicio', 1);
    }

    public function test_por_pagina_e_pagina_sao_validados(): void
    {
        $this->dados('?por_pagina=30')->assertUnprocessable()->assertJsonValidationErrors('por_pagina');
        $this->dados('?pagina=0')->assertUnprocessable()->assertJsonValidationErrors('pagina');
    }

    public function test_totais_por_situacao_somam_o_resultado_inteiro_sem_canceladas_no_total(): void
    {
        $this->avulsas(30); // R$ 1,00 + … + 30,00 = R$ 465,00, todas a vencer
        $paga = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-05', 'valor_centavos' => 1000]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05'])->save();
        Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-10', 'valor_centavos' => 2000]); // vencida
        $cancelada = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-11', 'valor_centavos' => 4000]);
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();

        $this->dados('?canceladas=1&por_pagina=25')->assertOk()
            ->assertJsonCount(33, 'linhas')
            ->assertJsonPath('totais', [
                ['situacao' => 'vencida', 'quantidade' => 1, 'soma_centavos' => 2000],
                ['situacao' => 'a_vencer', 'quantidade' => 30, 'soma_centavos' => 46500],
                ['situacao' => 'paga', 'quantidade' => 1, 'soma_centavos' => 1000],
                ['situacao' => 'cancelada', 'quantidade' => 1, 'soma_centavos' => 4000],
            ])
            ->assertJsonPath('total_geral', ['quantidade' => 32, 'soma_centavos' => 49500]);

        // A página 2 mostra só uma parte, mas os totais continuam sendo do resultado inteiro.
        $this->dados('?canceladas=1&por_pagina=25&pagina=2')->assertJsonPath('total_geral.soma_centavos', 49500);
    }

    public function test_previstas_entram_mescladas_e_somadas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(); // dia 21, R$ 260,60, nada gerado aqui

        $resposta = $this->dados('?mes=2026-10&ate=2026-12')->assertOk();

        $this->assertSame(['prevista', 'prevista', 'prevista'], array_column($resposta->json('linhas'), 'situacao'));
        $resposta->assertJsonPath('totais', [['situacao' => 'prevista', 'quantidade' => 3, 'soma_centavos' => 78180]]);
    }

    public function test_linha_real_traz_valor_em_centavos_e_filtro_de_cliente_vale(): void
    {
        $this->avulsas(1);
        $outro = Cliente::factory()->for($this->usuario)->create();
        Cobranca::factory()->for($this->usuario)->for($outro)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 999]);

        $this->dados("?cliente_id={$outro->id}")->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.valor_centavos', 999)
            ->assertJsonPath('filtros.cliente_id', $outro->id);
    }

    public function test_linha_paga_traz_o_valor_cheio_o_desconto_e_o_valor_pago(): void
    {
        $nova = function (int $valor, int $desconto) {
            $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-05', 'valor_centavos' => $valor]);
            $cobranca->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05', 'desconto_centavos' => $desconto])->save();
        };
        $nova(24757, 2476);
        $nova(10000, 0); // paga antes da Fase 26: sem desconto, valor pago = valor

        $linhas = $this->dados('?status=paga')->assertOk()->json('linhas');

        $this->assertSame(['247,57', '222,81', 2476], [$linhas[0]['valor'], $linhas[0]['valor_pago'], $linhas[0]['desconto_centavos']]);
        $this->assertSame(['100,00', '100,00', 0], [$linhas[1]['valor'], $linhas[1]['valor_pago'], $linhas[1]['desconto_centavos']]);
    }

    /**
     * Cobrança de um contrato inativo (a previsão não entra) com as condições da factory (15% até o vencimento, multa
     * 2%, juros 1% ao mês); `$contrato` troca ou zera campos do contrato.
     *
     * @param  array<string, mixed>  $contrato
     */
    private function cobrancaDeContrato(string $vencimento, int $valor, bool $comCondicoes = true, array $contrato = []): Cobranca
    {
        $fabrica = Contrato::factory()->for($this->usuario)->for($this->cliente);
        $registro = ($comCondicoes ? $fabrica->comCondicoes() : $fabrica)->create(array_merge(['ativo' => false], $contrato));

        return Cobranca::factory()->for($this->usuario)->for($this->cliente)
            ->create(['contrato_id' => $registro->id, 'vencimento' => $vencimento, 'valor_centavos' => $valor]);
    }

    private function pagar(Cobranca $cobranca, array $valores = []): Cobranca
    {
        $cobranca->forceFill(array_merge(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05'], $valores))->save();

        return $cobranca;
    }

    /** Fase 27: pendente com regra mostra o valor de hoje; o dia do vencimento ainda é "até". */
    public function test_pendente_com_regra_traz_a_linha_do_dia_ate_o_vencimento_ou_o_valor_de_hoje(): void
    {
        $aVencer = $this->cobrancaDeContrato('2026-10-20', 10000); // 15% de desconto: 85,00
        $venceHoje = $this->cobrancaDeContrato('2026-10-15', 20000); // no dia do vencimento o desconto ainda vale: 170,00
        $emAtraso = $this->cobrancaDeContrato(CarbonImmutable::parse('2026-10-15')->subDays(300)->toDateString(), 21000); // 210,00 + multa 4,20 + juros 21,00

        $linhas = collect($this->dados('?status=pendente&mes=2025-12&ate=2026-10')->assertOk()->json('linhas'))->keyBy('id');

        $this->assertCount(3, $linhas);
        $this->assertSame('até 20/10: R$ 85,00', $linhas[$aVencer->id]['linha_do_dia']);
        $this->assertSame('até 15/10: R$ 170,00', $linhas[$venceHoje->id]['linha_do_dia']);
        $this->assertSame('hoje R$ 235,20', $linhas[$emAtraso->id]['linha_do_dia']);
        $this->assertSame('100,00', $linhas[$aVencer->id]['valor']); // o valor cheio segue na linha
    }

    public function test_linha_do_dia_e_nula_sem_regra_sem_contrato_paga_cancelada_ou_valor_do_dia_igual(): void
    {
        $semContrato = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 10000]);
        $semRegra = $this->cobrancaDeContrato('2026-10-20', 10000, comCondicoes: false);
        $paga = $this->pagar($this->cobrancaDeContrato('2026-10-05', 10000));
        $cancelada = $this->cobrancaDeContrato('2026-10-20', 10000);
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();
        $soMultaAVencer = $this->cobrancaDeContrato('2026-10-20', 10000, contrato: ['desconto_pontualidade_tipo' => null, 'desconto_pontualidade_valor' => null]);
        $soDescontoEmAtraso = $this->cobrancaDeContrato('2026-10-05', 10000, contrato: ['multa_tipo' => null, 'multa_valor' => null, 'juros_tipo' => null, 'juros_valor' => null]);

        $linhas = collect($this->dados('?canceladas=1')->assertOk()->json('linhas'))->keyBy('id');

        $ids = [$semContrato, $semRegra, $paga, $cancelada, $soMultaAVencer, $soDescontoEmAtraso];
        $this->assertCount(count($ids), $linhas);
        foreach ($ids as $cobranca) {
            $this->assertNull($linhas[$cobranca->id]['linha_do_dia'], "cobrança {$cobranca->id}");
        }
    }

    public function test_pago_diferente_so_vale_para_paga_com_valor_pago_diferente_do_valor(): void
    {
        $comDesconto = $this->pagar($this->cobrancaDeContrato('2026-10-05', 10000), ['desconto_centavos' => 1000]);
        $comAcrescimos = $this->pagar($this->cobrancaDeContrato('2026-10-05', 10000), ['multa_centavos' => 200, 'juros_centavos' => 50, 'outros_centavos' => 500]);
        $seAnulam = $this->pagar($this->cobrancaDeContrato('2026-10-05', 10000), ['desconto_centavos' => 500, 'outros_centavos' => 500]);
        $igual = $this->pagar($this->cobrancaDeContrato('2026-10-05', 10000));
        $pendente = $this->cobrancaDeContrato('2026-10-20', 10000);

        $linhas = collect($this->dados('?status=paga')->assertOk()->json('linhas'))->keyBy('id');
        $pendentes = collect($this->dados('?status=pendente')->assertOk()->json('linhas'))->keyBy('id');

        $this->assertSame([true, '90,00'], [$linhas[$comDesconto->id]['pago_diferente'], $linhas[$comDesconto->id]['valor_pago']]);
        $this->assertSame([true, '107,50'], [$linhas[$comAcrescimos->id]['pago_diferente'], $linhas[$comAcrescimos->id]['valor_pago']]);
        $this->assertSame([false, '100,00'], [$linhas[$seAnulam->id]['pago_diferente'], $linhas[$seAnulam->id]['valor_pago']]);
        $this->assertSame([false, '100,00'], [$linhas[$igual->id]['pago_diferente'], $linhas[$igual->id]['valor_pago']]);
        $this->assertFalse($pendentes[$pendente->id]['pago_diferente']);
    }

    public function test_cobranca_paga_traz_a_data_do_pagamento_para_a_correcao(): void
    {
        $paga = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-05']);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-28'])->save();
        $pendente = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-20']);

        $linhas = collect($this->dados()->assertOk()->json('linhas'))->keyBy('id');

        $this->assertSame('2026-10-28', $linhas[$paga->id]['pago_em_iso']);
        $this->assertNull($linhas[$pendente->id]['pago_em_iso']);
    }

    public function test_filtro_de_contrato_mostra_so_as_cobrancas_daquele_contrato(): void
    {
        // Encerrados: sem isso, a previsão calculada pelo próprio contrato entra na lista e confunde a contagem.
        $contratoA = Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['ativo' => false]);
        $contratoB = Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['ativo' => false]);
        $daA = Cobranca::factory()->for($this->usuario)->for($this->cliente)->for($contratoA)
            ->create(['vencimento' => '2026-10-20', 'valor_centavos' => 1000]);
        Cobranca::factory()->for($this->usuario)->for($this->cliente)->for($contratoB)
            ->create(['vencimento' => '2026-10-21', 'valor_centavos' => 2000]);

        $this->dados("?contrato_id={$contratoA->id}")->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.id', $daA->id)
            ->assertJsonPath('filtros.contrato_id', $contratoA->id);
    }

    public function test_so_dados_do_proprio_usuario_e_nada_gravado(): void
    {
        // Antes do actingAs (ele vale até o fim do teste): sem sessão, o JSON não responde.
        $this->getJson('/cobrancas/dados')->assertUnauthorized();
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        Cobranca::factory()->create(['vencimento' => '2026-10-20']); // de outro usuário
        $antes = [Cobranca::count(), Recibo::count()];

        $this->dados('?mes=2026-09&ate=2026-12')->assertOk()->assertJsonPath('pagina.total', 3); // 3 previstas, sem a alheia

        $this->assertSame($antes, [Cobranca::count(), Recibo::count()]);
    }

    public function test_periodo_invalido_responde_422_com_a_mensagem(): void
    {
        $this->dados('?mes=2026-10&ate=2026-09')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->dados('?mes=2026-01&ate=2029-01')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->dados('?ate=outubro')->assertUnprocessable()->assertJsonValidationErrors('ate');
    }
}
