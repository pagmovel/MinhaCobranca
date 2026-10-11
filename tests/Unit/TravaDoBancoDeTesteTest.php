<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\TestCase as TestCaseDoProjeto;

/** A suíte apaga e recria as tabelas: só pode rodar no banco de teste. */
class TravaDoBancoDeTesteTest extends TestCase
{
    public function test_so_o_banco_de_teste_e_liberado(): void
    {
        $this->assertTrue(TestCaseDoProjeto::bancoDeTeste(['driver' => 'pgsql', 'database' => 'recibos_teste']));
        $this->assertTrue(TestCaseDoProjeto::bancoDeTeste(['driver' => 'sqlite', 'database' => ':memory:']));

        $this->assertFalse(TestCaseDoProjeto::bancoDeTeste(['driver' => 'pgsql', 'database' => 'recibos']));
        $this->assertFalse(TestCaseDoProjeto::bancoDeTeste(['driver' => 'pgsql', 'database' => 'meus_projetos']));
        $this->assertFalse(TestCaseDoProjeto::bancoDeTeste(['driver' => 'sqlite', 'database' => 'database/database.sqlite']));
        $this->assertFalse(TestCaseDoProjeto::bancoDeTeste(['driver' => 'mysql', 'database' => 'recibos_teste']));
    }
}
