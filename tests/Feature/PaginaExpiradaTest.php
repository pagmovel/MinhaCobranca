<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PaginaExpiradaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Nos testes o Laravel não confere o token CSRF: a rota lança a mesma exceção de um token que não bate.
        Route::middleware('web')->post('/_teste/token-vencido', fn () => throw new TokenMismatchException);
    }

    public function test_token_vencido_volta_para_a_pagina_com_aviso_em_vez_da_janela_419(): void
    {
        $this->from('/produtos-servicos/6/edit')->post('/_teste/token-vencido')
            ->assertRedirect('/produtos-servicos/6/edit')
            ->assertSessionHas('erro', 'A página expirou. Tente de novo.');
    }

    public function test_pedido_json_continua_recebendo_419(): void
    {
        // enviarJson/buscarJson já mostram "A sessão expirou. Recarregue a página e tente de novo." com o 419.
        $this->postJson('/_teste/token-vencido')->assertStatus(419);
    }
}
