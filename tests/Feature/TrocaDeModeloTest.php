<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Enums\ModeloRecibo;
use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Fase 22 (pedido do usuário, 2026-10-11): trocar o modelo do recibo no cliente refaz na hora os recibos das cobranças
 * em aberto e não enviadas; as enviadas e não pagas só com a caixa marcada (e voltam para "Para enviar"); pagas e
 * canceladas nunca mudam.
 */
class TrocaDeModeloTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    /** @var array<string, Cobranca> */
    private array $cobrancas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create(['modelo' => FamiliaModelo::Webiss]);

        $situacoes = [
            'nao_enviada' => [],
            'enviada' => ['enviado_em' => '2026-10-05 09:00:00'],
            'paga' => ['enviado_em' => '2026-10-05 09:00:00', 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-06'],
            'cancelada' => ['status' => StatusCobranca::Cancelada],
        ];
        foreach ($situacoes as $chave => $estado) {
            $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create();
            app(EmissorDeRecibos::class)->emitir($cobranca);
            $cobranca->forceFill($estado)->save();
            $this->cobrancas[$chave] = $cobranca;
        }
        $this->actingAs($this->usuario);
    }

    private function salvar(array $extra = []): void
    {
        $dados = array_map(fn ($valor) => $valor ?? '', $this->cliente->only(Cliente::CAMPOS));
        $this->put("/clientes/{$this->cliente->id}", array_merge($dados, ['apelido' => '', 'modelo' => 'danfse'], $extra))
            ->assertSessionHasNoErrors()->assertRedirect('/clientes');
    }

    private function modelo(string $chave): ModeloRecibo
    {
        return $this->cobrancas[$chave]->recibo()->firstOrFail()->modelo;
    }

    public function test_so_as_nao_enviadas_mudam_sem_marcar_a_caixa(): void
    {
        $numero = $this->cobrancas['nao_enviada']->recibo->numero;

        $this->salvar();

        $this->assertSame(ModeloRecibo::DanfseV1, $this->modelo('nao_enviada'));
        $this->assertSame($numero, $this->cobrancas['nao_enviada']->recibo()->firstOrFail()->numero, 'mesmo número');
        $this->assertSame(ModeloRecibo::WebissV1, $this->modelo('enviada'));
        $this->assertNotNull($this->cobrancas['enviada']->fresh()->enviado_em);
        $this->assertSame(ModeloRecibo::WebissV1, $this->modelo('paga'));
        $this->assertSame(ModeloRecibo::WebissV1, $this->modelo('cancelada'));
    }

    public function test_com_a_caixa_as_enviadas_mudam_e_voltam_para_enviar(): void
    {
        $this->salvar(['regerar_enviadas' => '1']);

        $this->assertSame(ModeloRecibo::DanfseV1, $this->modelo('enviada'));
        $this->assertNull($this->cobrancas['enviada']->fresh()->enviado_em);
        $this->assertSame(ModeloRecibo::WebissV1, $this->modelo('paga'));
        $this->assertNotNull($this->cobrancas['paga']->fresh()->enviado_em);
        $this->assertSame(ModeloRecibo::WebissV1, $this->modelo('cancelada'));
    }

    public function test_sem_trocar_o_modelo_nenhum_recibo_e_refeito(): void
    {
        $this->salvar(['modelo' => 'webiss', 'nome' => 'Nome Novo', 'regerar_enviadas' => '1']);

        $recibo = $this->cobrancas['nao_enviada']->recibo()->firstOrFail();
        $this->assertNotSame('Nome Novo', $recibo->snapshot['tomador']['nome']);
        $this->assertNotNull($this->cobrancas['enviada']->fresh()->enviado_em);
    }

    public function test_tela_de_edicao_mostra_quantas_seriam_refeitas(): void
    {
        $this->get("/clientes/{$this->cliente->id}/edit")
            ->assertInertia(fn (Assert $pagina) => $pagina->where('emAberto', ['nao_enviadas' => 1, 'enviadas' => 1]));
    }
}
