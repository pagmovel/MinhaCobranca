<?php

namespace Tests\Feature;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\ComItens;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    private function dados(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'itens' => $this->itensPara($cliente),
            'periodicidade' => 'mensal',
            'modalidade' => 'prepago',
            'dia_vencimento' => 21,
            'data_inicio' => '2026-09-21',
            'data_fim' => null,
            'ativo' => true,
        ], $extra);
    }

    /** @return array{0: User, 1: Cliente} */
    private function usuarioComCliente(): array
    {
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    public function test_cria_contrato_convertendo_o_valor(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente))->assertRedirect('/contratos');

        $contrato = $usuario->contratos()->firstOrFail();
        $this->assertSame(26060, $contrato->valor_centavos);
        $this->assertSame(Periodicidade::Mensal, $contrato->periodicidade);
        $this->assertSame('2026-09-21', $contrato->data_inicio->toDateString());
        $this->assertTrue($contrato->ativo);
    }

    public function test_lista_com_cliente_e_valor_formatado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        Contrato::factory()->for($usuario)->for($cliente)->create(['valor_centavos' => 26060]);

        $this->actingAs($usuario)->get('/contratos')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contratos/Index')
                ->has('contratos', 1)
                ->where('contratos.0.cliente', $cliente->nome)
                ->where('contratos.0.valor', '260,60'));
    }

    public function test_cliente_de_outro_usuario_e_recusado(): void
    {
        [$usuario] = $this->usuarioComCliente();
        $alheio = Cliente::factory()->create();

        $this->actingAs($usuario)->post('/contratos', $this->dados($alheio))
            ->assertSessionHasErrors(['cliente_id' => 'O valor escolhido para cliente é inválido.']);
    }

    public function test_descricao_longa_demais_e_recusada(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat("linha\n", 26)]))
            ->assertSessionHasErrors(['descricao' => 'Use no máximo 25 linhas.']);
        // Linha comprida ocupa várias linhas na caixa do recibo: conta pelo que aparece no PDF.
        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat('palavra ', 60)."\n".str_repeat("linha\n", 21)]))
            ->assertSessionHasErrors(['descricao' => 'Use no máximo 25 linhas.']);
        $this->post('/contratos', $this->dados($cliente, ['descricao' => str_repeat('a', 2001)]))
            ->assertSessionHasErrors(['descricao' => 'O campo descrição deve ter no máximo 2000 caracteres.']);
    }

    public function test_datas_dia_de_vencimento_e_valor_invalidos(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, ['data_fim' => '2026-09-01']))->assertSessionHasErrors('data_fim');
        $this->post('/contratos', $this->dados($cliente, ['dia_vencimento' => 0]))
            ->assertSessionHasErrors(['dia_vencimento' => 'O campo dia do vencimento deve estar entre 1 e 31.']);
        $this->post('/contratos', $this->dados($cliente, ['dia_vencimento' => 32]))->assertSessionHasErrors('dia_vencimento');
        $this->post('/contratos', $this->dados($cliente, ['itens' => $this->itensPara($cliente, '0,00')]))
            ->assertSessionHasErrors(['itens' => 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.']);
    }

    public function test_encerra_contrato_e_isola_contrato_alheio(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $alheio = Contrato::factory()->create();

        $this->actingAs($usuario)->put("/contratos/{$contrato->id}", $this->dados($cliente, ['ativo' => false]))
            ->assertRedirect('/contratos');
        $this->assertFalse($contrato->fresh()->ativo);

        $this->get("/contratos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/contratos/{$alheio->id}", $this->dados($cliente))->assertNotFound();
    }

    public function test_contrato_pos_pago_gera_cobranca_que_vence_depois_do_periodo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        [$usuario, $cliente] = $this->usuarioComCliente();
        $usuario->update(['dias_antecedencia' => 10]);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            'modalidade' => 'pospago', 'data_inicio' => '2026-10-01', 'dia_vencimento' => 10,
        ]))->assertRedirect('/contratos');

        app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::today());

        $cobranca = $usuario->cobrancas()->sole();
        $this->assertSame(
            ['2026-10-01', '2026-10-31', '2026-11-10'],
            [$cobranca->periodo_inicio->toDateString(), $cobranca->periodo_fim->toDateString(), $cobranca->vencimento->toDateString()],
        );
    }

    public function test_modalidade_invalida_e_recusada_e_nao_muda_com_cobrancas(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $this->post('/contratos', $this->dados($cliente, ['modalidade' => 'quinzenal']))->assertSessionHasErrors('modalidade');

        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        Cobranca::factory()->for($usuario)->for($cliente)->create(['contrato_id' => $contrato->id]);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, ['modalidade' => 'pospago']))
            ->assertSessionHasErrors(['data_inicio' => 'Confirme a correção: as cobranças pendentes deste contrato serão canceladas.']);
        $this->assertSame(Modalidade::Prepago, $contrato->fresh()->modalidade);
    }
}
