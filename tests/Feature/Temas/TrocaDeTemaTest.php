<?php

namespace Tests\Feature\Temas;

use App\Enums\TemaVisual;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrocaDeTemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_troca_para_tema_valido(): void
    {
        $usuario = User::factory()->create();
        DB::table('users')->where('id', $usuario->id)->update(['tema' => 'tema-removido']);
        $usuario->refresh(); // o objeto do ator tem que refletir o banco, senão o Eloquent não vê mudança

        foreach (TemaVisual::cases() as $tema) {
            $this->actingAs($usuario)->from('/painel')->put('/perfil/tema', ['tema' => $tema->value])
                ->assertRedirect('/painel')
                ->assertSessionHasNoErrors();

            $this->assertSame($tema->value, $usuario->refresh()->getRawOriginal('tema'));
            $this->assertSame($tema, $usuario->tema);
        }
    }

    public function test_tema_regua_e_aceito_na_troca(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from('/painel')->put('/perfil/tema', ['tema' => 'regua'])
            ->assertRedirect('/painel')
            ->assertSessionHasNoErrors();

        $this->assertSame('regua', $usuario->refresh()->getRawOriginal('tema'));
        $this->assertSame(TemaVisual::Regua, $usuario->tema);
    }

    public function test_tema_almanaque_e_aceito_na_troca(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from('/painel')->put('/perfil/tema', ['tema' => 'almanaque'])
            ->assertRedirect('/painel')
            ->assertSessionHasNoErrors();

        $this->assertSame('almanaque', $usuario->refresh()->getRawOriginal('tema'));
        $this->assertSame(TemaVisual::Almanaque, $usuario->tema);
    }

    public function test_recusa_tema_inexistente(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from('/painel')->put('/perfil/tema', ['tema' => 'tema-que-nao-existe'])
            ->assertRedirect('/painel')
            ->assertSessionHasErrors(['tema' => 'Esse tema não existe. Escolha um da lista.']);

        $this->assertSame('talao', $usuario->refresh()->getRawOriginal('tema'));
    }

    public function test_recusa_tema_ausente_ou_em_formato_errado_sem_erro_500(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->from('/painel');
        $this->put('/perfil/tema', [])->assertSessionHasErrors('tema');
        $this->put('/perfil/tema', ['tema' => ['talao']])->assertSessionHasErrors('tema');
        $this->put('/perfil/tema', ['tema' => ''])->assertSessionHasErrors('tema');
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->put('/perfil/tema', ['tema' => 'talao'])->assertRedirect('/entrar');
        $this->post('/perfil/novidade-temas')->assertRedirect('/entrar');
    }

    public function test_conta_pendente_nao_troca(): void
    {
        $usuario = User::factory()->pendente()->create();
        DB::table('users')->where('id', $usuario->id)->update(['tema' => 'tema-removido']);

        $this->actingAs($usuario)->put('/perfil/tema', ['tema' => 'talao'])->assertRedirect('/entrar');

        $this->assertSame('tema-removido', $usuario->refresh()->getRawOriginal('tema'));
    }

    public function test_troca_nao_altera_outros_campos(): void
    {
        $usuario = User::factory()->create(['dias_aviso' => 8, 'nome_fantasia' => 'Casa Azul']);
        DB::table('users')->where('id', $usuario->id)->update(['tema' => 'tema-removido']);
        $antes = collect($usuario->refresh()->getAttributes())->except(['tema', 'updated_at'])->all();

        $this->actingAs($usuario)->put('/perfil/tema', ['tema' => 'talao'])->assertRedirect();

        $depois = collect($usuario->refresh()->getAttributes())->except(['tema', 'updated_at'])->all();
        $this->assertSame($antes, $depois);
        $this->assertFalse($usuario->is_admin);
        $this->assertSame('talao', $usuario->getRawOriginal('tema'));
    }

    public function test_so_muda_o_proprio_tema(): void
    {
        [$eu, $outro] = User::factory()->count(2)->create();
        DB::table('users')->update(['tema' => 'tema-removido']);
        $eu->refresh();

        $this->actingAs($eu)->put('/perfil/tema', ['tema' => 'talao', 'id' => $outro->id, 'user_id' => $outro->id]);

        $this->assertSame('talao', $eu->refresh()->getRawOriginal('tema'));
        $this->assertSame('tema-removido', $outro->refresh()->getRawOriginal('tema'));
    }

    public function test_marca_novidade_como_vista(): void
    {
        $usuario = User::factory()->create();
        $usuario->forceFill(['novidade_temas_vista' => false])->save();

        $this->actingAs($usuario)->from('/painel')->post('/perfil/novidade-temas')->assertRedirect('/painel');

        $this->assertTrue($usuario->refresh()->novidade_temas_vista);
    }

    public function test_conta_pendente_nao_marca_a_novidade(): void
    {
        $usuario = User::factory()->pendente()->create();
        $usuario->forceFill(['novidade_temas_vista' => false])->save();

        $this->actingAs($usuario)->post('/perfil/novidade-temas')->assertRedirect('/entrar');

        $this->assertFalse($usuario->refresh()->novidade_temas_vista);
    }
}
