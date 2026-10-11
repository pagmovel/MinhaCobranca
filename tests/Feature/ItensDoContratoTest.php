<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItensDoContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOTAL_FORA = 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.';

    private const QUANTIDADE = 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).';

    /** @return array{0: User, 1: Cliente} */
    private function usuarioComCliente(): array
    {
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    private function dados(Cliente $cliente, array $itens, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => 'Hospedagem e suporte',
            'periodicidade' => 'mensal',
            'modalidade' => 'prepago',
            'dia_vencimento' => 10,
            'data_inicio' => '2026-10-01',
            'ativo' => true,
            'itens' => $itens,
        ], $extra);
    }

    private function item(ProdutoServico $produto, string $quantidade, string $preco, bool $principal = false): array
    {
        return ['produto_servico_id' => $produto->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => $principal];
    }

    public function test_valor_e_a_soma_dos_itens_e_codigos_vem_do_principal(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $hospedagem = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => '0103 - Hospedagem', 'codigo_nbs' => '111']);
        $suporte = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => '0107 - Suporte', 'codigo_nbs' => '222']);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($hospedagem, '1', '89,90'),
            $this->item($suporte, '1,5', '120,00', principal: true),
        ], ['valor' => '1,00']))->assertRedirect('/contratos'); // o valor enviado pela tela é ignorado

        $contrato = $usuario->contratos()->sole();
        $this->assertSame(8990 + 18000, $contrato->valor_centavos);
        $this->assertSame('0107 - Suporte', $contrato->codigo_servico);
        $this->assertSame('222', $contrato->codigo_nbs);
        $this->assertSame(['1.000', '1.500'], $contrato->itens->pluck('quantidade')->all());
        $this->assertSame([false, true], $contrato->itens->pluck('principal')->all());
    }

    public function test_sem_principal_marcado_vale_o_de_maior_total(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $barato = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => 'barato']);
        $caro = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => 'caro']);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($barato, '1', '89,90'),
            $this->item($caro, '2', '120,00'),
        ]))->assertRedirect('/contratos');

        $contrato = $usuario->contratos()->sole();
        $this->assertSame('caro', $contrato->codigo_servico);
        $this->assertSame([false, true], $contrato->itens->pluck('principal')->all());
    }

    public function test_arredonda_cada_item_meio_centavo_para_cima(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $hora = ProdutoServico::factory()->for($usuario)->create();
        $copia = ProdutoServico::factory()->for($usuario)->create();

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($hora, '1,5', '0,01'),
            $this->item($copia, '0,333', '3,00'),
        ]))->assertRedirect('/contratos');

        $this->assertSame(2 + 100, $usuario->contratos()->sole()->valor_centavos);
    }

    public function test_recusa_lista_vazia_item_alheio_repetido_e_quantidade_invalida(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $proprio = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, []))
            ->assertSessionHasErrors(['itens' => 'Inclua pelo menos um produto ou serviço.']);
        $this->post('/contratos', $this->dados($cliente, [$this->item($alheio, '1', '10,00')]))
            ->assertSessionHasErrors(['itens.0.produto_servico_id' => 'Escolha um produto ou serviço do seu catálogo.']);
        $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, '1', '10,00'), $this->item($proprio, '2', '10,00')]))
            ->assertSessionHasErrors(['itens.1.produto_servico_id' => 'Este produto ou serviço já está na lista.']);
        foreach (['0', '-1', '1,2345', '+1', '1e3', 'abc', '100000'] as $quantidade) {
            $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, $quantidade, '10,00')]))
                ->assertSessionHasErrors(['itens.0.quantidade' => self::QUANTIDADE]);
        }
        $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, '1', '-1,00')]))
            ->assertSessionHasErrors(['itens.0.preco' => 'Informe um valor válido, como 260,60.']);

        $this->assertSame(0, Contrato::count());
    }

    public function test_total_zero_ou_acima_do_limite_e_recusado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, [$this->item($produto, '1', '0,00')]))
            ->assertSessionHasErrors(['itens' => self::TOTAL_FORA]);
        $this->post('/contratos', $this->dados($cliente, [$this->item($produto, '99999', '99.999.999,99')]))
            ->assertSessionHasErrors(['itens' => self::TOTAL_FORA]);

        $this->assertSame(0, Contrato::count());
    }

    public function test_item_inativo_continua_aceito_e_editar_troca_os_itens(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $antigo = ProdutoServico::factory()->for($usuario)->create(['ativo' => false]);
        $novo = ProdutoServico::factory()->for($usuario)->create(['nome' => 'Suporte por hora', 'unidade' => 'hora']);
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, [$this->item($antigo, '1', '50,00')]))
            ->assertRedirect('/contratos');
        $this->assertSame(5000, $contrato->fresh()->valor_centavos);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, [$this->item($novo, '2', '10,00')]))
            ->assertRedirect('/contratos');
        $this->assertSame([$novo->id], $contrato->itens()->pluck('produto_servico_id')->all());
        $this->assertSame(2000, $contrato->fresh()->valor_centavos);

        $this->get("/contratos/{$contrato->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('contrato.itens.0.nome', 'Suporte por hora')
            ->where('contrato.itens.0.unidade', 'hora')
            ->where('contrato.itens.0.quantidade', '2')
            ->where('contrato.itens.0.preco', '10,00')
            ->where('contrato.itens.0.principal', true));
    }

    public function test_contrato_antigo_sem_itens_abre_com_lista_vazia_e_exige_itens_ao_salvar(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->get("/contratos/{$contrato->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('contrato.itens', [])
            ->where('contrato.valor', '260,60'));
        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, []))->assertSessionHasErrors('itens');

        $this->assertSame(26060, $contrato->fresh()->valor_centavos);
    }

    public function test_contrato_alheio_da_404_antes_da_validacao(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $proprio = Contrato::factory()->for($usuario)->for($cliente)->create();
        $alheio = Contrato::factory()->create();
        $this->actingAs($usuario);

        $this->put("/contratos/{$proprio->id}", $this->dados($cliente, [$this->item($produto, '1', '10,00')]))
            ->assertRedirect('/contratos');
        // Dados inválidos: sem o 404 no authorize, a resposta seria 302 com erros (revelaria que o contrato existe).
        $this->put("/contratos/{$alheio->id}", $this->dados($cliente, []))->assertNotFound();
    }

    public function test_item_usado_em_contrato_nao_e_excluido(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [$this->item($produto, '1', '10,00')]));

        $this->delete("/produtos-servicos/{$produto->id}")
            ->assertSessionHas('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        $this->assertModelExists($produto);
    }
}
