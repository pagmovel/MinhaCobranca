<?php

namespace Tests\Feature\Temas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** `GET /painel/cobrancas/{cobranca}` (Tarefa 5.2): a linha de um ponto da linha do tempo, igual à das listas. */
class PainelCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
    }

    public function test_devolve_a_linha_da_propria_cobranca(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['nome' => 'Padaria do Zé Ltda', 'apelido' => 'Padaria']);
        $pendente = Cobranca::factory()->for($usuario)->for($cliente)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 15000]);
        $paga = Cobranca::factory()->for($usuario)->for($cliente)->create([
            'vencimento' => '2026-10-05', 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-06', 'desconto_centavos' => 500,
        ]);

        // A linha é exatamente a que a lista do painel entrega para a mesma cobrança.
        $daLista = collect($this->actingAs($usuario)->getJson('/painel/dados?lista=para_enviar')->json('linhas'))->firstWhere('id', $pendente->id);
        $this->getJson("/painel/cobrancas/{$pendente->id}")->assertOk()->assertExactJson($daLista);

        $recebida = collect($this->getJson('/painel/dados?lista=recebidas')->json('linhas'))->firstWhere('id', $paga->id);
        $this->getJson("/painel/cobrancas/{$paga->id}")->assertOk()
            ->assertExactJson($recebida)
            ->assertJsonPath('cliente', 'Padaria')
            ->assertJsonPath('situacao', 'paga');
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        $usuario = User::factory()->create();
        $alheia = Cobranca::factory()->create();

        $this->actingAs($usuario)->getJson("/painel/cobrancas/{$alheia->id}")->assertNotFound();
        $this->getJson('/painel/cobrancas/999999')->assertNotFound();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $cobranca = Cobranca::factory()->create();

        $this->get("/painel/cobrancas/{$cobranca->id}")->assertRedirect('/entrar');
    }

    public function test_id_nao_numerico_da_404_sem_erro_500(): void
    {
        $this->actingAs(User::factory()->create());

        // No PostgreSQL `where id = 'abc'` é erro de SQL (500); o SQLite não reproduz, então confere que o texto não chega ao banco.
        $consultas = [];
        DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
            if (in_array('abc', $consulta->bindings, true)) {
                $consultas[] = $consulta->sql;
            }
        });

        $this->getJson('/painel/cobrancas/abc')->assertNotFound();
        $this->assertSame([], $consultas, 'o id não numérico chegou ao banco');
    }
}
