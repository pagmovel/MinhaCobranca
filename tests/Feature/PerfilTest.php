<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** PNG 1x1 válido — evita depender da extensão GD para gerar imagem falsa. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Marcos Prestador',
            'documento' => '529.982.247-25',
            'nome_fantasia' => '',
            'inscricao_municipal' => '1000736',
            'inscricao_estadual' => '',
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A-4',
            'numero' => 'M.06',
            'complemento' => '',
            'bairro' => 'Marcos Freire',
            'cidade' => 'Nossa Senhora do Socorro',
            'uf' => 'se',
            'cep' => '49160-000',
            'modelo_padrao' => 'danfse',
            'dias_antecedencia' => 7,
        ], $extra);
    }

    public function test_pagina_mostra_os_dados_do_prestador(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Perfil/Editar')
                ->where('perfil.name', $usuario->name)
                ->where('perfil.tem_logo', false)
                ->has('ufs', 27)
                ->has('modelos', 2));
    }

    public function test_salva_e_normaliza_os_dados(): void
    {
        $usuario = User::factory()->semPerfil()->create();

        $this->actingAs($usuario)->post('/perfil', $this->dados())
            ->assertRedirect()
            ->assertSessionHas('sucesso', 'Perfil salvo.');

        $usuario->refresh();
        $this->assertSame('52998224725', $usuario->documento);
        $this->assertSame('49160000', $usuario->cep);
        $this->assertSame('SE', $usuario->uf);
        $this->assertSame(FamiliaModelo::Danfse, $usuario->modelo_padrao);
        $this->assertSame(7, $usuario->dias_antecedencia);
        $this->assertTrue($usuario->perfilCompleto());
    }

    public function test_recusa_documento_uf_e_cep_invalidos(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/perfil', $this->dados(['documento' => '123', 'uf' => 'XX', 'cep' => '123']))
            ->assertSessionHasErrors([
                'documento' => 'Informe um CPF ou CNPJ válido.',
                'uf' => 'O valor escolhido para UF é inválido.',
                'cep' => 'O campo CEP deve ter 8 dígitos.',
            ]);
    }

    public function test_logo_e_gravado_sem_apagar_o_anterior_e_servido(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->create();
        $png = fn () => UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG));

        $this->actingAs($usuario)->post('/perfil', $this->dados(['logo' => $png()]));
        $primeiro = $usuario->fresh()->logo;
        $this->post('/perfil', $this->dados(['logo' => $png()]));
        $segundo = $usuario->fresh()->logo;

        $this->assertNotSame($primeiro, $segundo);
        Storage::disk('local')->assertExists([$primeiro, $segundo]);
        $this->get('/perfil/logo')->assertOk();
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        Storage::fake('local');
        // Arquivo real: o fake do Laravel informa o tipo pela extensão do nome, não pelo conteúdo.
        $caminho = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($caminho, 'isto é texto, não imagem');
        $disfarcado = new UploadedFile($caminho, 'logo.png', null, null, true);

        $this->actingAs(User::factory()->create())
            ->post('/perfil', $this->dados(['logo' => $disfarcado]))
            ->assertSessionHasErrors(['logo' => 'O campo logo deve ser uma imagem.']);
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/perfil')->assertRedirect('/entrar');
        $this->post('/perfil', $this->dados())->assertRedirect('/entrar');
    }
}
