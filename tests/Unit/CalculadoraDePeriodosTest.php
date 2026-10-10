<?php

namespace Tests\Unit;

use App\Enums\Periodicidade;
use App\Services\CalculadoraDePeriodos;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CalculadoraDePeriodosTest extends TestCase
{
    private function data(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data);
    }

    public function test_mensal_como_na_nota_de_referencia(): void
    {
        $calc = new CalculadoraDePeriodos;
        $p0 = $calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 0);
        $p1 = $calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 1);

        $this->assertSame(['2026-09-21', '2026-10-20', '2026-09-21'], [$p0->inicio->toDateString(), $p0->fim->toDateString(), $p0->vencimento->toDateString()]);
        $this->assertSame(['2026-10-21', '2026-11-20', '2026-10-21'], [$p1->inicio->toDateString(), $p1->fim->toDateString(), $p1->vencimento->toDateString()]);
    }

    public function test_inicio_no_dia_31_nao_escorrega_e_nao_deixa_buraco(): void
    {
        $calc = new CalculadoraDePeriodos;
        $inicio = $this->data('2026-01-31');
        $p0 = $calc->periodo($inicio, Periodicidade::Mensal, 31, 0);
        $p1 = $calc->periodo($inicio, Periodicidade::Mensal, 31, 1);
        $p2 = $calc->periodo($inicio, Periodicidade::Mensal, 31, 2);

        $this->assertSame('2026-02-27', $p0->fim->toDateString());
        $this->assertSame('2026-02-28', $p1->inicio->toDateString());
        $this->assertSame('2026-02-28', $p1->vencimento->toDateString(), 'dia 31 em fevereiro vira o último dia');
        $this->assertSame('2026-03-31', $p2->inicio->toDateString(), 'volta para o dia 31 original');
    }

    public function test_trimestral_e_anual(): void
    {
        $calc = new CalculadoraDePeriodos;
        $tri = $calc->periodo($this->data('2026-01-10'), Periodicidade::Trimestral, 10, 1);
        $anual = $calc->periodo($this->data('2026-02-15'), Periodicidade::Anual, 15, 0);

        $this->assertSame(['2026-04-10', '2026-07-09'], [$tri->inicio->toDateString(), $tri->fim->toDateString()]);
        $this->assertSame('2027-02-14', $anual->fim->toDateString());
    }

    public function test_devidos_respeita_a_antecedencia(): void
    {
        $calc = new CalculadoraDePeriodos;
        $devidos = fn (string $hoje) => $calc->devidos(
            $this->data('2026-09-21'), null, Periodicidade::Mensal, 21, 10, $this->data($hoje),
        );

        $this->assertCount(1, $devidos('2026-10-09'), 'próximo vencimento 21/10 − 10 dias = 11/10');
        $this->assertCount(1, $devidos('2026-10-10'));
        $this->assertCount(2, $devidos('2026-10-11'));
    }

    public function test_inicio_no_futuro_nao_gera_nada_antes_da_hora(): void
    {
        $this->assertSame([], (new CalculadoraDePeriodos)->devidos(
            $this->data('2026-12-01'), null, Periodicidade::Mensal, 1, 10, $this->data('2026-10-09'),
        ));
    }

    public function test_para_na_data_de_fim(): void
    {
        $devidos = (new CalculadoraDePeriodos)->devidos(
            $this->data('2026-01-01'), $this->data('2026-03-31'), Periodicidade::Mensal, 1, 0, $this->data('2026-12-01'),
        );

        $this->assertCount(3, $devidos);
        $this->assertSame('2026-03-01', $devidos[2]->inicio->toDateString());
    }

    public function test_vencimento_antes_do_inicio_do_periodo_e_permitido_pre_pago(): void
    {
        $p = (new CalculadoraDePeriodos)->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 10, 0);

        $this->assertSame('2026-09-10', $p->vencimento->toDateString());
    }
}
