<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cobranca;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DescontoNaBaixaTest extends TestCase
{
    use LazilyRefreshDatabase;

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
}
