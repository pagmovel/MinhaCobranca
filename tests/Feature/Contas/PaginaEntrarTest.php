<?php

namespace Tests\Feature\Contas;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginaEntrarTest extends TestCase
{
    public function test_pagina_entrar_renderiza_componente_vue(): void
    {
        $this->get('/entrar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Entrar'));
    }
}
