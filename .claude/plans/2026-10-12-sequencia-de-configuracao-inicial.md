# Sequência obrigatória de configuração inicial — plano de implementação (Fase 25)

> **Para quem for executar:** use `superpowers:subagent-driven-development` (recomendado) ou
> `superpowers:executing-plans`, tarefa por tarefa. Os passos usam checkbox (`- [ ]`) para acompanhamento.
> **Formato de status por tarefa** (regra do projeto): `pendente` → `concluída` (código pronto) → `testada` (suíte
> rodou e passou) → `validada` (usuário confirmou). Este arquivo é atualizado à medida que cada tarefa avança.

**Objetivo:** travar a navegação e a criação de registros novos (produtos/serviços, clientes, contratos, cobranças)
até que o usuário complete, nesta ordem, o bloco "perfil" (dados do prestador + 1 chave PIX + 1 modelo de mensagem)
e o bloco "catálogo" (1 produto/serviço + 1 cliente) — com um guia interativo (coach flutuante + holofote + modal de
boas-vindas) ensinando o caminho. Vale para contas novas e antigas.

**Arquitetura:** progresso sempre calculado na hora (nenhuma coluna nova, exceto `apresentacao_vista` para o modal),
numa classe só-leitura `App\Support\ProgressoDeConfiguracao`. Um middleware parametrizado (`progresso:perfil` /
`progresso:liberado`) trava só as rotas de **criar** registro novo nos 4 cadastros — nunca `index`/`edit`/`update`/
`destroy`. O front-end recebe o progresso como prop compartilhada do Inertia e usa 3 componentes novos (coach,
holofote, modal) montados sobre as telas existentes, sem framework de teste de componente Vue (convenção do
projeto): lógica pura vai para `.js` testável com `node --test`.

**Tech Stack:** Laravel 13 / PHP 8.4, Inertia v3 + Vue 3, PostgreSQL, PHPUnit (`php artisan test`), `node --test`
para os módulos `.js` puros, Pint para formatação.

**Spec:** `docs/superpowers/specs/2026-10-12-sequencia-de-configuracao-inicial-design.md` (aprovada pelo usuário).

## Global Constraints

- Nenhuma coluna nova para `perfil`/`catalogo`/`liberado` — sempre calculados a cada requisição. Única coluna nova:
  `users.apresentacao_vista` (`boolean`, padrão `false`).
- A trava no servidor é **só sobre criar** (`create`/`store`, e em contrato também `recomecar`/`renovar`/
  `renovar-vencido`) — **nunca** sobre `index`/`edit`/`update`/`destroy`/ações sobre registro existente, em nenhum
  dos 4 cadastros. Isso protege quem já usa o sistema hoje.
- Idioma pt-BR em mensagens, testes e comentários.
- PHP: chaves sempre, tipos explícitos, promoted constructor properties (regras do projeto/Boost).
- Depois de qualquer `.php` alterado: `vendor/bin/pint --dirty --format agent`.
- JS: lógica pura em módulo `.js` próprio, testável com `node --test resources/js/<arquivo>.test.js` — o projeto
  **não tem harness para `.vue`** (confirmado em `.claude/skills/recibos/SKILL.md`), então componentes `.vue` ficam
  finos (só wiring) e a lógica testável mora no `.js`.
- Migração só roda com autorização explícita do usuário (PORTÃO-BANCO: conexão, contagem, comando literal, "sim"
  por comando) — este plano cria o arquivo de migration; quem executa o plano pede autorização antes de
  `php artisan migrate`.
- `Route::resource(...)->only(['create','store'])` e `->except([...,'create','store'])` para o MESMO nome de
  recurso não colidem (nomes de rota gerados não se sobrepõem) — confirmado: nada no projeto usa `route('clientes.*')`
  nem equivalentes (todas as telas usam `href` literal), então dividir os `resource()` é seguro.

## Review Focus

1. **Usuário retroativo** (contratos/cobranças antigos, sem chave PIX): `/contratos`, `/cobrancas` (listas),
   `edit`/`update`/`pagar`/`cancelar`/`prorrogar` continuam abertos mesmo com o bloco incompleto — testado
   explicitamente na Tarefa 4 (`ProgressoDeConfiguracaoMiddlewareTest`).
2. **`recomecar`/`renovar`/`renovar-vencido` de contrato** também exigem `liberado` — criam contrato novo por
   baixo, fácil esquecer porque não se chamam `store`.
3. **Itens de menu nunca bloqueados:** Painel, Meu perfil, Relatórios, Auditoria, Usuários (admin) — mesmo com
   todo o progresso zerado.
4. **`mostrarApresentacao` nunca aparece para quem já está `liberado`**, mesmo que `apresentacao_vista` ainda seja
   `false` (conta antiga que nunca recebeu o modal mas já tem tudo cadastrado).
5. **Holofote em `/perfil` lê o campo vazio do `form` reativo**, não só o valor vindo do servidor — senão continua
   apontando para um campo que a pessoa já digitou na tela mas ainda não salvou.

---

## Tarefa 1 — `ProgressoDeConfiguracao`

**Status:** testada (commit 7712cb4)

**Arquivos:**
- Criar: `app/Support/ProgressoDeConfiguracao.php`
- Teste: `tests/Feature/ProgressoDeConfiguracaoTest.php`

**Interfaces:**
- Produz: `App\Support\ProgressoDeConfiguracao::de(User $usuario): array` com a forma:
  ```php
  array{
      perfil: bool, catalogo: bool, liberado: bool,
      pendencias: list<array{
          bloco: string, item: string, rotulo: string, feito: bool, link: string,
          seletor?: string,
          campos_faltando?: list<array{campo: string, rotulo: string, seletor: string}>,
      }>,
  }
  ```

- [x] **Passo 1: teste (falha).** Criar `tests/Feature/ProgressoDeConfiguracaoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\ModeloMensagem;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Support\ProgressoDeConfiguracao;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProgressoDeConfiguracaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_nada_cadastrado_nenhum_bloco_feito(): void
    {
        $usuario = User::factory()->semPerfil()->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertFalse($progresso['perfil']);
        $this->assertFalse($progresso['catalogo']);
        $this->assertFalse($progresso['liberado']);
        $this->assertFalse($progresso['pendencias'][0]['feito']);
        $this->assertCount(9, $progresso['pendencias'][0]['campos_faltando']);
    }

    public function test_perfil_completo_mas_sem_chave_pix_nem_modelo_bloco_perfil_incompleto(): void
    {
        $usuario = User::factory()->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertFalse($progresso['perfil']);
        $this->assertTrue($progresso['pendencias'][0]['feito']);
        $this->assertSame([], $progresso['pendencias'][0]['campos_faltando']);
        $this->assertFalse($progresso['pendencias'][1]['feito']);
        $this->assertFalse($progresso['pendencias'][2]['feito']);
    }

    public function test_bloco_perfil_completo_quando_tem_chave_pix_e_modelo(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create();
        ModeloMensagem::factory()->for($usuario)->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertTrue($progresso['perfil']);
        $this->assertFalse($progresso['catalogo']);
        $this->assertFalse($progresso['liberado']);
    }

    public function test_liberado_quando_perfil_e_catalogo_completos(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create();
        ModeloMensagem::factory()->for($usuario)->create();
        ProdutoServico::factory()->for($usuario)->create();
        Cliente::factory()->for($usuario)->create();

        $progresso = ProgressoDeConfiguracao::de($usuario);

        $this->assertTrue($progresso['catalogo']);
        $this->assertTrue($progresso['liberado']);
        foreach ($progresso['pendencias'] as $pendencia) {
            $this->assertTrue($pendencia['feito'], $pendencia['item']);
        }
    }

    public function test_campos_faltando_lista_so_os_campos_em_branco_do_prestador_na_ordem_certa(): void
    {
        $usuario = User::factory()->semPerfil()->create(['documento' => '52998224725', 'uf' => 'SE']);

        $campos = ProgressoDeConfiguracao::de($usuario)['pendencias'][0]['campos_faltando'];

        $this->assertSame(
            ['telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'cep'],
            array_column($campos, 'campo'),
        );
    }
}
```

- [x] **Passo 2: rodar e ver falhar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoTest`
Expected: FAIL — `Class "App\Support\ProgressoDeConfiguracao" not found`.

- [x] **Passo 3: implementar.** Criar `app/Support/ProgressoDeConfiguracao.php`:

```php
<?php

namespace App\Support;

use App\Models\User;

