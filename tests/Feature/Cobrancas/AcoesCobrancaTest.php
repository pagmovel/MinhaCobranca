<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\FamiliaModelo;
use App\Enums\FormaPagamento;
use App\Enums\ModeloRecibo;
use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\ComItens;
use Tests\TestCase;

class AcoesCobrancaTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    /** @return array{0: User, 1: Cliente} */
    private function prestadorComCliente(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    private function dados(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => 'Manutenção extra do site',
            'itens' => $this->itensPara($cliente, '150,00'),
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-20',
        ], $extra);
    }

    private function cobrancaComRecibo(User $usuario, Cliente $cliente, array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create($extra);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca->fresh('recibo');
    }

    public function test_avulsa_nasce_com_recibo(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();

        $this->actingAs($usuario)->post('/cobrancas', $this->dados($cliente))->assertRedirect('/cobrancas');

        $cobranca = $usuario->cobrancas()->with('recibo')->firstOrFail();
        $this->assertNull($cobranca->contrato_id);
        $this->assertSame(15000, $cobranca->valor_centavos);
        $this->assertSame(1, $cobranca->recibo->numero);
    }

    public function test_avulsa_exige_perfil_completo_e_cliente_proprio(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $this->actingAs($usuario);
        $this->post('/cobrancas', $this->dados(Cliente::factory()->create()))
            ->assertSessionHasErrors(['cliente_id' => 'O valor escolhido para cliente é inválido.']);

        $usuario->update(['cep' => null]);
        $this->post('/cobrancas', $this->dados($cliente))->assertSessionHas('erro', 'Complete seu perfil para emitir recibos.');
        $this->assertSame(0, Cobranca::count());
    }

    public function test_editar_nao_enviada_mantem_o_numero_do_recibo(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}", $this->dados($cliente, ['itens' => $this->itensPara($cliente, '300,00')]))
            ->assertRedirect('/cobrancas');

        $cobranca->refresh()->load('recibo');
        $this->assertSame(30000, $cobranca->valor_centavos);
        $this->assertSame(1, $cobranca->recibo->numero);
        $this->assertSame(30000, $cobranca->recibo->snapshot['servico']['valor_centavos']);
    }

    public function test_trocar_o_cliente_da_avulsa_usa_o_modelo_do_novo_cliente(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $nacional = Cliente::factory()->for($usuario)->create(['modelo' => FamiliaModelo::Danfse]);
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $this->assertSame(ModeloRecibo::WebissV2, $cobranca->recibo->modelo);

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}", $this->dados($nacional))->assertRedirect('/cobrancas');

        $recibo = $cobranca->recibo->fresh();
        $this->assertSame(ModeloRecibo::DanfseV2, $recibo->modelo);
        $this->assertSame(1, $recibo->numero);
        $this->assertSame($nacional->nome, $recibo->snapshot['tomador']['nome']);
    }

    public function test_cobranca_enviada_nao_e_editada(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $cobranca->enviado_em = now();
        $cobranca->save();

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}", $this->dados($cliente, ['itens' => $this->itensPara($cliente, '300,00')]))
            ->assertSessionHas('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        $this->assertSame(26060, $cobranca->fresh()->valor_centavos);
    }

    public function test_cobranca_de_contrato_nao_muda_periodo_nem_cliente(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, [
            'contrato_id' => $contrato->id, 'periodo_inicio' => '2026-09-21', 'periodo_fim' => '2026-10-20',
        ]);

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}", $this->dados($cliente, ['itens' => $this->itensPara($cliente, '300,00')]));

        $cobranca->refresh();
        $this->assertSame('2026-09-21', $cobranca->periodo_inicio->toDateString());
        $this->assertSame(30000, $cobranca->valor_centavos);
    }

    public function test_cancelar_so_pendente(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/cancelar")
            ->assertSessionHas('sucesso', 'Cobrança cancelada. O recibo passa a sair como cancelado.');
        $this->assertSame(StatusCobranca::Cancelada, $cobranca->fresh()->status);

        $this->post("/cobrancas/{$cobranca->id}/cancelar")
            ->assertSessionHas('erro', 'Só cobranças pendentes podem ser canceladas.');
    }

    public function test_baixa_com_data_valida(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $this->actingAs($usuario);

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-10'])->assertSessionHasErrors('pago_em');

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09'])->assertSessionHas('sucesso', 'Baixa registrada.');
        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Paga, $cobranca->status);
        $this->assertSame('2026-10-09', $cobranca->pago_em->toDateString());

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09'])
            ->assertSessionHas('erro', 'Só cobranças pendentes recebem baixa.');
    }

    public function test_marca_como_enviada(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/enviada")->assertRedirect();

        $this->assertNotNull($cobranca->fresh()->enviado_em);
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $propria = $this->cobrancaComRecibo($usuario, $cliente);
        $alheia = Cobranca::factory()->create();
        $this->actingAs($usuario);

        // A rota existe para a própria cobrança; o 404 abaixo vem do isolamento, não de rota ausente.
        $this->post("/cobrancas/{$propria->id}/enviada")->assertRedirect();

        $this->put("/cobrancas/{$alheia->id}", $this->dados($cliente))->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/cancelar")->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/pagar", ['pago_em' => '2026-10-09'])->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/enviada")->assertNotFound();
    }

    public function test_troca_a_forma_de_pagamento_de_cobranca_ja_enviada_sem_mexer_no_resto(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $cobranca->enviado_em = now();
        $cobranca->save();

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}/pagamento", [
            'forma_pagamento' => 'boleto',
            'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060',
            'valor' => '999,99',
        ])->assertSessionHas('sucesso', 'Forma de pagamento atualizada.');

        $cobranca->refresh();
        $this->assertSame(FormaPagamento::Boleto, $cobranca->forma_pagamento);
        $this->assertSame('23793381286000000000300000000400184340000026060', $cobranca->linha_digitavel);
        $this->assertSame(26060, $cobranca->valor_centavos);
    }

    public function test_paga_ou_cancelada_nao_troca_forma_e_alheia_da_404(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $cobranca->status = StatusCobranca::Paga;
        $cobranca->pago_em = '2026-10-09';
        $cobranca->save();
        $this->actingAs($usuario);

        $this->put("/cobrancas/{$cobranca->id}/pagamento", ['forma_pagamento' => 'boleto'])
            ->assertSessionHas('erro', 'Só cobranças pendentes mudam a forma de pagamento.');
        $this->assertSame(FormaPagamento::Nenhuma, $cobranca->fresh()->forma_pagamento);

        $alheia = Cobranca::factory()->create();
        $this->put("/cobrancas/{$alheia->id}/pagamento", ['forma_pagamento' => 'boleto'])->assertNotFound();
        $this->put("/cobrancas/{$alheia->id}/pagamento", ['forma_pagamento' => 'cheque'])->assertNotFound();
    }
}
