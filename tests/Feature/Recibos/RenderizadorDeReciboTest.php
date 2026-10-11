<?php

namespace Tests\Feature\Recibos;

use App\Enums\FormaPagamento;
use App\Enums\ModeloRecibo;
use App\Enums\StatusCobranca;
use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Recibo;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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

    /** T17.13: o tomador continua com o nome oficial; o apelido sai em "Unidade atendida", só nos modelos -v2. */
    public function test_apelido_sai_como_unidade_atendida_so_nos_modelos_novos(): void
    {
        $html = fn (Recibo $recibo) => view($recibo->modelo->view(), $this->renderizador->dados($recibo, CarbonImmutable::now()))->render();

        foreach (['webiss', 'danfse'] as $familia) {
            $recibo = $this->recibo(['modelo' => $familia, 'nome' => 'Zeta Sindicato', 'apelido' => 'Regional Araci']);
            $this->assertSame('Regional Araci', $recibo->snapshot['tomador']['apelido']);
            $this->assertStringContainsString('Unidade atendida', $html($recibo), $familia);
            $this->assertStringContainsString('Regional Araci', $html($recibo), $familia);
            $this->assertStringContainsString('Zeta Sindicato', $html($recibo), "{$familia}: nome oficial no tomador");

            $this->assertStringNotContainsString('Unidade atendida', $html($this->recibo(['modelo' => $familia])), "{$familia} sem apelido");
        }

        // Recibo já emitido (v1, snapshot sem a chave): sai igual a antes, e o -v1 nunca mostra a linha.
        $antigo = $this->recibo(['apelido' => 'Regional Araci']);
        $snapshot = $antigo->snapshot;
        unset($snapshot['tomador']['apelido']);
        $antigo->forceFill(['modelo' => ModeloRecibo::WebissV1, 'snapshot' => $snapshot])->save();
        $this->assertSame('', $this->renderizador->dados($antigo->fresh(), CarbonImmutable::now())['tomador']['apelido']);
        $this->assertStringNotContainsString('Unidade atendida', $html($antigo->fresh()));
    }
}
