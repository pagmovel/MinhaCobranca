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
        Route::middleware('web')->delete('/_teste/token-vencido', fn () => throw new TokenMismatchException);
    }

    public function test_token_vencido_volta_para_a_pagina_com_aviso_em_vez_da_janela_419(): void
    {
        $this->from('/produtos-servicos/6/edit')->post('/_teste/token-vencido')
            ->assertRedirect('/produtos-servicos/6/edit')
            ->assertSessionHas('erro', 'A página expirou. Tente de novo.');
    }

    /**
     * 302 preserva o método em DELETE/PUT/PATCH (a especificação Fetch só reescreve POST para GET em 301/302; em
     * qualquer outro método o navegador repete o mesmo verbo no destino). Um 302 na volta faria o navegador refazer o
     * DELETE em `/produtos-servicos` (rota só com GET/POST) e cair no 405. 303 reescreve para GET em qualquer método.
     */
    public function test_token_vencido_em_delete_tambem_volta_com_aviso_e_303_para_o_navegador_nao_repetir_o_metodo(): void
    {
        $this->from('/produtos-servicos')->delete('/_teste/token-vencido')
            ->assertStatus(303)
            ->assertRedirect('/produtos-servicos')
            ->assertSessionHas('erro', 'A página expirou. Tente de novo.');
    }

    public function test_pedido_json_continua_recebendo_419(): void
    {
        // enviarJson/buscarJson já mostram "A sessão expirou. Recarregue a página e tente de novo." com o 419.
        $this->postJson('/_teste/token-vencido')->assertStatus(419);
    }
}
