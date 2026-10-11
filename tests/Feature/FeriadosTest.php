<?php

namespace Tests\Feature;

use App\Models\Feriado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeriadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cadastra_os_tres_tipos_e_lista_no_perfil(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/perfil/feriados', ['descricao' => 'Independência da Bahia', 'tipo' => 'anual', 'dia' => '2', 'mes' => '7'])
            ->assertSessionHas('sucesso', 'Feriado cadastrado.');
        // Campos de outro tipo enviados juntos não ficam gravados.
        $this->post('/perfil/feriados', ['descricao' => 'Corpus Christi', 'tipo' => 'corpus_christi', 'dia' => '9', 'data' => '2026-01-01'])
            ->assertSessionHas('sucesso');
        $this->post('/perfil/feriados', ['descricao' => 'Decreto', 'tipo' => 'unico', 'data' => '2026-08-13'])
            ->assertSessionHas('sucesso');

        $corpus = $usuario->feriados()->where('tipo', 'corpus_christi')->sole();
        $this->assertSame([null, null], [$corpus->dia, $corpus->data]);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('feriados', 3)
            ->where('feriados.0.quando', '2 de julho, todo ano')
            ->where('feriados.1.quando', 'Corpus Christi (data móvel): 04/06 em 2026')
            ->where('feriados.2.quando', '13/08/2026, só nessa data')
            ->where('feriados.2.data', '2026-08-13')
            ->has('feriadosNacionais', 12)
            ->where('feriadosNacionais.0', ['data' => '01/01', 'descricao' => 'Confraternização Universal']));
    }

    public function test_recusa_dia_que_nao_existe_e_data_com_ano_de_cinco_digitos(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'anual', 'dia' => '31', 'mes' => '2'])
            ->assertSessionHasErrors(['dia' => 'Este dia não existe no mês escolhido.']);
        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'unico', 'data' => '72026-08-01'])
            ->assertSessionHasErrors('data');
        $this->post('/perfil/feriados', ['descricao' => '', 'tipo' => 'anual'])
            ->assertSessionHasErrors(['descricao' => 'Informe o nome do feriado.', 'dia' => 'Informe o dia.', 'mes' => 'Escolha o mês.']);
        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'outro'])->assertSessionHasErrors('tipo');
        $this->post('/perfil/feriados', ['descricao' => 'Bissexto', 'tipo' => 'anual', 'dia' => '29', 'mes' => '2'])
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso', 'Feriado cadastrado.');

        $this->assertSame(1, $usuario->feriados()->count());
    }

    public function test_edita_e_exclui_so_o_proprio_feriado(): void
    {
        $usuario = User::factory()->create();
        $meu = Feriado::factory()->for($usuario)->create();
        $alheio = Feriado::factory()->create();
        $this->actingAs($usuario);

        $this->put("/perfil/feriados/{$meu->id}", ['descricao' => 'Aniversário da cidade', 'tipo' => 'anual', 'dia' => '15', 'mes' => '9'])
            ->assertSessionHas('sucesso', 'Feriado atualizado.');
        $this->assertSame([15, 9], [$meu->fresh()->dia, $meu->fresh()->mes]);

        $this->put("/perfil/feriados/{$alheio->id}", ['descricao' => 'X', 'tipo' => 'anual', 'dia' => '1', 'mes' => '1'])->assertNotFound();
        $this->delete("/perfil/feriados/{$alheio->id}")->assertNotFound();
        $this->assertNotNull($alheio->fresh());

        $this->delete("/perfil/feriados/{$meu->id}")->assertSessionHas('sucesso', 'Feriado excluído.');
        $this->assertNull($meu->fresh());
    }
}