class ProgressoDeConfiguracao
{
    private const ROTULOS_DO_PRESTADOR = [
        'name' => 'Nome ou razão social',
        'documento' => 'CPF ou CNPJ',
        'telefone' => 'Telefone',
        'logradouro' => 'Logradouro',
        'numero' => 'Número',
        'bairro' => 'Bairro',
        'cidade' => 'Cidade',
        'uf' => 'UF',
        'cep' => 'CEP',
    ];

    /**
     * @return array{
     *     perfil: bool, catalogo: bool, liberado: bool,
     *     pendencias: list<array{
     *         bloco: string, item: string, rotulo: string, feito: bool, link: string,
     *         seletor?: string,
     *         campos_faltando?: list<array{campo: string, rotulo: string, seletor: string}>,
     *     }>,
     * }
     */
    public static function de(User $usuario): array
    {
        $dadosPrestadorFeito = $usuario->perfilCompleto();
        $temChavePix = $usuario->chavesPix()->count() >= 1;
        $temModeloMensagem = $usuario->modelosMensagem()->count() >= 1;
        $temProdutoServico = $usuario->produtosServicos()->count() >= 1;
        $temCliente = $usuario->clientes()->count() >= 1;

        $perfil = $dadosPrestadorFeito && $temChavePix && $temModeloMensagem;
        $catalogo = $temProdutoServico && $temCliente;

        return [
            'perfil' => $perfil,
            'catalogo' => $catalogo,
            'liberado' => $perfil && $catalogo,
            'pendencias' => [
                [
                    'bloco' => 'perfil', 'item' => 'dados_prestador', 'rotulo' => 'Completar os dados do prestador',
                    'feito' => $dadosPrestadorFeito, 'link' => '/perfil',
                    'campos_faltando' => self::camposFaltando($usuario),
                ],
                [
                    'bloco' => 'perfil', 'item' => 'chave_pix', 'rotulo' => 'Cadastrar uma chave PIX',
                    'feito' => $temChavePix, 'link' => '/perfil', 'seletor' => '#nova-chave-pix',
                ],
                [
                    'bloco' => 'perfil', 'item' => 'modelo_mensagem', 'rotulo' => 'Cadastrar um modelo de mensagem',
                    'feito' => $temModeloMensagem, 'link' => '/perfil', 'seletor' => '#novo-modelo-mensagem',
                ],
                [
                    'bloco' => 'catalogo', 'item' => 'produto_servico', 'rotulo' => 'Cadastrar um produto ou serviço',
                    'feito' => $temProdutoServico, 'link' => '/produtos-servicos', 'seletor' => '#novo-produto-servico',
                ],
                [
                    'bloco' => 'catalogo', 'item' => 'cliente', 'rotulo' => 'Cadastrar um cliente',
                    'feito' => $temCliente, 'link' => '/clientes', 'seletor' => '#novo-cliente',
                ],
            ],
        ];
    }

    /** @return list<array{campo: string, rotulo: string, seletor: string}> */
    private static function camposFaltando(User $usuario): array
    {
        $faltando = [];
        foreach (User::CAMPOS_DO_PRESTADOR as $campo) {
            if (blank($usuario->{$campo})) {
                $faltando[] = ['campo' => $campo, 'rotulo' => self::ROTULOS_DO_PRESTADOR[$campo], 'seletor' => "#{$campo}"];
            }
        }

        return $faltando;
    }
}
```

- [x] **Passo 4: rodar e ver passar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoTest`
Expected: PASS 5/5.

> **Ruling (achado na execução):** `test_nada_cadastrado_nenhum_bloco_feito` esperava 9 `campos_faltando`;
> a implementação (correta, bate com a spec) só encontrou 8, porque `semPerfil()` (factory já existente,
> Tarefa 1 não a modifica) nunca zera `name` — `name` é obrigatório no cadastro (`CadastroController.php:27`),
> então nenhuma conta real fica sem nome. Corrigido o teste para `assertCount(8, ...)`, não a implementação.

- [x] **Passo 5: Pint e commit.**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/ProgressoDeConfiguracao.php tests/Feature/ProgressoDeConfiguracaoTest.php
git commit -m "feat: calcula o progresso de configuração inicial (perfil/catálogo/liberado)"
```

---

## Tarefa 2 — Coluna `apresentacao_vista` e `POST /perfil/apresentacao`

**Status:** testada (commit 69e38f4) — ⚠️ migration ainda **não aplicada** no banco de dev/produção; precisa de
`php artisan migrate` com autorização explícita (PORTÃO-BANCO) antes do smoke test da Tarefa 10.

**Arquivos:**
- Criar: `database/migrations/2026_10_12_150000_add_apresentacao_vista_to_users_table.php`
- Modificar: `app/Models/User.php` (método `casts()`, por volta da linha 46-58)
- Modificar: `app/Http/Controllers/PerfilController.php` (novo método `apresentacao`)
- Modificar: `routes/web.php` (nova rota, depois da linha 38)
- Teste: `tests/Feature/PerfilTest.php`

**Interfaces:**
- Consome: nada de outras tarefas.
- Produz: `POST /perfil/apresentacao` (sem corpo) → marca `apresentacao_vista = true` no usuário logado, `back()`.
  Usado pela Tarefa 9 (`ModalBoasVindas.vue`).

- [x] **Passo 1: teste (falha).** Em `tests/Feature/PerfilTest.php`, adicionar:

```php
    public function test_marca_apresentacao_vista(): void
    {
        $usuario = User::factory()->create();
        $this->assertFalse($usuario->apresentacao_vista);

        $this->actingAs($usuario)->post('/perfil/apresentacao')->assertRedirect();

        $this->assertTrue($usuario->refresh()->apresentacao_vista);
    }
```

- [x] **Passo 2: rodar e ver falhar.**

Run: `php artisan test --filter=test_marca_apresentacao_vista`
Expected: FAIL — rota `/perfil/apresentacao` não existe (404) ou coluna não existe.

- [x] **Passo 3: migration.** Criar `database/migrations/2026_10_12_150000_add_apresentacao_vista_to_users_table.php`
  (mesmo padrão de `database/migrations/2026_10_12_103524_add_dias_aviso_contrato_to_users_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('apresentacao_vista')->default(false)->after('dias_aviso_contrato');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('apresentacao_vista');
        });
    }
};
```

  **PORTÃO-BANCO:** antes de `php artisan migrate`, mostrar ao usuário a conexão (`php artisan tinker --execute
  'echo config("database.connections.".config("database.default").".database");'`), dizer se é produção, e esperar
  "sim". Em ambiente de teste, `php artisan test` já roda as migrations na base de teste automaticamente — não
  precisa de autorização para isso (é a base de teste, não dev/produção).

- [x] **Passo 4: `User::casts()`.** Em `app/Models/User.php:46-58`, adicionar ao array retornado:

```php
            'apresentacao_vista' => 'boolean',
```

  (não adicionar `apresentacao_vista` ao `#[Fillable]` — é gravado só internamente, igual a `logo`.)

> **Ruling (achado na execução):** faltava no plano um passo extra — acrescentar `'apresentacao_vista' => false,`
> a `User::$attributes` (linhas 29-36), o array de defaults em PHP para o objeto recém-criado. Sem isso,
> `User::factory()->create()->apresentacao_vista` voltava `null` (não `false`) até reler do banco, porque o
> valor default só existe na migration/DB, nunca chega ao objeto em memória — exatamente o motivo pelo qual
> `dias_antecedencia`/`dias_aviso`/`dias_aviso_contrato` já estavam nesse array (comentário já existente na
> linha 28 do arquivo). Mesmo padrão aplicado a `apresentacao_vista`.

- [x] **Passo 5: rota.** Em `routes/web.php`, depois da linha `Route::get('/perfil/logo', ...)` (linha 38):

```php
    Route::post('/perfil/apresentacao', [PerfilController::class, 'apresentacao']);
```

- [x] **Passo 6: controller.** Em `app/Http/Controllers/PerfilController.php`, adicionar método (mesmo padrão de
  `logo()` para campo fora do Fillable: atribuição direta + `save()`):

```php
    public function apresentacao(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $usuario->apresentacao_vista = true;
        $usuario->save();

        return back();
    }
```

- [x] **Passo 7: rodar e ver passar.**

Run: `php artisan test --filter=PerfilTest`
Expected: PASS (todos os testes de `PerfilTest`, incluindo o novo).

