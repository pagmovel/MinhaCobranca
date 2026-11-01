<?php

namespace Tests\Unit;

use App\Support\CondicoesDePagamento;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Mesmos casos de resources/js/condicoes.test.js: a tela e o servidor precisam concordar. */
class CondicoesDePagamentoTest extends TestCase
{
    /** @return array<string, array{0: int, 1: ?string, 2: ?int, 3: int}> */
    public static function multas(): array
    {
        return [
            'percentual' => [21000, 'percentual', 200, 420],
            'valor fixo' => [21000, 'valor', 500, 500],
            'meio centavo para cima' => [10050, 'percentual', 100, 101],
            'sem tipo' => [21000, null, 200, 0],
            'sem regra' => [21000, 'percentual', null, 0],
            'regra zero' => [21000, 'percentual', 0, 0],
            'valor sem regra' => [21000, 'valor', null, 0],
        ];
    }

    /** @return array<string, array{0: int, 1: ?string, 2: ?int, 3: int, 4: int}> */
    public static function juros(): array
    {
        return [
            'percentual, 0 dia' => [21000, 'percentual', 100, 0, 0],
            'percentual, 1 dia' => [21000, 'percentual', 100, 1, 7],
            'percentual, 15 dias' => [21000, 'percentual', 100, 15, 105],
            'percentual, 30 dias' => [21000, 'percentual', 100, 30, 210],
            'percentual, 300 dias' => [21000, 'percentual', 100, 300, 2100],
            'percentual, meio centavo' => [45000, 'percentual', 10, 1, 2],
            'valor ao mês, 1 dia' => [21000, 'valor', 3000, 1, 100],
            'valor ao mês, 15 dias' => [21000, 'valor', 3000, 15, 1500],
            'valor ao mês, meio centavo' => [21000, 'valor', 45, 1, 2],
            'valor ao mês, 0 dia' => [21000, 'valor', 3000, 0, 0],
            'sem tipo' => [21000, null, 100, 30, 0],
            'sem regra' => [21000, 'percentual', null, 30, 0],
            'regra zero' => [21000, 'valor', 0, 30, 0],
            'dias negativos' => [21000, 'percentual', 100, -5, 0],
            'sem estouro' => [1000000000, 'percentual', 10000, 3650, 121666666667],
        ];
    }

    /** @return array<string, array{0: int, 1: ?string, 2: ?int, 3: int}> */
    public static function descontos(): array
    {
        return [
            'percentual' => [10000, 'percentual', 1500, 1500],
            'valor' => [10000, 'valor', 1500, 1500],
            'limitado ao valor' => [10000, 'valor', 20000, 10000],
            'cem por cento' => [10000, 'percentual', 10000, 10000],
            'sem tipo' => [10000, null, 1500, 0],
            'sem regra' => [10000, 'percentual', null, 0],
            'regra zero' => [10000, 'valor', 0, 0],
        ];
    }

    /** @return array<string, array{0: string, 1: ?string, 2: ?int}> */
    public static function entradas(): array
    {
        return [
            'inteiro' => ['percentual', '2', 200],
            'vírgula' => ['percentual', '2,5', 250],
            'ponto' => ['percentual', '2.5', 250],
            'cem' => ['percentual', '100', 10000],
            'cem com decimais' => ['percentual', '100,00', 10000],
            'acima de cem' => ['percentual', '150', null],
            'um centésimo acima de cem' => ['percentual', '100,01', null],
            'texto' => ['percentual', 'abc', null],
            'três casas' => ['percentual', '1,234', null],
            'vazio' => ['percentual', '', 0],
            'nulo' => ['percentual', null, 0],
            'zero' => ['percentual', '0', 0],
            'reais' => ['valor', '15,00', 1500],
            'reais com milhar' => ['valor', '1.234,56', 123456],
            'reais zero' => ['valor', '0', 0],
            'reais vazio' => ['valor', '  ', 0],
            'reais inválido' => ['valor', 'abc', null],
            'reais negativo' => ['valor', '-5', null],
            'reais gigante' => ['valor', '99999999999999999999', null],
            'tipo desconhecido' => ['abatimento', '10', null],
        ];
    }

