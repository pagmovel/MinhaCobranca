<?php

namespace Tests\Unit;

use App\Services\ValorPorExtenso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValorPorExtensoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'nota de referência' => [26060, 'duzentos e sessenta reais e sessenta centavos'],
            'zero' => [0, 'zero reais'],
            'um real' => [100, 'um real'],
            'um centavo' => [1, 'um centavo'],
            'cem' => [10000, 'cem reais'],
            'cento e um' => [10100, 'cento e um reais'],
            'mil' => [100000, 'mil reais'],
            'mil e um' => [100100, 'mil e um reais'],
            'mil e dez' => [101000, 'mil e dez reais'],
            'mil e duzentos' => [120000, 'mil e duzentos reais'],
            'mil duzentos e trinta' => [123000, 'mil duzentos e trinta reais'],
            'dois mil' => [200000, 'dois mil reais'],
            'dezenove' => [1900, 'dezenove reais'],
            'um milhão exato' => [100000000, 'um milhão de reais'],
            'dois milhões exatos' => [200000000, 'dois milhões de reais'],
            'milhão e meio' => [150000000, 'um milhão e quinhentos mil reais'],
            'milhão e cem' => [100010000, 'um milhão e cem reais'],
            'composto' => [123456789, 'um milhão duzentos e trinta e quatro mil quinhentos e sessenta e sete reais e oitenta e nove centavos'],
        ];
    }

    #[DataProvider('casos')]
    public function test_escreve_por_extenso(int $centavos, string $esperado): void
    {
        $this->assertSame($esperado, ValorPorExtenso::converter($centavos));
    }
}
