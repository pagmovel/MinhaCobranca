<?php

namespace Tests\Feature\Recibos;

use App\Enums\StatusCobranca;
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
}
