<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Concerns\ComItens;
use Tests\TestCase;

class FormaPagamentoTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    private const LINHA = '23793381286000000000300000000400184340000026060';

    /** @return array{0: User, 1: Cliente, 2: ChavePix} */
    private function cenario(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create(), ChavePix::factory()->for($usuario)->create()];
    }

    private function contrato(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id, 'descricao' => 'Hospedagem', 'itens' => $this->itensPara($cliente), 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 15, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ], $extra);
    }

    private function avulsa(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id, 'descricao' => 'Manutenção', 'valor' => '150,00',
            'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-31', 'vencimento' => '2026-10-20',
        ], $extra);
    }

    public function test_pix_exige_chave_do_proprio_usuario(): void
    {
        [$usuario, $cliente] = $this->cenario();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix']))
            ->assertSessionHasErrors(['chave_pix_id' => 'Escolha a chave PIX.']);
        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix', 'chave_pix_id' => ChavePix::factory()->create()->id]))
            ->assertSessionHasErrors(['chave_pix_id' => 'O valor escolhido para chave PIX é inválido.']);
        $this->assertSame(0, Contrato::count());
    }

    public function test_contrato_com_pix_gera_cobranca_com_a_mesma_chave(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();

        $this->actingAs($usuario)->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix', 'chave_pix_id' => $chave->id]))
            ->assertRedirect('/contratos');
        app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::today());

        $cobranca = $usuario->cobrancas()->sole();
        $this->assertSame(FormaPagamento::Pix, $cobranca->forma_pagamento);
        $this->assertSame($chave->id, $cobranca->chave_pix_id);
    }

    public function test_boleto_descarta_chave_e_guarda_linha_so_com_digitos(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'boleto', 'chave_pix_id' => $chave->id]));
        $this->assertNull(Contrato::sole()->chave_pix_id);

        $this->post('/cobrancas', $this->avulsa($cliente, [
            'forma_pagamento' => 'boleto', 'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060',
        ]))->assertRedirect('/cobrancas');
        $this->assertSame(self::LINHA, $usuario->cobrancas()->sole()->linha_digitavel);

        $this->post('/cobrancas', $this->avulsa($cliente, ['forma_pagamento' => 'boleto', 'linha_digitavel' => '123']))
            ->assertSessionHasErrors(['linha_digitavel' => 'A linha digitável tem 47 ou 48 números.']);
    }

    public function test_sem_forma_informada_vale_nao_informar(): void
    {
        [$usuario, $cliente] = $this->cenario();

        $this->actingAs($usuario)->post('/cobrancas', $this->avulsa($cliente))->assertRedirect('/cobrancas');

        $this->assertSame(FormaPagamento::Nenhuma, $usuario->cobrancas()->sole()->forma_pagamento);
    }

    public function test_chave_em_uso_nao_e_excluida(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();
        Contrato::factory()->for($usuario)->for($cliente)->create(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);

        $this->actingAs($usuario)->delete("/perfil/chaves-pix/{$chave->id}")
            ->assertSessionHas('erro', 'Esta chave está em uso em contratos ou cobranças. Troque a forma de pagamento deles antes de excluir.');
        $this->assertNotNull($chave->fresh());
    }
}
