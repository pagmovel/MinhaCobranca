<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AvisoWhatsappTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create(['telefone' => '(79) 99999-0000']);
        $this->actingAs($this->usuario);
    }

    private function cobranca(array $extra = []): Cobranca
    {
        return Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(array_merge(['status' => StatusCobranca::Pendente], $extra));
    }

    public function test_monta_os_dados_com_os_itens_da_cobranca(): void
    {
        $cobranca = $this->cobranca(['vencimento' => '2026-10-15', 'valor_centavos' => 8990]);
        $cobranca->itens()->create(['nome' => 'Hospedagem', 'unidade' => 'mês', 'quantidade' => '1.000', 'preco_unitario_centavos' => 8990, 'total_centavos' => 8990, 'principal' => true]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertOk()->assertJson([
            'cliente' => $this->cliente->exibicao(),
            'telefone' => '(79) 99999-0000',
            'itens' => [['nome' => 'Hospedagem', 'valor_centavos' => 8990]],
            'vencimentos' => ['2026-10-15'],
            'total_centavos' => 8990,
        ]);
    }

    public function test_cobranca_sem_itens_usa_a_primeira_linha_da_descricao(): void
    {
        $cobranca = $this->cobranca(['descricao' => "Manutenção mensal\nDetalhe que não entra", 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Manutenção mensal', 'valor_centavos' => 5000]]]);
    }

    public function test_cobranca_sem_itens_e_sem_descricao_usa_servico(): void
    {
        $cobranca = $this->cobranca(['descricao' => '', 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Serviço', 'valor_centavos' => 5000]]]);
    }

    public function test_junta_varias_cobrancas_do_mesmo_cliente(): void
    {
        $uma = $this->cobranca(['vencimento' => '2026-10-15', 'valor_centavos' => 3000]);
        $outra = $this->cobranca(['vencimento' => '2026-10-20', 'valor_centavos' => 4000]);

        $this->getJson("/cobrancas/aviso?ids[]={$uma->id}&ids[]={$outra->id}")->assertJson([
            'vencimentos' => ['2026-10-15', '2026-10-20'],
            'total_centavos' => 7000,
        ]);
    }

    public function test_ids_de_clientes_diferentes_e_recusado(): void
    {
        $outroCliente = Cliente::factory()->for($this->usuario)->create();
        $uma = $this->cobranca();
        $deOutroCliente = Cobranca::factory()->for($this->usuario)->for($outroCliente)->create(['status' => StatusCobranca::Pendente]);

        $this->getJson("/cobrancas/aviso?ids[]={$uma->id}&ids[]={$deOutroCliente->id}")->assertStatus(422);
    }

    public function test_ids_alheios_ou_ja_pagos_sao_ignorados_e_so_resta_nenhum_da_404(): void
    {
        $alheia = Cobranca::factory()->create(['status' => StatusCobranca::Pendente]);
        $this->getJson("/cobrancas/aviso?ids[]={$alheia->id}")->assertNotFound();

        $paga = $this->cobranca(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);
        $this->getJson("/cobrancas/aviso?ids[]={$paga->id}")->assertNotFound();
    }

    public function test_id_nao_numerico_e_recusado_sem_erro_500(): void
    {
        $this->getJson('/cobrancas/aviso?ids[]=abc')->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    }

    public function test_avisadas_grava_so_nas_proprias_pendentes(): void
    {
        $minha = $this->cobranca();
        $paga = $this->cobranca(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);
        $alheia = Cobranca::factory()->create(['status' => StatusCobranca::Pendente]);

        $this->post('/cobrancas/avisadas', ['ids' => [$minha->id, $paga->id, $alheia->id]])->assertRedirect();

        $this->assertNotNull($minha->fresh()->avisado_em);
        $this->assertNull($paga->fresh()->avisado_em);
        $this->assertNull($alheia->fresh()->avisado_em);
    }
}
