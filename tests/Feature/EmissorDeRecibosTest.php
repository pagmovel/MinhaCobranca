<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Services\EmissorDeRecibos;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

class EmissorDeRecibosTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** O lote vem sempre de um usuário só (Fase 23); misturar seria gravar o prestador errado no recibo de outro. */
    public function test_refazer_em_lote_recusa_cobrancas_de_usuarios_diferentes(): void
    {
        $deUm = Cobranca::factory()->create();
        $deOutro = Cobranca::factory()->create();

        $this->expectException(LogicException::class);

        app(EmissorDeRecibos::class)->refazerEmLote(collect([$deUm, $deOutro]));
    }
}
