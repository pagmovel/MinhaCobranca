<?php

namespace Tests\Unit;

use App\Enums\TipoChavePix;
use PHPUnit\Framework\TestCase;

class TipoChavePixTest extends TestCase
{
    public function test_normaliza_cada_tipo_como_o_dict_conhece(): void
    {
        $this->assertSame('52998224725', TipoChavePix::Cpf->normalizar('529.982.247-25'));
        $this->assertSame('11222333000181', TipoChavePix::Cnpj->normalizar('11.222.333/0001-81'));
        $this->assertSame('fulano@exemplo.com', TipoChavePix::Email->normalizar(' Fulano@Exemplo.com '));
        $this->assertSame('+5579999990000', TipoChavePix::Telefone->normalizar('(79) 99999-0000'));
        $this->assertSame('+5579999990000', TipoChavePix::Telefone->normalizar('+55 79 99999-0000'));
        $this->assertSame('123e4567-e12b-12d1-a456-426655440000', TipoChavePix::Aleatoria->normalizar('123E4567-E12B-12D1-A456-426655440000'));
    }

    public function test_recusa_chave_que_nao_serve_para_o_tipo(): void
    {
        $this->assertNull(TipoChavePix::Cpf->normalizar('111.111.111-11'));
        $this->assertNull(TipoChavePix::Cpf->normalizar('11.222.333/0001-81'));
        $this->assertSame('12ABC34501DE35', TipoChavePix::Cnpj->normalizar('12.abc.345/01de-35'), 'CNPJ alfanumérico (Manual do DICT 8.5)');
        $this->assertNull(TipoChavePix::Cnpj->normalizar('12.ABC.345/01DE-36'), 'dígito verificador errado');
        $this->assertNull(TipoChavePix::Telefone->normalizar('(79) 3222-0000'), 'só celular (E.164 no DICT)');
        $this->assertNull(TipoChavePix::Email->normalizar('sem-arroba'));
        $this->assertNull(TipoChavePix::Telefone->normalizar('9999-0000'));
        $this->assertNull(TipoChavePix::Aleatoria->normalizar('nao-e-uuid'));
    }

    public function test_formata_para_exibir(): void
    {
        $this->assertSame('529.982.247-25', TipoChavePix::Cpf->formatar('52998224725'));
        $this->assertSame('+55 (79) 99999-0000', TipoChavePix::Telefone->formatar('+5579999990000'));
        $this->assertSame('fulano@exemplo.com', TipoChavePix::Email->formatar('fulano@exemplo.com'));
    }
}
