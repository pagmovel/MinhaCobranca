<?php

namespace Tests\Unit;

use App\Support\ProRata;
use PHPUnit\Framework\TestCase;

class ProRataTest extends TestCase
{
    public function test_quantidade_proporcional_com_tres_casas_meio_para_cima(): void
    {
        $this->assertSame(581, ProRata::quantidade(1000, 18, 31));
        $this->assertSame(167, ProRata::quantidade(1000, 5, 30));
        $this->assertSame(1500, ProRata::quantidade(1500, 31, 31));
        $this->assertSame(750, ProRata::quantidade(1500, 15, 30));
    }

    public function test_quantidade_nunca_zera(): void
    {
        $this->assertSame(1, ProRata::quantidade(1, 1, 31));
    }

    public function test_valor_proporcional_para_cobranca_sem_itens(): void
    {
        // 247,57 × 18 ÷ 31 = 143,750… → 143,75; 100,00 × 1 ÷ 8 = 12,50 (meio centavo não aparece em centavos inteiros).
        $this->assertSame(14375, ProRata::valor(24757, 18, 31));
        $this->assertSame(1250, ProRata::valor(10000, 1, 8));
        // 0,05 × 1 ÷ 2 = 0,025 → 0,03 (meio para cima).
        $this->assertSame(3, ProRata::valor(5, 1, 2));
        $this->assertSame(24757, ProRata::valor(24757, 31, 31));
    }
}