- [x] **Passo 8: Pint e commit.**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_10_12_150000_add_apresentacao_vista_to_users_table.php app/Models/User.php app/Http/Controllers/PerfilController.php routes/web.php tests/Feature/PerfilTest.php
git commit -m "feat: coluna apresentacao_vista e rota para marcar o modal de boas-vindas como visto"
```

---

## Tarefa 3 — Middleware `progresso:perfil` em Produtos/Serviços e Clientes

**Status:** testada (commit 2ee75dd)

**Arquivos:**
- Criar: `app/Http/Middleware/GarantirProgressoDeConfiguracao.php`
- Modificar: `bootstrap/app.php` (alias `progresso`)
- Modificar: `routes/web.php` (linhas 51-53 e 64-67)
- Modificar: `database/factories/UserFactory.php` (novo estado `comPerfilPronto()`)
- Modificar: `tests/Feature/ClientesTest.php`, `tests/Feature/ProdutosServicosTest.php` (sites que agora exigem
  `progresso:perfil`)
- Criar: `tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php`

**Interfaces:**
- Consome: `App\Support\ProgressoDeConfiguracao::de()` (Tarefa 1).
- Produz: alias de middleware `progresso:<bloco>` (`<bloco>` = `perfil` ou `liberado`); estado de factory
  `User::factory()->comPerfilPronto()` (cria 1 `ChavePix` + 1 `ModeloMensagem` para o usuário — usado por esta e
  pela Tarefa 4).

- [x] **Passo 1: teste do middleware (falha).** Criar `tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProgressoDeConfiguracaoMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bloco_perfil_incompleto_impede_criar_produto_e_cliente(): void
    {
        $usuario = User::factory()->create(); // sem chave PIX nem modelo: perfil incompleto

        $this->actingAs($usuario)->get('/produtos-servicos/create')
            ->assertRedirect('/painel')
            ->assertSessionHas('erro', 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.');
        $this->post('/produtos-servicos', ['nome' => 'X', 'tipo' => 'servico', 'unidade' => 'un', 'preco' => '1,00'])
            ->assertRedirect('/painel');
        $this->assertSame(0, $usuario->produtosServicos()->count());

        $this->get('/clientes/create')->assertRedirect('/painel');
        $this->post('/clientes', ['nome' => 'Y', 'modelo' => 'webiss'])->assertRedirect('/painel');
        $this->assertSame(0, $usuario->clientes()->count());
    }

    public function test_bloco_perfil_completo_libera_criar_produto_e_cliente(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();

        $this->actingAs($usuario)->get('/produtos-servicos/create')->assertOk();
        $this->post('/produtos-servicos', ['nome' => 'X', 'tipo' => 'servico', 'unidade' => 'un', 'preco' => '1,00'])
            ->assertRedirect('/produtos-servicos');
        $this->get('/clientes/create')->assertOk();
        $this->post('/clientes', ['nome' => 'Y', 'modelo' => 'webiss'])->assertRedirect('/clientes');
    }

    /** Protege quem já usa o sistema: dados antigos continuam abertos mesmo com o progresso incompleto. */
    public function test_usuario_retroativo_continua_vendo_e_editando_o_que_ja_existe(): void
    {
        $usuario = User::factory()->create(); // perfil incompleto (sem chave/modelo), igual a uma conta antiga
        $cliente = Cliente::factory()->for($usuario)->create();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->get('/clientes')->assertOk();
        $this->get("/clientes/{$cliente->id}/edit")->assertOk();
        $this->put("/clientes/{$cliente->id}", ['nome' => 'Novo nome', 'modelo' => 'webiss'])->assertRedirect('/clientes');

        $this->get('/produtos-servicos')->assertOk();
        $this->get("/produtos-servicos/{$produto->id}/edit")->assertOk();

        $this->get('/contratos')->assertOk();
        $this->get("/contratos/{$contrato->id}/edit")->assertOk();

        $this->get('/cobrancas')->assertOk();
    }
}
```

- [x] **Passo 2: rodar e ver falhar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoMiddlewareTest`
Expected: FAIL — alias `progresso` não existe / `comPerfilPronto` não existe.

- [x] **Passo 3: estado de factory.** Em `database/factories/UserFactory.php`, adicionar `use App\Models\ChavePix;`
  e `use App\Models\ModeloMensagem;` no topo, e o método:

```php
    /** Bloco "perfil" completo (dados do prestador já vêm prontos da definition()): soma 1 chave PIX e 1 modelo. */
    public function comPerfilPronto(): static
    {
        return $this->afterCreating(function (User $usuario) {
            ChavePix::factory()->for($usuario)->create();
            ModeloMensagem::factory()->for($usuario)->create();
        });
    }
```

- [x] **Passo 4: middleware.** Criar `app/Http/Middleware/GarantirProgressoDeConfiguracao.php` (mesmo padrão de
  `GarantirContaAprovada`, mas parametrizado):

```php
<?php

namespace App\Http\Middleware;

use App\Support\ProgressoDeConfiguracao;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Trava só a criação de registro novo (produto, cliente, contrato, cobrança) até o bloco pedido estar completo. */
class GarantirProgressoDeConfiguracao
{
    private const MENSAGENS = [
        'perfil' => 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.',
        'liberado' => 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.',
    ];

    public function handle(Request $request, Closure $next, string $bloco): Response
    {
        $progresso = ProgressoDeConfiguracao::de($request->user());

        if ($progresso[$bloco]) {
            return $next($request);
        }

        // O bloco "liberado" cobre perfil + catálogo: quando o perfil é a causa real, a mensagem do perfil
        // orienta melhor do que a de catálogo (decisão do plano — a sequência pede perfil primeiro).
        $mensagem = ! $progresso['perfil'] ? self::MENSAGENS['perfil'] : self::MENSAGENS[$bloco];

        return redirect('/painel')->with('erro', $mensagem);
    }
}
```

- [x] **Passo 5: registrar o alias.** Em `bootstrap/app.php`, acrescentar o `use` e o alias:

```php
use App\Http\Middleware\GarantirProgressoDeConfiguracao;
```

  e, dentro de `$middleware->alias([...])`:

```php
            'progresso' => GarantirProgressoDeConfiguracao::class,
```

- [x] **Passo 6: dividir as rotas.** Em `routes/web.php`, trocar a linha 53 (clientes):

```php
    Route::resource('clientes', ClienteController::class)->except('show');
```

  por:

```php
    Route::resource('clientes', ClienteController::class)->except(['show', 'create', 'store']);
    Route::resource('clientes', ClienteController::class)->only(['create', 'store'])->middleware('progresso:perfil');
```

  e as linhas 66-67 (produtos-servicos):

```php
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->except('show')->parameters(['produtos-servicos' => 'produto']);
```

  por:

```php
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->except(['show', 'create', 'store'])->parameters(['produtos-servicos' => 'produto']);
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->only(['create', 'store'])->parameters(['produtos-servicos' => 'produto'])->middleware('progresso:perfil');
```

