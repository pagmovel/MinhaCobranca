<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'CPF com máscara' => ['529.982.247-25'],
            'CPF só dígitos' => ['52998224725'],
            'CNPJ numérico' => ['11.222.333/0001-81'],
            'CNPJ alfanumérico (exemplo oficial da Receita)' => ['12.ABC.345/01DE-35'],
            'CNPJ alfanumérico em minúsculas' => ['12abc34501de35'],
        ];
    }

    public static function invalidos(): array
    {
        return [
            'CPF com DV errado' => ['529.982.247-24'],
            'CPF repetido' => ['111.111.111-11'],
            'CNPJ com DV errado' => ['11.222.333/0001-80'],
            'CNPJ zerado' => ['00.000.000/0000-00'],
            'CNPJ alfanumérico com DV errado' => ['12ABC34501DE36'],
            'CNPJ com letra no DV' => ['12ABC34501DE3A'],
            'tamanho errado' => ['1234567890'],
            'vazio' => [''],
        ];
    }

    #[DataProvider('validos')]
    public function test_aceita_documentos_validos(string $documento): void
    {
        $this->assertTrue(Documento::valido($documento));
    }

    #[DataProvider('invalidos')]
    public function test_recusa_documentos_invalidos(string $documento): void
    {
        $this->assertFalse(Documento::valido($documento));
    }

    public function test_calcula_dv_do_exemplo_oficial(): void
    {
        $this->assertSame('12ABC34501DE35', Documento::cnpjComDv('12ABC34501DE'));
        $this->assertSame('11222333000181', Documento::cnpjComDv('112223330001'));
    }

    public function test_formata_para_exibicao(): void
    {
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertSame('11.222.333/0001-81', Documento::formatar('11222333000181'));
        $this->assertSame('12.ABC.345/01DE-35', Documento::formatar('12abc34501de35'));
        $this->assertSame('-', Documento::formatar(null));
    }

    public function test_normaliza_removendo_mascara(): void
    {
        $this->assertSame('12ABC34501DE35', Documento::normalizar(' 12.abc.345/01de-35 '));
    }
}
