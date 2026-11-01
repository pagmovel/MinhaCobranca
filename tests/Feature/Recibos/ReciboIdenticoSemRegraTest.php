<?php

namespace Tests\Feature\Recibos;

use App\Enums\FormaPagamento;
use App\Enums\StatusCobranca;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use App\Support\QrCodePix;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Prova permanente da Fase 27: sem regra no contrato e sem valores gravados, o recibo sai idêntico ao da Fase 26.
 * As fixtures em `fixtures/` foram geradas com o código anterior à Fase 27, antes de mexer em `RenderizadorDeRecibo`.
 * Não regerar para "consertar" o teste: se ele falhar, o recibo de quem não usa a Fase 27 mudou.
 */
class ReciboIdenticoSemRegraTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        config(['app.name' => 'Recibos']);
    }

    /** @return array<string, array{string, string}> */
    public static function casos(): array
    {
        $casos = [];

        foreach (['webiss', 'danfse'] as $familia) {
            foreach (['pendente', 'pendente-pix', 'pendente-boleto', 'vencida-pix-contrato-sem-regra', 'paga-pix', 'cancelada-pix'] as $caso) {
                $casos["{$familia} {$caso}"] = [$familia, $caso];
            }
        }

        return $casos;
    }

    #[DataProvider('casos')]
    public function test_recibo_sem_regra_e_sem_valores_sai_identico_ao_anterior(string $familia, string $caso): void
    {
        $usuario = User::factory()->create(['name' => 'Prestador Exemplo', 'email' => 'prestador@exemplo.com.br']);
        $chave = ChavePix::factory()->for($usuario)->create(['chave' => 'financeiro@exemplo.com.br']);
        $cliente = Cliente::factory()->for($usuario)->create([
            'nome' => 'Cliente Exemplo', 'documento' => '11222333000181', 'email' => 'cliente@exemplo.com.br', 'modelo' => $familia,
        ]);
        $pix = ['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id];
        $atributos = match ($caso) {
            'pendente' => [],
            'pendente-pix' => $pix,
            'pendente-boleto' => ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => '23793381286000000000300000000400184340000026060'],
            // Vencida, de um contrato que não tem regra nenhuma: o valor do dia é o valor original.
            'vencida-pix-contrato-sem-regra' => $pix + [
                'vencimento' => '2026-10-01',
                'contrato_id' => Contrato::factory()->for($usuario)->for($cliente)->create(['ativo' => false])->id,
            ],
            'paga-pix' => $pix + ['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-09'],
            'cancelada-pix' => $pix + ['status' => StatusCobranca::Cancelada],
        };
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create($atributos));

        $dados = app(RenderizadorDeRecibo::class)->dados($recibo, CarbonImmutable::now());
        $html = view($recibo->modelo->view(), $dados)->render();

        if ($dados['pagamento']['forma'] === 'pix') {
            // O QR é função do copia e cola que está no mesmo HTML; o PNG em si depende da versão do GD, por isso sai da comparação.
            $this->assertStringContainsString(QrCodePix::dataUri($dados['pagamento']['copia_e_cola']), $html);
            $html = preg_replace('~data:image/png;base64,[A-Za-z0-9+/=]+~', 'data:image/png;base64,[QR]', $html);
        }

        $this->assertSame(file_get_contents(__DIR__."/fixtures/{$familia}-{$caso}.html"), $html);
    }
}
