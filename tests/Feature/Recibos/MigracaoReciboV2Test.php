<?php

namespace Tests\Feature\Recibos;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 26: a migração de dados troca só `modelo` (v1 → v2). As linhas v1 são gravadas e lidas com `DB::table`:
 * o enum `ModeloRecibo` já não tem os casos v1 e o cast de `Recibo.modelo` quebraria ao lê-las.
 */
class MigracaoReciboV2Test extends TestCase
{
    use LazilyRefreshDatabase;

    private const ARQUIVO = 'migrations/2026_11_01_105832_troca_modelo_dos_recibos_para_v2.php';

    private function migracao(): Migration
    {
        return require database_path(self::ARQUIVO);
    }

    /** Recibo emitido hoje (v2) rebaixado para a linha que existia em produção: modelo v1, o resto igual. */
    private function reciboAntigo(string $familia, string $modeloAntigo): int
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['modelo' => $familia]);
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create());
        DB::table('recibos')->where('id', $recibo->id)->update(['modelo' => $modeloAntigo]);

        return $recibo->id;
    }

    /** @return array<string, mixed> */
    private function linha(int $id): array
    {
        return (array) DB::table('recibos')->where('id', $id)->first();
    }

    public function test_up_troca_so_o_modelo_das_duas_familias_e_down_volta(): void
    {
        $webiss = $this->reciboAntigo('webiss', 'webiss-v1');
        $danfse = $this->reciboAntigo('danfse', 'danfse-v1');
        $antesWebiss = $this->linha($webiss);
        $antesDanfse = $this->linha($danfse);
        $migracao = $this->migracao();

        $migracao->up();

        $depoisWebiss = $this->linha($webiss);
        $depoisDanfse = $this->linha($danfse);
        $this->assertSame('webiss-v2', $depoisWebiss['modelo']);
        $this->assertSame('danfse-v2', $depoisDanfse['modelo']);
        $this->assertSame(array_diff_key($antesWebiss, ['modelo' => 1]), array_diff_key($depoisWebiss, ['modelo' => 1]), 'número, snapshot e datas ficam');
        $this->assertSame(array_diff_key($antesDanfse, ['modelo' => 1]), array_diff_key($depoisDanfse, ['modelo' => 1]));

        $migracao->down();

        $this->assertSame($antesWebiss, $this->linha($webiss));
        $this->assertSame($antesDanfse, $this->linha($danfse));
    }

    public function test_up_roda_de_novo_sem_estragar_recibo_que_ja_e_v2(): void
    {
        $emitido = $this->reciboAntigo('webiss', 'webiss-v2');
        $antes = $this->linha($emitido);

        $this->migracao()->up();

        $this->assertSame($antes, $this->linha($emitido));
    }
}
