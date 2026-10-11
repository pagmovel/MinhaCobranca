<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * T17.13: onde o cliente aparece, aparece pelo apelido; nas listas, o nome oficial vai embaixo (`cliente_oficial`).
 * Várias consultas carregam só algumas colunas do cliente (`cliente:id,nome`): sem o apelido nelas, voltaria o nome.
 */
class ApelidoNasTelasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create(['nome' => 'Zeta Sindicato', 'apelido' => 'Regional Araci']);
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $this->actingAs($this->usuario);
    }

    public function test_cobrancas_painel_e_previstas(): void
    {
        $this->get('/painel');
        $this->getJson('/painel/dados?lista=para_enviar')
            ->assertJsonPath('linhas.0.cliente', 'Regional Araci')
            ->assertJsonPath('linhas.0.cliente_oficial', 'Zeta Sindicato');
        $this->getJson('/cobrancas/dados')
            ->assertJsonPath('linhas.0.cliente', 'Regional Araci')
            ->assertJsonPath('linhas.0.cliente_oficial', 'Zeta Sindicato');
        $this->getJson('/cobrancas/dados?mes=2027-01&ate=2027-01')
            ->assertJsonPath('linhas.0.situacao', 'prevista')
            ->assertJsonPath('linhas.0.cliente', 'Regional Araci')
            ->assertJsonPath('linhas.0.cliente_oficial', 'Zeta Sindicato');
    }

    public function test_contratos_e_filtros_de_cliente(): void
    {
        $this->getJson('/contratos/dados')
            ->assertJsonPath('linhas.0.cliente', 'Regional Araci')
            ->assertJsonPath('linhas.0.cliente_oficial', 'Zeta Sindicato');

        foreach (['/contratos', '/cobrancas'] as $tela) {
            $this->get("{$tela}?cliente_id={$this->cliente->id}")
                ->assertInertia(fn (Assert $pagina) => $pagina->where('clienteFiltro.nome', 'Regional Araci')
                    ->where('clienteFiltro.nome_oficial', 'Zeta Sindicato')->etc());
        }
        $contrato = $this->usuario->contratos()->sole();
        $this->get("/contratos/{$contrato->id}/edit")
            ->assertInertia(fn (Assert $pagina) => $pagina->where('clienteAtual.nome', 'Regional Araci')->etc());
    }

    /** Pedido do usuário (2026-10-11): nome de arquivo curto quando a razão social é extensa. O conteúdo não muda. */
    public function test_nome_do_arquivo_do_pdf_usa_o_apelido(): void
    {
        $this->get('/painel');
        $linha = $this->getJson('/painel/dados?lista=para_enviar')->json('linhas.0');
        $this->assertSame('recibo-2026-000001-regional-araci.pdf', $linha['recibo']['pdf_nome']);

        $this->get($linha['recibo']['pdf_url'])->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=recibo-2026-000001-regional-araci.pdf');

        // Sem apelido, continua o nome oficial.
        $this->cliente->update(['apelido' => '']);
        $this->assertSame('recibo-2026-000001-zeta-sindicato.pdf', $this->getJson('/painel/dados?lista=para_enviar')->json('linhas.0.recibo.pdf_nome'));
    }

    public function test_relatorios_mostram_o_apelido(): void
    {
        $this->get('/painel');
        $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00:00'));

        $this->getJson('/relatorios/clientes/dados')->assertJsonPath('linhas.0.cliente', 'Regional Araci');
        $this->getJson('/relatorios/a-receber/dados')->assertJsonPath('linhas.0.cliente', 'Regional Araci');
    }
}
