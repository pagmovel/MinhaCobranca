<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\ValoresDaBaixa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcrescimosNaBaixaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PERCENTUAL_INVALIDO = 'Percentual inválido: use de 0 a 100, com até 2 casas decimais.';

    private const REAIS_INVALIDO = 'Valor inválido: use reais, como 15,00, de no máximo R$ 99.999.999,99.';

    /**
     * Cobrança de R$ 210,00 vencida em 05/01/2026, de um contrato com desconto de 15%, multa de 2% e juros de 1% ao mês;
     * o relógio fica em 01/11/2026 (300 dias de atraso: multa R$ 4,20 e juros R$ 21,00).
     *
     * @return array{0: User, 1: Cobranca, 2: Contrato}
     */
    private function cenario(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $usuario = User::factory()->create();
        $contrato = Contrato::factory()->comCondicoes()->create(['user_id' => $usuario->id]);
        $cobranca = Cobranca::factory()->create([
            'user_id' => $usuario->id,
            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'vencimento' => '2026-01-05',
            'valor_centavos' => 21000,
        ]);
        $this->actingAs($usuario);

        return [$usuario, $cobranca, $contrato];
    }

    private function resumoDaAuditoria(User $usuario): string
    {
        return $usuario->auditorias()->where('acao', 'cobranca.paga')->sole()->resumo;
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} desconto, multa, juros e outros gravados */
    private function gravados(Cobranca $cobranca): array
    {
        $cobranca->refresh();

        return [$cobranca->desconto_centavos, $cobranca->multa_centavos, $cobranca->juros_centavos, $cobranca->outros_centavos];
    }

    public function test_pedido_sem_campos_usa_o_contrato(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01'])->assertSessionHas('sucesso', 'Baixa registrada.');

        $this->assertSame([0, 420, 2100, 0], $this->gravados($cobranca));
        $this->assertSame(23520, $cobranca->valorPagoCentavos());
        $this->assertSame(
            'Baixa registrada (R$ 210,00, multa R$ 4,20, juros R$ 21,00, desconto R$ 0,00, pago R$ 235,20, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_valor_digitado_vence_o_contrato(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", [
            'pago_em' => '2026-11-01',
            'multa_tipo' => 'valor', 'multa' => '10,00',
            'juros_tipo' => 'percentual', 'juros' => '2',
            'desconto_tipo' => 'valor', 'desconto' => '5,00',
        ])->assertSessionHas('sucesso');

        $this->assertSame([500, 1000, 4200, 0], $this->gravados($cobranca));
        $this->assertSame(25700, $cobranca->valorPagoCentavos());
    }

    public function test_juros_em_reais_digitado_e_ao_mes(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'juros_tipo' => 'valor', 'juros' => '3,00'])
            ->assertSessionHas('sucesso');

        // R$ 3,00 ao mês × 300 dias ÷ 30.
        $this->assertSame(3000, $cobranca->fresh()->juros_centavos);
    }

    public function test_campo_vazio_fica_sem_valor(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", [
            'pago_em' => '2026-11-01',
            'multa_tipo' => 'percentual', 'multa' => '',
            'juros_tipo' => 'percentual', 'juros' => '',
            'outros' => '',
            'desconto_tipo' => 'percentual', 'desconto' => '',
        ])->assertSessionHas('sucesso');

        $this->assertSame([0, 0, 0, 0], $this->gravados($cobranca));
        $this->assertSame(21000, $cobranca->valorPagoCentavos());
    }

    public function test_zero_fica_sem_valor(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", [
            'pago_em' => '2026-11-01',
            'multa_tipo' => 'percentual', 'multa' => '0',
            'juros_tipo' => 'valor', 'juros' => '0,00',
            'outros' => '0',
        ])->assertSessionHas('sucesso');

        $this->assertSame([0, 0, 0, 0], $this->gravados($cobranca));
    }

    public function test_outros_soma_e_audita(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'outros' => '5,00'])->assertSessionHas('sucesso');

        $this->assertSame([0, 420, 2100, 500], $this->gravados($cobranca));
        $this->assertSame(24020, $cobranca->valorPagoCentavos());
        $this->assertSame(
            'Baixa registrada (R$ 210,00, multa R$ 4,20, juros R$ 21,00, outros R$ 5,00, desconto R$ 0,00, pago R$ 240,20, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_com_acrescimo_a_auditoria_mostra_o_desconto_mesmo_integral(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", [
            'pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '100', 'outros' => '5,00',
            'multa_tipo' => 'percentual', 'multa' => '', 'juros_tipo' => 'percentual', 'juros' => '',
        ])->assertSessionHas('sucesso');

        $this->assertSame(
            'Baixa registrada (R$ 210,00, outros R$ 5,00, desconto 100% = R$ 210,00, pago R$ 5,00, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_sem_atraso_zera_acrescimos_mesmo_preenchidos(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", [
            'pago_em' => '2026-01-05',
            'multa_tipo' => 'percentual', 'multa' => '2',
            'juros_tipo' => 'valor', 'juros' => '3,00',
            'outros' => '5,00',
            'desconto_tipo' => 'percentual', 'desconto' => '',
        ])->assertSessionHas('sucesso');

        $this->assertSame([0, 0, 0, 0], $this->gravados($cobranca));
        $this->assertSame(21000, $cobranca->valorPagoCentavos());
        $this->assertSame('Baixa registrada (R$ 210,00, paga em 05/01/2026)', $this->resumoDaAuditoria($usuario));
    }

    public function test_pago_no_vencimento_aplica_desconto_do_contrato(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-01-05'])->assertSessionHas('sucesso');

        $this->assertSame([3150, 0, 0, 0], $this->gravados($cobranca));
        $this->assertSame(17850, $cobranca->valorPagoCentavos());
        $this->assertSame(
            'Baixa registrada (R$ 210,00, desconto 15% = R$ 31,50, pago R$ 178,50, em 05/01/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_pago_atrasado_nao_aplica_desconto_do_contrato(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-01-06'])->assertSessionHas('sucesso');

        // Um dia de atraso: multa de 2% e um trinta avos de 1% ao mês (R$ 0,07), sem o desconto de 15%.
        $this->assertSame([0, 420, 7, 0], $this->gravados($cobranca));
    }

    public function test_cobranca_sem_contrato_aceita_lancamento_manual(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $usuario = User::factory()->create();
        $avulsa = Cobranca::factory()->create(['user_id' => $usuario->id, 'vencimento' => '2026-01-05', 'valor_centavos' => 21000]);
        $this->actingAs($usuario);

        $this->post("/cobrancas/{$avulsa->id}/pagar", ['pago_em' => '2026-11-01'])->assertSessionHas('sucesso');
        $this->assertSame([0, 0, 0, 0], $this->gravados($avulsa));

        $outra = Cobranca::factory()->create(['user_id' => $usuario->id, 'vencimento' => '2026-01-05', 'valor_centavos' => 21000]);
        $this->post("/cobrancas/{$outra->id}/pagar", [
            'pago_em' => '2026-11-01', 'multa_tipo' => 'valor', 'multa' => '10,00', 'outros' => '3,00',
        ])->assertSessionHas('sucesso');

        $this->assertSame([0, 1000, 0, 300], $this->gravados($outra));
    }

    public function test_multa_invalida_da_erro_no_campo_e_nao_grava(): void
    {
        [$usuario, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'multa_tipo' => 'percentual', 'multa' => 'abc'])
            ->assertSessionHasErrors(['multa' => self::PERCENTUAL_INVALIDO]);

        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Pendente, $cobranca->status);
        $this->assertSame([0, 0, 0, 0], $this->gravados($cobranca));
        $this->assertSame(0, $usuario->auditorias()->count());
    }

    public function test_tipo_ausente_ou_invalido_da_erro_no_tipo(): void
    {
        [, $cobranca] = $this->cenario();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'juros' => '1'])
            ->assertSessionHasErrors(['juros_tipo' => 'Escolha se os juros são em % ou em R$.']);
        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'multa_tipo' => 'dobro', 'multa' => '1'])
            ->assertSessionHasErrors(['multa_tipo' => 'Tipo de multa inválido.']);
        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);
    }

    public function test_percentual_acima_de_100_e_recusado(): void
    {
        [, $cobranca] = $this->cenario();

        foreach (['multa', 'juros'] as $campo) {
            $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', "{$campo}_tipo" => 'percentual', $campo => '100,01'])
                ->assertSessionHasErrors([$campo => self::PERCENTUAL_INVALIDO]);
        }

        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'multa_tipo' => 'percentual', 'multa' => '100'])
            ->assertSessionHas('sucesso');
        $this->assertSame(21000, $cobranca->fresh()->multa_centavos);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function camposEmReais(): array
    {
        return ['multa' => ['multa', 'multa_tipo'], 'juros' => ['juros', 'juros_tipo'], 'outros' => ['outros', 'outros']];
    }

    #[DataProvider('camposEmReais')]
    public function test_acima_do_teto_em_reais_e_recusado(string $campo, string $tipo): void
    {
        [, $cobranca] = $this->cenario();
        $pedido = ['pago_em' => '2026-11-01', $tipo => 'valor'];

        $this->post("/cobrancas/{$cobranca->id}/pagar", [...$pedido, $campo => '100.000.000,00'])
            ->assertSessionHasErrors([$campo => self::REAIS_INVALIDO]);
        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);

        // Exatamente o teto passa.
        $this->post("/cobrancas/{$cobranca->id}/pagar", [...$pedido, $campo => '99.999.999,99'])->assertSessionHas('sucesso');
        $this->assertSame(StatusCobranca::Paga, $cobranca->fresh()->status);
    }

    public function test_cobranca_alheia_da_404_antes_da_validacao(): void
    {
        [, $cobranca] = $this->cenario();
        $alheia = Cobranca::factory()->create(['vencimento' => '2026-01-05', 'valor_centavos' => 21000]);
        $invalido = ['pago_em' => 'não é data', 'multa_tipo' => 'dobro', 'multa' => 'abc', 'outros' => 'x'];

        // Com a própria cobrança, a mesma rota responde (o 404 abaixo não é rota inexistente).
        $this->post("/cobrancas/{$cobranca->id}/pagar", $invalido)->assertSessionHasErrors('pago_em')->assertStatus(302);
        $this->post("/cobrancas/{$alheia->id}/pagar", $invalido)->assertNotFound();
        $this->assertSame(StatusCobranca::Pendente, $alheia->fresh()->status);
    }

    public function test_mudar_contrato_depois_nao_muda_o_valor_gravado(): void
    {
        [, $cobranca, $contrato] = $this->cenario();
        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01'])->assertSessionHas('sucesso');

        $contrato->update(['multa_valor' => 1000, 'juros_tipo' => null, 'juros_valor' => null, 'desconto_pontualidade_valor' => 5000]);

        $this->assertSame([0, 420, 2100, 0], $this->gravados($cobranca));
        $this->assertSame(23520, $cobranca->valorPagoCentavos());
    }

    public function test_lista_e_painel_levam_o_vencimento_e_as_regras_do_contrato(): void
    {
        [$usuario, $cobranca] = $this->cenario();
        $cobranca->update(['vencimento' => '2026-11-10']);
        $avulsa = Cobranca::factory()->create(['user_id' => $usuario->id, 'vencimento' => '2026-11-12']);
        $regras = [
            'desconto' => ['tipo' => 'percentual', 'valor' => 1500],
            'multa' => ['tipo' => 'percentual', 'valor' => 200],
            'juros' => ['tipo' => 'percentual', 'valor' => 100],
        ];

        $linhas = collect($this->getJson('/cobrancas/dados?mes=2026-11')->assertOk()->json('linhas'))->keyBy('id');

        $this->assertSame('2026-11-10', $linhas[$cobranca->id]['vencimento_iso']);
        $this->assertSame($regras, $linhas[$cobranca->id]['regras']);
        $this->assertSame('2026-11-12', $linhas[$avulsa->id]['vencimento_iso']);
        $this->assertNull($linhas[$avulsa->id]['regras']);

        $cobranca->forceFill(['enviado_em' => now()])->save();
        $painel = $this->getJson('/painel/dados?lista=aguardando')->assertOk()->json('linhas.0');
        $this->assertSame($regras, $painel['regras']);
        $this->assertSame('2026-11-10', $painel['vencimento_iso']);
    }

    /** @return array<string, array{0: string}> */
    public static function enderecosDaLista(): array
    {
        return ['cobranças' => ['/cobrancas/dados?mes=2026-11'], 'painel' => ['/painel/dados?lista=aguardando']];
    }

    #[DataProvider('enderecosDaLista')]
    public function test_lista_nao_faz_consulta_por_linha_ao_carregar_contrato(string $endereco): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);
        $nova = function () use ($usuario) {
            // Contrato inativo: a previsão de cobranças não entra na contagem.
            $contrato = Contrato::factory()->comCondicoes()->create(['user_id' => $usuario->id, 'ativo' => false]);

            return Cobranca::factory()->create([
                'user_id' => $usuario->id, 'cliente_id' => $contrato->cliente_id, 'contrato_id' => $contrato->id,
                'vencimento' => '2026-11-10', 'enviado_em' => now(),
            ]);
        };
        $contar = function () use ($endereco): int {
            $consultas = 0;
            DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
                $consultas++;
            });
            $this->getJson($endereco)->assertOk();

            return $consultas;
        };

        $nova();
        $comUma = $contar();
        foreach (range(1, 4) as $i) {
            $nova();
        }
        $this->assertCount(5, $this->getJson($endereco)->json('linhas'));

        $this->assertSame($comUma, $contar());
    }

    public function test_resolver_no_lote_segue_a_tabela_da_spec(): void
    {
        [, $cobranca] = $this->cenario();
        $cobranca->load('contrato');
        $data = CarbonImmutable::parse('2026-11-01');

        $ausente = ValoresDaBaixa::resolver($cobranca, $data, [], 'lote');
        $vazio = ValoresDaBaixa::resolver($cobranca, $data, ['multa_tipo' => 'percentual', 'multa' => null, 'juros_tipo' => 'percentual', 'juros' => null, 'outros' => null], 'lote');
        $zero = ValoresDaBaixa::resolver($cobranca, $data, ['multa_tipo' => 'percentual', 'multa' => '0', 'juros_tipo' => 'percentual', 'juros' => '0'], 'lote');
        $preenchido = ValoresDaBaixa::resolver($cobranca, $data, ['multa_tipo' => 'valor', 'multa' => '10,00', 'outros' => '1,00'], 'lote');

        $this->assertSame([420, 2100, 0], [$ausente['multa_centavos'], $ausente['juros_centavos'], $ausente['outros_centavos']]);
        $this->assertSame([420, 2100, 0], [$vazio['multa_centavos'], $vazio['juros_centavos'], $vazio['outros_centavos']]);
        $this->assertSame([0, 0, 0], [$zero['multa_centavos'], $zero['juros_centavos'], $zero['outros_centavos']]);
        $this->assertSame([1000, 2100, 100], [$preenchido['multa_centavos'], $preenchido['juros_centavos'], $preenchido['outros_centavos']]);
    }

    public function test_resolver_na_correcao_mantem_o_gravado_quando_ausente(): void
    {
        [, $cobranca] = $this->cenario();
        $cobranca->forceFill(['desconto_centavos' => 100, 'multa_centavos' => 200, 'juros_centavos' => 300, 'outros_centavos' => 400])->save();
        $cobranca->load('contrato');
        $data = CarbonImmutable::parse('2026-11-01');

        $ausente = ValoresDaBaixa::resolver($cobranca, $data, [], 'correcao');
        $vazio = ValoresDaBaixa::resolver($cobranca, $data, ['multa_tipo' => 'percentual', 'multa' => null, 'juros_tipo' => 'valor', 'juros' => null, 'outros' => null, 'desconto_tipo' => 'valor', 'desconto' => null], 'correcao');
        $preenchido = ValoresDaBaixa::resolver($cobranca, $data, ['multa_tipo' => 'valor', 'multa' => '1,50', 'outros' => '2,00'], 'correcao');

        $this->assertSame([100, 200, 300, 400], [$ausente['desconto_centavos'], $ausente['multa_centavos'], $ausente['juros_centavos'], $ausente['outros_centavos']]);
        $this->assertSame([0, 0, 0, 0], [$vazio['desconto_centavos'], $vazio['multa_centavos'], $vazio['juros_centavos'], $vazio['outros_centavos']]);
        $this->assertSame([100, 150, 300, 200], [$preenchido['desconto_centavos'], $preenchido['multa_centavos'], $preenchido['juros_centavos'], $preenchido['outros_centavos']]);
    }

    public function test_resolver_lanca_o_erro_no_campo_que_errou(): void
    {
        [, $cobranca] = $this->cenario();

        try {
            ValoresDaBaixa::resolver($cobranca, CarbonImmutable::parse('2026-11-01'), ['juros_tipo' => 'percentual', 'juros' => '101'], 'individual');
            $this->fail('Era esperado um erro de validação.');
        } catch (ValidationException $erro) {
            $this->assertSame(['juros'], array_keys($erro->errors()));
        }
    }
}
