<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem manifest do Vite nos testes; o fake também cobre a diretiva @fonts.
        $this->withoutVite();
    }
}
