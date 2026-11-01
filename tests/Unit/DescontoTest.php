<?php

namespace Tests\Unit;

use App\Support\Desconto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DescontoTest extends TestCase
{
    /** @return array<string, array{0: int, 1: string, 2: ?string, 3: int}> */
    public static function validos(): array
    {
        return [
            'percentual vazio' => [24757, 'percentual', '', 0],
            'percentual nulo' => [24757, 'percentual', null, 0],
            'percentual só com espaços' => [24757, 'percentual', '   ', 0],
            'percentual zero' => [24757, 'percentual', '0', 0],
            'percentual inteiro' => [24757, 'percentual', '10', 2476],
            'percentual com vírgula' => [24757, 'percentual', '12,5', 3095],
            'percentual com ponto' => [24757, 'percentual', '12.5', 3095],
            'percentual com duas casas' => [24757, 'percentual', '12,50', 3095],
            'percentual de um centésimo' => [24757, 'percentual', '0,01', 2],
            'percentual cem' => [24757, 'percentual', '100', 24757],
            'percentual acima de cem não limita' => [24757, 'percentual', '150', 37136],
            'meio centavo arredonda para cima' => [5, 'percentual', '50', 3],
            'meio centavo em um centavo' => [1, 'percentual', '50', 1],
            'valor vazio' => [24757, 'valor', '', 0],
            'valor nulo' => [24757, 'valor', null, 0],
            'valor zero' => [24757, 'valor', '0', 0],
            'valor com vírgula' => [24757, 'valor', '24,76', 2476],
            'valor inteiro é reais' => [24757, 'valor', '5', 500],
            'valor com uma casa' => [24757, 'valor', '260,6', 26060],
            'valor com milhar' => [24757, 'valor', '1.234,56', 123456],
            'valor com R$' => [24757, 'valor', 'R$ 260', 26000],
            'valor acima do valor não limita' => [24757, 'valor', '300,00', 30000],
        ];
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function invalidos(): array
    {
        $comuns = ['negativo' => '-5', 'notação científica' => '1e3', 'sinal de mais' => '+5', 'texto' => 'abc'];
        $casos = [];

        foreach (['percentual', 'valor'] as $tipo) {
            foreach ($comuns as $descricao => $entrada) {
                $casos["$tipo: $descricao"] = [$tipo, $entrada];
            }
        }

        return $casos + [
            'percentual: quatro dígitos inteiros' => ['percentual', '1000'],
            'percentual: três casas decimais' => ['percentual', '10,123'],
            'percentual: com sinal de percentagem' => ['percentual', '10%'],
            'percentual: só espaço inseparável' => ['percentual', "\u{00A0}"],
            'valor: só espaço inseparável' => ['valor', "\u{00A0}"],
            'valor: duas vírgulas' => ['valor', '1,234,56'],
            'valor: número gigante estoura o inteiro' => ['valor', '99999999999999999999'],
            'tipo desconhecido' => ['abatimento', '10'],
        ];
    }

    #[DataProvider('validos')]
    public function test_calcula_o_desconto_em_centavos(int $valor, string $tipo, ?string $entrada, int $esperado): void
    {
        $this->assertSame($esperado, Desconto::centavos($valor, $tipo, $entrada));
    }

    #[DataProvider('invalidos')]
    public function test_formato_invalido_devolve_nulo(string $tipo, string $entrada): void
    {
        $this->assertNull(Desconto::centavos(24757, $tipo, $entrada));
    }

    public function test_percentual_valido_vai_de_0_a_100_e_vazio_vale_zero(): void
    {
        foreach ([null, '', '  ', '0', '0,01', '12.5', '99,99', '100', '100,0', '100,00'] as $entrada) {
            $this->assertTrue(Desconto::percentualValido($entrada), var_export($entrada, true));
        }

        foreach (['100,01', '100.1', '101', '1000', '-5', 'abc', '10,555', '10%', "\u{00A0}"] as $entrada) {
            $this->assertFalse(Desconto::percentualValido($entrada), var_export($entrada, true));
        }
    }

    public function test_rotulo_do_percentual_mostra_o_percentual_e_o_valor(): void
    {
        $this->assertSame('10% = R$ 24,76', Desconto::rotulo(24757, 2476, 'percentual', '10'));
        $this->assertSame('100% = R$ 247,57', Desconto::rotulo(24757, 24757, 'percentual', '100'));
        $this->assertSame('12,05% = R$ 29,83', Desconto::rotulo(24757, 2983, 'percentual', '12,05'));
    }

    public function test_rotulo_do_percentual_e_sempre_com_virgula_e_sem_zeros_a_direita(): void
    {
        $this->assertSame('12,5% = R$ 30,95', Desconto::rotulo(24757, 3095, 'percentual', '12.5'));
        $this->assertSame('12,5% = R$ 30,95', Desconto::rotulo(24757, 3095, 'percentual', '12,50'));
        $this->assertSame('10% = R$ 24,76', Desconto::rotulo(24757, 2476, 'percentual', '10,00'));
    }

    public function test_rotulo_do_valor_mostra_so_o_valor(): void
    {
        $this->assertSame('R$ 24,76', Desconto::rotulo(24757, 2476, 'valor', '24,76'));
    }
}
