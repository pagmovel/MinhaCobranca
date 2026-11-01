<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Enums\TipoAvisoModelo;
use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ModeloMensagem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** Convenção do projeto: descrição em lista já começa com "- " (ex.: ContratoFactory) — não pode duplicar o traço. */
    public function test_cobranca_sem_itens_com_descricao_em_lista_nao_duplica_o_traco(): void
    {
        $cobranca = $this->cobranca(['descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos", 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Hospedagem do domínio exemplo.com.br', 'valor_centavos' => 5000]]]);
    }

    public function test_cobranca_sem_itens_e_sem_descricao_usa_servico(): void
    {
        $cobranca = $this->cobranca(['descricao' => '', 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Serviço', 'valor_centavos' => 5000]]]);
    }

    /** Pedido do usuário (2026-10-11): pré-selecionar o modelo cadastrado para a situação da cobrança. */
    public function test_sugere_o_modelo_marcado_para_a_situacao_da_cobranca(): void
    {
        $modeloVencida = ModeloMensagem::factory()->for($this->usuario)->create(['tipo_situacao' => TipoAvisoModelo::Vencida]);
        $cobranca = $this->cobranca(['vencimento' => '2026-10-05']); // antes de "hoje" (2026-10-11): vencida

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['modelo_sugerido_id' => $modeloVencida->id]);
    }

    public function test_sugere_o_modelo_de_vence_hoje(): void
    {
        $modeloHoje = ModeloMensagem::factory()->for($this->usuario)->create(['tipo_situacao' => TipoAvisoModelo::VenceHoje]);
        $cobranca = $this->cobranca(['vencimento' => '2026-10-11']); // "hoje"

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['modelo_sugerido_id' => $modeloHoje->id]);
    }

    public function test_sem_modelo_cadastrado_para_a_situacao_nao_sugere_nada(): void
    {
        ModeloMensagem::factory()->for($this->usuario)->create(['tipo_situacao' => TipoAvisoModelo::AVencer]);
        $cobranca = $this->cobranca(['vencimento' => '2026-10-05']); // vencida, sem modelo desse tipo

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['modelo_sugerido_id' => null]);
    }

    /** Lote misto (decisão do usuário): usa a flag mais urgente do grupo — vencida > vence hoje > a vencer. */
    public function test_grupo_misto_usa_o_modelo_da_situacao_mais_urgente(): void
    {
        $modeloVencida = ModeloMensagem::factory()->for($this->usuario)->create(['tipo_situacao' => TipoAvisoModelo::Vencida]);
        ModeloMensagem::factory()->for($this->usuario)->create(['nome' => 'A vencer', 'tipo_situacao' => TipoAvisoModelo::AVencer]);
        $vencida = $this->cobranca(['vencimento' => '2026-10-05']);
        $aVencer = $this->cobranca(['vencimento' => '2026-10-20']);

        $this->getJson("/cobrancas/aviso?ids[]={$vencida->id}&ids[]={$aVencer->id}")
            ->assertJson(['modelo_sugerido_id' => $modeloVencida->id]);
    }

    public function test_inclui_apelido_e_razao_social_separados(): void
    {
        $this->cliente->forceFill(['nome' => 'Padaria do João LTDA', 'apelido' => 'Padaria'])->save();
        $cobranca = $this->cobranca();

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['apelido' => 'Padaria', 'razao_social' => 'Padaria do João LTDA']);
    }

    public function test_apelido_vazio_quando_o_cliente_nao_tem_um_cadastrado(): void
    {
        $cobranca = $this->cobranca();

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['apelido' => '']);
    }

    public function test_inclui_o_contato_quando_o_cliente_tem_um_cadastrado(): void
    {
        $this->cliente->forceFill(['contato' => 'Wendel'])->save();
        $cobranca = $this->cobranca();

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['contato' => 'Wendel']);
    }

    public function test_contato_vazio_quando_o_cliente_nao_tem_um_cadastrado(): void
    {
        $cobranca = $this->cobranca();

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['contato' => '']);
    }

    public function test_inclui_a_chave_pix_quando_a_forma_de_pagamento_e_pix(): void
    {
        $chave = ChavePix::factory()->for($this->usuario)->create(['tipo' => TipoChavePix::Email, 'chave' => 'cobranca@paguemovel.net.br']);
        $cobranca = $this->cobranca(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['chave_pix' => 'cobranca@paguemovel.net.br']);
    }

    public function test_chave_pix_vazia_quando_a_forma_de_pagamento_nao_e_pix(): void
    {
        $cobranca = $this->cobranca(['forma_pagamento' => FormaPagamento::Nenhuma]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertJson(['chave_pix' => '']);
    }

    /** Decisão do usuário (2026-10-11): em lote, usa a chave PIX da primeira cobrança do grupo que tiver uma. */
    public function test_chave_pix_em_lote_usa_a_da_primeira_cobranca_que_tiver(): void
    {
        $chave = ChavePix::factory()->for($this->usuario)->create(['tipo' => TipoChavePix::Email, 'chave' => 'a@exemplo.com']);
        $semPix = $this->cobranca(['vencimento' => '2026-10-13', 'forma_pagamento' => FormaPagamento::Nenhuma]);
        $comPix = $this->cobranca(['vencimento' => '2026-10-14', 'forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);

        $this->getJson("/cobrancas/aviso?ids[]={$semPix->id}&ids[]={$comPix->id}")->assertJson(['chave_pix' => 'a@exemplo.com']);
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

    /** Cobrança de R$ 100,00 do cliente do teste, ligada a um contrato; sem `$contrato`, o de 15% / multa 2% / juros 1% ao mês. */
    private function cobrancaDeContrato(array $extra = [], ?array $contrato = null): Cobranca
    {
        $fabrica = $contrato === null ? Contrato::factory()->comCondicoes() : Contrato::factory();
        $registro = $fabrica->create(array_merge(['user_id' => $this->usuario->id, 'cliente_id' => $this->cliente->id, 'ativo' => false], $contrato ?? []));

        return $this->cobranca(array_merge(['contrato_id' => $registro->id, 'valor_centavos' => 10000], $extra));
    }

    public function test_total_soma_o_valor_do_dia_da_vencida_com_regra_e_os_itens_ficam_com_o_valor_original(): void
    {
        // Hoje é 11/10/2026: 30 dias de atraso, multa 2% = R$ 2,00 e juros 1% ao mês = R$ 1,00.
        $cobranca = $this->cobrancaDeContrato(['vencimento' => '2026-09-11']);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertOk()->assertJson([
            'itens' => [['nome' => 'Serviço avulso', 'valor_centavos' => 10000]],
            'total_centavos' => 10300,
        ]);
    }

    public function test_total_da_a_vencer_e_da_que_vence_hoje_leva_o_desconto_do_contrato(): void
    {
        $aVencer = $this->cobrancaDeContrato(['vencimento' => '2026-10-15']);
        $venceHoje = $this->cobrancaDeContrato(['vencimento' => '2026-10-11']);

        $this->getJson("/cobrancas/aviso?ids[]={$aVencer->id}")->assertJson(['total_centavos' => 8500]);
        $this->getJson("/cobrancas/aviso?ids[]={$venceHoje->id}")->assertJson(['total_centavos' => 8500]);
    }

    public function test_total_sem_regra_no_contrato_ou_sem_contrato_continua_o_valor_original(): void
    {
        $semRegra = $this->cobrancaDeContrato(['vencimento' => '2026-09-11'], []);
        $avulsa = $this->cobranca(['vencimento' => '2026-09-11', 'valor_centavos' => 10000]);

        $this->getJson("/cobrancas/aviso?ids[]={$semRegra->id}&ids[]={$avulsa->id}")
            ->assertJson(['total_centavos' => 20000, 'condicao' => '']);
    }

    public function test_total_do_lote_soma_o_valor_do_dia_de_cada_cobranca(): void
    {
        $vencida = $this->cobrancaDeContrato(['vencimento' => '2026-09-11']);
        $aVencer = $this->cobrancaDeContrato(['vencimento' => '2026-10-15']);
        $avulsa = $this->cobranca(['vencimento' => '2026-10-20', 'valor_centavos' => 4000]);

        $this->getJson("/cobrancas/aviso?ids[]={$vencida->id}&ids[]={$aVencer->id}&ids[]={$avulsa->id}")->assertOk()->assertJson([
            'itens' => [
                ['valor_centavos' => 10000],
                ['valor_centavos' => 10000],
                ['valor_centavos' => 4000],
            ],
            'total_centavos' => 10300 + 8500 + 4000,
        ]);
    }

    public function test_condicao_traz_a_frase_do_recibo_da_cobranca(): void
    {
        $aVencer = $this->cobrancaDeContrato(['vencimento' => '2026-10-15']);
        $vencida = $this->cobrancaDeContrato(['vencimento' => '2026-09-11']);

        $this->getJson("/cobrancas/aviso?ids[]={$aVencer->id}")
            ->assertJson(['condicao' => 'Pagando até 15/10/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.']);
        $this->getJson("/cobrancas/aviso?ids[]={$vencida->id}")
            ->assertJson(['condicao' => 'Valor atualizado em 11/10/2026: multa R$ 2,00; juros R$ 1,00.']);
    }

    public function test_condicao_do_lote_junta_as_frases_diferentes_por_espaco_e_nao_repete_a_igual(): void
    {
        $umaAVencer = $this->cobrancaDeContrato(['vencimento' => '2026-10-15']);
        $outraAVencer = $this->cobrancaDeContrato(['vencimento' => '2026-10-15']);
        $vencida = $this->cobrancaDeContrato(['vencimento' => '2026-09-11']);
        $avulsa = $this->cobranca(['vencimento' => '2026-10-20']);

        $resposta = $this->getJson("/cobrancas/aviso?ids[]={$avulsa->id}&ids[]={$umaAVencer->id}&ids[]={$vencida->id}&ids[]={$outraAVencer->id}");

        // Na ordem do vencimento, em qualquer ordem em que os ids cheguem.
        $resposta->assertJson([
            'condicao' => 'Valor atualizado em 11/10/2026: multa R$ 2,00; juros R$ 1,00. Pagando até 15/10/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.',
        ]);
    }

    /** @return array<string, array{array<string, mixed>, string}> campos do contrato e o trecho que a frase diz depois do vencimento */
    public static function contratosComMultaOuJuros(): array
    {
        $desconto = ['desconto_pontualidade_tipo' => 'percentual', 'desconto_pontualidade_valor' => 1500];

        return [
            'só multa' => [$desconto + ['multa_tipo' => 'percentual', 'multa_valor' => 200], 'R$ 100,00 + multa.'],
            'só juros' => [$desconto + ['juros_tipo' => 'percentual', 'juros_valor' => 100], 'R$ 100,00 + juros.'],
            'multa e juros' => [$desconto + ['multa_tipo' => 'percentual', 'multa_valor' => 200, 'juros_tipo' => 'percentual', 'juros_valor' => 100], 'R$ 100,00 + multa e juros.'],
            'nenhum dos dois' => [$desconto, 'R$ 100,00.'],
        ];
    }

    #[DataProvider('contratosComMultaOuJuros')]
    public function test_condicao_antes_do_vencimento_cita_so_o_encargo_que_o_contrato_tem(array $contrato, string $depois): void
    {
        $cobranca = $this->cobrancaDeContrato(['vencimento' => '2026-10-15'], $contrato);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['condicao' => "Pagando até 15/10/2026: R$ 85,00. Depois: {$depois}"]);
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

    public function test_painel_vencendo_agrupa_por_cliente_dentro_da_janela_de_dias(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $daJanela = $this->cobranca(['vencimento' => '2026-10-14', 'valor_centavos' => 8990]); // hoje + 3
        $this->cobranca(['vencimento' => '2026-10-20']); // hoje + 9, fora dos 5 dias
        $this->cobranca(['vencimento' => '2026-10-13', 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);

        $this->getJson('/painel/dados?lista=vencendo')->assertOk()->assertJson([
            'linhas' => [[
                'cliente' => $this->cliente->exibicao(),
                'quantidade' => 1,
                'vencimentos' => ['14/10/2026'],
                'total_centavos' => 8990,
                'ids' => [$daJanela->id],
            ]],
            'total_geral' => ['quantidade' => 1, 'soma_centavos' => 8990],
        ]);
    }

    /** Achado da revisão (T24.9): o teste anterior tinha 1 cliente e 1 cobrança — não provava o agrupamento. */
    public function test_painel_vencendo_agrupa_duas_cobrancas_do_mesmo_cliente(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $uma = $this->cobranca(['vencimento' => '2026-10-13', 'valor_centavos' => 3000]);
        $outra = $this->cobranca(['vencimento' => '2026-10-14', 'valor_centavos' => 4000]);

        $this->getJson('/painel/dados?lista=vencendo')->assertJson(['linhas' => [[
            'cliente' => $this->cliente->exibicao(),
            'quantidade' => 2,
            'vencimentos' => ['13/10/2026', '14/10/2026'],
            'total_centavos' => 7000,
        ]]])->assertJsonCount(1, 'linhas');
        $this->assertEqualsCanonicalizing([$uma->id, $outra->id], $this->getJson('/painel/dados?lista=vencendo')->json('linhas.0.ids'));
    }

    public function test_painel_vencendo_separa_grupos_por_cliente_diferente(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $outroCliente = Cliente::factory()->for($this->usuario)->create();
        $this->cobranca(['vencimento' => '2026-10-15']);
        Cobranca::factory()->for($this->usuario)->for($outroCliente)->create(['status' => StatusCobranca::Pendente, 'vencimento' => '2026-10-12']);

        $this->getJson('/painel/dados?lista=vencendo')->assertJsonCount(2, 'linhas');
    }

    public function test_painel_vencendo_inclui_as_bordas_hoje_e_hoje_mais_dias_aviso(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $hoje = $this->cobranca(['vencimento' => '2026-10-11']);
        $limite = $this->cobranca(['vencimento' => '2026-10-16']); // hoje + 5, borda inclusiva

        // Mesmo cliente: as duas caem no mesmo grupo — o que importa aqui é que as DUAS bordas entram na janela.
        $ids = $this->getJson('/painel/dados?lista=vencendo')->json('linhas.0.ids');
        $this->assertEqualsCanonicalizing([$hoje->id, $limite->id], $ids);
    }

    public function test_painel_vencendo_exclui_cobranca_vencida_e_de_outro_usuario(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $this->cobranca(['vencimento' => '2026-10-10']); // ontem, vencida — fora da janela "de hoje até hoje+N"
        Cobranca::factory()->create(['status' => StatusCobranca::Pendente, 'vencimento' => '2026-10-12']); // outro usuário

        $this->getJson('/painel/dados?lista=vencendo')->assertJsonCount(0, 'linhas');
    }

    public function test_painel_vencendo_mostra_avisado_em_mais_recente_do_grupo(): void
    {
        $this->cobranca(['vencimento' => '2026-10-13', 'avisado_em' => '2026-10-05 10:00:00']);
        $this->cobranca(['vencimento' => '2026-10-14', 'avisado_em' => '2026-10-08 10:00:00']);

        $this->getJson('/painel/dados?lista=vencendo')->assertJsonPath('linhas.0.avisado_em', '08/10');
    }
}
