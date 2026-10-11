<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Todo `/dados` de lista: exige sessão (401 em JSON) e só lê — a geração preguiçosa fica nas páginas. */
class DadosDasListasTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function enderecos(): array
    {
        return [
            'cobranças' => ['/cobrancas/dados'],
            'clientes' => ['/clientes/dados'],
            'contratos' => ['/contratos/dados'],
            'produtos' => ['/produtos-servicos/dados'],
            'auditoria' => ['/auditoria/dados'],
            'usuários' => ['/admin/usuarios/dados'],
            'painel' => ['/painel/dados?lista=para_enviar'],
            'faturamento' => ['/relatorios/faturamento/dados'],
            'clientes (relatório)' => ['/relatorios/clientes/dados'],
            'produtos (relatório)' => ['/relatorios/produtos/dados'],
            'a receber' => ['/relatorios/a-receber/dados'],
        ];
    }

    #[DataProvider('enderecos')]
    public function test_sem_sessao_responde_401(string $endereco): void
    {
        $this->getJson($endereco)->assertUnauthorized();
    }

    #[DataProvider('enderecos')]
    public function test_so_le_mesmo_com_cobranca_devida(string $endereco): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $admin = User::factory()->admin()->create();
        Contrato::factory()->for($admin)->create(); // tem período devido: abrir uma página geraria a cobrança
        $escritas = [];
        DB::listen(function ($consulta) use (&$escritas) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $consulta->sql)) {
                $escritas[] = $consulta->sql;
            }
        });

        $this->actingAs($admin)->getJson($endereco)->assertOk();

        $this->assertSame([], $escritas);
        $this->assertSame(0, Cobranca::count());
    }
}
