<?php

namespace Tests\Feature\Temas;

use App\Enums\TemaVisual;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TemaDoUsuarioTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_usuario_novo_nasce_com_o_tema_do_env(): void
    {
        // Cada tema novo entra neste laço sozinho, pelo enum.
        foreach (TemaVisual::cases() as $tema) {
            config(['temas.padrao' => $tema->value]);

            $this->assertSame($tema, User::factory()->create()->refresh()->tema);
        }
    }

    public function test_env_invalido_cai_no_talao(): void
    {
        config(['temas.padrao' => 'tema-que-nao-existe']);

        $usuario = User::factory()->create()->refresh();

        $this->assertSame(TemaVisual::Talao, TemaVisual::padrao());
        $this->assertSame(TemaVisual::Talao, $usuario->tema);
        $this->assertSame('talao', $usuario->getRawOriginal('tema'));
    }

    public function test_usuario_novo_nasce_sem_novidade_pendente(): void
    {
        $this->assertTrue(User::factory()->create()->refresh()->novidade_temas_vista);
    }

    public function test_usuario_antigo_fica_no_talao_com_a_novidade_pendente(): void
    {
        // Insert direto, sem passar pelo model: é o que a migração faz com as linhas que já existiam.
        $id = DB::table('users')->insertGetId([
            'name' => 'Antigo', 'email' => 'antigo@exemplo.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $antigo = User::findOrFail($id);
        $this->assertSame(TemaVisual::Talao, $antigo->tema);
        $this->assertFalse($antigo->novidade_temas_vista);
    }

    public function test_cadastro_pelo_formulario_tambem_nasce_com_tema_e_sem_novidade(): void
    {
        $this->post('/cadastro', [
            'name' => 'Fulana', 'email' => 'fulana@exemplo.com',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123',
        ])->assertRedirect('/entrar');

        $usuario = User::where('email', 'fulana@exemplo.com')->sole();
        $this->assertSame(TemaVisual::padrao(), $usuario->tema);
        $this->assertTrue($usuario->novidade_temas_vista);
    }

    public function test_cast_devolve_o_enum(): void
    {
        $usuario = User::factory()->create();

        $this->assertInstanceOf(TemaVisual::class, $usuario->refresh()->tema);

        $usuario->tema = TemaVisual::Talao;
        $usuario->save();
        $this->assertSame('talao', $usuario->getRawOriginal('tema'));
    }

    public function test_valor_corrompido_no_banco_vira_talao_sem_erro(): void
    {
        $usuario = User::factory()->create();
        DB::table('users')->where('id', $usuario->id)->update(['tema' => 'tema-removido']);

        $this->assertSame(TemaVisual::Talao, $usuario->refresh()->tema);
        $this->assertIsArray($usuario->toArray());
    }

    public function test_tema_nao_e_preenchivel_em_massa(): void
    {
        $usuario = (new User)->fill(['name' => 'Fulana', 'tema' => 'talao', 'novidade_temas_vista' => false]);

        $this->assertSame('Fulana', $usuario->name);
        $this->assertArrayNotHasKey('tema', $usuario->getAttributes());
        $this->assertArrayNotHasKey('novidade_temas_vista', $usuario->getAttributes());
    }

    public function test_casca_painel_e_linha_do_tempo_de_cada_tema(): void
    {
        $esperado = [
            'talao' => ['lateral', 'empilhado', false],
            'regua' => ['superior', 'regua', false],
            'almanaque' => ['lateral', 'mosaico', true],
        ];

        $this->assertSame(array_keys($esperado), array_map(fn (TemaVisual $tema) => $tema->value, TemaVisual::cases()));

        foreach (TemaVisual::cases() as $tema) {
            $this->assertSame($esperado[$tema->value], [$tema->casca(), $tema->painel(), $tema->comLinhaDoTempo()], $tema->value);
        }

        $this->assertSame('Régua', TemaVisual::Regua->rotulo());
        $this->assertSame('Almanaque', TemaVisual::Almanaque->rotulo());
    }

    public function test_listas_do_enum_estao_completas(): void
    {
        foreach (TemaVisual::cases() as $tema) {
            $this->assertNotSame('', $tema->rotulo());
            $this->assertNotSame('', $tema->descricao());
            $this->assertContains($tema->casca(), ['lateral', 'superior']);
            $this->assertContains($tema->painel(), ['empilhado', 'colunas', 'mosaico', 'regua']);
            $this->assertCount(4, $tema->amostra());
            foreach ($tema->amostra() as $cor) {
                $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $cor, "{$tema->value}: cor de amostra inválida");
            }
            $this->assertNotEmpty($tema->fontes());
        }

        $this->assertSame(
            array_column(TemaVisual::paraOFront(), 'id'),
            array_map(fn (TemaVisual $tema) => $tema->value, TemaVisual::cases()),
        );
        $this->assertSame(
            ['id', 'nome', 'descricao', 'casca', 'painel', 'linha_do_tempo', 'amostra'],
            array_keys(TemaVisual::paraOFront()[0]),
        );
    }
}
