<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DescontoNaBaixaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Cobrança pendente de R$ 247,57 do usuário logado; o relógio fica em 01/11/2026. */
    private function usuarioComCobranca(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 10:00:00'));
        $usuario = User::factory()->create();
        $cobranca = Cobranca::factory()->create(['user_id' => $usuario->id, 'valor_centavos' => 24757]);
        $this->actingAs($usuario);

        return [$usuario, $cobranca];
    }

    private function resumoDaAuditoria(User $usuario): string
    {
        return $usuario->auditorias()->where('acao', 'cobranca.paga')->sole()->resumo;
    }

    public function test_baixa_com_desconto_percentual_grava_e_audita(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '10'])
            ->assertSessionHas('sucesso', 'Baixa registrada.');

        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Paga, $cobranca->status);
        $this->assertSame(2476, $cobranca->desconto_centavos);
        $this->assertSame(22281, $cobranca->valorPagoCentavos());
        $this->assertSame(
            'Baixa registrada (R$ 247,57, desconto 10% = R$ 24,76, pago R$ 222,81, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_baixa_com_desconto_em_reais(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '24,76'])
            ->assertSessionHas('sucesso');

        $this->assertSame(2476, $cobranca->fresh()->desconto_centavos);
        $this->assertSame(
            'Baixa registrada (R$ 247,57, desconto R$ 24,76, pago R$ 222,81, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_baixa_com_desconto_integral_audita_como_integral(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '100'])
            ->assertSessionHas('sucesso');

        $cobranca->refresh();
        $this->assertSame(24757, $cobranca->desconto_centavos);
        $this->assertSame(0, $cobranca->valorPagoCentavos());
        $this->assertSame(
            'Baixa com desconto integral (100% = R$ 247,57, pago R$ 0,00, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_desconto_em_reais_igual_ao_valor_tambem_e_integral(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '247,57'])
            ->assertSessionHas('sucesso');

        $this->assertSame(
            'Baixa com desconto integral (100% = R$ 247,57, pago R$ 0,00, em 01/11/2026)',
            $this->resumoDaAuditoria($usuario),
        );
    }

    public function test_desconto_acima_do_valor_e_recusado(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '150'])
            ->assertSessionHasErrors(['desconto' => 'O desconto não pode passar do valor da cobrança.']);
        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '247,58'])
            ->assertSessionHasErrors(['desconto' => 'O desconto não pode passar do valor da cobrança.']);

        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Pendente, $cobranca->status);
        $this->assertSame(0, $cobranca->desconto_centavos);
        $this->assertSame(0, $usuario->auditorias()->count());
    }

    public function test_desconto_com_formato_ou_tipo_invalido_e_recusado(): void
    {
        [, $cobranca] = $this->usuarioComCobranca();
        $url = "/cobrancas/{$cobranca->id}/pagar";

        $this->post($url, ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => 'abc'])
            ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        $this->post($url, ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '-5'])
            ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        $this->post($url, ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '1e3'])
            ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        $this->post($url, ['pago_em' => '2026-11-01', 'desconto' => '10'])
            ->assertSessionHasErrors('desconto_tipo');
        $this->post($url, ['pago_em' => '2026-11-01', 'desconto_tipo' => 'dobro', 'desconto' => '10'])
            ->assertSessionHasErrors('desconto_tipo');

        $this->assertSame(StatusCobranca::Pendente, $cobranca->fresh()->status);
    }

    public function test_baixa_sem_desconto_mantem_texto_de_hoje(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();
        $segunda = Cobranca::factory()->create(['user_id' => $usuario->id, 'valor_centavos' => 24757]);

        // A tela manda o tipo e o campo vazio; um "0" digitado também não é desconto.
        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => ''])
            ->assertSessionHas('sucesso');
        $this->post("/cobrancas/{$segunda->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '0'])
            ->assertSessionHas('sucesso');

        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
        $this->assertSame(0, $segunda->fresh()->desconto_centavos);
        $this->assertSame(
            ['Baixa registrada (R$ 247,57, paga em 01/11/2026)', 'Baixa registrada (R$ 247,57, paga em 01/11/2026)'],
            $usuario->auditorias()->where('acao', 'cobranca.paga')->pluck('resumo')->all(),
        );
    }

    public function test_pedido_antigo_sem_campos_de_desconto_continua_valendo(): void
    {
        [, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01'])->assertSessionHas('sucesso');

        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
    }

    public function test_desconto_centavos_enviado_direto_e_ignorado(): void
    {
        [, $cobranca] = $this->usuarioComCobranca();

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_centavos' => 5000])
            ->assertSessionHas('sucesso');

        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
    }

    public function test_cobranca_ja_paga_nao_troca_o_desconto_pela_baixa(): void
    {
        [, $cobranca] = $this->usuarioComCobranca();
        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '10,00']);

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'valor', 'desconto' => '50,00'])
            ->assertSessionHas('erro', 'Só cobranças pendentes recebem baixa.');

        $this->assertSame(1000, $cobranca->fresh()->desconto_centavos);
    }

    public function test_cobranca_alheia_da_404_antes_da_validacao(): void
    {
        [$usuario, $propria] = $this->usuarioComCobranca();
        $alheia = Cobranca::factory()->create(['valor_centavos' => 24757]);

        // A rota existe para a própria cobrança; o 404 abaixo vem do isolamento, não de rota ausente.
        $this->post("/cobrancas/{$propria->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => '10'])
            ->assertSessionHas('sucesso');

        $this->post("/cobrancas/{$alheia->id}/pagar", ['pago_em' => '2026-11-01', 'desconto_tipo' => 'percentual', 'desconto' => 'abc'])
            ->assertNotFound();
        $this->assertSame(StatusCobranca::Pendente, $alheia->fresh()->status);
        $this->assertSame(1, $usuario->auditorias()->count());
    }

    public function test_cobranca_sem_desconto_paga_o_valor_cheio(): void
    {
        $cobranca = Cobranca::factory()->create()->refresh();

        $this->assertSame(0, $cobranca->desconto_centavos);
        $this->assertSame($cobranca->valor_centavos, $cobranca->valorPagoCentavos());
    }

    public function test_valor_pago_e_o_valor_menos_o_desconto(): void
    {
        $cobranca = Cobranca::factory()->create(['valor_centavos' => 26060, 'desconto_centavos' => 2476])->refresh();

        $this->assertSame(2476, $cobranca->desconto_centavos);
        $this->assertSame(23584, $cobranca->valorPagoCentavos());
    }

    public function test_desconto_nao_entra_por_atribuicao_em_massa(): void
    {
        $cobranca = (new Cobranca)->fill(['valor_centavos' => 26060, 'desconto_centavos' => 500]);

        $this->assertSame(0, $cobranca->desconto_centavos);
    }

    /** Cobrança paga de R$ 247,57 em 20/10/2026, com desconto de R$ 10,00 (a senha da factory é "password"). */
    private function usuarioComCobrancaPaga(): array
    {
        [$usuario, $cobranca] = $this->usuarioComCobranca();
        $cobranca->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-20', 'desconto_centavos' => 1000])->save();

        return [$usuario, $cobranca];
    }

    private function corrigir(Cobranca $cobranca, array $campos): TestResponse
    {
        return $this->post("/cobrancas/{$cobranca->id}/corrigir-pagamento", array_merge(['senha' => 'password', 'motivo' => 'Desconto combinado errado'], $campos));
    }

    public function test_corrigir_muda_data_e_desconto_com_auditoria_antes_e_depois(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobrancaPaga();

        $this->corrigir($cobranca, ['pago_em' => '2026-10-25', 'desconto_tipo' => 'percentual', 'desconto' => '10'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso');

        $cobranca->refresh();
        $this->assertSame('2026-10-25', $cobranca->pago_em->toDateString());
        $this->assertSame(2476, $cobranca->desconto_centavos);
        $this->assertSame(22281, $cobranca->valorPagoCentavos());

        $auditoria = $usuario->auditorias()->where('acao', 'pagamento.corrigido')->sole();
        $this->assertSame(['pago_em' => '2026-10-20', 'desconto_centavos' => 1000], $auditoria->antes);
        $this->assertSame(['pago_em' => '2026-10-25', 'desconto_centavos' => 2476], $auditoria->depois);
        $this->assertSame('Desconto combinado errado', $auditoria->motivo);
        $this->assertSame('Pagamento corrigido (antes: 20/10/2026, desconto de R$ 10,00 para R$ 24,76)', $auditoria->resumo);
    }

    public function test_corrigir_sem_mexer_no_desconto_mantem_o_desconto(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobrancaPaga();

        // A tela abre em R$ com o desconto atual; só a data muda.
        $this->corrigir($cobranca, ['pago_em' => '2026-10-25', 'desconto_tipo' => 'valor', 'desconto' => '10,00'])
            ->assertSessionHasNoErrors();

        $cobranca->refresh();
        $this->assertSame('2026-10-25', $cobranca->pago_em->toDateString());
        $this->assertSame(1000, $cobranca->desconto_centavos);

        $auditoria = $usuario->auditorias()->where('acao', 'pagamento.corrigido')->sole();
        $this->assertSame(1000, $auditoria->antes['desconto_centavos']);
        $this->assertSame(1000, $auditoria->depois['desconto_centavos']);
        $this->assertSame('Pagamento corrigido (antes: 20/10/2026)', $auditoria->resumo);
    }

    public function test_corrigir_so_a_data_sem_os_campos_do_desconto_mantem_o_desconto(): void
    {
        [, $cobranca] = $this->usuarioComCobrancaPaga();

        $this->corrigir($cobranca, ['pago_em' => '2026-10-25'])->assertSessionHasNoErrors();

        $this->assertSame(1000, $cobranca->fresh()->desconto_centavos);
    }

    public function test_corrigir_com_o_campo_do_desconto_vazio_tira_o_desconto(): void
    {
        [, $cobranca] = $this->usuarioComCobrancaPaga();

        $this->corrigir($cobranca, ['pago_em' => '2026-10-20', 'desconto_tipo' => 'valor', 'desconto' => ''])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $cobranca->fresh()->desconto_centavos);
    }

    public function test_corrigir_desconto_exige_senha_e_motivo(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobrancaPaga();
        $desconto = ['pago_em' => '2026-10-25', 'desconto_tipo' => 'percentual', 'desconto' => '50'];

        $this->corrigir($cobranca, $desconto + ['senha' => 'errada'])->assertSessionHasErrors(['senha' => 'Senha incorreta.']);
        $this->corrigir($cobranca, $desconto + ['motivo' => ' '])->assertSessionHasErrors('motivo');

        $cobranca->refresh();
        $this->assertSame('2026-10-20', $cobranca->pago_em->toDateString());
        $this->assertSame(1000, $cobranca->desconto_centavos);
        $this->assertSame(0, $usuario->auditorias()->count());
    }

    public function test_corrigir_desconto_acima_do_valor_ou_invalido_e_recusado(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobrancaPaga();
        $data = ['pago_em' => '2026-10-25'];

        $this->corrigir($cobranca, $data + ['desconto_tipo' => 'valor', 'desconto' => '247,58'])
            ->assertSessionHasErrors(['desconto' => 'O desconto não pode passar do valor da cobrança.']);
        $this->corrigir($cobranca, $data + ['desconto_tipo' => 'percentual', 'desconto' => 'abc'])
            ->assertSessionHasErrors(['desconto' => 'Desconto inválido.']);
        $this->corrigir($cobranca, $data + ['desconto' => '10'])->assertSessionHasErrors('desconto_tipo');

        $cobranca->refresh();
        $this->assertSame('2026-10-20', $cobranca->pago_em->toDateString());
        $this->assertSame(1000, $cobranca->desconto_centavos);
        $this->assertSame(0, $usuario->auditorias()->count());
    }

    public function test_desfazer_baixa_zera_o_desconto(): void
    {
        [$usuario, $cobranca] = $this->usuarioComCobrancaPaga();

        $this->post("/cobrancas/{$cobranca->id}/desfazer-baixa", ['senha' => 'password', 'motivo' => 'Baixa na cobrança errada'])
            ->assertSessionHasNoErrors();

        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Pendente, $cobranca->status);
        $this->assertSame(0, $cobranca->desconto_centavos);
        $this->assertSame($cobranca->valor_centavos, $cobranca->valorPagoCentavos());

        $auditoria = $usuario->auditorias()->where('acao', 'baixa.desfeita')->sole();
        $this->assertSame(1000, $auditoria->antes['desconto_centavos']);
        $this->assertSame(0, $auditoria->depois['desconto_centavos']);
    }

    public function test_lista_de_cobrancas_traz_o_desconto(): void
    {
        [, $cobranca] = $this->usuarioComCobrancaPaga();

        $this->getJson('/cobrancas/dados?status=paga&mes=2026-10')
            ->assertOk()
            ->assertJsonPath('linhas.0.id', $cobranca->id)
            ->assertJsonPath('linhas.0.desconto_centavos', 1000);
    }
}
