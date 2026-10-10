<?php

namespace Tests\Feature\Recibos;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PdfReciboTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_baixa_o_pdf_do_proprio_recibo(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['nome' => 'APLB Sindicato', 'modelo' => 'webiss']);
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create());

        $resposta = $this->actingAs($usuario)->get("/recibos/{$recibo->id}/pdf");

        $resposta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($recibo->nomeArquivo(), (string) $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_recibo_de_outro_usuario_da_404(): void
    {
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->create());
        $this->actingAs($recibo->cobranca->user)->get("/recibos/{$recibo->id}/pdf")->assertOk();

        $this->actingAs(User::factory()->create())->get("/recibos/{$recibo->id}/pdf")->assertNotFound();
    }

    public function test_cliente_danfse_recebe_pdf_no_modelo_nacional(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['modelo' => 'danfse']);
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create());

        $this->assertSame('recibos.pdf.danfse-v1', $recibo->modelo->view());
        $this->actingAs($usuario)->get("/recibos/{$recibo->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
