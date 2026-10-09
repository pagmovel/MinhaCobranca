<?php

namespace Tests\Feature\Contas;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UsuarioModeloTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_usuario_novo_nasce_pendente_sem_admin_e_com_padroes(): void
    {
        $usuario = User::create([
            'name' => 'Ana',
            'email' => 'ana@exemplo.com',
            'password' => 'segredo123',
        ])->refresh();

        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
        $this->assertSame(FamiliaModelo::Webiss, $usuario->modelo_padrao);
        $this->assertSame(10, $usuario->dias_antecedencia);
    }

    public function test_status_e_admin_nao_sao_preenchiveis_em_massa(): void
    {
        $usuario = User::create([
            'name' => 'Ana',
            'email' => 'ana@exemplo.com',
            'password' => 'segredo123',
            'status' => 'aprovado',
            'is_admin' => true,
        ])->refresh();

        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
    }

    public function test_perfil_completo_exige_os_campos_do_prestador(): void
    {
        $this->assertTrue(User::factory()->create()->perfilCompleto());
        $this->assertFalse(User::factory()->semPerfil()->create()->perfilCompleto());
        $this->assertFalse(User::factory()->create(['cep' => null])->perfilCompleto());
    }

    public function test_familia_do_modelo_aponta_para_a_versao_atual(): void
    {
        $this->assertSame('recibos.pdf.webiss-v1', FamiliaModelo::Webiss->versaoAtual()->view());
        $this->assertSame('recibos.pdf.danfse-v1', FamiliaModelo::Danfse->versaoAtual()->view());
    }
}
