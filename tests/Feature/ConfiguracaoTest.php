<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfiguracaoTest extends TestCase
{
    public function test_fuso_e_idioma_sao_brasileiros(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('Gerenciador de Cobranças', config('app.name'));
    }

    public function test_mensagens_de_validacao_saem_em_portugues(): void
    {
        $validador = validator(['email' => 'x', 'name' => ''], [
            'email' => ['required', 'email'],
            'name' => ['required'],
        ]);

        $this->assertSame('Informe um e-mail válido.', $validador->errors()->first('email'));
        $this->assertSame('Preencha o campo nome.', $validador->errors()->first('name'));
    }
}
