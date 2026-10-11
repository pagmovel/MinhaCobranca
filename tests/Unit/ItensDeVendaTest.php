<?php

namespace Tests\Unit;

use App\Support\ItensDeVenda;
use PHPUnit\Framework\TestCase;

class ItensDeVendaTest extends TestCase
{
    public function test_le_quantidade_com_virgula_ou_ponto_e_ate_tres_casas(): void
    {
        $this->assertSame(1500, ItensDeVenda::milesimos('1,5'));
        $this->assertSame(1500, ItensDeVenda::milesimos('1.5'));
        $this->assertSame(2000, ItensDeVenda::milesimos(2));
        $this->assertSame(333, ItensDeVenda::milesimos('0,333'));
        $this->assertSame(1500, ItensDeVenda::milesimos('1.500'));
        $this->assertNull(ItensDeVenda::milesimos('1,2345'));
        $this->assertNull(ItensDeVenda::milesimos('-1'));
        $this->assertNull(ItensDeVenda::milesimos('+1'));
        $this->assertNull(ItensDeVenda::milesimos('1e3'));
        $this->assertNull(ItensDeVenda::milesimos('abc'));
    }

    public function test_total_arredonda_meio_centavo_para_cima_so_com_inteiros(): void
    {
        $this->assertSame(39090, ItensDeVenda::total(1500, 26060)); // 1,5 × 260,60 = 390,90
        $this->assertSame(2, ItensDeVenda::total(1500, 1));          // 1,5 × 0,01 = 0,015 → 0,02
        $this->assertSame(1, ItensDeVenda::total(1499, 1));          // 0,01499 → 0,01
        $this->assertSame(100, ItensDeVenda::total(333, 300));       // 0,333 × 3,00 = 0,999 → 1,00
        $this->assertSame(0, ItensDeVenda::total(1000, 0));
    }

    public function test_quantidade_para_o_banco_e_para_a_tela(): void
    {
        $this->assertSame('1.500', ItensDeVenda::quantidade(1500));
        $this->assertSame('0.033', ItensDeVenda::quantidade(33));
        $this->assertSame('1,5', ItensDeVenda::formatarQuantidade(1500));
        $this->assertSame('2', ItensDeVenda::formatarQuantidade(2000));
        $this->assertSame('0,033', ItensDeVenda::formatarQuantidade(33));
    }

    public function test_principal_e_o_primeiro_marcado_ou_o_de_maior_total(): void
    {
        $item = fn (int $total, bool $principal = false) => ['total_centavos' => $total, 'principal' => $principal];

        $this->assertSame(1, ItensDeVenda::indicePrincipal([$item(500), $item(100, true), $item(900, true)]));
        $this->assertSame(2, ItensDeVenda::indicePrincipal([$item(500), $item(100), $item(900)]));
        $this->assertSame(0, ItensDeVenda::indicePrincipal([$item(500), $item(500)]));
    }
}