    /** @return array<string, array{0: ?string, 1: ?int, 2: string}> */
    public static function regras(): array
    {
        return [
            'percentual inteiro' => ['percentual', 1500, '15'],
            'percentual com uma casa' => ['percentual', 250, '2,5'],
            'percentual com duas casas' => ['percentual', 1005, '10,05'],
            'percentual menor que um' => ['percentual', 5, '0,05'],
            'reais' => ['valor', 1500, '15,00'],
            'reais com milhar' => ['valor', 123456, '1.234,56'],
            'sem regra' => [null, null, ''],
            'sem valor' => ['percentual', null, ''],
            'sem tipo' => [null, 1500, ''],
            'regra zero' => ['valor', 0, ''],
        ];
    }

    #[DataProvider('multas')]
    public function test_multa_em_centavos(int $valor, ?string $tipo, ?int $regra, int $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::multaCentavos($valor, $tipo, $regra));
    }

    #[DataProvider('juros')]
    public function test_juros_em_centavos(int $valor, ?string $tipo, ?int $regra, int $dias, int $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::jurosCentavos($valor, $tipo, $regra, $dias));
    }

    #[DataProvider('descontos')]
    public function test_desconto_em_centavos_limitado_ao_valor(int $valor, ?string $tipo, ?int $regra, int $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::descontoCentavos($valor, $tipo, $regra));
    }

    #[DataProvider('entradas')]
    public function test_regra_da_entrada(string $tipo, ?string $entrada, ?int $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::regraDaEntrada($tipo, $entrada));
    }

    #[DataProvider('regras')]
    public function test_entrada_da_regra(?string $tipo, ?int $regra, string $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::entradaDaRegra($tipo, $regra));
    }

    public function test_a_entrada_da_regra_volta_a_mesma_regra(): void
    {
        foreach ([['percentual', 1500], ['percentual', 250], ['percentual', 5], ['percentual', 10000], ['valor', 1500], ['valor', 123456], ['valor', 1]] as [$tipo, $regra]) {
            $this->assertSame($regra, CondicoesDePagamento::regraDaEntrada($tipo, CondicoesDePagamento::entradaDaRegra($tipo, $regra)));
        }
    }

    /** @return array<string, array{0: string, 1: string, 2: int}> */
    public static function atrasos(): array
    {
        return [
            'quase dez meses' => ['2026-01-05', '2026-11-01', 300],
            'mesmo dia' => ['2026-11-01', '2026-11-01', 0],
            'data anterior' => ['2026-11-05', '2026-11-01', 0],
            'um dia' => ['2026-02-15', '2026-02-16', 1],
            'virada de ano' => ['2026-12-31', '2027-01-01', 1],
            'ano bissexto' => ['2028-02-28', '2028-03-01', 2],
        ];
    }

    #[DataProvider('atrasos')]
    public function test_dias_de_atraso(string $vencimento, string $data, int $esperado): void
    {
        $this->assertSame($esperado, CondicoesDePagamento::diasDeAtraso(CarbonImmutable::parse($vencimento), CarbonImmutable::parse($data)));
    }

    public function test_hora_do_dia_nao_muda_os_dias_de_atraso_em_sao_paulo(): void
    {
        $vencimento = CarbonImmutable::parse('2026-01-05', 'America/Sao_Paulo');

        $this->assertSame(0, CondicoesDePagamento::diasDeAtraso($vencimento, CarbonImmutable::parse('2026-01-05 00:00:00', 'America/Sao_Paulo')));
        $this->assertSame(0, CondicoesDePagamento::diasDeAtraso($vencimento, CarbonImmutable::parse('2026-01-05 23:59:59', 'America/Sao_Paulo')));
        $this->assertSame(1, CondicoesDePagamento::diasDeAtraso($vencimento, CarbonImmutable::parse('2026-01-06 00:00:01', 'America/Sao_Paulo')));
        $this->assertSame(1, CondicoesDePagamento::diasDeAtraso($vencimento->setTime(23, 59), CarbonImmutable::parse('2026-01-06 00:01', 'America/Sao_Paulo')));
    }

    public function test_dias_de_atraso_aceita_carbon_mutavel_e_fusos_diferentes_na_mesma_data(): void
    {
        $this->assertSame(0, CondicoesDePagamento::diasDeAtraso(Carbon::parse('2026-01-05 12:00', 'UTC'), Carbon::parse('2026-01-05 23:30', 'America/Sao_Paulo')));
        $this->assertSame(2, CondicoesDePagamento::diasDeAtraso(Carbon::parse('2026-01-05', 'America/Sao_Paulo'), Carbon::parse('2026-01-07', 'UTC')));
    }
}
