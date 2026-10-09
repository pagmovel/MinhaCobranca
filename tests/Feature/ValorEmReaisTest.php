<?php

namespace Tests\Feature;

use App\Rules\ValorEmReais;
use Tests\TestCase;

class ValorEmReaisTest extends TestCase
{
    public function test_limites_do_valor(): void
    {
        $valida = fn (string $valor) => validator(['valor' => $valor], ['valor' => [new ValorEmReais]])->passes();

        $this->assertTrue($valida('260,60'));
        $this->assertTrue($valida('99.999.999,99'));
        $this->assertFalse($valida('0,00'));
        $this->assertFalse($valida('100.000.000,00'));
        $this->assertFalse($valida('abc'));
    }

    public function test_mensagem_de_valor_invalido(): void
    {
        $validador = validator(['valor' => 'abc'], ['valor' => [new ValorEmReais]]);

        $this->assertSame('Informe um valor válido, como 260,60.', $validador->errors()->first('valor'));
    }
}