- [x] **Passo 7: rodar o teste novo e ver passar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoMiddlewareTest`
Expected: PASS 3/3.

- [x] **Passo 8: ajustar `tests/Feature/ClientesTest.php` e `tests/Feature/ProdutosServicosTest.php`** — os sites
  abaixo criam um usuário e, na mesma respiração, criam ou abrem o formulário de criação; sem chave PIX/modelo,
  passam a cair no redirect para `/painel`. Trocar `User::factory()->create(` por
  `User::factory()->comPerfilPronto()->create(` **só** nestas linhas (as demais do arquivo testam listagem, busca,
  edição ou exclusão — continuam sem a trava):

  | Arquivo | Linhas (`User::factory()->create(`) |
  |---|---|
  | `tests/Feature/ClientesTest.php` | 51, 62, 74, 86, 99, 110, 121, 134, 146, 178 |
  | `tests/Feature/ProdutosServicosTest.php` | 33, 46, 60 |

- [x] **Passo 9: rodar as duas suítes e ver passar.**

Run: `php artisan test --filter=ClientesTest`
Expected: PASS (todos).

Run: `php artisan test --filter=ProdutosServicosTest`
Expected: PASS (todos).

- [x] **Passo 10: Pint e commit.**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/GarantirProgressoDeConfiguracao.php bootstrap/app.php routes/web.php database/factories/UserFactory.php tests/Feature/ClientesTest.php tests/Feature/ProdutosServicosTest.php tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php
git commit -m "feat: trava de criação em produtos/serviços e clientes até o bloco perfil estar completo"
```

---

## Tarefa 4 — Middleware `progresso:liberado` em Contratos e Cobranças

**Status:** testada (commits 0bacb37, 8da8a29) — suíte completa 560/560

**Contexto importante:** `CobrancaController::store()` (linha 173-175) já tinha um guard próprio —
`if (! $usuario->perfilCompleto()) { return back()->with('erro', 'Complete seu perfil para emitir recibos.'); }`.
Com `progresso:liberado` na rota, esse guard nunca mais é alcançado (chegar ao `store()` já implica
`$usuario->perfilCompleto() === true`, porque `liberado` exige `perfil`, que exige `perfilCompleto()`): é código
morto e deve ser removido, não duplicado.

**Arquivos:**
- Modificar: `routes/web.php` (linhas 56, 59, 61-62 — contratos; 80, 84 — cobranças)
- Modificar: `app/Http/Controllers/CobrancaController.php` (remover guard morto, linhas 173-175)
- Modificar: `tests/Feature/Cobrancas/AcoesCobrancaTest.php` (teste que dependia do guard removido)
- Modificar: `tests/Feature/CadastroRapidoTest.php` (3 testes que abrem `/contratos/create` ou `/cobrancas/create`
  sem catálogo — ver Passo 9)
- Modificar: os demais arquivos de teste listados no Passo 10
- Modificar: `tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php` (2 novos testes)

**Interfaces:**
- Consome: `App\Support\ProgressoDeConfiguracao` (Tarefa 1), middleware e `comPerfilPronto()` (Tarefa 3).

- [x] **Passo 1: testes novos (falham).** Em `tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php`, adicionar:

```php
    public function test_bloco_liberado_incompleto_impede_criar_contrato_e_cobranca(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create(); // perfil ok, catálogo vazio
        $cliente = Cliente::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/contratos/create')
            ->assertRedirect('/painel')
            ->assertSessionHas('erro', 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.');
        $this->get('/cobrancas/create')->assertRedirect('/painel');

        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->post('/contratos', [
            'cliente_id' => $cliente->id, 'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '10,00', 'principal' => true]],
            'periodicidade' => 'mensal', 'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ])->assertRedirect('/painel');
        $this->assertSame(0, $usuario->contratos()->count());
    }

    public function test_bloco_liberado_completo_libera_criar_contrato_e_cobranca(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        ProdutoServico::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/contratos/create')->assertOk();
        $this->get('/cobrancas/create')->assertOk();
    }

    /** recomecar/renovar/renovar-vencido criam contrato novo por baixo — também exigem "liberado" (Review Focus 2). */
    public function test_bloco_liberado_incompleto_impede_recomecar_renovar_e_renovar_vencido(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario)->post('/contratos', [
            'cliente_id' => $cliente->id,
            'itens' => [['produto_servico_id' => $produto->id, 'quantidade' => '1', 'preco' => '10,00', 'principal' => true]],
            'periodicidade' => 'mensal', 'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ])->assertRedirect('/contratos');
        $contrato = $usuario->contratos()->sole();

        // Simula o progresso voltando a ficar incompleto depois do contrato já existir, pra provar que as 3
        // ações (que criam contrato novo por baixo) também passam pelo gate — não só o "store" original.
        $usuario->chavesPix()->delete();

        $this->post("/contratos/{$contrato->id}/recomecar", [])->assertRedirect('/painel');
        $this->post("/contratos/{$contrato->id}/renovar", [])->assertRedirect('/painel');
        $this->post("/contratos/{$contrato->id}/renovar-vencido", [])->assertRedirect('/painel');
    }
```

- [x] **Passo 2: rodar e ver falhar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoMiddlewareTest`
Expected: FAIL — `/contratos/create` e `/cobrancas/create` ainda abrem sem a trava.

- [x] **Passo 3: dividir e travar as rotas de contrato.** Em `routes/web.php`, trocar a linha 56:

```php
    Route::resource('contratos', ContratoController::class)->except(['show', 'destroy']);
```

  por:

```php
    Route::resource('contratos', ContratoController::class)->except(['show', 'destroy', 'create', 'store']);
    Route::resource('contratos', ContratoController::class)->only(['create', 'store'])->middleware('progresso:liberado');
```

  e as linhas 59, 61, 62, acrescentando `->middleware('progresso:liberado')`:

```php
    Route::post('/contratos/{contrato}/recomecar', [ContratoController::class, 'recomecar'])->middleware('progresso:liberado');
    ...
    Route::post('/contratos/{contrato}/renovar', [ContratoController::class, 'renovar'])->middleware('progresso:liberado');
    Route::post('/contratos/{contrato}/renovar-vencido', [ContratoController::class, 'renovarVencido'])->middleware('progresso:liberado');
```

- [x] **Passo 4: travar as rotas de cobrança.** Nas linhas 80 e 84, acrescentar o middleware:

```php
    Route::get('/cobrancas/create', [CobrancaController::class, 'create'])->middleware('progresso:liberado');
    ...
    Route::post('/cobrancas', [CobrancaController::class, 'store'])->middleware('progresso:liberado');
```

- [x] **Passo 5: remover o guard morto.** Em `app/Http/Controllers/CobrancaController.php:173-176`, remover só o
  `if` (a linha 171, `$usuario = $request->user();`, continua — é usada em `DB::transaction(... use ($usuario, ...))`
  nas linhas seguintes):

```php
        if (! $usuario->perfilCompleto()) {
            return back()->with('erro', 'Complete seu perfil para emitir recibos.');
        }

```

  O método passa a ser:

```php
    public function store(CobrancaRequest $request, EmissorDeRecibos $emissor): RedirectResponse
    {
        $usuario = $request->user();

        DB::transaction(function () use ($usuario, $request, $emissor) {
            $cobranca = $usuario->cobrancas()->create($request->dadosCobranca());
            $cobranca->itens()->createMany($request->itensDaCobranca());
            $emissor->emitir($cobranca);
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança criada com o recibo.');
    }
```

- [x] **Passo 6: ajustar o teste do guard removido.** Em `tests/Feature/Cobrancas/AcoesCobrancaTest.php:64-74`,
  trocar o teste `test_avulsa_exige_perfil_completo_e_cliente_proprio` (que cobria as duas coisas) por um só sobre
  isolamento de cliente — a parte do perfil incompleto já está coberta pelo novo
  `test_bloco_liberado_incompleto_impede_criar_contrato_e_cobranca` (Passo 1 acima), com uma mensagem diferente
  (a do gate, não a do controller):

```php
    public function test_avulsa_recusa_cliente_de_outro_usuario(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $this->actingAs($usuario);

        $this->post('/cobrancas', $this->dados(Cliente::factory()->create()))
            ->assertSessionHasErrors(['cliente_id' => 'O valor escolhido para cliente é inválido.']);
        $this->assertSame(0, Cobranca::count());
    }
```

  **E** trocar `prestadorComCliente()` (linha 24-30) para devolver um usuário já com o bloco perfil pronto (o
  catálogo já fecha sozinho: `dados()` cria um produto do próprio usuário via `itensPara()`):

```php
    private function prestadorComCliente(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->comPerfilPronto()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }
```

- [x] **Passo 7: rodar os dois arquivos e ver passar.**

Run: `php artisan test --filter=ProgressoDeConfiguracaoMiddlewareTest`
Expected: PASS 6/6 (3 da Tarefa 3 + 3 desta tarefa).

Run: `php artisan test --filter=AcoesCobrancaTest`
Expected: PASS (todos, inclusive `test_avulsa_nasce_com_recibo` que também passa por `prestadorComCliente()`).

- [x] **Passo 8: Pint e commit parcial.**

```bash
vendor/bin/pint --dirty --format agent
git add routes/web.php app/Http/Controllers/CobrancaController.php tests/Feature/Cobrancas/AcoesCobrancaTest.php tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php
git commit -m "feat: trava de criação em contratos e cobranças até perfil e catálogo estarem completos"
```

- [x] **Passo 9: `tests/Feature/CadastroRapidoTest.php`.** Este arquivo usa `->sole()` em várias asserções
  (`produtosServicos()->sole()`, `clientes()->sole()`, `chavesPix()->sole()`) — **não** dar perfil/catálogo prontos
  no `setUp()` global, porque `sole()` quebra (`MultipleRecordsFoundException`) se o usuário já tiver um
  registro daquele tipo antes do teste criar "o" registro que a asserção espera ser único. A correção é por
  teste, só nos que de fato passam pela trava:

  1. Adicionar os imports que faltam no topo do arquivo: `use App\Models\ChavePix;` e
     `use App\Models\ModeloMensagem;` (`Cliente` e `ProdutoServico` já estão importados).
  2. Remover `test_formulario_abre_mesmo_sem_cliente_cadastrado` (linhas 98-103) — testava exatamente o
     comportamento que esta fase revoga de propósito (abrir `/contratos/create`/`/cobrancas/create` sem nenhum
     cliente). Já está coberto por `test_bloco_liberado_incompleto_impede_criar_contrato_e_cobranca` (Passo 1).
  3. Nestas 3 (`POST /produtos-servicos` ou `POST /clientes`, gate `progresso:perfil`), acrescentar no início do
     corpo do teste (**não** cria cliente/produto — só o que falta para o bloco perfil, pra não colidir com os
     `sole()` de produto/cliente que cada teste já faz):

```php
        ChavePix::factory()->for($this->usuario)->create();
        ModeloMensagem::factory()->for($this->usuario)->create();
```

     — em `test_produto_novo_volta_no_formato_da_autosugestao` (linha 30), `test_produto_com_dados_invalidos_responde_422_com_os_campos`
     (linha 39), `test_cliente_novo_volta_no_formato_da_autosugestao` (linha 48) e
     `test_formulario_tradicional_continua_redirecionando` (linha 68).
  4. Nestas 2 (`GET /contratos/create` ou `GET /cobrancas/create`, gate `progresso:liberado`), acrescentar as
     mesmas duas linhas **mais** cliente e produto:

```php
        ChavePix::factory()->for($this->usuario)->create();
        ModeloMensagem::factory()->for($this->usuario)->create();
        Cliente::factory()->for($this->usuario)->create();
        ProdutoServico::factory()->for($this->usuario)->create();
```

     — em `test_formularios_de_lancamento_trazem_as_opcoes_do_cadastro_rapido` (linha 80, depois do `forceFill`) e
     `test_tipos_de_chave_pix_chegam_a_toda_tela_com_forma_de_pagamento` (linha 92).
  5. `test_chave_pix_nova_volta_no_formato_das_opcoes` e `test_busca_vazia_mostra_os_primeiros_ao_focar` não tocam
     rota travada — **não mudar**.

  Rodar: `php artisan test --filter=CadastroRapidoTest` → PASS (7 testes — o removido no item 2 não conta mais).

- [x] **Passo 10: repertório restante — rodar a suíte inteira, ler as falhas, corrigir pelo mesmo padrão.**
  Os arquivos abaixo criam contrato(s) ou cobrança(s) avulsa(s) via HTTP (`POST /contratos`, `POST /cobrancas`,
  `GET .../create`, `.../recomecar`, `.../renovar`, `.../renovar-vencido`) com um usuário que, hoje, nunca tem
  chave PIX nem modelo de mensagem — vão quebrar com o redirect para `/painel`:

  `ContratosTest.php`, `DatasComAnoInvalidoTest.php`, `CorrecaoDoInicioTest.php`, `EncerramentoContratoTest.php`,
  `Cobrancas/TelasCobrancaTest.php`, `FormaPagamentoTest.php`, `ItensDoContratoTest.php`, `ReativarContratoTest.php`,
  `RenovarContratoTest.php`, `Cobrancas/ItensDaCobrancaTest.php`, `AuditoriaTest.php`.

  Rodar `php artisan test --compact` e, para cada falha cujo resultado é um redirect para `/painel` em vez do
  esperado:
  1. Achar onde o usuário "ator" do teste é criado (`User::factory()->create(...)`, geralmente em `setUp()` ou num
     helper como `usuarioComCliente()`) e acrescentar `->comPerfilPronto()` (ex.: `User::factory()->comPerfilPronto()->create(...)`).
     **Não** aplicar a um usuário usado só como `$outro`/`$alheio`/`$alheia` (dono de um registro de outra pessoa,
     nunca quem faz a requisição travada).
  2. Rodar de novo. Se ainda redirecionar para `/painel` com a mensagem de catálogo ("Cadastre pelo menos..."), o
     ator não tem produto/serviço e/ou cliente próprio ainda nesse ponto do teste — acrescentar, antes da
     requisição travada, `ProdutoServico::factory()->for($usuario)->create();` e/ou confirmar que já existe um
     `Cliente::factory()->for($usuario)->create()` (a maioria já cria um cliente para o contrato/cobrança referenciar;
     o que falta é o produto, porque alguns criam o item só via o array `itens` sem passar por `ComItens::itensPara()`).
  3. **Cuidado com `->sole()`/`->firstOrFail()`** sobre `chavesPix()`, `modelosMensagem()`, `clientes()` ou
     `produtosServicos()` mais adiante no mesmo teste (achado na Tarefa 4, Passo 9, em `CadastroRapidoTest.php`):
     se o teste cria esse registro só pra provar que "o" registro criado pela requisição é aquele, usar
     `->comPerfilPronto()`/`ProdutoServico::factory()->for($usuario)->create()`/etc. **se setUp() for compartilhado
     por outros testes do arquivo**, aplicar só dentro do teste que precisa, nunca em `setUp()`, se algum outro
     teste do mesmo arquivo usa `sole()`/`firstOrFail()` nessa mesma tabela.
  4. Repetir até a suíte ficar verde. Nenhuma correção deve enfraquecer a trava (nunca remover o middleware da rota
     para "destravar" um teste) — a correção é sempre no fixture do teste.

- [x] **Passo 11: suíte PHP completa.**

Run: `php artisan test --compact`
Expected: PASS (todos — mesmo total de antes da Tarefa 3, menos o teste removido no Passo 9, mais os testes novos
das Tarefas 1, 2, 3 e 4).

- [x] **Passo 12: Pint e commit.**

```bash
vendor/bin/pint --dirty --format agent
git add -A -- tests/
git commit -m "fix: ajusta fixtures de teste para o novo gate de progresso de configuração"
```

> **Rulings (achados na execução da Tarefa 4):**
> 1. **Passo 1** — `test_bloco_liberado_incompleto_impede_criar_contrato_e_cobranca` criava um `ProdutoServico`
>    real do próprio usuário bem antes do `POST /contratos`; como o `$cliente` já existia, isso completava o
>    catálogo e o POST passava a ser legitimamente permitido (não bloqueado, como o teste esperava). Corrigido
>    removendo a criação do produto — o middleware roda antes de qualquer validação do corpo, então o payload
>    nem precisa citar um produto real para provar o bloqueio.
> 2. **Passo 6** — `prestadorComCliente()` dá `comPerfilPronto()`, mas o teste
>    `test_avulsa_recusa_cliente_de_outro_usuario` posta com um cliente de **outro** usuário; `dados()` cria o
>    produto do **dono desse cliente**, não do `$usuario` que faz a requisição. Sem um produto próprio, o
>    catálogo do `$usuario` ficava incompleto e o gate bloqueava antes da validação de "cliente inválido" que o
>    teste queria provar. Corrigido com `ProdutoServico::factory()->for($usuario)->create();` explícito no teste.
> 3. **Passo 9** — `CadastroRapidoTest` ficou com **8** testes (não 7): o arquivo tinha 9 antes, 1 foi removido.
>    Contagem do plano estava errada por 1; sem efeito prático.
> 4. **Passo 10** — a suíte completa revelou **2 arquivos a mais**, não citados na lista do plano:
>    `IdsNaoNumericosTest.php` e `RenovarContratoVencidoTest.php`. Corrigidos pelo mesmo padrão.
> 5. **Padrão recorrente no Passo 10** — todo teste `*_de_outro_usuario_da_404` que aciona uma rota travada
>    (`recomecar`/`renovar`/`renovar-vencido`) com um usuário "outro" sem perfil/catálogo próprios quebra: o
>    gate de progresso intercepta **antes** da checagem de dono do contrato (que é o 404 que o teste quer
>    provar), e devolve redirect para `/painel` em vez de 404. Achado em `EncerramentoContratoTest`,
>    `RenovarContratoTest` e `RenovarContratoVencidoTest` — corrigido dando `comPerfilPronto()` + 1 cliente + 1
>    produto ao usuário "outro" em cada um desses testes específicos (nunca ao usuário "ator" principal do
>    cenário, que já tinha o necessário).

---

## Tarefa 5 — Prop compartilhada `progresso` e `mostrarApresentacao`

**Status:** pendente

**Arquivos:**
- Modificar: `app/Http/Middleware/HandleInertiaRequests.php`
- Teste: `tests/Feature/PerfilTest.php` (ou um teste novo simples via `/painel`)

**Interfaces:**
- Consome: `App\Support\ProgressoDeConfiguracao` (Tarefa 1), `apresentacao_vista` (Tarefa 2).
- Produz: props Inertia `progresso` (a forma da Tarefa 1) e `mostrarApresentacao: bool`, disponíveis em toda tela.

- [ ] **Passo 1: teste (falha).** Em `tests/Feature/PerfilTest.php`, adicionar:

```php
    public function test_progresso_e_mostrar_apresentacao_vem_compartilhados(): void
    {
        $usuario = User::factory()->create(); // perfil incompleto, apresentacao_vista = false

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn ($pagina) => $pagina
                ->where('progresso.perfil', false)
                ->where('progresso.liberado', false)
                ->where('mostrarApresentacao', true));

        $usuario->apresentacao_vista = true;
        $usuario->save();
        $this->get('/painel')->assertInertia(fn ($pagina) => $pagina->where('mostrarApresentacao', false));
    }

    public function test_mostrar_apresentacao_nunca_aparece_para_quem_ja_esta_liberado(): void
    {
        $usuario = User::factory()->comPerfilPronto()->create();
        \App\Models\Cliente::factory()->for($usuario)->create();
        \App\Models\ProdutoServico::factory()->for($usuario)->create();
        // apresentacao_vista continua false (nunca viu o modal), mas já está liberado.

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn ($pagina) => $pagina->where('mostrarApresentacao', false));
    }
```

- [ ] **Passo 2: rodar e ver falhar.**

Run: `php artisan test --filter=PerfilTest`
Expected: FAIL — `progresso`/`mostrarApresentacao` ausentes das props.

- [ ] **Passo 3: implementar.** Em `app/Http/Middleware/HandleInertiaRequests.php`, adicionar `use
  App\Support\ProgressoDeConfiguracao;` e, dentro do array de `share()` (depois de `'flash' => [...]`):

```php
            'progresso' => fn () => $request->user() ? ProgressoDeConfiguracao::de($request->user()) : null,
            'mostrarApresentacao' => function () use ($request) {
                $usuario = $request->user();
                if (! $usuario || $usuario->apresentacao_vista) {
                    return false;
                }

                return ! ProgressoDeConfiguracao::de($usuario)['liberado'];
            },
```

- [ ] **Passo 4: rodar e ver passar.**

Run: `php artisan test --filter=PerfilTest`
Expected: PASS (todos).

- [ ] **Passo 5: Pint e commit.**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/PerfilTest.php
git commit -m "feat: compartilha o progresso de configuração e o aviso do modal de boas-vindas em toda tela"
```

---

## Tarefa 6 — Menu com itens bloqueados (`CamadaApp.vue`)

**Status:** pendente

**Arquivos:**
- Criar: `resources/js/progresso.js`, `resources/js/progresso.test.js`
- Modificar: `resources/js/Layouts/CamadaApp.vue`

**Interfaces:**
- Consome: prop `progresso` (Tarefa 5), disponível em `page.props.progresso` via `usePage()`.
- Produz: `itemBloqueado(href: string, progresso: object|null): boolean` — usado pela Tarefa 6 e reaproveitável
  pelas Tarefas 7/8/9 para decidir quando mostrar coach/holofote/modal.

- [ ] **Passo 1: teste (falha).** Criar `resources/js/progresso.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { itemBloqueado } from './progresso.js';

const progresso = { perfil: false, catalogo: false, liberado: false, pendencias: [] };

test('clientes e produtos bloqueiam só quando o bloco perfil está incompleto', () => {
    assert.equal(itemBloqueado('/clientes', progresso), true);
    assert.equal(itemBloqueado('/produtos-servicos', progresso), true);
    assert.equal(itemBloqueado('/clientes', { ...progresso, perfil: true }), false);
});

test('contratos e cobranças bloqueiam só quando o bloco liberado está incompleto', () => {
    assert.equal(itemBloqueado('/contratos', progresso), true);
    assert.equal(itemBloqueado('/cobrancas', { ...progresso, perfil: true, catalogo: true, liberado: true }), false);
});

test('painel, perfil, relatórios, auditoria e admin nunca bloqueiam', () => {
    for (const href of ['/painel', '/perfil', '/relatorios', '/auditoria', '/admin/usuarios']) {
        assert.equal(itemBloqueado(href, progresso), false);
    }
});

test('sem usuário logado (progresso nulo) nada bloqueia — a tela de login não usa isso', () => {
    assert.equal(itemBloqueado('/clientes', null), false);
});
```

- [ ] **Passo 2: rodar e ver falhar.**

Run: `node --test resources/js/progresso.test.js`
Expected: FAIL — `progresso.js` não existe.

- [ ] **Passo 3: implementar.** Criar `resources/js/progresso.js`:

```js
const BLOCOS_POR_HREF = {
    '/clientes': 'perfil',
    '/produtos-servicos': 'perfil',
    '/contratos': 'liberado',
    '/cobrancas': 'liberado',
};

/** Só os 4 itens de cadastro bloqueiam, e só pelo bloco que a Fase 25 exige para cada um. */
export function itemBloqueado(href, progresso) {
    if (!progresso) {
        return false;
    }
    const bloco = BLOCOS_POR_HREF[href];

    return bloco ? !progresso[bloco] : false;
}
```

- [ ] **Passo 4: rodar e ver passar.**

Run: `node --test resources/js/progresso.test.js`
Expected: PASS 4/4.

- [ ] **Passo 5: wiring em `CamadaApp.vue`.** Em `resources/js/Layouts/CamadaApp.vue`, importar e usar:

```js
import { itemBloqueado } from '../progresso.js';
```

  No `computed(() => [...])` dos itens (linha 10-22), acrescentar `bloqueado` a cada item:

```js
const itens = computed(() => [
    { href: '/painel', rotulo: 'Painel' },
    { href: '/cobrancas', rotulo: 'Cobranças', bloqueado: itemBloqueado('/cobrancas', page.props.progresso) },
    { href: '/contratos', rotulo: 'Contratos', bloqueado: itemBloqueado('/contratos', page.props.progresso) },
    { href: '/clientes', rotulo: 'Clientes', bloqueado: itemBloqueado('/clientes', page.props.progresso) },
    { href: '/produtos-servicos', rotulo: 'Produtos e serviços', bloqueado: itemBloqueado('/produtos-servicos', page.props.progresso) },
    { href: '/relatorios', rotulo: 'Relatórios' },
    { href: '/auditoria', rotulo: 'Auditoria' },
    { href: '/perfil', rotulo: 'Meu perfil' },
    ...(usuario.value?.is_admin
        ? [{ href: '/admin/usuarios', rotulo: 'Usuários', contador: page.props.pendentes }]
        : []),
]);
```

  No template (linha 50-65), trocar o `<li>` para desenhar `<span>` quando bloqueado:

```html
                <li v-for="item in itens" :key="item.href">
                    <span
                        v-if="item.bloqueado"
                        class="flex items-center justify-between px-5 py-2 text-sm text-grafite/50"
                        :title="item.href === '/contratos' || item.href === '/cobrancas'
                            ? 'Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos ou cobranças.'
                            : 'Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem) antes de cadastrar produtos, serviços ou clientes.'"
                    >
                        🔒 {{ item.rotulo }}
                    </span>
                    <Link
                        v-else
                        :href="item.href"
                        class="flex items-center justify-between px-5 py-2 text-sm hover:bg-papel"
                        :class="ativo(item.href) ? 'bg-papel font-semibold text-carbono' : ''"
                        :aria-current="ativo(item.href) ? 'page' : undefined"
                        @click="menuAberto = false"
                    >
                        {{ item.rotulo }}
                        <span
                            v-if="item.contador"
                            class="rounded-full bg-tinta-vermelha px-2 text-xs font-semibold text-white"
                            :aria-label="`${item.contador} cadastros aguardando liberação`"
                        >{{ item.contador }}</span>
                    </Link>
                </li>
```

- [ ] **Passo 6: build e smoke visual rápido.**

Run: `npm run build`
Expected: build sem erro.

- [ ] **Passo 7: commit.**

```bash
git add resources/js/progresso.js resources/js/progresso.test.js resources/js/Layouts/CamadaApp.vue
git commit -m "feat: itens de Contratos/Cobranças/Clientes/Produtos no menu ficam inertes até o progresso liberar"
```

---

## Tarefa 7 — IDs que faltam + `CoachDeConfiguracao.vue`

**Status:** pendente

**Arquivos:**
- Modificar: `resources/js/Components/ChavesPix.vue:98`, `resources/js/Components/MensagensWhatsapp.vue:177`,
  `resources/js/Pages/ProdutosServicos/Index.vue:35`, `resources/js/Pages/Clientes/Index.vue:39`
- Criar: `resources/js/Components/CoachDeConfiguracao.vue`
- Modificar: `resources/js/Pages/Perfil/Editar.vue`, `resources/js/Pages/ProdutosServicos/Index.vue`,
  `resources/js/Pages/Clientes/Index.vue`, `resources/js/Pages/Painel.vue`

**Interfaces:**
- Consome: prop `progresso` (Tarefa 5).
- Produz: `<CoachDeConfiguracao :progresso="page.props.progresso" />` — sem props adicionais, lê tudo de
  `progresso.pendencias`.

- [ ] **Passo 1: IDs.** Quatro trocas de uma linha só (o seletor já está certo na Tarefa 1, só falta o elemento ter
  o `id`):

  `resources/js/Components/ChavesPix.vue:98`:
  ```diff
  -            <button type="button" class="botao-secundario" @click="abrir()">Nova chave PIX</button>
  +            <button id="nova-chave-pix" type="button" class="botao-secundario" @click="abrir()">Nova chave PIX</button>
  ```

  `resources/js/Components/MensagensWhatsapp.vue:177`:
  ```diff
  -            <button type="button" class="botao-secundario" @click="abrir()">Novo modelo</button>
  +            <button id="novo-modelo-mensagem" type="button" class="botao-secundario" @click="abrir()">Novo modelo</button>
  ```

  `resources/js/Pages/ProdutosServicos/Index.vue:35`:
  ```diff
  -            <Link href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
  +            <Link id="novo-produto-servico" href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
  ```

  `resources/js/Pages/Clientes/Index.vue:39`:
  ```diff
  -            <Link href="/clientes/create" class="botao">Novo cliente</Link>
  +            <Link id="novo-cliente" href="/clientes/create" class="botao">Novo cliente</Link>
  ```

- [ ] **Passo 2: `CoachDeConfiguracao.vue`.** Criar `resources/js/Components/CoachDeConfiguracao.vue`:

```vue
<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    progresso: { type: Object, required: true },
});

const proxima = computed(() => props.progresso.pendencias.find((pendencia) => !pendencia.feito));
</script>

<template>
    <aside
        v-if="!progresso.liberado"
        class="fixed inset-x-4 top-16 z-40 border border-linha bg-white p-4 shadow-lg sm:inset-x-auto sm:right-4 sm:top-auto sm:bottom-4 sm:w-72"
        aria-label="Primeiros passos"
    >
        <p class="mb-2 text-sm font-semibold">Primeiros passos</p>
        <ul class="flex flex-col gap-1 text-sm">
            <li v-for="pendencia in progresso.pendencias" :key="pendencia.item">
                <Link
                    v-if="!pendencia.feito"
                    :href="pendencia.link"
                    :class="pendencia === proxima ? 'font-semibold text-carbono' : 'text-grafite/70'"
                >
                    → {{ pendencia.rotulo }}
                </Link>
                <span v-else class="text-tinta-verde">✓ {{ pendencia.rotulo }}</span>
            </li>
        </ul>
    </aside>
</template>
```

- [ ] **Passo 3: montar nas 4 telas.** Em `resources/js/Pages/Perfil/Editar.vue`,
  `resources/js/Pages/ProdutosServicos/Index.vue`, `resources/js/Pages/Clientes/Index.vue` e
  `resources/js/Pages/Painel.vue`: importar `CoachDeConfiguracao` e `usePage` do `@inertiajs/vue3`, e adicionar no
  template, dentro de `<CamadaApp>`:

```js
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CoachDeConfiguracao from '../../Components/CoachDeConfiguracao.vue'; // ajustar caminho relativo em Painel.vue: '../Components/...'

// computed(), não uma const direta: page.props.progresso é substituído a cada visita do Inertia (ex.: depois de
// salvar o perfil), e só um computed continua reativo a essa troca — uma const simples ficaria com o valor do
// primeiro carregamento da tela.
const progresso = computed(() => usePage().props.progresso);
```

```html
        <CoachDeConfiguracao v-if="progresso" :progresso="progresso" />
```

- [ ] **Passo 4: build.**

Run: `npm run build`
Expected: build sem erro.

- [ ] **Passo 5: commit.**

```bash
git add resources/js/Components/ChavesPix.vue resources/js/Components/MensagensWhatsapp.vue resources/js/Pages/ProdutosServicos/Index.vue resources/js/Pages/Clientes/Index.vue resources/js/Components/CoachDeConfiguracao.vue resources/js/Pages/Perfil/Editar.vue resources/js/Pages/Painel.vue
git commit -m "feat: coach flutuante com a lista de pendências da configuração inicial"
```

---

## Tarefa 8 — `HolofoteDePendencia.vue`

**Status:** pendente

**Arquivos:**
- Criar: `resources/js/progressoHolofote.js`, `resources/js/progressoHolofote.test.js`
- Criar: `resources/js/Components/HolofoteDePendencia.vue`
- Modificar: `resources/js/Pages/Perfil/Editar.vue`, `resources/js/Pages/ProdutosServicos/Index.vue`,
  `resources/js/Pages/Clientes/Index.vue`

**Interfaces:**
- Consome: `progresso.pendencias` (Tarefa 1/5).
- Produz: `camposFaltandoNoForm(camposFaltando: Array<{campo,rotulo,seletor}>, form: object): Array<...>` — filtra
  de novo contra o `form` reativo (não o valor vindo do servidor), para acompanhar o que a pessoa já digitou antes
  de salvar (Review Focus item 5).

- [ ] **Passo 1: teste (falha).** Criar `resources/js/progressoHolofote.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { camposFaltandoNoForm } from './progressoHolofote.js';

const campos = [
    { campo: 'telefone', rotulo: 'Telefone', seletor: '#telefone' },
    { campo: 'cep', rotulo: 'CEP', seletor: '#cep' },
];

test('filtra de novo contra o form: o que a pessoa já digitou sai da lista mesmo sem ter salvo', () => {
    const resultado = camposFaltandoNoForm(campos, { telefone: '(79) 99999-0000', cep: '' });
    assert.deepEqual(resultado, [{ campo: 'cep', rotulo: 'CEP', seletor: '#cep' }]);
});

test('lista vazia quando o form já preencheu tudo', () => {
    assert.deepEqual(camposFaltandoNoForm(campos, { telefone: 'x', cep: 'y' }), []);
});
```

- [ ] **Passo 2: rodar e ver falhar.**

Run: `node --test resources/js/progressoHolofote.test.js`
Expected: FAIL — módulo não existe.

- [ ] **Passo 3: implementar.** Criar `resources/js/progressoHolofote.js`:

```js
/** Re-filtra contra o form reativo: o que a pessoa já digitou (ainda não salvo) sai da lista do holofote. */
export function camposFaltandoNoForm(camposFaltando, form) {
    return camposFaltando.filter((campo) => !String(form[campo.campo] ?? '').trim());
}
```

- [ ] **Passo 4: rodar e ver passar.**

Run: `node --test resources/js/progressoHolofote.test.js`
Expected: PASS 2/2.

- [ ] **Passo 5: `HolofoteDePendencia.vue`.** Criar `resources/js/Components/HolofoteDePendencia.vue`:

```vue
<script setup>
import { computed, ref } from 'vue';
import { camposFaltandoNoForm } from '../progressoHolofote.js';

const props = defineProps({
    pendencia: { type: Object, required: true }, // a pendência "dados_prestador" (tem campos_faltando) ou outra (tem seletor)
    form: { type: Object, default: null }, // só para dados_prestador — re-filtra contra o que já foi digitado
});

const pulado = ref(false);
const indice = ref(0); // posição em `campos` — um ref próprio, nunca mutar o array do computed diretamente

// Recalcula a cada mudança no form: o que a pessoa já digitou sai da lista sem precisar salvar.
const campos = computed(() => (props.form ? camposFaltandoNoForm(props.pendencia.campos_faltando ?? [], props.form) : []));
const atual = computed(() => campos.value[Math.min(indice.value, campos.value.length - 1)] ?? null);
const seletorAtual = computed(() => atual.value?.seletor ?? props.pendencia.seletor);
const textoAtual = computed(() => (atual.value
    ? `Passo ${indice.value + 1} de ${campos.value.length} — ${atual.value.rotulo}`
    : props.pendencia.rotulo));
const temHolofote = computed(() => !pulado.value && (props.form ? campos.value.length > 0 : true));

function proximoCampo() {
    indice.value = Math.min(indice.value + 1, campos.value.length - 1);
}
</script>

<template>
    <div v-if="temHolofote && seletorAtual" class="fixed inset-x-4 bottom-20 z-30 border border-carbono bg-white p-3 text-sm shadow-lg sm:max-w-xs" role="status">
        <p>{{ textoAtual }}</p>
        <div class="mt-2 flex gap-3">
            <button v-if="form && campos.length > 1" type="button" class="font-semibold underline" @click="proximoCampo">Próximo campo</button>
            <button type="button" class="text-grafite/70 underline" @click="pulado = true">Pular</button>
        </div>
    </div>
</template>
```

  (o holofote visual de "recorte iluminado" sobre o campo — spotlight CSS — fica como melhoria incremental: o
  balão de texto apontando o próximo campo já cobre a função didática pedida pelo usuário; adicionar o recorte
  depois do smoke test da Tarefa 10, se o usuário validar que precisa. **Não** desenhar o recorte em telas
  ≤ 640 px, conforme a seção 4 da spec, quando ele for adicionado.)

- [ ] **Passo 6: montar nas 3 telas.** Em `resources/js/Pages/Perfil/Editar.vue`:

```html
        <HolofoteDePendencia v-if="progresso && !progresso.pendencias[0].feito" :pendencia="progresso.pendencias[0]" :form="form" />
        <HolofoteDePendencia v-else-if="progresso && !progresso.perfil" :pendencia="progresso.pendencias.find((p) => !p.feito)" />
```

  Em `resources/js/Pages/ProdutosServicos/Index.vue` e `resources/js/Pages/Clientes/Index.vue` (só quando o bloco
  perfil já estiver completo e o item da própria tela ainda não — senão o coach já cobre o que falta antes):

```html
        <HolofoteDePendencia v-if="progresso && progresso.perfil && !progresso.catalogo" :pendencia="progresso.pendencias.find((p) => !p.feito)" />
```

  (importar `HolofoteDePendencia` de `'../../Components/HolofoteDePendencia.vue'` nas três.)

- [ ] **Passo 7: build.**

Run: `npm run build`
Expected: build sem erro.

- [ ] **Passo 8: commit.**

```bash
git add resources/js/progressoHolofote.js resources/js/progressoHolofote.test.js resources/js/Components/HolofoteDePendencia.vue resources/js/Pages/Perfil/Editar.vue resources/js/Pages/ProdutosServicos/Index.vue resources/js/Pages/Clientes/Index.vue
git commit -m "feat: holofote aponta o próximo campo ou botão pendente na configuração inicial"
```

---

## Tarefa 9 — `ModalBoasVindas.vue`

**Status:** pendente

**Arquivos:**
- Criar: `resources/js/Components/ModalBoasVindas.vue`
- Modificar: `resources/js/Layouts/CamadaApp.vue`

**Interfaces:**
- Consome: `mostrarApresentacao` (Tarefa 5), `progresso.pendencias` (Tarefa 1/5).
- Produz: `POST /perfil/apresentacao` ao fechar (Tarefa 2) — não expõe mais nada (é folha).

- [ ] **Passo 1: `ModalBoasVindas.vue`.** Criar `resources/js/Components/ModalBoasVindas.vue` (mesmo padrão de
  `Modal.vue` usado em `CadastroRapidoCliente.vue`):

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';

const props = defineProps({
    progresso: { type: Object, required: true },
});

const tela = ref(1);

const pendentes = (bloco) => props.progresso.pendencias.filter((p) => p.bloco === bloco && !p.feito).map((p) => p.rotulo);

function concluir() {
    router.post('/perfil/apresentacao');
}
</script>

<template>
    <Modal :aberto="true" titulo="Bem-vindo" largura="max-w-lg" @fechar="concluir">
        <div v-if="tela === 1" class="flex flex-col gap-4 text-sm">
            <p>Bem-vindo! Pra organizar seus recibos, hoje falta completar alguns cadastros.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao-secundario" @click="concluir">Pular</button>
                <button type="button" class="botao" @click="tela = 2">Avançar</button>
            </div>
        </div>
        <div v-else-if="tela === 2" class="flex flex-col gap-4 text-sm">
            <p>Primeiro: seu perfil, uma chave PIX e um modelo de mensagem.</p>
            <ul class="list-disc pl-5">
                <li v-for="rotulo in pendentes('perfil')" :key="rotulo">{{ rotulo }}</li>
            </ul>
            <div class="flex justify-end gap-2">
                <button type="button" class="botao-secundario" @click="concluir">Pular</button>
                <button type="button" class="botao" @click="tela = 3">Avançar</button>
            </div>
        </div>
        <div v-else class="flex flex-col gap-4 text-sm">
            <p>Depois: produtos/serviços e clientes. Só então Contratos e Cobranças ficam liberados.</p>
            <ul class="list-disc pl-5">
                <li v-for="rotulo in pendentes('catalogo')" :key="rotulo">{{ rotulo }}</li>
            </ul>
            <div class="flex justify-end">
                <button type="button" class="botao" @click="concluir">Entendi, vamos lá</button>
            </div>
        </div>
    </Modal>
</template>
```

- [ ] **Passo 2: montar em `CamadaApp.vue`.** Importar `ModalBoasVindas` e adicionar, dentro do `<div
  class="min-h-screen ...">` (depois do `<main>`, para não interferir no grid):

```js
import ModalBoasVindas from '../Components/ModalBoasVindas.vue';
```

```html
        <ModalBoasVindas v-if="page.props.mostrarApresentacao" :progresso="page.props.progresso" />
```

- [ ] **Passo 3: build.**

Run: `npm run build`
Expected: build sem erro.

- [ ] **Passo 4: commit.**

```bash
git add resources/js/Components/ModalBoasVindas.vue resources/js/Layouts/CamadaApp.vue
git commit -m "feat: modal de boas-vindas explica os blocos pendentes no primeiro login após o deploy"
```

---

## Tarefa 10 — Suíte completa e smoke test manual

**Status:** pendente

**Arquivos:** nenhum (só verificação).

- [ ] **Passo 1: suíte PHP completa.**

Run: `php artisan test --compact`
Expected: PASS (0 falhas).

- [ ] **Passo 2: suíte JS completa.**

Run: `node --test resources/js/*.test.js`
Expected: PASS (0 falhas) — inclui os `.test.js` novos das Tarefas 6 e 8.

- [ ] **Passo 3: build de produção.**

Run: `npm run build`
Expected: build sem erro.

- [ ] **Passo 4: smoke test no navegador (browser-agent, não Playwright) — usuário novo do zero.**
  Cadastrar um usuário novo (via `/cadastro`), aprovar (ou usar um já aprovado), logar e confirmar:
  - Modal de boas-vindas aparece uma vez, com "Pular"/"Avançar" funcionando; some depois de clicar em "Entendi".
  - Menu mostra Cobranças/Contratos/Clientes/Produtos e serviços com 🔒, inertes (não navegam).
  - `/perfil`: coach no canto mostra as 5 pendências; holofote aponta o primeiro campo vazio do prestador; ao
    preencher name/documento/telefone/endereço e salvar, cadastrar 1 chave PIX e 1 modelo de mensagem, os itens do
    coach ficam ✓ e o menu libera Clientes e Produtos e serviços (continua travando Contratos/Cobranças).
  - `/produtos-servicos` e `/clientes`: holofote aponta o botão "+ Novo..."; cadastrar 1 de cada libera Contratos e
    Cobranças no menu (🔒 some).
  - Abrir `/contratos/create` e `/cobrancas/create`: abrem normalmente agora.

- [ ] **Passo 5: smoke test — usuário "antigo" (retroativo).**
  Usar (ou simular via tinker, sem escrever nada além do que os testes automatizados já cobrem) um usuário com
  contratos/cobranças já existentes e sem chave PIX: confirmar que `/contratos`, `/cobrancas` (listas),
  editar/pagar/cancelar continuam abertos; só `/contratos/create` e `/cobrancas/create` ficam bloqueados até
  completar o progresso.

- [ ] **Passo 6: atualizar este plano e a skill do projeto.**
  Marcar todas as tarefas como `validada` (depois da confirmação do usuário nos Passos 4-5), e documentar em
  `.claude/skills/recibos/SKILL.md` (seção de Acertos) o padrão novo: progresso sem coluna, trava só em
  criar, `UserFactory::comPerfilPronto()` para testes que precisam passar pelo gate.

- [ ] **Passo 7: commit final (se o Passo 6 tiver mudado algo).**

```bash
git add .claude/plans/2026-10-12-sequencia-de-configuracao-inicial.md .claude/skills/recibos/SKILL.md
git commit -m "docs: fecha a Fase 25 (sequência obrigatória de configuração inicial) como validada"
```
