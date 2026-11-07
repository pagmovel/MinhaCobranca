<?php

namespace Tests\Feature\Temas;

use App\Models\Auditoria;
use App\Models\Cobranca;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AlterarSenhaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const NOVA = 'NovaSenha#2026';

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create(); // a senha da fábrica é "password"
    }

    /** @param  array<string, string>  $extra */
    private function enviar(array $extra = []): TestResponse
    {
        return $this->actingAs($this->usuario)->from('/painel')->put('/perfil/senha', $extra + [
            'senha_atual' => 'password',
            'nova_senha' => self::NOVA,
            'nova_senha_confirmation' => self::NOVA,
        ]);
    }

    private function senhaAtualDoBanco(): string
    {
        return $this->usuario->refresh()->password;
    }

    public function test_altera_com_a_senha_atual_correta(): void
    {
        $this->enviar()
            ->assertRedirect('/painel')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Senha alterada.');

        $this->assertTrue(Hash::check(self::NOVA, $this->senhaAtualDoBanco()));
        $this->assertFalse(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_recusa_senha_atual_errada(): void
    {
        $this->enviar(['senha_atual' => 'senha-errada'])
            ->assertRedirect('/painel')
            ->assertSessionHasErrors(['senha_atual' => 'Senha incorreta.']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
        $this->assertSame(0, Auditoria::count());
    }

    /** O login não apara a senha, então a troca também não pode aparar: senão quem tem espaço nas pontas nunca consegue trocar. */
    public function test_senha_com_espaco_nas_pontas_nao_e_aparada(): void
    {
        $this->usuario->forceFill(['password' => ' senha com espaco '])->save();
        $nova = ' Nova Senha#2026 ';

        $this->enviar(['senha_atual' => ' senha com espaco ', 'nova_senha' => $nova, 'nova_senha_confirmation' => $nova])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('sucesso', 'Senha alterada.');

        $this->assertTrue(Hash::check($nova, $this->senhaAtualDoBanco()));
    }

    public function test_recusa_senha_atual_em_branco(): void
    {
        $this->enviar(['senha_atual' => ''])->assertSessionHasErrors(['senha_atual' => 'Digite a senha atual.']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_recusa_confirmacao_diferente(): void
    {
        $this->enviar(['nova_senha_confirmation' => 'Outra#Senha2026'])
            ->assertSessionHasErrors(['nova_senha' => 'A confirmação não confere com a nova senha.']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_recusa_menos_de_8(): void
    {
        $this->enviar(['nova_senha' => 'curta12', 'nova_senha_confirmation' => 'curta12'])
            ->assertSessionHasErrors(['nova_senha' => 'O campo nova senha deve ter pelo menos 8 caracteres.']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_recusa_igual_a_atual(): void
    {
        $this->enviar(['nova_senha' => 'password', 'nova_senha_confirmation' => 'password'])
            ->assertSessionHasErrors(['nova_senha' => 'A nova senha precisa ser diferente da atual.']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_recusa_campos_em_formato_errado_sem_erro_500(): void
    {
        $this->enviar(['senha_atual' => ['password'], 'nova_senha' => ['x'], 'nova_senha_confirmation' => ['x']])
            ->assertSessionHasErrors(['senha_atual', 'nova_senha']);

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_limita_tentativas(): void
    {
        for ($tentativa = 1; $tentativa <= 6; $tentativa++) {
            $this->enviar(['senha_atual' => 'chute'.$tentativa])->assertSessionHasErrors('senha_atual');
        }

        $this->enviar()->assertStatus(429);
        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }

    public function test_auditoria_registra_a_troca_sem_guardar_a_senha(): void
    {
        $hashAnterior = $this->usuario->password;
        $this->enviar()->assertSessionHasNoErrors();

        $registro = Auditoria::sole();
        $this->assertSame('usuario.senha_alterada', $registro->acao);
        $this->assertSame('user', $registro->alvo_tipo);
        $this->assertSame($this->usuario->id, $registro->alvo_id);
        $this->assertSame($this->usuario->id, $registro->user_id);
        $this->assertSame('Senha alterada', $registro->resumo);
        $this->assertNull($registro->antes);
        $this->assertNull($registro->depois);
        $this->assertNull($registro->motivo);

        $linha = json_encode($registro->getAttributes());
        foreach ([self::NOVA, 'password', $hashAnterior, $this->senhaAtualDoBanco()] as $segredo) {
            $this->assertStringNotContainsString($segredo, $linha);
        }
    }

    public function test_senha_digitada_nao_volta_na_sessao_nem_na_pagina_depois_do_erro(): void
    {
        $this->enviar(['senha_atual' => 'chute-errado-123', 'nova_senha_confirmation' => 'confirmacao-diferente-456']);

        $antigos = session()->get('_old_input', []);
        foreach (['senha_atual', 'nova_senha', 'nova_senha_confirmation'] as $campo) {
            $this->assertArrayNotHasKey($campo, $antigos);
        }

        $this->get('/painel')->assertOk()
            ->assertDontSee('chute-errado-123')
            ->assertDontSee(self::NOVA)
            ->assertDontSee('confirmacao-diferente-456');
    }

    public function test_senha_da_baixa_tambem_nao_fica_na_sessao(): void
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->create();

        $this->actingAs($this->usuario)->from('/cobrancas')->post("/cobrancas/{$cobranca->id}/desfazer-baixa", [
            'senha' => 'chute-errado-789', 'motivo' => 'Teste',
        ])->assertSessionHasErrors('senha');

        $this->assertArrayNotHasKey('senha', session()->get('_old_input', []));
    }

    public function test_registro_de_senha_na_tela_de_auditoria_nao_ganha_link_de_cobranca(): void
    {
        // O id da cobrança é igual ao do usuário: o link só pode sair do tipo do alvo, nunca da coincidência de ids.
        $cobranca = Cobranca::factory()->for($this->usuario)->create(['id' => $this->usuario->id, 'vencimento' => '2026-05-17']);
        $this->usuario->auditorias()->create([
            'acao' => 'cobranca.cancelada', 'alvo_tipo' => 'cobranca', 'alvo_id' => $cobranca->id, 'resumo' => 'Cobrança cancelada',
        ]);
        $this->enviar()->assertSessionHasNoErrors();

        $linhas = collect($this->getJson('/auditoria/dados?de=2000-01-01&ate='.now()->toDateString())->assertOk()->json('linhas'))
            ->keyBy('resumo');

        $this->assertSame('/cobrancas?mes=2026-05', $linhas['Cobrança cancelada']['link']);
        $this->assertNull($linhas['Senha alterada']['link']);
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->put('/perfil/senha', [
            'senha_atual' => 'password', 'nova_senha' => self::NOVA, 'nova_senha_confirmation' => self::NOVA,
        ])->assertRedirect('/entrar');

        $this->assertTrue(Hash::check('password', $this->senhaAtualDoBanco()));
    }
}
