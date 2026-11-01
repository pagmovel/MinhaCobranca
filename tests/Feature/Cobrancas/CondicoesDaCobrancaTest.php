<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CondicoesDaCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Cobrança de R$ 100,00 vencida em 05/01/2026; com `$condicoes`, o contrato é o de 15% / multa 2% / juros 1%. */
    private function cobranca(bool $condicoes = true, array $contrato = []): Cobranca
    {
        $usuario = User::factory()->create();
        $fabrica = $condicoes ? Contrato::factory()->comCondicoes() : Contrato::factory();
        $registro = $fabrica->create(['user_id' => $usuario->id] + $contrato);

        return Cobranca::factory()->create([
            'user_id' => $usuario->id,
            'cliente_id' => $registro->cliente_id,
            'contrato_id' => $registro->id,
            'vencimento' => '2026-01-05',
            'valor_centavos' => 10000,
        ]);
    }

    public function test_ate_o_vencimento_vale_o_desconto_e_nao_ha_acrescimo(): void
    {
        $condicoes = $this->cobranca()->condicoesNaData(CarbonImmutable::parse('2026-01-05'));

        $this->assertSame(
            ['dias' => 0, 'desconto_centavos' => 1500, 'multa_centavos' => 0, 'juros_centavos' => 0, 'total_centavos' => 8500],
            $condicoes,
        );
    }

    public function test_antes_do_vencimento_tambem_vale_o_desconto(): void
    {
        $condicoes = $this->cobranca()->condicoesNaData(CarbonImmutable::parse('2025-12-20'));

        $this->assertSame(0, $condicoes['dias']);
        $this->assertSame(1500, $condicoes['desconto_centavos']);
        $this->assertSame(8500, $condicoes['total_centavos']);
    }

    public function test_depois_do_vencimento_vale_multa_e_juros_e_nao_ha_desconto(): void
    {
        $condicoes = $this->cobranca()->condicoesNaData(CarbonImmutable::parse('2026-02-04'));

        $this->assertSame(
            ['dias' => 30, 'desconto_centavos' => 0, 'multa_centavos' => 200, 'juros_centavos' => 100, 'total_centavos' => 10300],
            $condicoes,
        );
    }

    public function test_no_dia_do_vencimento_a_hora_nao_tira_o_desconto_nem_cobra_multa(): void
    {
        $cobranca = $this->cobranca();

        foreach (['2026-01-05 00:00:00', '2026-01-05 12:00:00', '2026-01-05 23:59:59'] as $momento) {
            $condicoes = $cobranca->condicoesNaData(CarbonImmutable::parse($momento, 'America/Sao_Paulo'));

            $this->assertSame(0, $condicoes['dias'], $momento);
            $this->assertSame(0, $condicoes['multa_centavos'], $momento);
            $this->assertSame(8500, $condicoes['total_centavos'], $momento);
        }

        $this->assertSame(1, $cobranca->condicoesNaData(CarbonImmutable::parse('2026-01-06 00:00:01', 'America/Sao_Paulo'))['dias']);
    }

    public function test_so_com_multa_e_juros_o_valor_em_dia_e_o_original(): void
    {
        $cobranca = $this->cobranca(condicoes: false, contrato: ['multa_tipo' => 'valor', 'multa_valor' => 700, 'juros_tipo' => 'percentual', 'juros_valor' => 300]);

        $this->assertSame(10000, $cobranca->condicoesNaData(CarbonImmutable::parse('2026-01-05'))['total_centavos']);
        $this->assertSame(10000 + 700 + 300, $cobranca->condicoesNaData(CarbonImmutable::parse('2026-02-04'))['total_centavos']);
    }

    public function test_so_com_desconto_atrasada_fica_com_o_valor_original(): void
    {
        $cobranca = $this->cobranca(condicoes: false, contrato: ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1500]);
        $condicoes = $cobranca->condicoesNaData(CarbonImmutable::parse('2026-02-04'));

        $this->assertSame(30, $condicoes['dias']);
        $this->assertSame(0, $condicoes['desconto_centavos']);
        $this->assertSame(10000, $condicoes['total_centavos']);
    }

    public function test_desconto_em_reais_maior_que_o_valor_nao_deixa_o_total_negativo(): void
    {
        $cobranca = $this->cobranca(condicoes: false, contrato: ['desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade_valor' => 20000]);
        $condicoes = $cobranca->condicoesNaData(CarbonImmutable::parse('2026-01-05'));

        $this->assertSame(10000, $condicoes['desconto_centavos']);
        $this->assertSame(0, $condicoes['total_centavos']);
    }

    public function test_contrato_sem_regra_nao_muda_o_valor(): void
    {
        $cobranca = $this->cobranca(condicoes: false);

        foreach (['2026-01-05', '2026-02-04'] as $data) {
            $condicoes = $cobranca->condicoesNaData(CarbonImmutable::parse($data));

            $this->assertSame(10000, $condicoes['total_centavos']);
            $this->assertSame([0, 0, 0], [$condicoes['desconto_centavos'], $condicoes['multa_centavos'], $condicoes['juros_centavos']]);
        }
    }

    public function test_cobranca_sem_contrato_nao_da_erro_e_fica_com_o_valor_original(): void
    {
        $cobranca = Cobranca::factory()->create(['contrato_id' => null, 'vencimento' => '2026-01-05', 'valor_centavos' => 10000]);

        $this->assertSame(
            ['dias' => 30, 'desconto_centavos' => 0, 'multa_centavos' => 0, 'juros_centavos' => 0, 'total_centavos' => 10000],
            $cobranca->condicoesNaData(CarbonImmutable::parse('2026-02-04')),
        );
    }

    public function test_regras_de_pagamento_do_contrato(): void
    {
        $this->assertNull(Contrato::factory()->make()->regrasDePagamento());
        $this->assertNull(Contrato::factory()->make(['multa_tipo' => 'percentual', 'multa_valor' => 0])->regrasDePagamento());
        $this->assertSame(
            [
                'desconto' => ['tipo' => 'percentual', 'valor' => 1500],
                'multa' => ['tipo' => 'percentual', 'valor' => 200],
                'juros' => ['tipo' => 'percentual', 'valor' => 100],
            ],
            Contrato::factory()->comCondicoes()->make()->regrasDePagamento(),
        );
        $this->assertSame(
            ['desconto' => null, 'multa' => ['tipo' => 'valor', 'valor' => 500], 'juros' => null],
            Contrato::factory()->make(['multa_tipo' => 'valor', 'multa_valor' => 500])->regrasDePagamento(),
        );
    }

    public function test_acrescimos_e_valor_pago_somam_o_gravado(): void
    {
        $cobranca = Cobranca::factory()->create(['valor_centavos' => 21000]);

        $this->assertSame([0, 0, 0, 0], [$cobranca->desconto_centavos, $cobranca->multa_centavos, $cobranca->juros_centavos, $cobranca->outros_centavos]);
        $this->assertSame(21000, $cobranca->valorPagoCentavos());

        $cobranca->forceFill(['desconto_centavos' => 1000, 'multa_centavos' => 420, 'juros_centavos' => 2100, 'outros_centavos' => 500])->save();

        $this->assertSame(3020, $cobranca->acrescimosCentavos());
        $this->assertSame(23020, $cobranca->valorPagoCentavos());
        $this->assertSame(23020, (int) Cobranca::query()->whereKey($cobranca->id)->toBase()->sum(DB::raw(Cobranca::VALOR_PAGO_SQL)));
    }

    public function test_acrescimos_ficam_fora_do_preenchimento_em_massa(): void
    {
        $cobranca = new Cobranca(['multa_centavos' => 5, 'juros_centavos' => 5, 'outros_centavos' => 5]);

        $this->assertSame(0, $cobranca->acrescimosCentavos());
    }

    public function test_frase_antes_do_vencimento_diz_o_valor_com_desconto_e_o_original_com_multa_e_juros(): void
    {
        $cobranca = $this->cobranca();

        $this->assertSame(
            'Pagando até 05/01/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.',
            $cobranca->fraseDaCondicao(CarbonImmutable::parse('2025-12-20')),
        );
        // O dia do vencimento ainda vale o desconto, a qualquer hora.
        $this->assertSame(
            'Pagando até 05/01/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.',
            $cobranca->fraseDaCondicao(CarbonImmutable::parse('2026-01-05 23:59:59')),
        );
    }

    public function test_frase_antes_do_vencimento_cita_so_o_encargo_que_o_contrato_tem(): void
    {
        $soDesconto = $this->cobranca(false, ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1500]);
        $soMulta = $this->cobranca(false, ['desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade_valor' => 500, 'multa_tipo' => 'valor', 'multa_valor' => 300]);
        $soJuros = $this->cobranca(false, ['desconto_pontualidade_tipo' => 'valor', 'desconto_pontualidade_valor' => 500, 'juros_tipo' => 'percentual', 'juros_valor' => 100]);
        $data = CarbonImmutable::parse('2025-12-20');

        $this->assertSame('Pagando até 05/01/2026: R$ 85,00. Depois: R$ 100,00.', $soDesconto->fraseDaCondicao($data));
        $this->assertSame('Pagando até 05/01/2026: R$ 95,00. Depois: R$ 100,00 + multa.', $soMulta->fraseDaCondicao($data));
        $this->assertSame('Pagando até 05/01/2026: R$ 95,00. Depois: R$ 100,00 + juros.', $soJuros->fraseDaCondicao($data));
    }

    public function test_frase_depois_do_vencimento_diz_a_data_e_so_os_acrescimos_maiores_que_zero(): void
    {
        // 300 dias de atraso: multa de 2% e juros de 1% ao mês (10 meses) sobre R$ 210,00.
        $comTudo = $this->cobranca();
        $comTudo->valor_centavos = 21000;
        $soJuros = $this->cobranca(false, ['juros_tipo' => 'percentual', 'juros_valor' => 100]);
        $soJuros->valor_centavos = 21000;
        $data = CarbonImmutable::parse('2026-11-01');

        $this->assertSame('Valor atualizado em 01/11/2026: multa R$ 4,20; juros R$ 21,00.', $comTudo->fraseDaCondicao($data));
        $this->assertSame('Valor atualizado em 01/11/2026: juros R$ 21,00.', $soJuros->fraseDaCondicao($data));
    }

    public function test_frase_e_nula_sem_regra_sem_contrato_ou_quando_o_valor_do_dia_e_o_original(): void
    {
        $semRegra = $this->cobranca(false);
        $avulsa = Cobranca::factory()->create(['vencimento' => '2026-01-05', 'valor_centavos' => 10000]);
        $soMultaEmDia = $this->cobranca(false, ['multa_tipo' => 'percentual', 'multa_valor' => 200]);
        $soDescontoAtrasada = $this->cobranca(false, ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1500]);

        foreach ([$semRegra, $avulsa] as $cobranca) {
            $this->assertNull($cobranca->fraseDaCondicao(CarbonImmutable::parse('2025-12-20')));
            $this->assertNull($cobranca->fraseDaCondicao(CarbonImmutable::parse('2026-02-04')));
        }
        $this->assertNull($soMultaEmDia->fraseDaCondicao(CarbonImmutable::parse('2026-01-05')));
        $this->assertNull($soDescontoAtrasada->fraseDaCondicao(CarbonImmutable::parse('2026-02-04')));
    }

    public function test_frase_e_so_da_pendente(): void
    {
        foreach ([StatusCobranca::Paga, StatusCobranca::Cancelada] as $status) {
            $cobranca = $this->cobranca();
            $cobranca->status = $status;

            $this->assertNull($cobranca->fraseDaCondicao(CarbonImmutable::parse('2025-12-20')), $status->value);
            $this->assertNull($cobranca->fraseDaCondicao(CarbonImmutable::parse('2026-02-04')), $status->value);
        }
    }

    public function test_detalhe_dos_acrescimos_da_paga_cita_so_os_maiores_que_zero(): void
    {
        $cobranca = $this->cobranca();
        $cobranca->status = StatusCobranca::Paga;
        $this->assertNull($cobranca->detalheDosAcrescimos());

        $cobranca->forceFill(['multa_centavos' => 420, 'juros_centavos' => 2100, 'outros_centavos' => 500]);
        $this->assertSame('Acréscimos por atraso: multa R$ 4,20; juros R$ 21,00; outros R$ 5,00.', $cobranca->detalheDosAcrescimos());

        $cobranca->forceFill(['multa_centavos' => 420, 'juros_centavos' => 0]);
        $this->assertSame('Acréscimos por atraso: multa R$ 4,20; outros R$ 5,00.', $cobranca->detalheDosAcrescimos());

        // Só "outros": não pode dizer "por atraso", porque pode ter sido paga em dia.
        $cobranca->forceFill(['multa_centavos' => 0, 'juros_centavos' => 0]);
        $this->assertSame('Outros acréscimos: R$ 5,00.', $cobranca->detalheDosAcrescimos());
    }

    public function test_detalhe_dos_acrescimos_nao_existe_fora_da_paga(): void
    {
        $cobranca = $this->cobranca()->forceFill(['multa_centavos' => 420]);

        $this->assertNull($cobranca->detalheDosAcrescimos());
        $cobranca->status = StatusCobranca::Cancelada;
        $this->assertNull($cobranca->detalheDosAcrescimos());
    }
}
