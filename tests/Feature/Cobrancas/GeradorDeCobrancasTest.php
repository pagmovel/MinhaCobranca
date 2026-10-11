<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Contrato;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GeradorDeCobrancasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function prestador(array $extra = []): User
    {
        return User::factory()->create(['dias_antecedencia' => 10] + $extra);
    }

    private function contrato(User $usuario, array $extra = []): Contrato
    {
        return Contrato::factory()->for($usuario)->create($extra);
    }

    /** Congela o relógio no mesmo dia, para a numeração dos recibos sair determinística. */
    private function gerar(User $usuario, string $hoje): int
    {
        $this->travelTo(CarbonImmutable::parse($hoje.' 10:00:00'));

        return app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::parse($hoje));
    }

    public function test_gera_cobranca_com_recibo_copiando_o_contrato(): void
    {
        $usuario = $this->prestador();
        $contrato = $this->contrato($usuario);

        $this->assertSame(1, $this->gerar($usuario, '2026-10-09'));

        $cobranca = $contrato->cobrancas()->with('recibo')->firstOrFail();
        $this->assertSame('2026-09-21', $cobranca->periodo_inicio->toDateString());
        $this->assertSame('2026-10-20', $cobranca->periodo_fim->toDateString());
        $this->assertSame('2026-09-21', $cobranca->vencimento->toDateString());
        $this->assertSame(26060, $cobranca->valor_centavos);
        $this->assertSame($contrato->descricao, $cobranca->descricao);
        $this->assertSame('0102 - Programação', $cobranca->codigo_servico);
        $this->assertNotNull($cobranca->recibo);
    }

    public function test_rodar_de_novo_nao_duplica(): void
    {
        $usuario = $this->prestador();
        $this->contrato($usuario);

        $this->gerar($usuario, '2026-10-09');
        $this->assertSame(0, $this->gerar($usuario, '2026-10-09'));
        $this->assertSame(1, $usuario->cobrancas()->count());
    }

    public function test_respeita_a_antecedencia(): void
    {
        $usuario = $this->prestador();
        $this->contrato($usuario);

        $this->assertSame(2, $this->gerar($usuario, '2026-10-11'));
    }

    public function test_recupera_periodos_atrasados_em_ordem(): void
    {
        $usuario = $this->prestador();
        $this->contrato($usuario);

        $this->assertSame(5, $this->gerar($usuario, '2027-01-15'));

        $numerosPorVencimento = $usuario->cobrancas()->with('recibo')->orderBy('vencimento')->get()
            ->map(fn ($cobranca) => $cobranca->recibo->numero)->all();
        $this->assertSame([1, 2, 3, 4, 5], $numerosPorVencimento);
    }

    public function test_perfil_incompleto_suspende_a_geracao(): void
    {
        $usuario = $this->prestador(['documento' => null]);
        $this->contrato($usuario);

        $this->assertSame(0, $this->gerar($usuario, '2026-10-09'));
    }

    public function test_contrato_inativo_ou_encerrado(): void
    {
        $usuario = $this->prestador();
        $this->contrato($usuario, ['ativo' => false]);
        $this->assertSame(0, $this->gerar($usuario, '2026-10-09'));

        $this->contrato($usuario, ['data_fim' => '2026-10-31']);
        $this->assertSame(2, $this->gerar($usuario, '2027-03-01'));
    }

    public function test_varios_contratos_numerados_por_vencimento(): void
    {
        $usuario = $this->prestador();
        $tardio = $this->contrato($usuario, ['data_inicio' => '2026-10-05', 'dia_vencimento' => 5]);
        $cedo = $this->contrato($usuario, ['data_inicio' => '2026-09-21', 'dia_vencimento' => 21]);

        $this->gerar($usuario, '2026-10-09');

        $this->assertSame(1, $cedo->cobrancas()->firstOrFail()->recibo->numero);
        $this->assertSame(2, $tardio->cobrancas()->firstOrFail()->recibo->numero);
    }

    public function test_uma_consulta_so_para_as_cobrancas_existentes_de_todos_os_contratos(): void
    {
        $usuario = $this->prestador();
        foreach ([5, 12, 21] as $dia) {
            $this->contrato($usuario, ['dia_vencimento' => $dia]);
        }
        $consultas = 0;
        DB::listen(function ($consulta) use (&$consultas) {
            $sql = strtolower(ltrim($consulta->sql));
            if (str_starts_with($sql, 'select') && str_contains($sql, 'periodo_inicio')) {
                $consultas++;
            }
        });

        $this->gerar($usuario, '2026-10-09');

        $this->assertSame(1, $consultas);
    }
}
