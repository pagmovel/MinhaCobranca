<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use App\Models\User;
use Database\Seeders\PerfilInicialSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerfilInicialSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Prestador Exemplo',
            'email' => 'prestador@exemplo.com',
            'password_hash' => Hash::make('senha-original'),
            'documento' => '52998224725',
            'nome_fantasia' => 'Exemplo',
            'inscricao_municipal' => null,
            'inscricao_estadual' => null,
            'telefone' => '79 9 9999-0000',
            'logradouro' => 'Rua A',
            'numero' => '3',
            'complemento' => 'Bloco 2',
            'bairro' => 'Centro',
            'cidade' => 'Fortaleza',
            'uf' => 'CE',
            'cep' => '60000000',
            'modelo_padrao' => 'danfse',
            'dias_antecedencia' => 17,
        ], $extra);
    }

    public function test_cria_o_admin_com_perfil_e_mantem_a_senha_original(): void
    {
        (new PerfilInicialSeeder)->semear($this->dados());

        $usuario = User::where('email', 'prestador@exemplo.com')->firstOrFail();
        $this->assertTrue($usuario->is_admin);
        $this->assertSame(StatusUsuario::Aprovado, $usuario->status);
        $this->assertSame(FamiliaModelo::Danfse, $usuario->modelo_padrao);
        $this->assertSame(17, $usuario->dias_antecedencia);
        $this->assertSame('Bloco 2', $usuario->complemento);
        $this->assertTrue($usuario->perfilCompleto());
        $this->assertTrue(Hash::check('senha-original', $usuario->password));
    }

    public function test_rodar_de_novo_atualiza_sem_duplicar(): void
    {
        (new PerfilInicialSeeder)->semear($this->dados());
        (new PerfilInicialSeeder)->semear($this->dados(['cidade' => 'Aracaju']));

        $this->assertSame(1, User::count());
        $this->assertSame('Aracaju', User::firstOrFail()->cidade);
    }

    public function test_sem_arquivo_de_dados_nao_cria_nada(): void
    {
        (new PerfilInicialSeeder(database_path('seeders/dados/nao-existe.json')))->run();

        $this->assertSame(0, User::count());
    }
}
