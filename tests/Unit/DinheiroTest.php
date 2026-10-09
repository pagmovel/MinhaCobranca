<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'vírgula decimal' => ['260,60', 26060],
            'uma casa decimal' => ['260,6', 26060],
            'inteiro' => ['260', 26000],
            'com R$ e espaço' => ['R$ 260,60', 26060],
            'milhar com vírgula' => ['1.234,56', 123456],
            'milhar sem centavos' => ['1.234', 123400],
            'ponto decimal' => ['260.60', 26060],
            'um centavo' => ['0,01', 1],
            'espaços nas pontas' => [' 15 ', 1500],
        ];
    }

    public static function invalidos(): array
    {
        return [
            'vazio' => [''],
            'negativo' => ['-5'],
            'texto' => ['abc'],
            'duas vírgulas' => ['1,234,56'],
            'três casas decimais' => ['12,345'],
            'pontos fora do padrão' => ['1.23.4'],
        ];
    }

    #[DataProvider('validos')]
    public function test_converte_formatos_aceitos(string $entrada, int $centavos): void
    {
        $this->assertSame($centavos, Dinheiro::paraCentavos($entrada));
    }

    #[DataProvider('invalidos')]
    public function test_recusa_formatos_invalidos(string $entrada): void
    {
        $this->assertNull(Dinheiro::paraCentavos($entrada));
    }

    public function test_formata_em_reais(): void
    {
        $this->assertSame('260,60', Dinheiro::formatar(26060));
        $this->assertSame('1.234.567,89', Dinheiro::formatar(123456789));
        $this->assertSame('0,05', Dinheiro::formatar(5));
        $this->assertSame('0,00', Dinheiro::formatar(0));
    }
}
