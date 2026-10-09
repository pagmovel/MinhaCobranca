<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'APLB Sindicato',
            'documento' => '11.222.333/0001-81',
            'inscricao_municipal' => '',
            'inscricao_estadual' => '',
            'telefone' => '',
            'email' => 'contato@exemplo.com',
            'logradouro' => 'Rua Virgílio Oliveira Lima',
            'numero' => '185',
            'complemento' => '',
            'bairro' => 'Centro',
            'cidade' => 'Araci',
            'uf' => 'BA',
            'cep' => '48760-000',
            'modelo' => 'webiss',
        ], $extra);
    }

    public function test_lista_so_os_clientes_do_usuario(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->create(['nome' => 'Meu cliente']);
        Cliente::factory()->create(['nome' => 'Cliente de outro']);

        $this->actingAs($usuario)->get('/clientes')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clientes/Index')
                ->has('clientes', 1)
                ->where('clientes.0.nome', 'Meu cliente'));
    }

    public function test_formulario_novo_sugere_o_modelo_padrao_do_usuario(): void
    {
        $usuario = User::factory()->create(['modelo_padrao' => FamiliaModelo::Danfse]);

        $this->actingAs($usuario)->get('/clientes/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Clientes/Form')
                ->where('cliente', null)
                ->where('modeloPadrao', 'danfse'));
    }

    public function test_cria_cliente_normalizando_documento_e_cep(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post('/clientes', $this->dados())->assertRedirect('/clientes');

        $cliente = $usuario->clientes()->firstOrFail();
        $this->assertSame('11222333000181', $cliente->documento);
        $this->assertSame('48760000', $cliente->cep);
        $this->assertSame(FamiliaModelo::Webiss, $cliente->modelo);
    }

    public function test_aceita_cnpj_alfanumerico(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post('/clientes', $this->dados(['documento' => '12.ABC.345/01DE-35']))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/clientes');

        $this->assertSame('12ABC34501DE35', $usuario->clientes()->firstOrFail()->documento);
    }

    public function test_recusa_documento_invalido_e_repetido_no_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->create(['documento' => '11222333000181']);

        $this->actingAs($usuario)->post('/clientes', $this->dados(['documento' => '123']))
            ->assertSessionHasErrors(['documento' => 'Informe um CPF ou CNPJ válido.']);
        $this->post('/clientes', $this->dados())
            ->assertSessionHasErrors(['documento' => 'Este CPF/CNPJ já está cadastrado.']);
    }

    public function test_mesmo_documento_em_usuarios_diferentes_e_permitido(): void
    {
        Cliente::factory()->create(['documento' => '11222333000181']);

        $this->actingAs(User::factory()->create())->post('/clientes', $this->dados())
            ->assertSessionHasNoErrors()
            ->assertRedirect('/clientes');
    }

    public function test_cliente_de_outro_usuario_da_404(): void
    {
        $alheio = Cliente::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get("/clientes/{$alheio->id}/edit")->assertNotFound();
        $this->put("/clientes/{$alheio->id}", $this->dados())->assertNotFound();
        $this->delete("/clientes/{$alheio->id}")->assertNotFound();
    }

    public function test_edita_troca_modelo_e_exclui(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['documento' => '11222333000181']);

        $this->actingAs($usuario)->put("/clientes/{$cliente->id}", $this->dados(['modelo' => 'danfse']))
            ->assertRedirect('/clientes');
        $this->assertSame(FamiliaModelo::Danfse, $cliente->fresh()->modelo);

        $this->delete("/clientes/{$cliente->id}")->assertRedirect('/clientes');
        $this->assertModelMissing($cliente);
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/clientes')->assertRedirect('/entrar');
    }
}
