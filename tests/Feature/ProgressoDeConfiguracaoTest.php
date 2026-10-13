<?php

namespace Tests\Feature;

use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\ModeloMensagem;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Support\ProgressoDeConfiguracao;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProgressoDeConfiguracaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nada_cadastrado_nenhum_bloco_feito(): void
    {
        $usuario = User::factory()->semPerfil()->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertFalse($progresso['perfil']);
        $this->assertFalse($progresso['catalogo']);
        $this->assertFalse($progresso['liberado']);
        $this->assertFalse($progresso['pendencias'][0]['feito']);
        // 8, não 9: "name" é obrigatório no cadastro (CadastroController), então semPerfil() nunca o zera —
        // uma conta recém-criada sempre tem nome, mesmo sem nenhum outro dado do prestador preenchido.
        $this->assertCount(8, $progresso['pendencias'][0]['campos_faltando']);
    }

    public function test_perfil_completo_mas_sem_chave_pix_nem_modelo_bloco_perfil_incompleto(): void
    {
        $usuario = User::factory()->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertFalse($progresso['perfil']);
        $this->assertTrue($progresso['pendencias'][0]['feito']);
        $this->assertSame([], $progresso['pendencias'][0]['campos_faltando']);
        $this->assertFalse($progresso['pendencias'][1]['feito']);
        $this->assertFalse($progresso['pendencias'][2]['feito']);
    }

    public function test_bloco_perfil_completo_quando_tem_chave_pix_e_modelo(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create();
        ModeloMensagem::factory()->for($usuario)->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertTrue($progresso['perfil']);
        $this->assertFalse($progresso['catalogo']);
        $this->assertFalse($progresso['liberado']);
    }

    public function test_liberado_quando_perfil_e_catalogo_completos(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create();
        ModeloMensagem::factory()->for($usuario)->create();
        ProdutoServico::factory()->for($usuario)->create();
        Cliente::factory()->for($usuario)->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertTrue($progresso['catalogo']);
        $this->assertTrue($progresso['liberado']);
        foreach ($progresso['pendencias'] as $pendencia) {
            $this->assertTrue($pendencia['feito'], $pendencia['item']);
        }
    }

    public function test_campos_faltando_lista_so_os_campos_em_branco_do_prestador_na_ordem_certa(): void
    {
        $usuario = User::factory()->semPerfil()->create(['documento' => '52998224725', 'uf' => 'SE']);

        $campos = ProgressoDeConfiguracao::de($usuario)['pendencias'][0]['campos_faltando'];

        $this->assertSame(
            ['telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'cep'],
            array_column($campos, 'campo'),
        );
    }
}
