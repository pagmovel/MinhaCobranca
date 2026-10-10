<?php

namespace Tests\Unit;

use App\Support\LinhasDoRecibo;
use PHPUnit\Framework\TestCase;

class LinhasDoReciboTest extends TestCase
{
    public function test_linha_longa_quebra_em_palavras_sem_passar_da_largura(): void
    {
        $frase = str_repeat('Manutenção mensal do servidor ', 10);

        $linhas = LinhasDoRecibo::quebrar($frase);

        $this->assertCount(3, $linhas);
        foreach ($linhas as $linha) {
            $this->assertLessThanOrEqual(LinhasDoRecibo::LARGURA, mb_strlen($linha));
        }
        $this->assertSame(trim($frase), implode(' ', $linhas));
    }

    public function test_mantem_quebras_digitadas_e_linhas_em_branco(): void
    {
        $this->assertSame(['- Item 1', '', '- Item 2'], LinhasDoRecibo::quebrar("- Item 1\r\n\n- Item 2\n"));
    }

    public function test_palavra_maior_que_a_largura_e_cortada_sem_estragar_acentos(): void
    {
        $palavra = str_repeat('ã', LinhasDoRecibo::LARGURA + 5);

        $this->assertSame([str_repeat('ã', LinhasDoRecibo::LARGURA), 'ããããã'], LinhasDoRecibo::quebrar($palavra));
    }
}
