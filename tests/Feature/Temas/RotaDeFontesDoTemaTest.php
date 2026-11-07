<?php

namespace Tests\Feature\Temas;

use App\Enums\TemaVisual;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Tests\TestCase;

/**
 * Tarefa 2.4: `GET /perfil/tema/{tema}/fontes` devolve o que o `@fonts` geraria para o tema, para o seletor trocar de
 * tema sem recarregar a página (o `<head>` da página só tem as fontes do tema com que ela foi aberta).
 */
class RotaDeFontesDoTemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private array $pastas = [];

    protected function tearDown(): void
    {
        foreach ($this->pastas as $pasta) {
            File::deleteDirectory($pasta);
        }

        parent::tearDown();
    }

    /** Aponta o Vite para uma pasta de teste com (ou sem) o manifesto de fontes. */
    private function usarManifesto(bool $comManifesto = true): void
    {
        $pasta = sys_get_temp_dir().'/rota-de-fontes-'.bin2hex(random_bytes(4));
        $this->pastas[] = $pasta;
        File::ensureDirectoryExists("{$pasta}/build/assets");

        if ($comManifesto) {
            $manifesto = $this->manifestoDeTeste();
            File::put("{$pasta}/build/fonts-manifest.json", json_encode($manifesto));
            File::put("{$pasta}/build/{$manifesto['style']['file']}", implode("\n", $manifesto['style']['familyStyles']));
        }

        $this->app->usePublicPath($pasta);
        // Instância nova: o Vite lembra dos preloads já emitidos e a rota é chamada várias vezes por teste.
        ViteFacade::clearResolvedInstance();
        $this->swap(Vite::class, new Vite);
    }

    public function test_devolve_o_html_e_as_familias_do_tema(): void
    {
        $this->usarManifesto();
        $usuario = User::factory()->create();

        foreach (TemaVisual::cases() as $tema) {
            ViteFacade::clearResolvedInstance();
            $this->swap(Vite::class, new Vite);

            $resposta = $this->actingAs($usuario)->getJson("/perfil/tema/{$tema->value}/fontes")
                ->assertOk()
                ->assertJsonPath('familias', $tema->fontes());
            $html = $resposta->json('html');

            foreach (TemaVisual::cases() as $outro) {
                foreach ($outro->fontes() as $familia => $pesos) {
                    $this->assertSame(
                        array_key_exists($familia, $tema->fontes()),
                        str_contains($html, "font-family: \"{$familia}\""),
                        "{$tema->value}: @font-face de {$familia}",
                    );
                }

                foreach ($outro->aliasesDasFontes() as $alias) {
                    $this->assertSame(
                        in_array($alias, $tema->aliasesDasFontes(), true),
                        str_contains($html, "assets/{$alias}-400.woff2"),
                        "{$tema->value}: preload de {$alias}",
                    );
                }
            }

            $this->assertStringNotContainsString('Familia Fora de Tema', $html);
        }
    }

    public function test_sem_manifesto_o_html_vem_vazio_e_nada_quebra(): void
    {
        $this->usarManifesto(false);

        $this->actingAs(User::factory()->create())->getJson('/perfil/tema/regua/fontes')
            ->assertOk()
            ->assertJsonPath('html', '')
            ->assertJsonPath('familias', TemaVisual::Regua->fontes());
    }

    public function test_tema_inexistente_da_404(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->getJson('/perfil/tema/nao-existe/fontes')->assertNotFound();
        $this->actingAs($usuario)->getJson('/perfil/tema/TALAO/fontes')->assertNotFound();
        $this->actingAs($usuario)->getJson('/perfil/tema/talao%00/fontes')->assertNotFound();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        $this->get('/perfil/tema/talao/fontes')->assertRedirect('/entrar');
    }

    public function test_conta_pendente_nao_recebe(): void
    {
        $usuario = User::factory()->pendente()->create();

        $this->actingAs($usuario)->get('/perfil/tema/talao/fontes')->assertRedirect('/entrar');
    }

    /**
     * Manifesto do Vite (versão 1) com as famílias de todos os temas mais uma que não é de tema nenhum.
     *
     * @return array<string, mixed>
     */
    private function manifestoDeTeste(): array
    {
        $familias = ['fora-de-tema' => 'Familia Fora de Tema'];

        foreach (TemaVisual::cases() as $tema) {
            $familias += array_combine($tema->aliasesDasFontes(), array_keys($tema->fontes()));
        }

        $manifesto = ['version' => 1, 'families' => [], 'preloads' => [], 'style' => ['file' => 'assets/fonts-teste.css', 'familyStyles' => [], 'variables' => []]];

        foreach ($familias as $alias => $familia) {
            $manifesto['families'][$alias] = ['family' => $familia, 'variable' => "--font-{$alias}", 'variants' => []];
            $manifesto['style']['familyStyles'][$alias] = "@font-face {\n  font-family: \"{$familia}\";\n}";
            $manifesto['style']['variables'][$alias] = "--font-{$alias}: \"{$familia}\";";
            $manifesto['preloads'][] = ['alias' => $alias, 'family' => $familia, 'weight' => 400, 'style' => 'normal', 'file' => "assets/{$alias}-400.woff2", 'as' => 'font', 'type' => 'font/woff2', 'crossorigin' => 'anonymous'];
        }

        return $manifesto;
    }
}
