<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\ComItens;
use Tests\TestCase;

class ProtecoesTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    public function test_cliente_com_contrato_ou_cobranca_nao_e_excluido(): void
    {
        $usuario = User::factory()->create();
        $comContrato = Cliente::factory()->for($usuario)->create();
        Contrato::factory()->for($usuario)->for($comContrato)->create();
        $comCobranca = Cliente::factory()->for($usuario)->create();
        Cobranca::factory()->for($usuario)->for($comCobranca)->create();

        $this->actingAs($usuario);
        foreach ([$comContrato, $comCobranca] as $cliente) {
            $this->delete("/clientes/{$cliente->id}")
                ->assertSessionHas('erro', 'Este cliente tem contratos ou cobranças e não pode ser excluído.');
            $this->assertModelExists($cliente);
        }
    }

    public function test_contrato_com_cobranca_nao_muda_inicio_nem_periodicidade(): void
    {
        $usuario = User::factory()->create();
        $contrato = Contrato::factory()->for($usuario)->create();
        Cobranca::factory()->for($usuario)->create([
            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'periodo_inicio' => '2026-09-21',
        ]);
        $dados = [
            'cliente_id' => $contrato->cliente_id, 'descricao' => 'Hospedagem', 'itens' => $this->itensPara($contrato->cliente, '300,00'),
            'periodicidade' => 'mensal', 'modalidade' => 'prepago', 'dia_vencimento' => 21, 'data_inicio' => '2026-09-21', 'ativo' => true,
        ];

        $this->actingAs($usuario);
        $this->put("/contratos/{$contrato->id}", ['data_inicio' => '2026-10-01'] + $dados)
            ->assertSessionHasErrors(['data_inicio' => 'Confirme a correção: as cobranças pendentes deste contrato serão canceladas.']);
        $this->put("/contratos/{$contrato->id}", ['periodicidade' => 'anual'] + $dados)
            ->assertSessionHasErrors('data_inicio');

        $this->put("/contratos/{$contrato->id}", $dados)->assertSessionHasNoErrors()->assertRedirect('/contratos');
        $this->assertSame(30000, $contrato->fresh()->valor_centavos, 'valor pode mudar: vale para as próximas');
    }
}
