<?php

namespace Tests\Feature\Temas;

use App\Enums\TemaVisual;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite as ViteFacade;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * Tarefa 3.3: a página só carrega (e pré-carrega) as fontes do tema ativo. O `@fonts` do layout recebe os aliases do
 * tema; alias que não está no manifesto do Vite derruba a página inteira (`ViteException`), por isso o enum e o
 * `vite.config.js` precisam andar juntos.
 */
class FontesDoTemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cada_tema_declara_fontes_e_arquivo_css(): void
    {
        foreach (TemaVisual::cases() as $tema) {
            $this->assertNotEmpty($tema->fontes(), "{$tema->value}: sem fontes");

            if ($tema !== TemaVisual::Talao) {
                $this->assertFileExists(resource_path("css/temas/{$tema->value}.css"), "{$tema->value}: falta resources/css/temas/{$tema->value}.css");
            }
        }
    }

    public function test_aliases_seguem_o_slug_que_o_plugin_do_vite_usa(): void
    {
        $this->assertSame(['archivo', 'archivo-narrow'], TemaVisual::Talao->aliasesDasFontes());
    }

    /**
     * Cada família e seus pesos do enum estão no `vite.config.js` (qualquer provedor: `bunny(`, `google(`...). Atalho:
     * lê o texto do arquivo e espera `weights` antes de qualquer `}` da família (ponha `weights` primeiro).
     */
    public function test_vite_config_declara_as_familias_e_os_pesos_de_cada_tema(): void
    {
        $config = file_get_contents(base_path('vite.config.js'));

        foreach (TemaVisual::cases() as $tema) {
            foreach ($tema->fontes() as $familia => $pesos) {
                $achou = preg_match('/\w+\(\s*\''.preg_quote($familia, '/').'\'\s*,\s*\{[^}]*weights:\s*\[([\d,\s]+)\]/', $config, $trecho);

                $this->assertSame(1, $achou, "{$tema->value}: o vite.config.js não declara a família {$familia} com weights");
                $this->assertSame($pesos, array_map('intval', explode(',', $trecho[1])), "{$tema->value}: pesos de {$familia} diferentes do vite.config.js");
            }
        }
    }

    public function test_fontes_do_tema_ativo_saem_no_html(): void
    {
        $pasta = sys_get_temp_dir().'/fontes-do-tema-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists("{$pasta}/build");
        $manifesto = $this->manifestoDeTeste();
        File::put("{$pasta}/build/fonts-manifest.json", json_encode($manifesto));
        // O CSS completo que o `@fonts` sem aliases leria: se o layout esquecer os aliases, a família de fora aparece.
        File::ensureDirectoryExists("{$pasta}/build/assets");
        File::put("{$pasta}/build/{$manifesto['style']['file']}", implode("\n", $manifesto['style']['familyStyles']));

        try {
            // O `@fonts` de verdade (lê o manifesto da pasta de teste); só o `@vite` continua falso, sem build.
            $this->app->usePublicPath($pasta);
            // Instância nova a cada pedido: o Vite lembra dos preloads já emitidos (sem Octane, o app sobe a cada pedido).
            $viteNovo = function () {
                ViteFacade::clearResolvedInstance();
                $this->swap(Vite::class, new class extends Vite
                {
                    public function __invoke($entrypoints, $buildDirectory = null)
                    {
                        return new HtmlString('');
                    }
                });
            };

            // Visitante: Talão.
            $viteNovo();
            $this->get('/entrar')->assertOk()->assertSee('font-family: "Archivo"', false)->assertDontSee('Familia Fora de Tema', false);

            foreach (TemaVisual::cases() as $tema) {
                $usuario = User::factory()->create();
                $usuario->forceFill(['tema' => $tema])->save();
                $viteNovo();
                $html = $this->actingAs($usuario)->get('/painel')->assertOk()->getContent();

                $this->assertStringStartsWith('<!DOCTYPE html>', $html);
                $this->assertStringContainsString("data-tema=\"{$tema->value}\"", $html);

                foreach (TemaVisual::cases() as $outro) {
                    foreach ($outro->fontes() as $familia => $pesos) {
                        $esperado = $outro === $tema;
                        $this->assertSame($esperado, str_contains($html, "font-family: \"{$familia}\""), "{$tema->value}: @font-face de {$familia} ".($esperado ? 'faltou' : 'não devia sair'));
                    }

                    foreach ($outro->aliasesDasFontes() as $alias) {
                        $this->assertSame($outro === $tema, str_contains($html, "assets/{$alias}-400.woff2"), "{$tema->value}: preload de {$alias}");
                    }
                }

                $this->assertStringNotContainsString('Familia Fora de Tema', $html);
            }
        } finally {
            File::deleteDirectory($pasta);
        }
    }

    /**
     * Manifesto do Vite (versão 1) com as famílias de todos os temas mais uma que não é de tema nenhum, para provar
     * que o filtro do `@fonts` deixa de fora o que não foi pedido.
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
