<?php

namespace Tests\Unit;

use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DiasUteisTest extends TestCase
{
    private function data(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data);
    }

    public function test_pascoa_de_anos_conhecidos(): void
    {
        $this->assertSame(
            ['2024-03-31', '2025-04-20', '2026-04-05', '2027-03-28'],
            array_map(fn (int $ano) => DiasUteis::pascoa($ano)->toDateString(), [2024, 2025, 2026, 2027]),
        );
    }

    public function test_fim_de_semana_e_feriado_nacional_em_sequencia(): void
    {
        $dias = new DiasUteis;

        $this->assertSame('sábado', $dias->motivo($this->data('2026-10-10')));
        $this->assertSame('domingo', $dias->motivo($this->data('2026-10-11')));
        $this->assertSame('Nossa Senhora Aparecida', $dias->motivo($this->data('2026-10-12')));
        // Sábado, domingo e o feriado da segunda: só na terça.
        $this->assertSame('2026-10-13', $dias->proximoDiaUtil($this->data('2026-10-10'))->toDateString());
        $this->assertSame('2026-10-14', $dias->proximoDiaUtil($this->data('2026-10-14'))->toDateString(), 'dia útil fica');
    }

    public function test_carnaval_e_sexta_feira_da_paixao_pela_pascoa(): void
    {
        $dias = new DiasUteis;

        $this->assertSame(['Carnaval', 'Carnaval'], [$dias->motivo($this->data('2026-02-16')), $dias->motivo($this->data('2026-02-17'))]);
        $this->assertSame(['Carnaval', 'Carnaval'], [$dias->motivo($this->data('2025-03-03')), $dias->motivo($this->data('2025-03-04'))]);
        $this->assertTrue($dias->ehDiaUtil($this->data('2026-02-18')), 'quarta-feira de cinzas é dia útil');
        $this->assertSame('Sexta-feira da Paixão', $dias->motivo($this->data('2026-04-03')));
        $this->assertSame('2026-04-06', $dias->proximoDiaUtil($this->data('2026-04-03'))->toDateString());
    }

    public function test_consciencia_negra_so_a_partir_de_2024(): void
    {
        $dias = new DiasUteis;

        $this->assertTrue($dias->ehDiaUtil($this->data('2023-11-20')));
        $this->assertSame('Dia Nacional de Zumbi e da Consciência Negra', $dias->motivo($this->data('2024-11-20')));
        // 2026: sexta feriado + fim de semana → segunda, 23/11.
        $this->assertSame('2026-11-23', $dias->proximoDiaUtil($this->data('2026-11-20'))->toDateString());
    }

    public function test_virada_de_ano(): void
    {
        $dias = new DiasUteis;

        $this->assertSame('Natal', $dias->motivo($this->data('2026-12-25')));
        // 01/01/2028 é sábado (e feriado): segunda, 03/01.
        $this->assertSame('2028-01-03', $dias->proximoDiaUtil($this->data('2028-01-01'))->toDateString());
    }

    public function test_lista_nacional_do_ano_para_a_tela(): void
    {
        $nacionais = (new DiasUteis)->nacionais(2026);

        $this->assertCount(12, $nacionais);
        $this->assertSame(['2026-01-01', 'Confraternização Universal'], [array_key_first($nacionais), $nacionais['2026-01-01']]);
        $this->assertSame('2026-12-25', array_key_last($nacionais));
    }

    public function test_feriados_locais_anual_corpus_christi_e_data_unica(): void
    {
        $dias = new DiasUteis([
            ['descricao' => 'Independência da Bahia', 'tipo' => 'anual', 'dia' => 2, 'mes' => 7, 'data' => null],
            ['descricao' => 'Corpus Christi', 'tipo' => 'corpus_christi', 'dia' => null, 'mes' => null, 'data' => null],
            ['descricao' => 'Ponto facultativo decretado', 'tipo' => 'unico', 'dia' => null, 'mes' => null, 'data' => '2026-08-13'],
            ['descricao' => 'Aniversário da cidade', 'tipo' => 'anual', 'dia' => 21, 'mes' => 4, 'data' => null],
        ]);

        $this->assertSame('Independência da Bahia', $dias->motivo($this->data('2026-07-02')));
        $this->assertSame('Independência da Bahia', $dias->motivo($this->data('2027-07-02')), 'todo ano');
        $this->assertSame('Corpus Christi', $dias->motivo($this->data('2026-06-04')));
        $this->assertSame('Corpus Christi', $dias->motivo($this->data('2025-06-19')));
        $this->assertSame('Ponto facultativo decretado', $dias->motivo($this->data('2026-08-13')));
        $this->assertTrue($dias->ehDiaUtil($this->data('2027-08-13')), 'data única não se repete');
        $this->assertSame('Tiradentes', $dias->motivo($this->data('2026-04-21')), 'no mesmo dia, vale o nome do nacional');
    }

    public function test_29_de_fevereiro_anual_so_nos_anos_bissextos(): void
    {
        $dias = new DiasUteis([['descricao' => 'Dia bissexto', 'tipo' => 'anual', 'dia' => 29, 'mes' => 2, 'data' => null]]);

        // 2036 (sexta, Páscoa em 13/04); em 2028 o 29/02 é terça de Carnaval e o nome nacional venceria.
        $this->assertSame('Dia bissexto', $dias->motivo($this->data('2036-02-29')));
        $this->assertTrue($dias->ehDiaUtil($this->data('2027-03-01')), 'não escorrega para 01/03');
    }
}
