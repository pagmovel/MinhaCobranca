<?php

namespace Tests\Feature;

use App\Enums\Periodicidade;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'valor' => '260,60',
            'periodicidade' => 'mensal',
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
        $this->post('/contratos', $this->dados($cliente, ['valor' => '0']))
            ->assertSessionHasErrors(['valor' => 'Informe um valor válido, como 260,60.']);
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
}
