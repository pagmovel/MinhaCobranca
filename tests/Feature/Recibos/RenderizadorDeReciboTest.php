<?php

namespace Tests\Feature\Recibos;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use App\Support\QrCodePix;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RenderizadorDeReciboTest extends TestCase
{
    use LazilyRefreshDatabase;

    private RenderizadorDeRecibo $renderizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->renderizador = app(RenderizadorDeRecibo::class);
    }

    private function recibo(array $cliente = [], array $cobranca = []): Recibo
    {
        $usuario = User::factory()->create(['name' => 'Marcos Prestador', 'cidade' => 'Aracaju', 'uf' => 'SE']);
        $tomador = Cliente::factory()->for($usuario)->create($cliente);
        $registro = Cobranca::factory()->for($usuario)->for($tomador)->create($cobranca);

        return app(EmissorDeRecibos::class)->emitir($registro);
    }

    public function test_campos_vazios_do_tomador_viram_traco(): void
    {
        $recibo = $this->recibo(array_fill_keys(
            ['inscricao_municipal', 'inscricao_estadual', 'telefone', 'email', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'],
            null,
        ));

        $tomador = $this->renderizador->dados($recibo, CarbonImmutable::now())['tomador'];

        foreach (['inscricao_municipal', 'inscricao_estadual', 'telefone', 'email', 'endereco', 'endereco_completo', 'municipio', 'cidade_uf', 'cep'] as $campo) {
            $this->assertSame('-', $tomador[$campo], "campo {$campo}");
        }
    }

    /** Decisão do usuário (2026-10-11): o apelido é só das telas; no recibo, só se ele o puser na descrição. */
    public function test_apelido_do_cliente_nao_vai_para_o_recibo(): void
    {
        $recibo = $this->recibo(['nome' => 'Zeta Sindicato', 'apelido' => 'Regional Araci']);

        $this->assertArrayNotHasKey('apelido', $recibo->snapshot['tomador']);
        $html = view($recibo->modelo->view(), $this->renderizador->dados($recibo, CarbonImmutable::now()))->render();
        $this->assertStringContainsString('Zeta Sindicato', $html);
        $this->assertStringNotContainsString('Regional Araci', $html);
    }

    public function test_monta_endereco_e_documento_como_na_nota(): void
    {
        $recibo = $this->recibo(['documento' => '11222333000181', 'complemento' => null]);

        $tomador = $this->renderizador->dados($recibo, CarbonImmutable::now())['tomador'];

        $this->assertSame('11.222.333/0001-81', $tomador['documento']);
        $this->assertSame('Rua Virgílio Oliveira Lima, 185, Centro - CEP: 48760-000 - Araci - BA', $tomador['endereco_completo']);
        $this->assertSame('Araci / BA', $tomador['municipio']);
    }

    public function test_valores_periodo_e_extenso(): void
    {
        $dados = $this->renderizador->dados($this->recibo(), CarbonImmutable::now());

        $this->assertSame('2026/000001', $dados['numero']);
        $this->assertSame('260,60', $dados['valor']);
        $this->assertSame('10/2026', $dados['competencia']);
        $this->assertSame('Obs.: Período de 01/10/2026 a 31/10/2026', $dados['servico']['obs_periodo']);
        $this->assertContains('Valor por extenso: duzentos e sessenta reais e sessenta centavos.', $dados['informacoes']);
    }

    public function test_descricao_sai_em_linhas_que_cabem_na_caixa(): void
    {
        $recibo = $this->recibo([], ['descricao' => str_repeat('Suporte técnico remoto ', 20)."\nSEGUNDA LINHA"]);

        $linhas = $this->renderizador->dados($recibo, CarbonImmutable::now())['servico']['linhas'];

        $this->assertSame('SEGUNDA LINHA', end($linhas));
        $this->assertGreaterThan(2, count($linhas));
        foreach ($linhas as $linha) {
            $this->assertLessThanOrEqual(110, mb_strlen($linha));
        }
    }

    public function test_carimbo_vencendo_hoje(): void
    {
        $recibo = $this->recibo([], ['vencimento' => '2026-10-09']);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $this->assertSame(['texto' => 'VENCENDO HOJE', 'detalhe' => '09/10/2026', 'classe' => 'vence_hoje'], $dados['carimbo']);
        $this->assertSame('Vence hoje', $dados['situacao']);
    }

    public function test_carimbo_acompanha_a_situacao_atual(): void
    {
        $recibo = $this->recibo();
        $cobranca = $recibo->cobranca;

        $this->assertSame(['texto' => 'A VENCER', 'detalhe' => '10/10/2026', 'classe' => 'a_vencer'], $this->renderizador->dados($recibo, CarbonImmutable::now())['carimbo']);
        $this->assertSame('VENCIDO', $this->renderizador->dados($recibo, CarbonImmutable::parse('2026-10-11'))['carimbo']['texto']);

        $cobranca->status = StatusCobranca::Paga;
        $cobranca->pago_em = '2026-10-09';
        $cobranca->save();
        $dados = $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now());
        $this->assertSame(['texto' => 'PAGO', 'detalhe' => 'em 09/10/2026', 'classe' => 'pago'], $dados['carimbo']);
        $this->assertSame('Pago em 09/10/2026', $dados['situacao']);
    }

    /** @return array<string, array{string, string, string, string}> família, campo do desconto, valor líquido e valor cheio */
    public static function familiasComOsCamposDoDesconto(): array
    {
        return [
            'webiss' => [
                'webiss',
                'Desc. Cond. (R$)</span><span class="val">24,76<',
                'Valor Líquido (R$)</span><span class="val">222,81<',
                'Valor Total (R$)</span><span style="display: block; font-size: 12pt; font-weight: bold;">247,57<',
            ],
            'danfse' => [
                'danfse',
                'Desconto Condicionado</span><span class="val">R$ 24,76<',
                'VALOR LÍQUIDO DO RECIBO</span><span class="val" style="font-weight: bold;">R$ 222,81<',
                'VALOR DA OPERAÇÃO / SERVIÇO</span><span class="val">R$ 247,57<',
            ],
        ];
    }

    #[DataProvider('familiasComOsCamposDoDesconto')]
    public function test_paga_com_desconto_preenche_desconto_e_valor_liquido_e_mantem_o_valor_total(string $familia, string $campoDesconto, string $campoLiquido, string $campoValorCheio): void
    {
        $recibo = $this->recibo(['modelo' => $familia], [
            'valor_centavos' => 24757, 'desconto_centavos' => 2476, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09',
        ]);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());
        $html = view($recibo->modelo->view(), $dados)->render();

        $this->assertSame('24,76', $dados['desconto']);
        $this->assertSame('222,81', $dados['valor_pago']);
        $this->assertSame('247,57', $dados['valor']);
        $this->assertStringContainsString($campoDesconto, $html);
        $this->assertStringContainsString($campoLiquido, $html);
        $this->assertStringContainsString($campoValorCheio, $html);
    }

    public function test_desconto_integral_mostra_valor_pago_zero(): void
    {
        $recibo = $this->recibo([], [
            'valor_centavos' => 24757, 'desconto_centavos' => 24757, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09',
        ]);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $this->assertSame('247,57', $dados['desconto']);
        $this->assertSame('0,00', $dados['valor_pago']);
    }

    public function test_sem_desconto_ou_sem_estar_paga_desconto_e_valor_pago_sao_nulos(): void
    {
        $pagaSemDesconto = $this->recibo([], ['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09']);
        $pendente = $this->recibo([], ['desconto_centavos' => 500]);

        foreach ([$pagaSemDesconto, $pendente] as $recibo) {
            $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

            $this->assertArrayHasKey('desconto', $dados);
            $this->assertNull($dados['desconto']);
            $this->assertArrayHasKey('valor_pago', $dados);
            $this->assertNull($dados['valor_pago']);
        }
    }

    public function test_usa_o_snapshot_e_nao_o_perfil_atual(): void
    {
        $recibo = $this->recibo();
        $recibo->cobranca->user->update(['name' => 'Nome Novo']);

        $this->assertSame('Marcos Prestador', $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now())['prestador']['nome']);
    }

    public function test_pagamento_pix_traz_qr_chave_e_copia_e_cola(): void
    {
        $usuario = User::factory()->create(['name' => 'Marcos Prestador', 'cidade' => 'Aracaju', 'uf' => 'SE']);
        $chave = ChavePix::factory()->for($usuario)->create(['tipo' => TipoChavePix::Telefone, 'chave' => '+5579999990000']);
        $cobranca = Cobranca::factory()->for($usuario)->for(Cliente::factory()->for($usuario))
            ->create(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);
        $recibo = app(EmissorDeRecibos::class)->emitir($cobranca);

        $pagamento = $this->renderizador->dados($recibo, CarbonImmutable::now())['pagamento'];

        $this->assertSame('pix', $pagamento['forma']);
        $this->assertSame('+55 (79) 99999-0000', $pagamento['chave']);
        $this->assertSame('Telefone', $pagamento['tipo']);
        $this->assertStringStartsWith('data:image/png;base64,', $pagamento['qr']);
        $copiaECola = $pagamento['copia_e_cola'];
        $this->assertStringStartsWith('000201', $copiaECola);
        $this->assertStringContainsString('5406260.60', $copiaECola);
        $this->assertStringContainsString('0513REC2026000001', $copiaECola);
        $this->assertStringContainsString('5916MARCOS PRESTADOR6007ARACAJU', $copiaECola);
    }

    public function test_pagamento_boleto_e_sem_forma(): void
    {
        $boleto = $this->recibo([], ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => '23793381286000000000300000000400184340000026060']);
        $this->assertSame(
            ['forma' => 'boleto', 'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060'],
            $this->renderizador->dados($boleto, CarbonImmutable::now())['pagamento'],
        );

        $this->assertSame(['forma' => null], $this->renderizador->dados($this->recibo(), CarbonImmutable::now())['pagamento']);
    }

    public function test_recibo_pago_ou_cancelado_nao_mostra_como_pagar(): void
    {
        $usuario = User::factory()->create();
        $chave = ChavePix::factory()->for($usuario)->create();
        $cobranca = Cobranca::factory()->for($usuario)->for(Cliente::factory()->for($usuario))
            ->create(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);
        $recibo = app(EmissorDeRecibos::class)->emitir($cobranca);

        $cobranca->status = StatusCobranca::Paga;
        $cobranca->pago_em = '2026-10-09';
        $cobranca->save();
        // O comprovante de pagamento não pode trazer um QR pagável de novo.
        $this->assertSame(['forma' => null], $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now())['pagamento']);

        $cobranca->status = StatusCobranca::Cancelada;
        $cobranca->pago_em = null;
        $cobranca->save();
        $this->assertSame(['forma' => null], $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now())['pagamento']);
    }

    /** Cobrança de R$ 100,00 (vence em 20/10/2026) de um contrato com 15% de desconto, multa de 2% e juros de 1% ao mês. */
    private function reciboDoContrato(array $cobranca = [], array $contrato = [], array $cliente = []): Recibo
    {
        $usuario = User::factory()->create(['name' => 'Marcos Prestador', 'cidade' => 'Aracaju', 'uf' => 'SE']);
        $tomador = Cliente::factory()->for($usuario)->create($cliente);
        $registro = Contrato::factory()->comCondicoes()->for($usuario)->for($tomador)->create(array_merge(['ativo' => false], $contrato));
        $emitida = Cobranca::factory()->for($usuario)->for($tomador)
            ->create(array_merge(['contrato_id' => $registro->id, 'valor_centavos' => 10000, 'vencimento' => '2026-10-20'], $cobranca));

        return app(EmissorDeRecibos::class)->emitir($emitida);
    }

    private function comPix(Recibo $recibo): Recibo
    {
        $chave = ChavePix::factory()->for($recibo->cobranca->user)->create(['chave' => 'financeiro@exemplo.com.br']);
        $recibo->cobranca->update(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);

        return $recibo;
    }

    /** @return array<string, array{string, string, string, string}> família e os campos de desconto, valor líquido e valor total, com `%s` no valor */
    public static function familiasComOsCamposDoValor(): array
    {
        return [
            'webiss' => [
                'webiss',
                'Desc. Cond. (R$)</span><span class="val">%s<',
                'Valor Líquido (R$)</span><span class="val">%s<',
                'Valor Total (R$)</span><span style="display: block; font-size: 12pt; font-weight: bold;">%s<',
            ],
            'danfse' => [
                'danfse',
                'Desconto Condicionado</span><span class="val">R$ %s<',
                'VALOR LÍQUIDO DO RECIBO</span><span class="val" style="font-weight: bold;">R$ %s<',
                'VALOR DA OPERAÇÃO / SERVIÇO</span><span class="val">R$ %s<',
            ],
        ];
    }

    #[DataProvider('familiasComOsCamposDoValor')]
    public function test_pendente_antes_do_vencimento_mostra_desconto_valor_do_dia_e_a_frase(string $familia, string $campoDesconto, string $campoLiquido, string $campoValorTotal): void
    {
        $recibo = $this->reciboDoContrato(cliente: ['modelo' => $familia]);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());
        $html = view($recibo->modelo->view(), $dados)->render();

        $this->assertSame(['100,00', '15,00', '85,00'], [$dados['valor'], $dados['desconto'], $dados['valor_pago']]);
        $this->assertSame([
            'Valor por extenso: oitenta e cinco reais.',
            'Pagando até 20/10/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.',
        ], $dados['informacoes']);
        $this->assertStringContainsString(sprintf($campoDesconto, '15,00'), $html);
        $this->assertStringContainsString(sprintf($campoLiquido, '85,00'), $html);
        $this->assertStringContainsString(sprintf($campoValorTotal, '100,00'), $html);
        $this->assertStringContainsString('Pagando até 20/10/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.', $html);
    }

    #[DataProvider('familiasComOsCamposDoValor')]
    public function test_pendente_vencida_mostra_o_valor_do_dia_com_multa_e_juros_e_o_valor_total_original(string $familia, string $campoDesconto, string $campoLiquido, string $campoValorTotal): void
    {
        // 20 dias de atraso: multa 2% = R$ 2,00; juros 1% ao mês × 20/30 = R$ 0,67; R$ 102,67 no dia.
        $recibo = $this->reciboDoContrato(['vencimento' => '2026-09-19'], cliente: ['modelo' => $familia]);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());
        $html = view($recibo->modelo->view(), $dados)->render();

        $this->assertNull($dados['desconto']);
        $this->assertSame('102,67', $dados['valor_pago']);
        $this->assertSame([
            'Valor por extenso: cento e dois reais e sessenta e sete centavos.',
            'Valor atualizado em 09/10/2026: multa R$ 2,00; juros R$ 0,67.',
        ], $dados['informacoes']);
        $this->assertStringContainsString(sprintf($campoLiquido, '102,67'), $html);
        $this->assertStringContainsString(sprintf($campoValorTotal, '100,00'), $html);
        $this->assertStringContainsString('Valor atualizado em 09/10/2026: multa R$ 2,00; juros R$ 0,67.', $html);
    }

    public function test_paga_com_acrescimos_mostra_valor_pago_detalhe_e_extenso_pelo_liquido(): void
    {
        $recibo = $this->reciboDoContrato([
            'valor_centavos' => 21000, 'multa_centavos' => 420, 'juros_centavos' => 2100, 'outros_centavos' => 500,
            'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09',
        ]);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $this->assertNull($dados['desconto']);
        $this->assertSame('240,20', $dados['valor_pago']);
        $this->assertSame('210,00', $dados['valor']);
        $this->assertSame([
            'Valor por extenso: duzentos e quarenta reais e vinte centavos.',
            'Acréscimos por atraso: multa R$ 4,20; juros R$ 21,00; outros R$ 5,00.',
        ], $dados['informacoes']);
    }

    public function test_paga_vale_o_gravado_mesmo_que_o_contrato_mude_depois(): void
    {
        $recibo = $this->reciboDoContrato([
            'valor_centavos' => 21000, 'desconto_centavos' => 0, 'multa_centavos' => 420, 'juros_centavos' => 2100,
            'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09',
        ]);
        $antes = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $recibo->cobranca->contrato->update(['multa_tipo' => 'valor', 'multa_valor' => 99900, 'desconto_pontualidade_tipo' => null, 'desconto_pontualidade_valor' => null]);
        $depois = $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now());

        $this->assertSame('235,20', $depois['valor_pago']);
        $this->assertSame($antes, $depois);
    }

    public function test_paga_com_desconto_e_acrescimo_mostra_os_dois_e_o_valor_pago_ainda_aparece_quando_empatam(): void
    {
        $comDesconto = $this->reciboDoContrato(['desconto_centavos' => 1500, 'multa_centavos' => 200, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09']);
        $empate = $this->reciboDoContrato(['desconto_centavos' => 500, 'outros_centavos' => 500, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09']);

        $dados = $this->renderizador->dados($comDesconto, CarbonImmutable::now());
        $this->assertSame(['15,00', '87,00'], [$dados['desconto'], $dados['valor_pago']]);
        $this->assertContains('Valor por extenso: oitenta e sete reais.', $dados['informacoes']);
        $this->assertContains('Acréscimos por atraso: multa R$ 2,00.', $dados['informacoes']);

        $dados = $this->renderizador->dados($empate, CarbonImmutable::now());
        $this->assertSame(['5,00', '100,00'], [$dados['desconto'], $dados['valor_pago']]);
    }

    public function test_valor_por_extenso_da_paga_com_desconto_segue_o_valor_liquido(): void
    {
        $recibo = $this->recibo([], ['valor_centavos' => 24757, 'desconto_centavos' => 2476, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09']);
        $integral = $this->recibo([], ['valor_centavos' => 24757, 'desconto_centavos' => 24757, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09']);

        $this->assertSame(
            ['Valor por extenso: duzentos e vinte e dois reais e oitenta e um centavos.'],
            $this->renderizador->dados($recibo, CarbonImmutable::now())['informacoes'],
        );
        $this->assertSame(['Valor por extenso: zero reais.'], $this->renderizador->dados($integral, CarbonImmutable::now())['informacoes']);
    }

    public function test_cancelada_nao_mostra_nem_calcula_nada_novo(): void
    {
        $recibo = $this->comPix($this->reciboDoContrato(['vencimento' => '2026-09-19', 'status' => StatusCobranca::Cancelada]));

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $this->assertNull($dados['desconto']);
        $this->assertNull($dados['valor_pago']);
        $this->assertSame(['Valor por extenso: cem reais.'], $dados['informacoes']);
        $this->assertSame(['forma' => null], $dados['pagamento']);
    }

    public function test_pix_pendente_usa_o_valor_do_dia(): void
    {
        $aVencer = $this->renderizador->dados($this->comPix($this->reciboDoContrato()), CarbonImmutable::now())['pagamento'];
        $vencida = $this->renderizador->dados($this->comPix($this->reciboDoContrato(['vencimento' => '2026-09-19'])), CarbonImmutable::now())['pagamento'];

        $this->assertSame('pix', $aVencer['forma']);
        $this->assertStringContainsString('540585.00', $aVencer['copia_e_cola']);
        $this->assertSame(QrCodePix::dataUri($aVencer['copia_e_cola']), $aVencer['qr']);
        $this->assertStringContainsString('5406102.67', $vencida['copia_e_cola']);
        $this->assertSame(QrCodePix::dataUri($vencida['copia_e_cola']), $vencida['qr']);
    }

    public function test_valor_do_dia_zero_nao_gera_pix_nem_qr(): void
    {
        // Desconto de 100% até o vencimento: o cliente não paga nada, então não há o que cobrar por PIX.
        $recibo = $this->comPix($this->reciboDoContrato([], ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 10000]));

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());
        $html = view($recibo->modelo->view(), $dados)->render();

        $this->assertSame(['forma' => null], $dados['pagamento']);
        $this->assertSame(['100,00', '0,00'], [$dados['desconto'], $dados['valor_pago']]);
        $this->assertStringNotContainsString('data:image/png', $html);
        $this->assertStringNotContainsString('copia e cola', strtolower($html));
    }

    public function test_boleto_com_valor_do_dia_diferente_avisa_que_foi_emitido_com_o_valor_original(): void
    {
        $linha = '23793381286000000000300000000400184340000026060';
        $comDesconto = $this->reciboDoContrato(['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => $linha]);
        $semMudanca = $this->reciboDoContrato(
            ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => $linha],
            ['desconto_pontualidade_tipo' => null, 'desconto_pontualidade_valor' => null],
        );

        $dados = $this->renderizador->dados($comDesconto, CarbonImmutable::now());
        $this->assertSame('O boleto foi emitido com o valor original.', end($dados['informacoes']));
        $this->assertSame(['forma' => 'boleto', 'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060'], $dados['pagamento']);

        // Antes do vencimento só com multa e juros: o valor do dia é o original, então não há o que avisar.
        $this->assertNotContains('O boleto foi emitido com o valor original.', $this->renderizador->dados($semMudanca, CarbonImmutable::now())['informacoes']);
    }
}
