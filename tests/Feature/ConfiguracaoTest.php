<?php

namespace Tests\Feature;

use Illuminate\Foundation\Console\ServeCommand;
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

    /** Sem TEMP/TMP o `php -S` do Windows não cria o temporário do upload e o logo some sem erro. */
    public function test_artisan_serve_repassa_a_pasta_temporaria(): void
    {
        $this->assertContains('TEMP', ServeCommand::$passthroughVariables);
        $this->assertContains('TMP', ServeCommand::$passthroughVariables);
    }
}
