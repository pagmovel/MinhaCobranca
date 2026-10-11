<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReajusteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function contratoCom(User $usuario, array $itens, array $extra = []): Contrato
    {
        $contrato = Contrato::factory()->for($usuario)->create($extra);
        foreach ($itens as $ordem => [$produto, $quantidade, $preco]) {
            $contrato->itens()->create([
                'produto_servico_id' => $produto->id, 'quantidade' => $quantidade,
                'preco_unitario_centavos' => $preco, 'principal' => $ordem === 0, 'ordem' => $ordem,
            ]);
        }
        $contrato->update(['valor_centavos' => $contrato->itens()->get()->sum(fn ($item) => $item->total())]);

        return $contrato;
    }

    public function test_lista_so_contratos_ativos_que_usam_o_item(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create(['preco_centavos' => 12000]);
        $outro = ProdutoServico::factory()->for($usuario)->create();
        $usa = $this->contratoCom($usuario, [[$suporte, '2.000', 10000]]);
        $this->contratoCom($usuario, [[$suporte, '1.000', 10000]], ['ativo' => false]);
        $this->contratoCom($usuario, [[$outro, '1.000', 5000]]);

        $this->actingAs($usuario)->get("/produtos-servicos/{$suporte->id}/reajuste")->assertInertia(fn (Assert $page) => $page
            ->component('ProdutosServicos/Reajuste')
            ->where('item.preco', '120,00')
            ->has('contratos', 1)
            ->where('contratos.0.id', $usa->id)
            ->where('contratos.0.quantidade', '2')
            ->where('contratos.0.preco', '100,00'));
    }

    public function test_reajusta_so_os_escolhidos_e_recalcula_o_valor_sem_mexer_em_cobranca(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($usuario)->create();
        $escolhido = $this->contratoCom($usuario, [[$suporte, '2.000', 10000], [$hospedagem, '1.000', 5000]]);
        $deFora = $this->contratoCom($usuario, [[$suporte, '1.000', 10000]]);
        $cobranca = Cobranca::factory()->for($usuario)->create([
            'cliente_id' => $escolhido->cliente_id, 'contrato_id' => $escolhido->id, 'valor_centavos' => 25000,
        ]);

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$escolhido->id], 'preco' => '120,00',
        ])->assertRedirect('/produtos-servicos')
            ->assertSessionHas('sucesso', 'Preço reajustado em 1 contrato(s). Vale para as próximas cobranças.');

        $this->assertSame(2 * 12000 + 5000, $escolhido->fresh()->valor_centavos);
        $this->assertSame([12000, 5000], $escolhido->itens()->pluck('preco_unitario_centavos')->all());
        $this->assertSame(10000, $deFora->fresh()->valor_centavos);
        $this->assertSame(25000, $cobranca->fresh()->valor_centavos);
    }

    public function test_contrato_inativo_alheio_ou_sem_o_item_e_ignorado(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $inativo = $this->contratoCom($usuario, [[$suporte, '1.000', 10000]], ['ativo' => false]);
        $alheio = Contrato::factory()->create();

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$inativo->id, $alheio->id], 'preco' => '120,00',
        ])->assertSessionHasErrors(['contratos' => 'Escolha pelo menos um contrato ativo que usa este item.']);

        $this->assertSame(10000, $inativo->itens()->sole()->preco_unitario_centavos);
    }

    public function test_preco_que_estoura_o_limite_desfaz_tudo(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $pequeno = $this->contratoCom($usuario, [[$suporte, '1.000', 100]]);
        $grande = $this->contratoCom($usuario, [[$suporte, '99999.000', 100]]);

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$pequeno->id, $grande->id], 'preco' => '99.999.999,99',
        ])->assertSessionHasErrors(['preco' => 'Com este preço, o total de algum contrato ficaria fora do limite (R$ 0,01 a R$ 99.999.999,99).']);

        $this->assertSame(100, $pequeno->itens()->sole()->preco_unitario_centavos);
        $this->assertSame(100, $pequeno->fresh()->valor_centavos);
    }

    public function test_item_alheio_da_404(): void
    {
        $usuario = User::factory()->create();
        $proprio = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->get("/produtos-servicos/{$proprio->id}/reajuste")->assertOk();
        $this->get("/produtos-servicos/{$alheio->id}/reajuste")->assertNotFound();
        $this->post("/produtos-servicos/{$alheio->id}/reajuste", ['contratos' => [], 'preco' => ''])->assertNotFound();
    }
}
