<?php

namespace Tests\Feature\Recibos;

use App\Enums\FamiliaModelo;
use App\Enums\ModeloRecibo;
use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EmissaoReciboTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: User, 1: Cliente} */
    private function prestadorComCliente(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create(['logradouro' => 'Rua Antiga']);

        return [$usuario, Cliente::factory()->for($usuario)->create(['nome' => 'APLB Sindicato'])];
    }

    private function cobranca(User $usuario, Cliente $cliente, array $extra = []): Cobranca
    {
        return Cobranca::factory()->for($usuario)->for($cliente)->create($extra);
    }

    public function test_numera_em_sequencia_por_usuario(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $emissor = app(EmissorDeRecibos::class);

        $r1 = $emissor->emitir($this->cobranca($usuario, $cliente));
        $r2 = $emissor->emitir($this->cobranca($usuario, $cliente));
        $deOutro = $emissor->emitir(Cobranca::factory()->create());

        $this->assertSame([2026, 1], [$r1->ano, $r1->numero]);
        $this->assertSame(2, $r2->numero);
        $this->assertSame(1, $deOutro->numero);
        $this->assertSame('2026/000002', $r2->numeroFormatado());
        $this->assertSame('recibo-2026-000002-aplb-sindicato.pdf', $r2->nomeArquivo());
    }

    public function test_numeracao_reinicia_no_ano_novo(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $emissor = app(EmissorDeRecibos::class);
        $emissor->emitir($this->cobranca($usuario, $cliente));

        $this->travelTo(CarbonImmutable::parse('2027-01-01 08:00:00'));
        $recibo = $emissor->emitir($this->cobranca($usuario, $cliente));

        $this->assertSame([2027, 1], [$recibo->ano, $recibo->numero]);
    }

    public function test_numero_de_cobranca_cancelada_nao_volta(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $emissor = app(EmissorDeRecibos::class);
        $cancelada = $this->cobranca($usuario, $cliente);
        $emissor->emitir($cancelada);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();

        $this->assertSame(2, $emissor->emitir($this->cobranca($usuario, $cliente))->numero);
    }

    public function test_grava_a_versao_atual_do_modelo_do_cliente(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $emissor = app(EmissorDeRecibos::class);

        $webiss = $emissor->emitir($this->cobranca($usuario, $cliente));
        $cliente->update(['modelo' => FamiliaModelo::Danfse]);
        $danfse = $emissor->emitir($this->cobranca($usuario, $cliente));

        $this->assertSame(ModeloRecibo::WebissV2, $webiss->fresh()->modelo, 'troca de modelo não altera recibo antigo');
        $this->assertSame(ModeloRecibo::DanfseV2, $danfse->modelo);
    }

    public function test_snapshot_fica_congelado(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $recibo = app(EmissorDeRecibos::class)->emitir($this->cobranca($usuario, $cliente, ['valor_centavos' => 26060]));

        $usuario->update(['logradouro' => 'Rua Nova']);
        $cliente->update(['nome' => 'Outro Nome']);

        $snapshot = $recibo->fresh()->snapshot;
        $this->assertSame('Rua Antiga', $snapshot['prestador']['logradouro']);
        $this->assertSame('APLB Sindicato', $snapshot['tomador']['nome']);
        $this->assertSame(26060, $snapshot['servico']['valor_centavos']);
        $this->assertSame('2026-10-10', $snapshot['servico']['vencimento']);
    }

    public function test_refazer_mantem_numero_e_modelo_e_atualiza_dados(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $emissor = app(EmissorDeRecibos::class);
        $cobranca = $this->cobranca($usuario, $cliente);
        $recibo = $emissor->emitir($cobranca);
        $cobranca->update(['valor_centavos' => 30000]);
        $cliente->update(['modelo' => FamiliaModelo::Danfse]);

        $emissor->refazer($recibo->fresh());

        $recibo->refresh();
        $this->assertSame(1, $recibo->numero);
        $this->assertSame(ModeloRecibo::WebissV2, $recibo->modelo);
        $this->assertSame(30000, $recibo->snapshot['servico']['valor_centavos']);
    }
}
