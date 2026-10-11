# Fase 20 — o padrão de lista por ajax em todas as telas com lista — plano de implementação

> **Para quem executar:** superpowers:executing-plans (inline, escolha do usuário), tarefa por tarefa, com TDD
> (superpowers:test-driven-development). Os passos usam checkbox (`- [ ]`). Os scripts `task-start`/`task-done` procuram
> "Task N"; o registro fica **na tabela de estado e no "Registro de execução"** abaixo, e nos commits.

**Objetivo:** Clientes, Contratos, Produtos e serviços, Auditoria, Usuários (admin), Painel e Relatórios carregam as
listas por ajax no padrão da Fase 19, e os três cadastros viram tabela.

**Arquitetura:**
- Cada tela ganha um `GET …/dados` (JSON, **só leitura**) que devolve um bloco de 4 páginas
  (`App\Support\BlocoDePaginas`), a contagem e, onde houver, totais do resultado inteiro. A página Inertia só abre a
  tela com os filtros.
- No navegador, `usarListaPaginada` (novo) liga `criarListaPaginada` (já existe, com teste) à tela: primeira carga,
  URL, foco e recarga depois das ações (`router.on('success')`). `EstadoDaLista.vue` desenha esqueleto, filtro
  recusado, falha e vazio.
- A previsão e o gerador passam a buscar as cobranças existentes de todos os contratos numa consulta só.

**Tecnologias:** Laravel 13, PHPUnit 12, Inertia v3 (core 3.9.0), Vue 3, Tailwind 4, `node --test`.

**Especificação:** [docs/superpowers/specs/2026-10-11-listas-por-ajax-design.md](../../docs/superpowers/specs/2026-10-11-listas-por-ajax-design.md)
(aprovada pelo usuário em 2026-10-11: "concordo").

Branch: `feat/modulo-cobranca-catalogo` (continua). **Sem migração, sem escrita em banco** (testes em SQLite).

## Restrições gerais
- Bloco de **4 páginas** por pedido. Por página **25, 50 ou 100** (padrão 25); no Painel **10, 25 ou 50** (padrão 10).
- Página além da última devolve a última (`pagina.inicio` diz qual).
- Todo `/dados` **não grava nada** e parte sempre da relação do usuário (`$request->user()->…`); usuários: só admin.
- Filtro inválido no JSON → **422**. A página Inertia continua validando só o formato (link ruim volta com erro, como
  hoje).
- Filtros aplicam ao escolher: selects, datas e autosugestão na hora; texto **400 ms** depois de parar de digitar ou
  no Enter; o botão "Filtrar" continua. "Limpar filtros" (link para a tela sem parâmetros) aparece quando algum filtro
  difere do padrão.
- Tabelas no padrão da T17.15: tabela a partir de `xl`, cartões abaixo, mesmo HTML; uma ação principal por linha
  (`min-w-28 whitespace-nowrap`), as demais no `MenuAcoes` "⋯"; só a coluna do nome quebra linha.
- Situação de cadastro usa `Etiqueta.vue` (selo reto). O carimbo girado fica só para cobranças e recibos.
- Textos novos: "Carregando clientes…", "Carregando contratos…", "Carregando produtos e serviços…",
  "Carregando registros…", "Carregando usuários…", "Carregando relatório…", "Tentar de novo", "Limpar filtros",
  "Ver contratos", "Ver cobranças", "Ver cobranças do cliente", "Reajustar preço", "Liberar acesso", "Reativar",
  "Bloquear", "Confirmar bloqueio".
- pt-BR. Commits com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Depois de mexer em PHP:
  `vendor/bin/pint --dirty --format agent`. Antes de commitar front-end: `npm run build` (parar se falhar).
- Suítes: PHP `php artisan test --compact`; JS `node --test resources/js/*.test.js`.

## Pontos para a revisão final (casos que os testes não cobrem)
1. **Digitação rápida no filtro de texto.** Várias teclas seguidas: só um pedido sai (400 ms), e se uma resposta velha
   chegar depois, o contador de `criarListaPaginada` a descarta. Conferir no navegador que a lista não "pisca".
2. **Contagem no PostgreSQL.** `count(*)` com `ORDER BY` de coluna é erro no PostgreSQL e passa no SQLite dos testes.
   `BlocoDePaginas` conta com `getCountForPagination` (tira o `ORDER BY`); os totais (contratos, painel) somam antes de
   ordenar. Há teste do SQL da contagem; conferir também na conferência visual (banco de dev é PostgreSQL).
3. **Ação no Painel que esvazia a página atual.** Dar baixa na única cobrança da página 2 de "Aguardando": a recarga
   cai na nova última página, e a cobrança aparece em "Recebidas".
4. **Link antigo com `?page=2`** (paginação velha do Laravel): ignorado, abre a página 1 sem erro.
5. **"Ver contratos" de um produto inativo:** o filtro mostra o nome (a página busca o item em qualquer situação),
   embora a autosugestão só ofereça ativos.

## Estado das tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T20.1 | Servidor: `BlocoDePaginas`; `ListaDeCobrancas` com ele; cobranças existentes numa consulta só | testada (RED: classe inexistente; 3 consultas em vez de 1 → GREEN; suíte 349/349) |
| T20.2 | Navegador: `chaveDoFiltro` genérica, `adiar`, `filtrosAlterados`, `periodoAoRedor`, `usarListaPaginada`, `EstadoDaLista`, `Etiqueta`, `Paginador` com opções; cobranças migradas | testada (RED: 3 exports inexistentes, chave genérica, prop `padroes` → GREEN; JS 43/43, Cobrancas 67/67, build ok) |
| T20.3 | Clientes em tabela por ajax | testada (RED: 7 falhas, 404 e prop antiga → GREEN; suíte 351/351, build ok) |
| T20.4 | Produtos e serviços em tabela por ajax | testada (RED: 3 falhas → GREEN; suíte 353/353, build ok) |
| T20.5 | Contratos em tabela por ajax, totais por periodicidade, produto por autosugestão | testada (RED: 6 falhas → GREEN; suíte 356/356, build ok) |
| T20.6 | Auditoria por ajax | testada (RED → GREEN; suíte 356/356, build ok) |
| T20.7 | Usuários (admin) em tabela por ajax, filtros, ação principal pela situação | pendente |
| T20.8 | Painel: três listas por ajax | pendente |
| T20.9 | Relatórios por ajax | pendente |
| T20.10 | Limpeza (`Paginacao.vue`, `App\Support\Paginacao`), teste "só lê" de todos os `/dados`, skill, revisão final, conferência visual, validação | pendente |

## Registro de execução (decisões tomadas durante a execução)
- **Decisões do plano (antes da execução):**
  - O esqueleto da especificação (`EsqueletoLista.vue`) virou `EstadoDaLista.vue`: além do esqueleto, desenha filtro
    recusado, falha e vazio, que se repetiriam em 7 telas.
  - `usarListaPaginada` só liga peças (ciclo de vida do Vue e Inertia); a lógica testável fica em `criarListaPaginada`
    e nos módulos puros. Sem teste próprio; coberto pela conferência no navegador.
  - "Limpar filtros" é um link para a tela sem parâmetros (visita nova, padrões do servidor), em vez de uma função de
    limpar no JS.
  - Produtos: o nome é único por usuário (`unique(user_id, nome)`), então o desempate por `id` pedido na especificação
    não muda nada hoje. Entra o `orderBy('id')` (custo zero, igual às outras listas), sem teste próprio.
  - Usuários: a especificação diz que "Bloquear" **continua** com confirmação no segundo clique, mas hoje não há
    confirmação. Entra a confirmação (padrão de usabilidade 8).
  - "Ver cobranças" (clientes e contratos) abre de 12 meses atrás a 12 meses à frente (`periodoAoRedor`): com só o
    `cliente_id`, a lista abriria no mês atual e poderia vir vazia.
  - Relatórios ficam **sem "Limpar filtros"**: o período é sempre preenchido (o servidor põe os últimos 12 meses) e
    não há outro filtro; o link só voltaria ao padrão.
  - Painel: não tem filtros (só página e "por página"), então também fica sem "Limpar filtros" e sem URL própria
    para a página de cada lista (são três listas na mesma tela).
  - Usuários: a busca por e-mail usa `Busca::contem` (minúsculas, `%` e `_` escapados), igual à de clientes.
- **T20.1:**
  - Decisão: no teste da previsão, `contratoComItem()` três vezes estoura o `unique(user_id, nome)` do produto (o
    auxiliar cria sempre "Hospedagem de site"). O teste usa um contrato com item e dois sem item; mede a mesma coisa.
    No teste do gerador, os auxiliares do próprio arquivo (`prestador`, `contrato`, `gerar`). Custo se errado: nenhum
    (só o arranjo do teste).

---

### Tarefa T20.1: `BlocoDePaginas` e cobranças existentes numa consulta só

**Arquivos:**
- Criar: `app/Support/BlocoDePaginas.php`, `tests/Feature/BlocoDePaginasTest.php`
- Modificar: `app/Services/ListaDeCobrancas.php`, `app/Http/Controllers/CobrancaController.php` (método `filtros`),
  `app/Services/GeradorDeCobrancas.php` (`gerarPara`, `existentes`), `app/Services/PrevisaoDeCobrancas.php` (`entre`)
- Testes: `tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php`, `tests/Feature/Cobrancas/GeradorDeCobrancasTest.php`

**Interfaces:**
- Produz: `BlocoDePaginas::PAGINAS = 4`; `BlocoDePaginas::regras(array $porPagina = [25, 50, 100]): array`;
  `BlocoDePaginas::de(Builder|Relation|Collection $fonte, int $pagina, int $porPagina): array{itens: Collection, pagina: array{inicio: int, por_pagina: int, total: int}}`.
- Produz: `GeradorDeCobrancas::existentes(Collection $contratos): array<int, list<string>>` (troca a assinatura antiga
  `existentes(Contrato)`; os dois únicos chamadores são o gerador e a previsão — conferido com grep em 2026-10-11).

- [ ] **Passo 1: teste do `BlocoDePaginas` (falha)** — `tests/Feature/BlocoDePaginasTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Support\BlocoDePaginas;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BlocoDePaginasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bloco_de_quatro_paginas_a_partir_da_pedida_numa_consulta(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->count(30)
            ->sequence(fn ($sequencia) => ['nome' => sprintf('Cliente %02d', $sequencia->index + 1)])->create();

        $bloco = BlocoDePaginas::de($usuario->clientes()->orderBy('nome'), 2, 5);

        $this->assertSame(['inicio' => 2, 'por_pagina' => 5, 'total' => 30], $bloco['pagina']);
        $this->assertCount(20, $bloco['itens']);
        $this->assertSame(['Cliente 06', 'Cliente 25'], [$bloco['itens']->first()->nome, $bloco['itens']->last()->nome]);
    }

    public function test_pagina_alem_da_ultima_devolve_a_ultima(): void
    {
        $bloco = BlocoDePaginas::de(collect(range(1, 12)), 9, 5);

        $this->assertSame(['inicio' => 3, 'por_pagina' => 5, 'total' => 12], $bloco['pagina']);
        $this->assertSame([11, 12], $bloco['itens']->all());
    }

    public function test_lista_vazia_fica_na_pagina_1(): void
    {
        $this->assertSame(['inicio' => 1, 'por_pagina' => 25, 'total' => 0], BlocoDePaginas::de(collect(), 4, 25)['pagina']);
    }

    /** No PostgreSQL, count(*) com ORDER BY de coluna é erro (o SQLite deixa passar): a contagem sai sem ORDER BY. */
    public function test_contagem_sem_order_by(): void
    {
        $usuario = User::factory()->create();
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas) {
            $consultas[] = strtolower($consulta->sql);
        });

        BlocoDePaginas::de($usuario->clientes()->orderBy('nome'), 1, 25);

        $contagem = collect($consultas)->first(fn (string $sql) => str_contains($sql, 'count('));
        $this->assertNotNull($contagem);
        $this->assertStringNotContainsString('order by', $contagem);
    }

    public function test_regras_de_pagina_e_por_pagina(): void
    {
        $this->assertTrue(Validator::make(['pagina' => 2, 'por_pagina' => 50], BlocoDePaginas::regras())->passes());
        $this->assertFalse(Validator::make(['pagina' => 0], BlocoDePaginas::regras())->passes());
        $this->assertFalse(Validator::make(['por_pagina' => 10], BlocoDePaginas::regras())->passes());
        $this->assertTrue(Validator::make(['por_pagina' => 10], BlocoDePaginas::regras([10, 25, 50]))->passes());
    }
}
```

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/BlocoDePaginasTest.php`
  Esperado: FAIL, `Class "App\Support\BlocoDePaginas" not found`.

- [ ] **Passo 3: implementação** — `app/Support/BlocoDePaginas.php`:

```php
<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Bloco de páginas das listas por ajax (Fase 19): o navegador guarda o bloco e troca de página sem pedir de novo. */
final class BlocoDePaginas
{
    public const PAGINAS = 4;

    /**
     * Regras de `pagina` e `por_pagina` dos `/dados`.
     *
     * @param  list<int>  $porPagina
     * @return array<string, list<mixed>>
     */
    public static function regras(array $porPagina = [25, 50, 100]): array
    {
        return [
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in($porPagina)],
        ];
    }

    /**
     * Fatia de `PAGINAS` páginas a partir de `$pagina`; além da última, devolve a última (`pagina.inicio` diz qual).
     * Consulta: conta e traz só o bloco. Coleção (cobranças, que mesclam previstas; relatórios): fatia na memória.
     *
     * @return array{itens: Collection, pagina: array{inicio: int, por_pagina: int, total: int}}
     */
    public static function de(Builder|Relation|Collection $fonte, int $pagina, int $porPagina): array
    {
        // getCountForPagination tira o ORDER BY: no PostgreSQL, count(*) com ORDER BY de coluna é erro.
        $total = $fonte instanceof Collection ? $fonte->count() : $fonte->toBase()->getCountForPagination();
        $inicio = min(max(1, $pagina), max(1, (int) ceil($total / $porPagina)));
        $pular = ($inicio - 1) * $porPagina;
        $quantos = self::PAGINAS * $porPagina;

        return [
            'itens' => $fonte instanceof Collection
                ? $fonte->slice($pular, $quantos)->values()
                : $fonte->offset($pular)->limit($quantos)->get(),
            'pagina' => ['inicio' => $inicio, 'por_pagina' => $porPagina, 'total' => $total],
        ];
    }
}
```

  (Conferido em 2026-10-11: `Relation::toBase()` em `Relations/Relation.php:406` e `Query\Builder::getCountForPagination`
  em `Query/Builder.php:3776`, que conta num clone sem `columns`, `orders`, `limit` e `offset`.)

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: 5 passam.

- [ ] **Passo 5: `ListaDeCobrancas` usa o helper.** Em `app/Services/ListaDeCobrancas.php`: apagar a constante
  `PAGINAS_POR_BLOCO` e o comentário dela; `use App\Support\BlocoDePaginas;`; trocar o trecho de `$porPagina = …` até
  o fim do `return` por:

```php
        $bloco = BlocoDePaginas::de($leves, $filtros['pagina'], $filtros['por_pagina']);

        // Só as linhas do bloco carregam recibo, cliente e histórico.
        $completas = $usuario->cobrancas()->with(['cliente:id,nome', 'recibo', 'auditorias'])
            ->findMany($bloco['itens']->whereNull('prevista')->pluck('id'))
            ->keyBy('id');

        return [
            'linhas' => $bloco['itens']->map(fn (array $leve) => $leve['prevista'] ?? $completas[$leve['id']]->paraLista($hoje))->all(),
            'totais' => $totais,
            'total_geral' => ['quantidade' => $aReceber->count(), 'soma_centavos' => (int) $aReceber->sum('valor_centavos')],
            'pagina' => $bloco['pagina'],
        ];
```

  Em `CobrancaController::filtros()`, trocar as regras `'pagina'` e `'por_pagina'` por `...BlocoDePaginas::regras(),`
  (com `use App\Support\BlocoDePaginas;`). Conferir: `grep -rn PAGINAS_POR_BLOCO app tests resources` → nada.

- [ ] **Passo 6:** `php artisan test --compact tests/Feature/Cobrancas`. Esperado: tudo passa (os testes da Fase 19
  não mudam).

- [ ] **Passo 7: teste da consulta única (falha).** Em `PrevisaoDeCobrancasTest` (usa `prever()` e
  `contratoComItem()` do próprio arquivo; acrescentar `use Illuminate\Support\Facades\DB;`):

```php
    public function test_uma_consulta_so_para_as_cobrancas_existentes_de_todos_os_contratos(): void
    {
        $this->contratoComItem();
        $this->contratoComItem(['dia_vencimento' => 5]);
        $this->contratoComItem(['dia_vencimento' => 12]);
        $consultas = 0;
        DB::listen(function ($consulta) use (&$consultas) {
            if (str_contains($consulta->sql, 'periodo_inicio')) {
                $consultas++;
            }
        });

        $this->prever('2026-11-01', '2027-01-31');

        $this->assertSame(1, $consultas);
    }
```

  Em `GeradorDeCobrancasTest`, o mesmo para o gerador (acrescentar os `use` que faltarem):

```php
    public function test_uma_consulta_so_para_as_cobrancas_existentes_de_todos_os_contratos(): void
    {
        $usuario = User::factory()->create();
        Contrato::factory()->for($usuario)->count(3)->create();
        $consultas = 0;
        DB::listen(function ($consulta) use (&$consultas) {
            $sql = strtolower(ltrim($consulta->sql));
            if (str_starts_with($sql, 'select') && str_contains($sql, 'periodo_inicio')) {
                $consultas++;
            }
        });

        app(GeradorDeCobrancas::class)->gerarPara($usuario, CarbonImmutable::today());

        $this->assertSame(1, $consultas);
    }
```

- [ ] **Passo 8:** `php artisan test --compact --filter=uma_consulta_so`. Esperado: 2 FAIL (`3` em vez de `1`).

- [ ] **Passo 9: implementação.** Em `GeradorDeCobrancas`, trocar `existentes(Contrato $contrato)` por (com
  `use Illuminate\Support\Collection;`; `periodo_inicio` tem cast `immutable_date`):

```php
    /**
     * Início (Y-m-d) dos períodos que já têm cobrança, em qualquer situação, por contrato: uma consulta para todos.
     *
     * @param  Collection<int, Contrato>  $contratos
     * @return array<int, list<string>>
     */
    public function existentes(Collection $contratos): array
    {
        return Cobranca::query()->whereIn('contrato_id', $contratos->modelKeys())
            ->get(['contrato_id', 'periodo_inicio'])
            ->groupBy('contrato_id')
            ->map(fn (Collection $cobrancas) => $cobrancas->map(fn (Cobranca $cobranca) => $cobranca->periodo_inicio->toDateString())->all())
            ->all();
    }
```

  Em `gerarPara`, antes do laço:

```php
        $contratos = $usuario->contratos()->where('ativo', true)->with('itens.produtoServico')->get();
        $existentes = $this->existentes($contratos);

        foreach ($contratos as $contrato) {
```

  apagar a linha `$existentes = $this->existentes($contrato);` de dentro do laço e usar
  `$this->cobravel($contrato, $periodo, $existentes[$contrato->id] ?? [])`. Em `PrevisaoDeCobrancas::entre`, o mesmo:
  `$contratos = $usuario->contratos()->where('ativo', true)->with(['itens', 'cliente:id,nome'])->get();`,
  `$existentes = $this->gerador->existentes($contratos);` antes do laço, apagar a linha de dentro e o comentário
  `ponytail` dela, e `$existentes[$contrato->id] ?? []` no `cobravel`.

- [ ] **Passo 10:** `php artisan test --compact` (suíte inteira). Esperado: tudo passa, inclusive
  `test_previsao_bate_com_o_que_o_gerador_cria`.

- [ ] **Passo 11:** `vendor/bin/pint --dirty --format agent`; commit:

```bash
git add app/Support/BlocoDePaginas.php app/Services app/Http/Controllers/CobrancaController.php tests/Feature/BlocoDePaginasTest.php tests/Feature/Cobrancas
git commit -m "feat: BlocoDePaginas para as listas por ajax; cobranças existentes de todos os contratos numa consulta só"
```

### Tarefa T20.2: base do navegador e cobranças migradas

**Arquivos:**
- Criar: `resources/js/usarListaPaginada.js`, `resources/js/estilosLista.js`,
  `resources/js/Components/EstadoDaLista.vue`, `resources/js/Components/Etiqueta.vue`
- Modificar: `resources/js/paginacao.js` (+ teste), `resources/js/listaPaginada.js` (+ teste),
  `resources/js/filtroPeriodo.js` (+ teste), `resources/js/Components/Paginador.vue`,
  `resources/js/Pages/Cobrancas/Index.vue`, `app/Http/Controllers/CobrancaController.php` (prop `padroes`)
- Teste PHP: `tests/Feature/Cobrancas/TelasCobrancaTest.php` (prop `padroes`)

**Interfaces:**
- Consome: `criarListaPaginada({ url, filtro, buscar, aoMostrar })` (já existe), `buscarJson` (`http.js`).
- Produz (JS):
  - `chaveDoFiltro(filtro): string` — todas as chaves menos `pagina`.
  - `filtrosAlterados(filtro, padroes): boolean` (`paginacao.js`).
  - `adiar(acao, ms = 400)` → função com `.agora()` (`listaPaginada.js`).
  - `periodoAoRedor(hoje = new Date(), meses = 12): { mes, ate }` (`filtroPeriodo.js`).
  - `usarListaPaginada({ url, filtro, componente, endereco = null, idDoFoco = 'paginador-topo', aoMostrar = () => {} })`
    → o mesmo objeto de `criarListaPaginada`.
  - `botaoPrincipal`, `itemMenu` (`estilosLista.js`).
- Produz (componentes): `<EstadoDaLista :estado :falha carregando vazio [erro-filtro] [:linhas=6] @tentar>` com slot
  padrão (mostrado no estado `lista`); `<Etiqueta texto tom>` (`tom`: `neutro` | `verde` | `laranja` | `vermelho` |
  `apagado`); `<Paginador … :opcoes="[25, 50, 100]">`.

- [ ] **Passo 1: testes JS (falham).** Em `resources/js/paginacao.test.js` (importar também `filtrosAlterados`):

```js
test('chave do cache aceita qualquer filtro: muda com qualquer campo, não com a página', () => {
    const base = { q: 'pad', uf: 'BA', contrato: '', por_pagina: 25, pagina: 1 };
    assert.equal(chaveDoFiltro(base), chaveDoFiltro({ ...base, pagina: 4 }));
    assert.notEqual(chaveDoFiltro(base), chaveDoFiltro({ ...base, uf: 'SE' }));
    assert.equal(chaveDoFiltro(base), chaveDoFiltro({ pagina: 9, por_pagina: 25, contrato: '', uf: 'BA', q: 'pad' }));
});

test('filtros alterados: só os campos dos padrões contam, e 7 é igual a "7"', () => {
    const padroes = { mes: '2026-10', cliente_id: '', canceladas: false };
    assert.equal(filtrosAlterados({ ...padroes, pagina: 3, por_pagina: 50 }, padroes), false);
    assert.equal(filtrosAlterados({ ...padroes, cliente_id: 7 }, padroes), true);
    assert.equal(filtrosAlterados({ ...padroes, canceladas: true }, padroes), true);
    assert.equal(filtrosAlterados({ ...padroes, cliente_id: '7' }, { ...padroes, cliente_id: 7 }), false);
});
```

  Em `resources/js/listaPaginada.test.js` (importar também `adiar`):

```js
test('página além da última: mostra a que o servidor devolveu', async () => {
    const falso = servidor();
    const { filtro, lista: l } = lista(falso);

    l.irPara(9);
    falso.pedidos[0].resolver(bloco(8)); // 200 linhas de 25: a última é a 8
    await esperar();

    assert.deepEqual([filtro.pagina, l.linhas.value[0].id], [8, 176]);
});

test('busca ao digitar: só depois de 400 ms sem tecla nova; Enter busca na hora', (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    let buscas = 0;
    const buscar = adiar(() => buscas++, 400);

    buscar();
    t.mock.timers.tick(300);
    buscar();
    t.mock.timers.tick(300);
    assert.equal(buscas, 0);
    t.mock.timers.tick(100);
    assert.equal(buscas, 1);

    buscar();
    buscar.agora();
    t.mock.timers.tick(1000);
    assert.equal(buscas, 2);
});
```

  Em `resources/js/filtroPeriodo.test.js` (importar `periodoAoRedor`):

```js
test('período ao redor: de 12 meses atrás a 12 meses à frente (cabe nos 36 meses)', () => {
    assert.deepEqual(periodoAoRedor(new Date(2026, 9, 15)), { mes: '2025-10', ate: '2027-10' });
    assert.deepEqual(periodoAoRedor(new Date(2026, 0, 31)), { mes: '2025-01', ate: '2027-01' });
});
```

- [ ] **Passo 2:** `node --test resources/js/*.test.js`. Esperado: FAIL — `filtrosAlterados`, `adiar` e
  `periodoAoRedor` não exportados; o teste da chave genérica falha (a chave atual ignora `q` e `uf`).

- [ ] **Passo 3: implementação dos módulos puros.** Em `resources/js/paginacao.js`, trocar `chaveDoFiltro` e acrescentar:

```js
/** Chave do cache da lista: todos os filtros menos a página (o "por página" entra: muda o fatiamento). */
export function chaveDoFiltro(filtro) {
    const { pagina, ...resto } = filtro;

    return JSON.stringify(Object.keys(resto).sort().map((chave) => [chave, resto[chave]]));
}

/** Algum filtro difere do padrão? (mostra "Limpar filtros"). Compara como texto: o id vem número ou texto. */
export function filtrosAlterados(filtro, padroes) {
    return Object.entries(padroes).some(([campo, valor]) => String(filtro[campo] ?? '') !== String(valor ?? ''));
}
```

  Em `resources/js/listaPaginada.js`, acrescentar no fim:

```js
/** Busca ao digitar: espera `ms` sem tecla nova; `.agora()` (Enter, "Filtrar") não espera. */
export function adiar(acao, ms = 400) {
    let espera = null;
    const adiada = () => {
        clearTimeout(espera);
        espera = setTimeout(acao, ms);
    };
    adiada.agora = () => {
        clearTimeout(espera);
        acao();
    };

    return adiada;
}
```

  Em `resources/js/filtroPeriodo.js`, acrescentar:

```js
/** De `meses` atrás a `meses` à frente (AAAA-MM), para os links "Ver cobranças": com 12, são 24 meses (limite 36). */
export function periodoAoRedor(hoje = new Date(), meses = 12) {
    const mes = (deslocamento) => {
        const data = new Date(hoje.getFullYear(), hoje.getMonth() + deslocamento, 1);

        return `${data.getFullYear()}-${String(data.getMonth() + 1).padStart(2, '0')}`;
    };

    return { mes: mes(-meses), ate: mes(meses) };
}
```

- [ ] **Passo 4:** `node --test resources/js/*.test.js`. Esperado: tudo passa (38 + 5 = 43).

- [ ] **Passo 5: peças Vue.** `resources/js/estilosLista.js`:

```js
/** Classes comuns das listas em tabela (padrão de usabilidade 12 da skill recibos). */

// Mesma largura mínima em todas as linhas: os botões principais ficam alinhados e numa linha só.
export const botaoPrincipal = 'min-w-28 whitespace-nowrap';
export const itemMenu = 'w-full px-4 py-2 text-left text-sm hover:bg-papel focus-visible:bg-papel focus-visible:outline-none';
```

  `resources/js/usarListaPaginada.js`:

```js
import { nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { buscarJson } from './http.js';
import { criarListaPaginada } from './listaPaginada.js';

/**
 * Liga a lista paginada (listaPaginada.js) a uma tela Inertia: primeira carga ao montar; filtros e página na URL
 * (`endereco`; sem ele a URL não muda — o Painel tem três listas); foco em `idDoFoco` ao trocar de página; e recarga
 * quando uma ação da tela termina (evento "success", que só sai de resposta do servidor, da página `componente`).
 */
export function usarListaPaginada({ url, filtro, componente, endereco = null, idDoFoco = 'paginador-topo', aoMostrar = () => {} }) {
    const lista = criarListaPaginada({
        url,
        filtro,
        buscar: buscarJson,
        aoMostrar(pagina, focar) {
            aoMostrar(pagina);
            if (endereco) {
                // Sem pedido ao servidor nem entrada nova no histórico: F5, voltar e link copiado reabrem igual.
                router.replace({
                    url: `${endereco}?${lista.parametros(pagina)}`,
                    props: (atuais) => ({ ...atuais, filtros: { ...filtro } }),
                    preserveState: true,
                    preserveScroll: true,
                });
            }
            if (focar) {
                nextTick(() => {
                    const alvo = document.getElementById(idDoFoco);
                    alvo?.scrollIntoView({ block: 'start' });
                    alvo?.focus({ preventScroll: true });
                });
            }
        },
    });

    let pararDeOuvir = () => {};
    onMounted(() => {
        lista.irPara(filtro.pagina);
        pararDeOuvir = router.on('success', (evento) => {
            if (evento.detail.page.component === componente) {
                lista.aoTerminarAcao();
            }
        });
    });
    onUnmounted(() => pararDeOuvir());

    return lista;
}
```

  `resources/js/Components/EstadoDaLista.vue` (o esqueleto sai de `Cobrancas/Index.vue`):

```vue
<script setup>
/**
 * Estados de uma lista por ajax antes de haver linhas: esqueleto na primeira carga (pedido do usuário, "como no
 * YouTube"), filtro recusado, falha com "Tentar de novo" e vazio. No estado "lista", mostra o slot.
 */
defineProps({
    estado: { type: String, required: true },
    falha: { type: String, default: '' },
    carregando: { type: String, required: true },
    vazio: { type: String, required: true },
    erroFiltro: { type: String, default: 'Corrija os filtros acima para ver a lista.' },
    linhas: { type: Number, default: 6 },
});
defineEmits(['tentar']);
</script>

<template>
    <div v-if="estado === 'carregando'" class="border border-linha bg-white" aria-busy="true">
        <p class="flex items-center gap-2 px-4 py-3 text-sm text-grafite/75" role="status">
            {{ carregando }}
            <span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
        </p>
        <div v-for="linha in linhas" :key="linha" class="flex items-center gap-4 border-t border-linha px-4 py-4 motion-safe:animate-pulse" aria-hidden="true">
            <span class="h-3 w-1/4 rounded-sm bg-linha" />
            <span class="hidden h-3 w-40 rounded-sm bg-linha xl:block" />
            <span class="hidden h-3 w-20 rounded-sm bg-linha xl:block" />
            <span class="ml-auto h-3 w-20 rounded-sm bg-linha" />
            <span class="hidden h-8 w-28 rounded-sm bg-linha sm:block" />
        </div>
    </div>
    <p v-else-if="estado === 'erro-filtro'" class="border border-carbono bg-white px-4 py-3 text-sm">{{ erroFiltro }}</p>
    <div v-else-if="estado === 'falha'" class="flex flex-wrap items-center gap-3 border border-tinta-vermelha bg-white px-4 py-3 text-sm">
        <span>{{ falha }}</span>
        <button type="button" class="botao-secundario" @click="$emit('tentar')">Tentar de novo</button>
    </div>
    <p v-else-if="estado === 'vazio'" class="border border-carbono bg-white px-4 py-3 text-sm">{{ vazio }}</p>
    <slot v-else />
</template>
```

  `resources/js/Components/Etiqueta.vue`:

```vue
<script setup>
/** Situação de cadastro (contrato, produto, usuário): selo reto. O carimbo girado fica para cobranças e recibos. */
defineProps({
    texto: { type: String, required: true },
    tom: { type: String, default: 'neutro' },
});

const tons = {
    neutro: 'border-carbono text-carbono',
    verde: 'border-tinta-verde text-tinta-verde',
    laranja: 'border-tinta-laranja text-tinta-laranja',
    vermelho: 'border-tinta-vermelha text-tinta-vermelha',
    apagado: 'border-grafite/40 text-grafite/60',
};
</script>

<template>
    <span class="inline-block rounded-sm border px-2 py-0.5 font-estreita text-xs whitespace-nowrap" :class="tons[tom]">{{ texto }}</span>
</template>
```

  Em `Paginador.vue`: nova prop `opcoes: { type: Array, default: () => [25, 50, 100] }` e
  `v-for="opcao in opcoes"` no select "Por página".

- [ ] **Passo 6: cobranças com as peças novas.** Em `CobrancaController::index`, acrescentar a prop
  `'padroes' => ['mes' => $hoje->format('Y-m'), 'ate' => $hoje->format('Y-m'), 'status' => '', 'cliente_id' => '', 'canceladas' => false],`
  e no `TelasCobrancaTest` (o teste que abre `/cobrancas`) a asserção
  `->where('padroes', ['mes' => '2026-10', 'ate' => '2026-10', 'status' => '', 'cliente_id' => '', 'canceladas' => false])`
  (ajustar o mês ao `travelTo` do arquivo). Rodar antes de mudar o controller: FAIL (prop ausente); depois: PASS.

  Em `resources/js/Pages/Cobrancas/Index.vue`:
  - imports: `import { computed, nextTick, reactive, ref, watch } from 'vue';`; tirar `buscarJson` e
    `criarListaPaginada`; acrescentar `EstadoDaLista`, `usarListaPaginada`, `filtrosAlterados` (`paginacao.js`) e
    `botaoPrincipal`, `itemMenu` (`estilosLista.js`); prop `padroes: { type: Object, required: true }`;
  - trocar o bloco de `// A lista vem de /cobrancas/dados…` até `const erroAte = …` **e** o bloco `let pararDeOuvir … onUnmounted(…)` por:

```js
// A lista vem de /cobrancas/dados em blocos de páginas (usarListaPaginada.js); a tela só fecha a faixa aberta.
const {
    linhas: linhasDaPagina, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina,
} = usarListaPaginada({
    url: '/cobrancas/dados', filtro, componente: 'Cobrancas/Index', endereco: '/cobrancas', aoMostrar: () => { faixa.value = null; },
});
const erroAte = computed(() => erros.value.ate ?? '');
// Cliente escolhido (ou apagado) aplica na hora, como os selects.
watch(() => filtro.cliente_id, filtrar);
const filtrando = computed(() => filtrosAlterados(filtro, props.padroes));
```

  - apagar as constantes locais `botaoPrincipal` e `itemMenu` (vêm de `estilosLista.js`);
  - no template, ao lado do botão "Filtrar":
    `<Link v-if="filtrando" href="/cobrancas" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>`;
  - trocar os quatro blocos `v-if="estado === 'carregando'"` … `v-else-if="estado === 'vazio'"` e o
    `<template v-else>` por `<EstadoDaLista :estado="estado" :falha="falha" carregando="Carregando cobranças…" :vazio="vazio" erro-filtro="Corrija o período acima para ver as cobranças." @tentar="recarregar">`
    envolvendo o conteúdo que estava no `<template v-else>` (paginadores, tabela e rodapé), sem mudar esse conteúdo.

- [ ] **Passo 7:** `npm run build` (sem erro); `node --test resources/js/*.test.js`; `php artisan test --compact tests/Feature/Cobrancas`.
  Esperado: build ok, JS 43/43, Cobrancas tudo verde.

- [ ] **Passo 8:** commit:

```bash
git add resources/js app/Http/Controllers/CobrancaController.php tests/Feature/Cobrancas/TelasCobrancaTest.php
git commit -m "feat: base das listas por ajax no navegador (usarListaPaginada, EstadoDaLista, Etiqueta, busca ao digitar); cobranças migradas"
```

### Tarefa T20.3: Clientes em tabela por ajax

**Arquivos:**
- Modificar: `app/Http/Controllers/ClienteController.php` (`index`, novo `dados`, novo `filtros` privado),
  `routes/web.php`, `resources/js/Pages/Clientes/Index.vue` (reescrita)
- Testes: `tests/Feature/FiltrosClientesTest.php`, `tests/Feature/ClientesTest.php` (as 2 asserções sobre a prop
  `clientes` passam a ler `/clientes/dados`)

**Interfaces:**
- Consome: `BlocoDePaginas` (T20.1); `usarListaPaginada`, `adiar`, `filtrosAlterados`, `periodoAoRedor`,
  `EstadoDaLista`, `estilosLista.js` (T20.2).
- Produz: `GET /clientes/dados` →
  `{ linhas: [{ id, nome, documento, cidade, modelo, contratos_ativos }], pagina, filtros }`; a página
  `Clientes/Index` recebe só `filtros` (`q`, `cidade`, `uf`, `modelo`, `contrato`, `pagina`, `por_pagina`), `ufs`,
  `modelos`.

- [ ] **Passo 1: testes (falham).** Em `FiltrosClientesTest`:
  - trocar o helper `nomes()` por:

```php
    /** @return list<string> */
    private function nomes(string $consulta = ''): array
    {
        return array_column($this->actingAs($this->usuario)->getJson('/clientes/dados'.$consulta)->assertOk()->json('linhas'), 'nome');
    }
```

  - nas chamadas existentes, trocar o prefixo `'/clientes?…'` por `'?…'` e `'/clientes'` por `''`;
  - trocar `test_pagina_de_25_em_25_mantendo_os_filtros` por:

```php
    public function test_bloco_de_paginas_mantendo_os_filtros(): void
    {
        foreach (range(1, 30) as $numero) {
            $this->cliente(['nome' => sprintf('Cliente %02d', $numero), 'cidade' => 'Araci']);
        }
        $this->cliente(['nome' => 'Cliente de Aracaju', 'cidade' => 'Aracaju']);

        $resposta = $this->actingAs($this->usuario)->getJson('/clientes/dados?cidade=araci&por_pagina=25&pagina=2')->assertOk();

        $resposta->assertJsonPath('pagina', ['inicio' => 2, 'por_pagina' => 25, 'total' => 30]);
        $this->assertSame(['Cliente 26', 'Cliente 27', 'Cliente 28', 'Cliente 29', 'Cliente 30'], array_column($resposta->json('linhas'), 'nome'));
    }
```

  - em `test_filtro_invalido_e_recusado`, acrescentar
    `$this->getJson('/clientes/dados?uf=XX')->assertUnprocessable()->assertJsonValidationErrors('uf');`
  - testes novos (com `use Inertia\Testing\AssertableInertia as Assert;`):

```php
    public function test_dados_trazem_contratos_ativos_e_documento_formatado(): void
    {
        $cliente = $this->cliente(['nome' => 'Padaria', 'documento' => Documento::cnpjComDv('998887770001')]);
        Contrato::factory()->for($this->usuario)->for($cliente)->count(2)->create();
        Contrato::factory()->for($this->usuario)->for($cliente)->create(['ativo' => false]);

        $this->actingAs($this->usuario)->getJson('/clientes/dados')->assertOk()
            ->assertJsonPath('linhas.0.contratos_ativos', 2)
            ->assertJsonPath('linhas.0.documento', Documento::formatar(Documento::cnpjComDv('998887770001')));
    }

    public function test_pagina_abre_so_com_os_filtros(): void
    {
        $this->cliente(['nome' => 'Padaria']);

        $this->actingAs($this->usuario)->get('/clientes?uf=BA&pagina=2&page=7')->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Clientes/Index')
                ->missing('clientes')
                ->where('filtros', ['q' => '', 'cidade' => '', 'uf' => 'BA', 'modelo' => '', 'contrato' => '', 'pagina' => 2, 'por_pagina' => 25]));
    }
```

  Em `ClientesTest`, as asserções sobre a prop `clientes` (2) passam a ler
  `$this->getJson('/clientes/dados')->json('linhas')` com os mesmos valores esperados.

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/FiltrosClientesTest.php tests/Feature/ClientesTest.php`.
  Esperado: FAIL — `/clientes/dados` dá 404 (o padrão numérico de `{cliente}` não casa com `dados`) e a página ainda
  manda `clientes`.

- [ ] **Passo 3: servidor.** Rota, antes do `Route::resource('clientes', …)`:
  `Route::get('/clientes/dados', [ClienteController::class, 'dados']);`. No controller (`use App\Support\BlocoDePaginas;`,
  tirar `use App\Support\Paginacao;`), trocar `index` por:

```php
    public function index(Request $request): Response
    {
        // A lista vem por ajax de /clientes/dados; a página só abre a tela com os filtros.
        return Inertia::render('Clientes/Index', [
            'filtros' => $this->filtros($request),
            'ufs' => Ufs::TODAS,
            'modelos' => FamiliaModelo::opcoes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $consulta = $request->user()->clientes()->orderBy('nome')->orderBy('id')
            ->withCount(['contratos as contratos_ativos' => fn ($contratos) => $contratos->where('ativo', true)]);

        if ($filtros['q'] !== '') {
            $consulta->comTermo($filtros['q']);
        }
        if ($filtros['cidade'] !== '') {
            $consulta->whereRaw("lower(cidade) like ? escape '\\'", [Busca::contem($filtros['cidade'])]);
        }
        if ($filtros['uf'] !== '') {
            $consulta->where('uf', $filtros['uf']);
        }
        if ($filtros['modelo'] !== '') {
            $consulta->where('modelo', $filtros['modelo']);
        }
        if ($filtros['contrato'] !== '') {
            $ativo = fn ($contratos) => $contratos->where('ativo', true);
            $filtros['contrato'] === 'com' ? $consulta->whereHas('contratos', $ativo) : $consulta->whereDoesntHave('contratos', $ativo);
        }

        $bloco = BlocoDePaginas::de($consulta, $filtros['pagina'], $filtros['por_pagina']);

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => Documento::formatar($cliente->documento),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
                'contratos_ativos' => $cliente->contratos_ativos,
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{q: string, cidade: string, uf: string, modelo: string, contrato: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'modelo' => ['nullable', Rule::enum(FamiliaModelo::class)],
            'contrato' => ['nullable', Rule::in(['com', 'sem'])],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'q' => trim($validados['q'] ?? ''),
            'cidade' => trim($validados['cidade'] ?? ''),
            'uf' => $validados['uf'] ?? '',
            'modelo' => $validados['modelo'] ?? '',
            'contrato' => $validados['contrato'] ?? '',
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
```

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Pages/Clientes/Index.vue` inteiro:

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { periodoAoRedor } from '../../filtroPeriodo.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    ufs: { type: Array, required: true },
    modelos: { type: Array, required: true },
});

const PADROES = { q: '', cidade: '', uf: '', modelo: '', contrato: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/clientes/dados', filtro, componente: 'Clientes/Index', endereco: '/clientes',
});
// Texto busca 400 ms depois de parar de digitar; Enter e "Filtrar" buscam na hora.
const buscarAoDigitar = adiar(filtrar);
const periodo = periodoAoRedor();
const cobrancasDo = (cliente) => `/cobrancas?${new URLSearchParams({ cliente_id: cliente.id, ...periodo })}`;
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="q" rotulo="Nome ou CPF/CNPJ" class="sm:col-span-3">
                <input id="q" v-model="filtro.q" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="cidade" rotulo="Cidade" class="sm:col-span-2">
                <input id="cidade" v-model="filtro.cidade" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="uf" rotulo="UF">
                <select id="uf" v-model="filtro.uf" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                </select>
            </Campo>
            <Campo id="modelo" rotulo="Modelo do recibo" class="sm:col-span-3">
                <select id="modelo" v-model="filtro.modelo" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="contrato" rotulo="Contrato" class="sm:col-span-3">
                <select id="contrato" v-model="filtro.contrato" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="com">Com contrato ativo</option>
                    <option value="sem">Sem contrato ativo</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/clientes" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando clientes…"
            :vazio="filtrando ? 'Nenhum cliente com estes filtros.' : 'Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <!-- Tabela a partir de 1280 px; abaixo disso, cada cliente vira um cartão (mesmo HTML, outro display). -->
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cidade/UF</th>
                            <th scope="col" class="px-3 py-2 font-medium">Recibo</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Contratos ativos</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="cliente in linhas" :key="cliente.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ cliente.nome }}</p>
                                <p class="font-estreita text-xs tabular-nums text-grafite/80">{{ cliente.documento }}</p>
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">{{ cliente.cidade || 'Sem cidade' }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">Recibo </span>{{ cliente.modelo }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/80 xl:px-3 xl:py-3 xl:text-right xl:text-sm">
                                {{ cliente.contratos_ativos }}<span class="xl:hidden"> {{ cliente.contratos_ativos === 1 ? 'contrato ativo' : 'contratos ativos' }}</span>
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/clientes/${cliente.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${cliente.nome}`">
                                        <Link :href="`/contratos?cliente_id=${cliente.id}`" :class="itemMenu">Ver contratos</Link>
                                        <Link :href="cobrancasDo(cliente)" :class="itemMenu">Ver cobranças</Link>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`.
  Esperado: build ok; suíte inteira verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/ClienteController.php routes/web.php resources/js/Pages/Clientes/Index.vue tests/Feature/FiltrosClientesTest.php tests/Feature/ClientesTest.php
git commit -m "feat: clientes em tabela por ajax (contratos ativos, ver contratos e cobranças, filtros ao escolher)"
```

### Tarefa T20.4: Produtos e serviços em tabela por ajax

**Arquivos:**
- Modificar: `app/Http/Controllers/ProdutoServicoController.php` (`index`, novo `dados`, novo `filtros`),
  `routes/web.php`, `resources/js/Pages/ProdutosServicos/Index.vue` (reescrita)
- Testes: `tests/Feature/ProdutosServicosTest.php` (as asserções sobre a prop `itens` da **lista** passam a ler
  `/produtos-servicos/dados`; as de formulário e reajuste ficam)

**Interfaces:**
- Consome: T20.1 e T20.2.
- Produz: `GET /produtos-servicos/dados` →
  `{ linhas: [{ id, nome, tipo, unidade, preco, ativo, em_contratos_ativos }], pagina, filtros }`; a página recebe
  `filtros` (`nome`, `tipo`, `situacao`, `pagina`, `por_pagina`) e `tipos`.

- [ ] **Passo 1: testes (falham).** Em `ProdutosServicosTest` (com `use App\Models\Contrato;`):

```php
    public function test_dados_da_lista_com_contratos_ativos_e_filtros(): void
    {
        $usuario = User::factory()->create();
        $hospedagem = ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem', 'ativo' => true]);
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Domínio', 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Hospedagem de outro usuário']);
        foreach ([true, true, false] as $ativo) {
            Contrato::factory()->for($usuario)->create(['ativo' => $ativo])->itens()->create([
                'produto_servico_id' => $hospedagem->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 1000, 'principal' => true, 'ordem' => 0,
            ]);
        }

        $this->actingAs($usuario)->getJson('/produtos-servicos/dados')->assertOk()
            ->assertJsonPath('pagina.total', 2)
            ->assertJsonPath('linhas.0.nome', 'Domínio')
            ->assertJsonPath('linhas.0.em_contratos_ativos', 0)
            ->assertJsonPath('linhas.1.em_contratos_ativos', 2);
        $this->getJson('/produtos-servicos/dados?situacao=inativos')->assertJsonCount(1, 'linhas')->assertJsonPath('linhas.0.nome', 'Domínio');
        $this->getJson('/produtos-servicos/dados?nome=HOSP')->assertJsonCount(1, 'linhas');
        $this->getJson('/produtos-servicos/dados?tipo=nada')->assertUnprocessable()->assertJsonValidationErrors('tipo');
    }

    public function test_pagina_da_lista_abre_so_com_os_filtros(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/produtos-servicos?situacao=ativos')->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('ProdutosServicos/Index')
                ->missing('itens')
                ->where('filtros', ['nome' => '', 'tipo' => '', 'situacao' => 'ativos', 'pagina' => 1, 'por_pagina' => 25]));
    }
```

  As asserções antigas da lista (`->has('itens', …)`, `->where('itens.N…')` em `GET /produtos-servicos`) passam a
  `getJson('/produtos-servicos/dados…')` com `assertJsonCount(…, 'linhas')` / `assertJsonPath('linhas.N…')`.
  ("Domínio" vem antes de "Hospedagem" pela ordem do nome; no SQLite e no PostgreSQL `D` < `H`.)

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/ProdutosServicosTest.php`. Esperado: FAIL (404 e prop
  `itens` ainda presente).

- [ ] **Passo 3: servidor.** Rota antes do `Route::resource('produtos-servicos', …)`:
  `Route::get('/produtos-servicos/dados', [ProdutoServicoController::class, 'dados']);`. No controller
  (`use App\Support\BlocoDePaginas;`, tirar `use App\Support\Paginacao;`):

```php
    public function index(Request $request): Response
    {
        // A lista vem por ajax de /produtos-servicos/dados; a página só abre a tela com os filtros.
        return Inertia::render('ProdutosServicos/Index', [
            'filtros' => $this->filtros($request),
            'tipos' => TipoItem::opcoes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas, com quantos contratos ativos usam cada item. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        // Um contrato não repete o item (regra `distinct` em RegrasDeItens): contar itens é contar contratos.
        $consulta = $request->user()->produtosServicos()->orderBy('nome')->orderBy('id')
            ->withCount(['contratoItens as em_contratos_ativos' => fn ($itens) => $itens->whereHas('contrato', fn ($contrato) => $contrato->where('ativo', true))]);

        if ($filtros['nome'] !== '') {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($filtros['nome'])]);
        }
        if ($filtros['tipo'] !== '') {
            $consulta->where('tipo', $filtros['tipo']);
        }
        if ($filtros['situacao'] !== '') {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }

        $bloco = BlocoDePaginas::de($consulta, $filtros['pagina'], $filtros['por_pagina']);

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (ProdutoServico $item) => [
                'id' => $item->id,
                'nome' => $item->nome,
                'tipo' => $item->tipo->rotulo(),
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
                'ativo' => $item->ativo,
                'em_contratos_ativos' => $item->em_contratos_ativos,
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{nome: string, tipo: string, situacao: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'nome' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::enum(TipoItem::class)],
            'situacao' => ['nullable', Rule::in(['ativos', 'inativos'])],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'nome' => trim($validados['nome'] ?? ''),
            'tipo' => $validados['tipo'] ?? '',
            'situacao' => $validados['situacao'] ?? '',
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
```

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Pages/ProdutosServicos/Index.vue` inteiro:

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    tipos: { type: Array, required: true },
});

const PADROES = { nome: '', tipo: '', situacao: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/produtos-servicos/dados', filtro, componente: 'ProdutosServicos/Index', endereco: '/produtos-servicos',
});
const buscarAoDigitar = adiar(filtrar);
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Produtos e serviços</h1>
            <Link href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="nome" rotulo="Nome" class="sm:col-span-2">
                <input id="nome" v-model="filtro.nome" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="tipo" rotulo="Tipo">
                <select id="tipo" v-model="filtro.tipo" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="situacao" rotulo="Situação">
                <select id="situacao" v-model="filtro.situacao" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-4">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/produtos-servicos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando produtos e serviços…"
            :vazio="filtrando ? 'Nenhum produto ou serviço com estes filtros.' : 'Nenhum produto ou serviço ainda. Cadastre o que você vende para montar contratos por itens.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Nome</th>
                            <th scope="col" class="px-3 py-2 font-medium">Tipo</th>
                            <th scope="col" class="px-3 py-2 font-medium">Unidade</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Preço</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Em contratos ativos</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="item in linhas" :key="item.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full font-semibold xl:px-4 xl:py-3">{{ item.nome }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">{{ item.tipo }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">por </span>{{ item.unidade }}</td>
                            <td class="font-semibold whitespace-nowrap tabular-nums xl:px-3 xl:py-3 xl:text-right">R$ {{ item.preco }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="item.ativo ? 'Ativo' : 'Inativo'" :tom="item.ativo ? 'verde' : 'apagado'" />
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/80 xl:px-3 xl:py-3 xl:text-right xl:text-sm">
                                {{ item.em_contratos_ativos }}<span class="xl:hidden"> em contratos ativos</span>
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/produtos-servicos/${item.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${item.nome}`">
                                        <Link :href="`/produtos-servicos/${item.id}/reajuste`" :class="itemMenu">Reajustar preço</Link>
                                        <Link :href="`/contratos?produto_servico_id=${item.id}`" :class="itemMenu">Ver contratos</Link>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`.
  Esperado: build ok; suíte verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/ProdutoServicoController.php routes/web.php resources/js/Pages/ProdutosServicos/Index.vue tests/Feature/ProdutosServicosTest.php
git commit -m "feat: produtos e serviços em tabela por ajax (contratos ativos, reajustar e ver contratos no menu)"
```

### Tarefa T20.5: Contratos em tabela por ajax, totais por periodicidade

**Arquivos:**
- Modificar: `app/Http/Controllers/ContratoController.php` (`index`, novos `dados`, `filtrada`, `filtros`),
  `routes/web.php`, `resources/js/Pages/Contratos/Index.vue` (reescrita)
- Testes: `tests/Feature/FiltrosContratosTest.php`, `tests/Feature/ContratosTest.php` (as asserções da prop
  `contratos` da lista passam a ler `/contratos/dados`)

**Interfaces:**
- Consome: T20.1 e T20.2.
- Produz: `GET /contratos/dados` →
  `{ linhas: [{ id, cliente_id, cliente, resumo, periodicidade, modalidade, dia_vencimento, inicio, fim, valor, situacao }], totais: { ativos: [{ periodicidade, quantidade, soma_centavos }], encerrados }, pagina, filtros }`,
  `situacao` ∈ `ativo` | `encerra` | `encerrado`. A página recebe `filtros`, `clienteFiltro`, `produtoFiltro`,
  `modalidades`, `formasPagamento` (sai `produtos`).

- [ ] **Passo 1: testes (falham).** Em `FiltrosContratosTest`:
  - o helper `ids()` passa a ler `getJson('/contratos/dados'.…)->json('linhas')` (trocar o prefixo das URLs
    `'/contratos?…'` por `'?…'`); o teste de paginação de 26 contratos passa a pedir `?por_pagina=25&pagina=2` e
    afirmar `pagina.total = 26` e 1 linha;
  - filtro inválido: acrescentar `getJson('/contratos/dados?situacao=talvez')->assertUnprocessable()`;
  - testes novos (`use Inertia\Testing\AssertableInertia as Assert;`):

```php
    public function test_totais_dos_ativos_por_periodicidade_e_encerrados(): void
    {
        Contrato::factory()->for($this->usuario)->count(2)->create(['periodicidade' => 'mensal', 'valor_centavos' => 10000]);
        Contrato::factory()->for($this->usuario)->create(['periodicidade' => 'trimestral', 'valor_centavos' => 30000]);
        Contrato::factory()->for($this->usuario)->create(['periodicidade' => 'mensal', 'valor_centavos' => 99999, 'ativo' => false]);
        Contrato::factory()->create(['periodicidade' => 'mensal']); // de outro usuário

        $this->actingAs($this->usuario)->getJson('/contratos/dados')->assertOk()
            ->assertJsonPath('totais.ativos', [
                ['periodicidade' => 'mensal', 'quantidade' => 2, 'soma_centavos' => 20000],
                ['periodicidade' => 'trimestral', 'quantidade' => 1, 'soma_centavos' => 30000],
            ])
            ->assertJsonPath('totais.encerrados', 1)
            ->assertJsonPath('pagina.total', 4);
    }

    public function test_situacao_ativo_encerra_em_e_encerrado(): void
    {
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-01-01']);
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-02-01', 'data_fim' => '2026-12-31']);
        Contrato::factory()->for($this->usuario)->create(['data_inicio' => '2026-03-01', 'data_fim' => '2026-06-30', 'ativo' => false]);

        $linhas = $this->actingAs($this->usuario)->getJson('/contratos/dados')->json('linhas');

        $this->assertSame(['ativo', 'encerra', 'encerrado'], array_column($linhas, 'situacao'));
        $this->assertSame([null, '31/12/2026', '30/06/2026'], array_column($linhas, 'fim'));
    }

    public function test_pagina_traz_o_produto_do_filtro_mesmo_inativo(): void
    {
        $produto = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Domínio antigo', 'ativo' => false]);

        $this->actingAs($this->usuario)->get("/contratos?produto_servico_id={$produto->id}")->assertOk()
            ->assertInertia(fn (Assert $pagina) => $pagina
                ->component('Contratos/Index')
                ->missing('contratos')
                ->missing('produtos')
                ->where('produtoFiltro', ['id' => $produto->id, 'nome' => 'Domínio antigo']));
    }
```

  (A ordem da lista continua: ativos primeiro, depois início, depois id. Se o `setUp` do arquivo usar outro nome que
  não `$this->usuario`, seguir o do arquivo.)

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/FiltrosContratosTest.php tests/Feature/ContratosTest.php`.
  Esperado: FAIL (404 em `/contratos/dados`; props antigas na página).

- [ ] **Passo 3: servidor.** Rota antes do `Route::resource('contratos', …)`:
  `Route::get('/contratos/dados', [ContratoController::class, 'dados']);`. No controller
  (`use App\Models\User;`, `use App\Support\BlocoDePaginas;`, `use Illuminate\Database\Eloquent\Relations\HasMany;`;
  tirar `use App\Support\Paginacao;`), trocar `index` por:

```php
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $filtros = $this->filtros($request);

        // A lista vem por ajax de /contratos/dados; a página só abre a tela com os filtros.
        return Inertia::render('Contratos/Index', [
            'filtros' => $filtros,
            'clienteFiltro' => $filtros['cliente_id'] === '' ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
            // Em qualquer situação: o "Ver contratos" de um item inativo também mostra o nome no filtro.
            'produtoFiltro' => $filtros['produto_servico_id'] === '' ? null : $usuario->produtosServicos()->find($filtros['produto_servico_id'], ['id', 'nome']),
            'modalidades' => Modalidade::opcoes(),
            'formasPagamento' => FormaPagamento::opcoes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): um bloco de páginas e os totais dos ativos por periodicidade. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $filtrada = fn () => $this->filtrada($request->user(), $filtros);

        $bloco = BlocoDePaginas::de(
            $filtrada()->with('cliente:id,nome')->orderByDesc('ativo')->orderBy('data_inicio')->orderBy('id'),
            $filtros['pagina'],
            $filtros['por_pagina'],
        );
        // Sem ORDER BY (o PostgreSQL recusaria) e sem somar periodicidades diferentes (o número não faria sentido).
        $porPeriodicidade = $filtrada()->where('ativo', true)->toBase()
            ->selectRaw('periodicidade, count(*) as quantidade, sum(valor_centavos) as soma')
            ->groupBy('periodicidade')->get()->keyBy('periodicidade');

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => (string) strtok($contrato->descricao, "\n"),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'modalidade' => $contrato->modalidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'inicio' => $contrato->data_inicio->format('d/m/Y'),
                'fim' => $contrato->data_fim?->format('d/m/Y'),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                // Ativo com fim marcado = encerramento agendado (T17.9).
                'situacao' => match (true) {
                    ! $contrato->ativo => 'encerrado',
                    $contrato->data_fim !== null => 'encerra',
                    default => 'ativo',
                },
            ])->all(),
            'totais' => [
                'ativos' => collect(Periodicidade::cases())
                    ->filter(fn (Periodicidade $periodicidade) => $porPeriodicidade->has($periodicidade->value))
                    ->map(fn (Periodicidade $periodicidade) => [
                        'periodicidade' => $periodicidade->value,
                        'quantidade' => (int) $porPeriodicidade[$periodicidade->value]->quantidade,
                        'soma_centavos' => (int) $porPeriodicidade[$periodicidade->value]->soma,
                    ])
                    ->values()
                    ->all(),
                'encerrados' => $filtrada()->where('ativo', false)->count(),
            ],
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /** Contratos do usuário com os filtros da lista, sem ordem (a lista ordena; as somas não podem ter ORDER BY). */
    private function filtrada(User $usuario, array $filtros): HasMany
    {
        $consulta = $usuario->contratos();

        foreach (['cliente_id', 'modalidade', 'forma_pagamento'] as $campo) {
            if ($filtros[$campo] !== '') {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if ($filtros['situacao'] !== '') {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }
        if ($filtros['produto_servico_id'] !== '') {
            $consulta->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $filtros['produto_servico_id']));
        }

        return $consulta;
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{cliente_id: int|string, situacao: string, modalidade: string, forma_pagamento: string, produto_servico_id: int|string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativos', 'encerrados'])],
            'modalidade' => ['nullable', Rule::enum(Modalidade::class)],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'produto_servico_id' => ['nullable', 'integer'],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'cliente_id' => empty($validados['cliente_id']) ? '' : (int) $validados['cliente_id'],
            'situacao' => $validados['situacao'] ?? '',
            'modalidade' => $validados['modalidade'] ?? '',
            'forma_pagamento' => $validados['forma_pagamento'] ?? '',
            'produto_servico_id' => empty($validados['produto_servico_id']) ? '' : (int) $validados['produto_servico_id'],
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
```

  (`Periodicidade` já é importado no controller; conferir `Relation::toBase` no T20.1.)

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Pages/Contratos/Index.vue` inteiro:

```vue
<script setup>
import { computed, reactive, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { periodoAoRedor } from '../../filtroPeriodo.js';
import { formatarCentavos } from '../../itens.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    produtoFiltro: { type: Object, default: null },
    modalidades: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const PADROES = { cliente_id: '', situacao: '', modalidade: '', forma_pagamento: '', produto_servico_id: '' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/contratos/dados', filtro, componente: 'Contratos/Index', endereco: '/contratos',
});
// Cliente ou produto escolhido (ou apagado) aplica na hora, como os selects.
watch(() => [filtro.cliente_id, filtro.produto_servico_id], filtrar);

const SITUACAO = {
    ativo: () => ['Ativo', 'verde'],
    encerra: (contrato) => [`Encerra em ${contrato.fim}`, 'laranja'],
    encerrado: () => ['Encerrado', 'apagado'],
};
// Valores de periodicidades diferentes não se somam: uma linha de total por periodicidade.
const PERIODICIDADE = {
    mensal: ['Mensais ativos', 'por mês'],
    trimestral: ['Trimestrais ativos', 'por trimestre'],
    semestral: ['Semestrais ativos', 'por semestre'],
    anual: ['Anuais ativos', 'por ano'],
};
const periodo = periodoAoRedor();
const cobrancasDo = (contrato) => `/cobrancas?${new URLSearchParams({ cliente_id: contrato.cliente_id, ...periodo })}`;
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Contratos</h1>
            <Link href="/contratos/create" class="botao">Novo contrato</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="filtrar">
            <Campo id="cliente_id" rotulo="Cliente" class="sm:col-span-3">
                <BuscaAutosugestao id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />
            </Campo>
            <Campo id="situacao" rotulo="Situação" class="sm:col-span-3">
                <select id="situacao" v-model="filtro.situacao" class="entrada" @change="filtrar">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="encerrados">Encerrados</option>
                </select>
            </Campo>
            <Campo id="modalidade" rotulo="Modalidade" class="sm:col-span-2">
                <select id="modalidade" v-model="filtro.modalidade" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="modalidade in modalidades" :key="modalidade.valor" :value="modalidade.valor">{{ modalidade.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="forma_pagamento" rotulo="Forma de pagamento" class="sm:col-span-2">
                <select id="forma_pagamento" v-model="filtro.forma_pagamento" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option v-for="forma in formasPagamento" :key="forma.valor" :value="forma.valor">{{ forma.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="produto_servico_id" rotulo="Produto ou serviço" class="sm:col-span-2">
                <BuscaAutosugestao
                    id="produto_servico_id"
                    v-model="filtro.produto_servico_id"
                    :inicial="produtoFiltro"
                    url="/produtos-servicos/busca"
                    vazio="Todos os produtos e serviços"
                    nenhum="Nenhum produto ou serviço ativo encontrado."
                />
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/contratos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando contratos…"
            :vazio="filtrando ? 'Nenhum contrato com estes filtros.' : 'Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.'"
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Cliente</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cobrança</th>
                            <th scope="col" class="px-3 py-2 font-medium">Vigência</th>
                            <th scope="col" class="px-3 py-2 text-right font-medium">Valor</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="contrato in linhas" :key="contrato.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ contrato.cliente }}</p>
                                <p v-if="contrato.resumo" class="text-xs text-grafite/80">{{ contrato.resumo }}</p>
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                {{ contrato.periodicidade }}, {{ contrato.modalidade }}, vence dia {{ contrato.dia_vencimento }}
                            </td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm">
                                {{ contrato.inicio }}<template v-if="contrato.fim"> a {{ contrato.fim }}</template>
                            </td>
                            <td class="font-semibold whitespace-nowrap tabular-nums xl:px-3 xl:py-3 xl:text-right">R$ {{ contrato.valor }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="SITUACAO[contrato.situacao](contrato)[0]" :tom="SITUACAO[contrato.situacao](contrato)[1]" />
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div class="flex items-center gap-2 xl:justify-end">
                                    <Link :href="`/contratos/${contrato.id}/edit`" :class="['botao', botaoPrincipal]">Editar</Link>
                                    <MenuAcoes :rotulo="`Mais ações: ${contrato.cliente}`">
                                        <Link :href="cobrancasDo(contrato)" :class="itemMenu">Ver cobranças do cliente</Link>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="resumo.totais.ativos.length || resumo.totais.encerrados" key="rodape" class="block border-t-2 border-carbono/20 font-estreita xl:table-footer-group">
                        <tr v-for="total in resumo.totais.ativos" :key="total.periodicidade" class="flex justify-between gap-3 px-4 py-1.5 xl:table-row xl:p-0">
                            <td colspan="3" class="xl:px-4 xl:py-1.5">{{ PERIODICIDADE[total.periodicidade][0] }} <span class="text-grafite/60">({{ total.quantidade }})</span></td>
                            <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-1.5">R$ {{ formatarCentavos(total.soma_centavos) }}</td>
                            <td colspan="2" class="text-grafite/60 xl:px-3 xl:py-1.5">{{ PERIODICIDADE[total.periodicidade][1] }}</td>
                        </tr>
                        <tr v-if="resumo.totais.encerrados" class="flex justify-between px-4 py-1.5 text-grafite/70 xl:table-row xl:p-0">
                            <td colspan="6" class="xl:px-4 xl:py-1.5">Encerrados <span class="text-grafite/60">({{ resumo.totais.encerrados }})</span></td>
                        </tr>
                    </tfoot>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`.
  Esperado: build ok; suíte verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/ContratoController.php routes/web.php resources/js/Pages/Contratos/Index.vue tests/Feature/FiltrosContratosTest.php tests/Feature/ContratosTest.php
git commit -m "feat: contratos em tabela por ajax (vigência, situação, totais dos ativos por periodicidade, produto por autosugestão)"
```

### Tarefa T20.6: Auditoria por ajax

**Arquivos:**
- Modificar: `app/Http/Controllers/AuditoriaController.php`, `routes/web.php`, `resources/js/Pages/Auditoria/Index.vue`
- Testes: `tests/Feature/AuditoriaTest.php` (as asserções sobre a prop `registros` passam a ler `/auditoria/dados`)

**Interfaces:**
- Produz: `GET /auditoria/dados?de=&ate=&pagina=&por_pagina=` →
  `{ linhas: [{ id, quando, resumo, motivo, link }], pagina, filtros }`; `ate` antes de `de` → 422 em `ate`. A página
  recebe `filtros` (`de`, `ate`, `pagina`, `por_pagina`) e `padroes` (`de` = hoje − 30 dias, `ate` = hoje).

- [ ] **Passo 1: testes (falham).** Em `AuditoriaTest::test_tela_de_auditoria_lista_so_os_registros_do_usuario_no_periodo`,
  trocar as duas leituras da página por:

```php
        $this->actingAs($this->usuario)->get('/auditoria')->assertInertia(fn (Assert $pagina) => $pagina
            ->component('Auditoria/Index')
            ->missing('registros')
            ->where('filtros', ['de' => '2026-09-10', 'ate' => '2026-10-10', 'pagina' => 1, 'por_pagina' => 25])
            ->where('padroes', ['de' => '2026-09-10', 'ate' => '2026-10-10']));

        $this->getJson('/auditoria/dados')->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.resumo', 'Baixa desfeita (estava paga em 17/06/2026)')
            ->assertJsonPath('linhas.0.motivo', 'Baixa errada')
            ->assertJsonPath('linhas.0.quando', '10/10/2026 10:00')
            ->assertJsonPath('linhas.0.link', '/cobrancas?mes=2026-05');

        $this->getJson('/auditoria/dados?de=2026-10-11&ate=2026-10-31')->assertOk()->assertJsonCount(0, 'linhas');
        $this->getJson('/auditoria/dados?de=2026-10-11&ate=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('ate');
```

  Outras leituras de `registros` no arquivo (`grep -n "registros" tests/Feature/AuditoriaTest.php`) passam a
  `getJson('/auditoria/dados')` com `assertJsonPath('linhas.N…')`.

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/AuditoriaTest.php`. Esperado: FAIL.

- [ ] **Passo 3: servidor.** Rota: `Route::get('/auditoria/dados', [AuditoriaController::class, 'dados']);`.
  Controller inteiro:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Support\BlocoDePaginas;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditoriaController extends Controller
{
    /** Ações sensíveis do usuário. A lista vem por ajax de /auditoria/dados; a página só abre a tela com os filtros. */
    public function index(Request $request): Response
    {
        return Inertia::render('Auditoria/Index', [
            'filtros' => $this->filtros($request),
            'padroes' => $this->padroes(),
        ]);
    }

    /** Dados da lista (JSON, só leitura): padrão últimos 30 dias, mais recentes primeiro. */
    public function dados(Request $request): JsonResponse
    {
        $request->validate(['ate' => ['nullable', 'date', 'after_or_equal:de']]);
        $filtros = $this->filtros($request);

        $bloco = BlocoDePaginas::de(
            $request->user()->auditorias()
                ->whereBetween('created_at', [CarbonImmutable::parse($filtros['de'])->startOfDay(), CarbonImmutable::parse($filtros['ate'])->endOfDay()])
                ->orderByDesc('id'),
            $filtros['pagina'],
            $filtros['por_pagina'],
        );

        // Uma consulta para os links das cobranças do bloco (a lista abre no mês do vencimento).
        $vencimentos = $request->user()->cobrancas()
            ->whereIn('id', $bloco['itens']->where('alvo_tipo', 'cobranca')->pluck('alvo_id'))
            ->pluck('vencimento', 'id');

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Auditoria $registro) => [
                'id' => $registro->id,
                'quando' => $registro->created_at->format('d/m/Y H:i'),
                'resumo' => $registro->resumo,
                'motivo' => $registro->motivo,
                'link' => match (true) {
                    $registro->alvo_tipo === 'contrato' => "/contratos/{$registro->alvo_id}/edit",
                    isset($vencimentos[$registro->alvo_id]) => '/cobrancas?mes='.CarbonImmutable::parse($vencimentos[$registro->alvo_id])->format('Y-m'),
                    default => null,
                },
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /** @return array{de: string, ate: string} */
    private function padroes(): array
    {
        $hoje = CarbonImmutable::today();

        return ['de' => $hoje->subDays(30)->toDateString(), 'ate' => $hoje->toDateString()];
    }

    /**
     * Filtros já no formato da tela. Aqui só o formato: a ordem das datas é conferida no JSON (422 embaixo do "Até").
     *
     * @return array{de: string, ate: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date'],
            ...BlocoDePaginas::regras(),
        ]);
        $padroes = $this->padroes();

        return [
            'de' => isset($validados['de']) ? CarbonImmutable::parse($validados['de'])->toDateString() : $padroes['de'],
            'ate' => isset($validados['ate']) ? CarbonImmutable::parse($validados['ate'])->toDateString() : $padroes['ate'],
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
}
```

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Pages/Auditoria/Index.vue` inteiro:

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import Paginador from '../../Components/Paginador.vue';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    filtros: { type: Object, required: true },
    padroes: { type: Object, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, props.padroes));
const { linhas, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/auditoria/dados', filtro, componente: 'Auditoria/Index', endereco: '/auditoria',
});
</script>

<template>
    <CamadaApp>
        <h1 class="mb-2 text-2xl font-semibold">Auditoria</h1>
        <p class="mb-6 max-w-3xl text-sm text-grafite/80">
            Correções e desfazimentos de baixa, cancelamentos, edições de cobrança e encerramentos ou correções de contrato:
            quem fez, quando e por quê. Os registros não podem ser alterados.
        </p>

        <form class="mb-6 flex flex-wrap items-end gap-4" @submit.prevent="filtrar">
            <Campo id="de" rotulo="De" :erro="erros.de">
                <input id="de" v-model="filtro.de" type="date" class="entrada" @change="filtrar">
            </Campo>
            <Campo id="ate" rotulo="Até" :erro="erros.ate">
                <input id="ate" v-model="filtro.ate" type="date" class="entrada" :min="filtro.de" @change="filtrar">
            </Campo>
            <Link v-if="filtrando" href="/auditoria" class="pb-2 font-estreita text-sm text-carbono underline">Limpar filtros</Link>
        </form>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando registros…"
            vazio="Nenhuma ação registrada neste período."
            erro-filtro="Corrija o período acima para ver os registros."
            @tentar="recarregar"
        >
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Quando</th>
                            <th scope="col" class="px-3 py-2 font-medium">Registro</th>
                            <th scope="col" class="px-4 py-2 font-medium">Motivo</th>
                        </tr>
                    </thead>
                    <tbody v-for="registro in linhas" :key="registro.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-col gap-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="font-estreita text-xs whitespace-nowrap tabular-nums text-grafite/75 xl:w-px xl:px-4 xl:py-3 xl:text-sm">{{ registro.quando }}</td>
                            <td class="xl:px-3 xl:py-3">
                                <Link v-if="registro.link" :href="registro.link" class="underline">{{ registro.resumo }}</Link>
                                <template v-else>{{ registro.resumo }}</template>
                            </td>
                            <td class="text-grafite/80 xl:px-4 xl:py-3">
                                <template v-if="registro.motivo"><span class="xl:hidden">Motivo: </span>{{ registro.motivo }}</template>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`. Esperado:
  build ok; suíte verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/AuditoriaController.php routes/web.php resources/js/Pages/Auditoria/Index.vue tests/Feature/AuditoriaTest.php
git commit -m "feat: auditoria por ajax (tabela, período com erro embaixo do campo, limpar filtros)"
```

### Tarefa T20.7: Usuários (admin) em tabela por ajax

**Arquivos:**
- Criar: `resources/js/acoesUsuario.js`, `resources/js/acoesUsuario.test.js`
- Modificar: `app/Http/Controllers/Admin/UsuarioController.php`, `routes/web.php` (grupo `admin`),
  `resources/js/Pages/Admin/Usuarios.vue`
- Testes: `tests/Feature/Contas/AdminUsuariosTest.php`

**Interfaces:**
- Produz: `GET /admin/usuarios/dados?q=&status=&pagina=&por_pagina=` →
  `{ linhas: [{ id, nome, email, status, is_admin, cadastrado_em, eu }], pagina, filtros }` (pendentes primeiro, depois
  nome, depois id); a página recebe `filtros` (`q`, `status`, `pagina`, `por_pagina`).
- Produz: `acoesDoUsuario(usuario): { principal: 'aprovar' | 'reativar' | null, menu: ['bloquear'] | [] }`.

- [ ] **Passo 1: testes (falham).** `resources/js/acoesUsuario.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { acoesDoUsuario } from './acoesUsuario.js';

test('ação principal pela situação; "Bloquear" no menu, nunca para a própria conta nem para quem já está bloqueado', () => {
    assert.deepEqual(acoesDoUsuario({ status: 'pendente', eu: false }), { principal: 'aprovar', menu: ['bloquear'] });
    assert.deepEqual(acoesDoUsuario({ status: 'aprovado', eu: false }), { principal: null, menu: ['bloquear'] });
    assert.deepEqual(acoesDoUsuario({ status: 'bloqueado', eu: false }), { principal: 'reativar', menu: [] });
    assert.deepEqual(acoesDoUsuario({ status: 'aprovado', eu: true }), { principal: null, menu: [] });
});
```

  Em `AdminUsuariosTest`, trocar `test_lista_mostra_pendentes_primeiro_e_conta_pendentes` (manter as asserções que
  não são da lista, como a contagem de pendentes, se houver) e acrescentar:

```php
    public function test_dados_pendentes_primeiro_com_busca_situacao_e_eu(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Ana Liberada', 'email' => 'ana.liberada@exemplo.test']);
        User::factory()->pendente()->create(['name' => 'Zé Pendente']);
        User::factory()->bloqueado()->create(['name' => 'Bia Bloqueada']);
        $nomes = fn (string $consulta = '') => array_column($this->actingAs($admin)->getJson('/admin/usuarios/dados'.$consulta)->assertOk()->json('linhas'), 'nome');

        $this->assertSame(['Zé Pendente', 'Admin', 'Ana Liberada', 'Bia Bloqueada'], $nomes());
        $this->assertSame(['Ana Liberada'], $nomes('?q=ANA.LIBERADA@'));
        $this->assertSame(['Ana Liberada'], $nomes('?q=liberada'));
        $this->assertSame(['Bia Bloqueada'], $nomes('?status=bloqueado'));
        $this->getJson('/admin/usuarios/dados')->assertJsonPath('linhas.1.eu', true)->assertJsonPath('linhas.0.eu', false);
        $this->getJson('/admin/usuarios/dados?status=talvez')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_dados_so_para_admin(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/admin/usuarios/dados')->assertForbidden();
    }
```

  E no teste da página: `get('/admin/usuarios')` passa a afirmar `->missing('usuarios')->where('filtros', ['q' => '', 'status' => '', 'pagina' => 1, 'por_pagina' => 25])`.

- [ ] **Passo 2:** `node --test resources/js/acoesUsuario.test.js`; `php artisan test --compact tests/Feature/Contas/AdminUsuariosTest.php`.
  Esperado: FAIL (módulo inexistente; 404 em `/admin/usuarios/dados`).

- [ ] **Passo 3: implementação.** `resources/js/acoesUsuario.js`:

```js
/** Ações de um usuário na lista do admin: a principal pela situação; "Bloquear" no menu, nunca na própria conta. */
export function acoesDoUsuario(usuario) {
    const principal = { pendente: 'aprovar', bloqueado: 'reativar' }[usuario.status] ?? null;

    return { principal, menu: usuario.status !== 'bloqueado' && !usuario.eu ? ['bloquear'] : [] };
}
```

  Rota no grupo `admin`, antes das de `{usuario}`: `Route::get('/usuarios/dados', [UsuarioController::class, 'dados']);`.
  Controller (`use App\Support\BlocoDePaginas;`, `use App\Support\Busca;`, `use Illuminate\Http\JsonResponse;`,
  `use Illuminate\Validation\Rule;`), trocar `index` por:

```php
    public function index(Request $request): Response
    {
        // A lista vem por ajax de /admin/usuarios/dados; a página só abre a tela com os filtros.
        return Inertia::render('Admin/Usuarios', ['filtros' => $this->filtros($request)]);
    }

    /** Dados da lista (JSON, só leitura): pendentes primeiro, depois nome. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $this->filtros($request);
        $consulta = User::query()
            ->orderByRaw("case when status = 'pendente' then 0 else 1 end")
            ->orderBy('name')
            ->orderBy('id');

        if ($filtros['q'] !== '') {
            $termo = Busca::contem($filtros['q']);
            $consulta->where(fn ($busca) => $busca->whereRaw("lower(name) like ? escape '\\'", [$termo])->orWhereRaw("lower(email) like ? escape '\\'", [$termo]));
        }
        if ($filtros['status'] !== '') {
            $consulta->where('status', $filtros['status']);
        }

        $bloco = BlocoDePaginas::de($consulta, $filtros['pagina'], $filtros['por_pagina']);

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'status' => $usuario->status->value,
                'is_admin' => $usuario->is_admin,
                'cadastrado_em' => $usuario->created_at?->format('d/m/Y'),
                // A tela não oferece "Bloquear" na própria conta (o servidor também recusa).
                'eu' => $usuario->is($request->user()),
            ])->all(),
            'pagina' => $bloco['pagina'],
            'filtros' => $filtros,
        ]);
    }

    /**
     * Filtros da lista, já no formato da tela ('' = sem filtro).
     *
     * @return array{q: string, status: string, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request): array
    {
        $validados = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(StatusUsuario::class)],
            ...BlocoDePaginas::regras(),
        ]);

        return [
            'q' => trim($validados['q'] ?? ''),
            'status' => $validados['status'] ?? '',
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }
```

- [ ] **Passo 4:** mesmos comandos do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Pages/Admin/Usuarios.vue` inteiro:

```vue
<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Etiqueta from '../../Components/Etiqueta.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import MenuAcoes from '../../Components/MenuAcoes.vue';
import Paginador from '../../Components/Paginador.vue';
import { acoesDoUsuario } from '../../acoesUsuario.js';
import { botaoPrincipal, itemMenu } from '../../estilosLista.js';
import { adiar } from '../../listaPaginada.js';
import { filtrosAlterados } from '../../paginacao.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({ filtros: { type: Object, required: true } });

const PADROES = { q: '', status: '' };
const SITUACAO = { pendente: ['Aguardando liberação', 'laranja'], aprovado: ['Liberado', 'verde'], bloqueado: ['Bloqueado', 'vermelho'] };
const ROTULO = { aprovar: 'Liberar acesso', reativar: 'Reativar' };
const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => filtrosAlterados(filtro, PADROES));
const { linhas: linhasDaPagina, resumo, carregando, falha, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/admin/usuarios/dados', filtro, componente: 'Admin/Usuarios', endereco: '/admin/usuarios',
    aoMostrar: () => { confirmando.value = null; },
});
const buscarAoDigitar = adiar(filtrar);
const linhas = computed(() => linhasDaPagina.value.map((usuario) => ({ ...usuario, acoes: acoesDoUsuario(usuario) })));

// "Bloquear" é ação destrutiva: confirmação no segundo clique, na própria linha (padrão de usabilidade 8).
const confirmando = ref(null);
// A resposta é o redirect do Inertia: o evento "success" recarrega a lista (usarListaPaginada).
const acao = (id, nome) => router.post(`/admin/usuarios/${id}/${nome}`, {}, { preserveScroll: true, onFinish: () => { confirmando.value = null; } });
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Usuários</h1>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="buscarAoDigitar.agora">
            <Campo id="q" rotulo="Nome ou e-mail" class="sm:col-span-2">
                <input id="q" v-model="filtro.q" type="search" class="entrada" @input="buscarAoDigitar">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada" @change="filtrar">
                    <option value="">Todas</option>
                    <option value="pendente">Aguardando liberação</option>
                    <option value="aprovado">Liberado</option>
                    <option value="bloqueado">Bloqueado</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-end gap-3">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/admin/usuarios" class="pb-2 font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <EstadoDaLista :estado="estado" :falha="falha" carregando="Carregando usuários…" vazio="Nenhum usuário com estes filtros." @tentar="recarregar">
            <Paginador id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <TransitionGroup tag="table" name="lista" class="block w-full text-sm xl:table">
                    <thead key="cabecalho" class="hidden font-estreita text-xs text-grafite/75 xl:table-header-group">
                        <tr class="border-b border-linha text-left">
                            <th scope="col" class="px-4 py-2 font-medium">Nome</th>
                            <th scope="col" class="px-3 py-2 font-medium">E-mail</th>
                            <th scope="col" class="px-3 py-2 font-medium">Cadastro</th>
                            <th scope="col" class="px-3 py-2 font-medium">Situação</th>
                            <th scope="col" class="px-4 py-2 font-medium"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody v-for="usuario in linhas" :key="usuario.id" class="block border-b border-linha last:border-b-0 xl:table-row-group">
                        <tr class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 xl:table-row xl:p-0">
                            <td class="basis-full xl:px-4 xl:py-3">
                                <p class="font-semibold">{{ usuario.nome }} <span v-if="usuario.is_admin" class="font-estreita text-xs font-normal text-grafite/70">administrador</span></p>
                            </td>
                            <td class="text-grafite/80 xl:px-3 xl:py-3">{{ usuario.email }}</td>
                            <td class="font-estreita text-xs whitespace-nowrap text-grafite/80 xl:px-3 xl:py-3 xl:text-sm"><span class="xl:hidden">cadastro em </span>{{ usuario.cadastrado_em }}</td>
                            <td class="whitespace-nowrap xl:px-3 xl:py-3">
                                <Etiqueta :texto="SITUACAO[usuario.status][0]" :tom="SITUACAO[usuario.status][1]" />
                            </td>
                            <td class="basis-full pt-2 xl:w-px xl:px-4 xl:py-3 xl:whitespace-nowrap">
                                <div v-if="confirmando === usuario.id" class="flex flex-wrap items-center gap-2 xl:justify-end">
                                    <button type="button" class="botao-perigo-confirmar" @click="acao(usuario.id, 'bloquear')">Confirmar bloqueio</button>
                                    <button type="button" class="botao-secundario" @click="confirmando = null">Cancelar</button>
                                </div>
                                <div v-else class="flex items-center gap-2 xl:justify-end">
                                    <button v-if="usuario.acoes.principal" type="button" :class="['botao', botaoPrincipal]" @click="acao(usuario.id, 'aprovar')">
                                        {{ ROTULO[usuario.acoes.principal] }}
                                    </button>
                                    <MenuAcoes v-if="usuario.acoes.menu.length" v-slot="{ fechar }" :rotulo="`Mais ações: ${usuario.nome}`">
                                        <button type="button" :class="[itemMenu, 'text-tinta-vermelha']" @click="fechar(); confirmando = usuario.id">Bloquear</button>
                                    </MenuAcoes>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </TransitionGroup>
            </div>
            <Paginador id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

  ("Reativar" e "Liberar acesso" usam a mesma rota `aprovar`, como hoje.)

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `node --test resources/js/*.test.js`;
  `php artisan test --compact`. Esperado: tudo verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/Admin/UsuarioController.php routes/web.php resources/js/acoesUsuario.js resources/js/acoesUsuario.test.js resources/js/Pages/Admin/Usuarios.vue tests/Feature/Contas/AdminUsuariosTest.php
git commit -m "feat: usuários (admin) em tabela por ajax (busca, situação, ação principal pela situação, bloquear com confirmação)"
```

### Tarefa T20.8: Painel com três listas por ajax

**Arquivos:**
- Criar: `resources/js/Components/ListaDoPainel.vue`
- Modificar: `app/Http/Controllers/PainelController.php` (`__invoke` sem as listas, novo `dados`), `routes/web.php`,
  `resources/js/Pages/Painel.vue`
- Testes: `tests/Feature/PainelTest.php`

**Interfaces:**
- Produz: `GET /painel/dados?lista=para_enviar|aguardando|recebidas&pagina=&por_pagina=` (por página 10/25/50,
  padrão 10) → `{ linhas: [paraLista], total_geral: { quantidade, soma_centavos }, pagina }`. Ordem: `para_enviar` e
  `aguardando` por vencimento e id; `recebidas` por `pago_em` e id, mais recentes primeiro. **Não gera cobranças.**
- A página `Painel` continua com `mes`, `perfilCompleto`, `diasAntecedencia`, `chavesPix`, `formasPagamento`,
  `resumo`; saem `paraEnviar`, `aguardando`, `recebidas`.
- `<ListaDoPainel lista titulo vazio v-slot="{ cobranca }">` — o slot recebe as ações de cada linha.

- [ ] **Passo 1: testes (falham).** Em `PainelTest`:
  - `test_abrir_o_painel_gera_as_cobrancas_devidas`: depois do `get('/painel')` (que gera), trocar as asserções das
    listas por:

```php
        $this->getJson('/painel/dados?lista=para_enviar')->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.recibo.numero', '2026/000001');
        $this->getJson('/painel/dados?lista=aguardando')->assertJsonCount(0, 'linhas');
        $this->getJson('/painel/dados?lista=recebidas')->assertJsonCount(0, 'linhas');
```

    e a página passa a afirmar `->missing('paraEnviar')->missing('aguardando')->missing('recebidas')`;
  - `test_blocos_seguem_envio_e_pagamento`: as três asserções `has/where` viram, para cada lista,
    `getJson('/painel/dados?lista=…')->assertJsonCount(1, 'linhas')->assertJsonPath('linhas.0.id', …)`; as de `resumo`
    continuam na página;
  - `test_perfil_incompleto_avisa_e_nao_gera`: `->has('paraEnviar', 0)` vira
    `getJson('/painel/dados?lista=para_enviar')->assertJsonCount(0, 'linhas')`;
  - testes novos:

```php
    public function test_dados_nao_geram_cobrancas(): void
    {
        $usuario = User::factory()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->getJson('/painel/dados?lista=para_enviar')->assertOk()->assertJsonCount(0, 'linhas');
        $this->assertSame(0, Cobranca::count());
    }

    public function test_contagem_e_soma_da_lista_inteira_em_blocos_de_10(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        foreach (range(1, 12) as $dia) {
            Cobranca::factory()->for($usuario)->for($cliente)->create([
                'vencimento' => sprintf('2026-10-%02d', $dia + 10), 'valor_centavos' => 1000, 'enviado_em' => now(),
            ]);
        }
        Cobranca::factory()->create(['vencimento' => '2026-10-20', 'valor_centavos' => 99900, 'enviado_em' => now()]); // de outro usuário

        $this->actingAs($usuario)->getJson('/painel/dados?lista=aguardando')->assertOk()
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 10, 'total' => 12])
            ->assertJsonPath('total_geral', ['quantidade' => 12, 'soma_centavos' => 12000])
            ->assertJsonCount(12, 'linhas'); // bloco de 4 páginas de 10
        $this->getJson('/painel/dados?lista=aguardando&pagina=9')->assertJsonPath('pagina.inicio', 2);
        $this->getJson('/painel/dados?lista=outra')->assertUnprocessable()->assertJsonValidationErrors('lista');
        $this->getJson('/painel/dados?lista=aguardando&por_pagina=100')->assertUnprocessable();
    }
```

  (Se a `CobrancaFactory` não criar recibo, as linhas saem com `recibo` nulo — o painel já trata. Status padrão da
  factory: pendente; conferir no arquivo e passar `'status' => StatusCobranca::Pendente` se não for.)

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/PainelTest.php`. Esperado: FAIL.

- [ ] **Passo 3: servidor.** Rota: `Route::get('/painel/dados', [PainelController::class, 'dados']);`. No
  controller (`use App\Support\BlocoDePaginas;`, `use Illuminate\Http\JsonResponse;`, `use Illuminate\Validation\Rule;`):
  no `__invoke`, apagar `$comRelacoes`, `$paraLista`, `$pendentes` e as props `paraEnviar`, `aguardando`, `recebidas`
  (com um comentário: "As três listas vêm por ajax de /painel/dados; só a página gera"). Acrescentar:

```php
    /** Uma lista do painel (JSON, só leitura — a geração preguiçosa fica na página): bloco de páginas, contagem e soma. */
    public function dados(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'lista' => ['required', Rule::in(['para_enviar', 'aguardando', 'recebidas'])],
            ...BlocoDePaginas::regras([10, 25, 50]),
        ]);
        $hoje = CarbonImmutable::today();
        $consulta = $request->user()->cobrancas();

        match ($filtros['lista']) {
            'para_enviar' => $consulta->where('status', StatusCobranca::Pendente)->whereNull('enviado_em'),
            'aguardando' => $consulta->where('status', StatusCobranca::Pendente)->whereNotNull('enviado_em'),
            'recebidas' => $consulta->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$hoje->startOfMonth(), $hoje->endOfMonth()]),
        };
        // Soma antes de ordenar: no PostgreSQL, sum() com ORDER BY de coluna é erro.
        $soma = (int) $consulta->toBase()->sum('valor_centavos');
        $filtros['lista'] === 'recebidas'
            ? $consulta->orderByDesc('pago_em')->orderByDesc('id')
            : $consulta->orderBy('vencimento')->orderBy('id');

        $bloco = BlocoDePaginas::de($consulta->with(['cliente:id,nome', 'recibo']), (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 10));

        return response()->json([
            'linhas' => $bloco['itens']->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje))->all(),
            'total_geral' => ['quantidade' => $bloco['pagina']['total'], 'soma_centavos' => $soma],
            'pagina' => $bloco['pagina'],
        ]);
    }
```

  (`toBase()` devolve o construtor de consulta da própria relação; `sum()` agrega num clone e não o altera.)

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS.

- [ ] **Passo 5: tela.** `resources/js/Components/ListaDoPainel.vue`:

```vue
<script setup>
import { reactive } from 'vue';
import EstadoDaLista from './EstadoDaLista.vue';
import LinhaCobranca from './LinhaCobranca.vue';
import Paginador from './Paginador.vue';
import { formatarCentavos } from '../itens.js';
import { usarListaPaginada } from '../usarListaPaginada.js';

/**
 * Uma lista do painel, carregada por conta própria (/painel/dados?lista=…): 10 por página, paginador só embaixo e só
 * com mais de uma página (seis paginadores numa tela de resumo pesariam demais); contagem e soma no título.
 */
const props = defineProps({
    lista: { type: String, required: true },
    titulo: { type: String, required: true },
    vazio: { type: String, required: true },
});

const filtro = reactive({ lista: props.lista, pagina: 1, por_pagina: 10 });
const { linhas, resumo, carregando, falha, estado, irPara, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/painel/dados', filtro, componente: 'Painel', idDoFoco: `lista-${props.lista}`,
});
</script>

<template>
    <section class="mt-8" :aria-labelledby="`titulo-${lista}`">
        <h2 :id="`titulo-${lista}`" class="mb-2 text-lg font-semibold">
            {{ titulo }}
            <span v-if="resumo" class="font-estreita font-normal text-grafite/70">
                ({{ resumo.total_geral.quantidade }})<template v-if="resumo.total_geral.quantidade"> · R$ {{ formatarCentavos(resumo.total_geral.soma_centavos) }}</template>
            </span>
        </h2>
        <EstadoDaLista :estado="estado" :falha="falha" :carregando="`Carregando ${titulo.toLowerCase()}…`" :vazio="vazio" :linhas="3" @tentar="recarregar">
            <ul
                :id="`lista-${lista}`"
                tabindex="-1"
                class="divide-y divide-linha border border-linha bg-white transition-opacity focus:outline-none"
                :class="{ 'opacity-60': carregando }"
                :aria-busy="carregando"
            >
                <LinhaCobranca v-for="cobranca in linhas" :key="cobranca.id" :cobranca="cobranca">
                    <slot :cobranca="cobranca" />
                </LinhaCobranca>
            </ul>
            <Paginador
                v-if="resumo.pagina.total > filtro.por_pagina"
                :id="`paginador-${lista}`"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                :opcoes="[10, 25, 50]"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
    </section>
</template>
```

  Em `Painel.vue`: tirar as props `paraEnviar`, `aguardando`, `recebidas`; importar `ListaDoPainel`; trocar as três
  `<section>` por:

```vue
        <ListaDoPainel
            v-slot="{ cobranca }"
            lista="para_enviar"
            titulo="Para enviar"
            :vazio="`Nada para enviar agora. Cobranças novas aparecem aqui ${diasAntecedencia} dias antes do vencimento.`"
        >
            <BotaoEnviar v-if="cobranca.recibo" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>

        <ListaDoPainel v-slot="{ cobranca }" lista="aguardando" titulo="Aguardando pagamento" vazio="Nenhuma cobrança enviada esperando pagamento.">
            <BaixaInline :cobranca-id="cobranca.id" />
            <PagamentoInline :cobranca-id="cobranca.id" :pagamento="cobranca.pagamento" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            <BotaoEnviar v-if="cobranca.recibo" rotulo="Reenviar" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>

        <ListaDoPainel v-slot="{ cobranca }" lista="recebidas" titulo="Recebidas no mês" vazio="Nenhum pagamento registrado neste mês.">
            <BotaoEnviar v-if="cobranca.recibo" rotulo="Enviar recibo pago" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
        </ListaDoPainel>
```

  (Depois de uma ação, as três listas recarregam: cada uma ouve o `success` da página `Painel`. O `resumo` do título
  da página vem atualizado no redirect do Inertia.)

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`. Esperado:
  build ok; suíte verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/PainelController.php routes/web.php resources/js/Components/ListaDoPainel.vue resources/js/Pages/Painel.vue tests/Feature/PainelTest.php
git commit -m "feat: painel com as três listas por ajax (10 por página, contagem e soma no título)"
```

### Tarefa T20.9: Relatórios por ajax

**Arquivos:**
- Criar: `resources/js/relatorios.js`
- Modificar: `app/Http/Controllers/RelatorioController.php` (`show`, novo `dados`, privados `periodo`,
  `erroDoPeriodo`, `tabela`), `routes/web.php`, `resources/js/Pages/Relatorios/Ver.vue` (reescrita),
  `resources/js/Pages/Relatorios/Index.vue` (lista vem de `relatorios.js`)
- Testes: `tests/Feature/Relatorios/FaturamentoTest.php`, `ReceitaTest.php`, `ContasAReceberTest.php`

**Interfaces:**
- Produz: `GET /relatorios/{tipo}/dados?de=&ate=&pagina=&por_pagina=` →
  `{ titulo, descricao, colunas, linhas (bloco), totais (do relatório inteiro), pagina, filtros }`. O faturamento
  devolve **todas** as linhas numa página só. Período inválido → 422 em `ate`.
- A página `Relatorios/Ver` recebe `tipo` e `filtros` (`de`, `ate`, `pagina`, `por_pagina`); sai `tabela`.
- `GET /relatorios/{tipo}?formato=csv` continua igual (todas as linhas; período inválido volta com erro).
- `RELATORIOS` e `relatorio(tipo)` em `relatorios.js`.

- [ ] **Passo 1: testes (falham).** Regra de conversão nos três arquivos: cada
  `get('/relatorios/X?…')->assertInertia(fn (Assert $page) => $page->…)` que lê a tabela vira
  `getJson('/relatorios/X/dados?…')->assertOk()` com:
  - `->has('tabela.linhas', N)` → `->assertJsonCount(N, 'linhas')`;
  - `->where('tabela.linhas.N…', v)` → `->assertJsonPath('linhas.N…', v)`;
  - `->where('tabela.totais…', v)` → `->assertJsonPath('totais…', v)`;
  - `->where('tabela.colunas…', v)` → `->assertJsonPath('colunas…', v)`;
  - `->where('filtros', [...])` → `->assertJsonPath('filtros', [... + 'pagina' => 1, 'por_pagina' => 25])`.

  Em `FaturamentoTest::test_periodo_invalido_e_tipo_desconhecido`, os dois erros de período passam a
  `getJson('/relatorios/faturamento/dados?…')->assertUnprocessable()->assertJsonValidationErrors(['ate' => '…'])` (mesmas
  mensagens); `?de=outubro` continua na página (`assertSessionHasErrors('de')`); acrescentar
  `$this->getJson('/relatorios/inexistente/dados')->assertNotFound();`. Testes novos:

```php
    // FaturamentoTest
    public function test_faturamento_nao_pagina_e_a_pagina_abre_sem_a_tabela(): void
    {
        $this->actingAs($this->usuario)->getJson('/relatorios/faturamento/dados?de=2024-05&ate=2026-10&por_pagina=25')->assertOk()
            ->assertJsonCount(30, 'linhas')
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 30, 'total' => 30]);

        $this->get('/relatorios/faturamento?de=2026-01&ate=2026-10')->assertInertia(fn (Assert $page) => $page
            ->component('Relatorios/Ver')
            ->missing('tabela')
            ->where('filtros', ['de' => '2026-01', 'ate' => '2026-10', 'pagina' => 1, 'por_pagina' => 25]));
    }

    // ReceitaTest (clientes pagos no período; o setUp do arquivo congela em 2026-10-15)
    public function test_por_cliente_pagina_com_os_totais_do_relatorio_inteiro_e_csv_inteiro(): void
    {
        foreach (range(1, 30) as $numero) {
            $cliente = Cliente::factory()->for($this->usuario)->create(['nome' => sprintf('Cliente %02d', $numero)]);
            Cobranca::factory()->for($this->usuario)->for($cliente)->create([
                'vencimento' => '2026-10-05', 'valor_centavos' => 1000, 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05',
            ]);
        }
        $this->actingAs($this->usuario);

        $primeira = $this->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10&por_pagina=25')->assertOk();
        $segunda = $this->getJson('/relatorios/clientes/dados?de=2026-10&ate=2026-10&por_pagina=25&pagina=2')->assertOk();

        $primeira->assertJsonPath('pagina.total', 30)->assertJsonCount(30, 'linhas'); // o bloco de 4 páginas cobre as 30
        $segunda->assertJsonPath('pagina.inicio', 2)->assertJsonCount(5, 'linhas');
        $this->assertSame($primeira->json('totais'), $segunda->json('totais'));
        $csv = $this->get('/relatorios/clientes?de=2026-10&ate=2026-10&formato=csv')->streamedContent();
        $this->assertSame(32, substr_count($csv, "\n")); // cabeçalho + 30 clientes + total
    }
```

  (Ajustar os campos da `Cobranca::factory()` ao que os outros testes do `ReceitaTest` já usam para "paga no período",
  se diferirem.)

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/Relatorios`. Esperado: FAIL (404 em `/dados`; `tabela`
  ainda na página).

- [ ] **Passo 3: servidor.** Rota, depois da `/relatorios/{tipo}`:
  `Route::get('/relatorios/{tipo}/dados', [RelatorioController::class, 'dados'])->whereIn('tipo', RelatorioController::TIPOS);`.
  No controller (`use App\Models\User;`, `use App\Support\BlocoDePaginas;`, `use Illuminate\Http\JsonResponse;`,
  `use Illuminate\Validation\ValidationException;`), trocar `show` por:

```php
    public function show(Request $request, string $tipo, Relatorios $relatorios): Response|RedirectResponse|StreamedResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate([
            'formato' => ['nullable', Rule::in(['csv'])],
            ...$this->regras(),
        ]);
        [$de, $ate] = $this->periodo($filtros, $hoje);

        // O CSV leva todas as linhas, pelo mesmo link de antes.
        if (($filtros['formato'] ?? null) === 'csv') {
            if ($erro = $this->erroDoPeriodo($de, $ate)) {
                return back()->withErrors(['ate' => $erro]);
            }

            return $this->csv($this->tabela($tipo, $relatorios, $request->user(), $de, $ate, $hoje), "relatorio-{$tipo}-{$de->format('Y-m')}-a-{$ate->format('Y-m')}.csv");
        }

        // A tabela vem por ajax de /relatorios/{tipo}/dados; o período é conferido lá (erro embaixo do "Até").
        return Inertia::render('Relatorios/Ver', ['tipo' => $tipo, 'filtros' => $this->paraTela($filtros, $de, $ate)]);
    }

    /** Dados do relatório (JSON, só leitura): um bloco de páginas; os totais são sempre do relatório inteiro. */
    public function dados(Request $request, string $tipo, Relatorios $relatorios): JsonResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate($this->regras());
        [$de, $ate] = $this->periodo($filtros, $hoje);
        if ($erro = $this->erroDoPeriodo($de, $ate)) {
            throw ValidationException::withMessages(['ate' => $erro]);
        }

        $tabela = $this->tabela($tipo, $relatorios, $request->user(), $de, $ate, $hoje);
        $linhas = collect($tabela['linhas']);
        // O faturamento não pagina: tem no máximo 36 linhas, e o gráfico precisa de todas.
        $bloco = $tipo === 'faturamento'
            ? BlocoDePaginas::de($linhas, 1, max(1, $linhas->count()))
            : BlocoDePaginas::de($linhas, (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 25));

        return response()->json([
            'titulo' => $tabela['titulo'],
            'descricao' => $tabela['descricao'],
            'colunas' => $tabela['colunas'],
            'linhas' => $bloco['itens']->all(),
            'totais' => $tabela['totais'],
            'pagina' => $bloco['pagina'],
            'filtros' => $this->paraTela($filtros, $de, $ate),
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function regras(): array
    {
        return ['de' => ['nullable', 'date_format:Y-m'], 'ate' => ['nullable', 'date_format:Y-m'], ...BlocoDePaginas::regras()];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} padrão: os últimos 12 meses */
    private function periodo(array $filtros, CarbonImmutable $hoje): array
    {
        return [
            CarbonImmutable::createFromFormat('Y-m-d', ($filtros['de'] ?? $hoje->startOfMonth()->subMonthsNoOverflow(11)->format('Y-m')).'-01')->startOfDay(),
            CarbonImmutable::createFromFormat('Y-m-d', ($filtros['ate'] ?? $hoje->format('Y-m')).'-01')->startOfDay(),
        ];
    }

    private function erroDoPeriodo(CarbonImmutable $de, CarbonImmutable $ate): ?string
    {
        return match (true) {
            $de->gt($ate) => 'O fim do período deve ser igual ou depois do início.',
            $de->diffInMonths($ate) >= 36 => 'Escolha um período de até 36 meses.',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function tabela(string $tipo, Relatorios $relatorios, User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        return match ($tipo) {
            'faturamento' => $relatorios->faturamento($usuario, $de, $ate, $hoje),
            'clientes' => $relatorios->porCliente($usuario, $de, $ate, $hoje),
            'produtos' => $relatorios->porProduto($usuario, $de, $ate, $hoje),
            'a-receber' => $relatorios->aReceber($usuario, $hoje),
        };
    }

    /** @return array{de: string, ate: string, pagina: int, por_pagina: int} */
    private function paraTela(array $filtros, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return ['de' => $de->format('Y-m'), 'ate' => $ate->format('Y-m'), 'pagina' => (int) ($filtros['pagina'] ?? 1), 'por_pagina' => (int) ($filtros['por_pagina'] ?? 25)];
    }
```

- [ ] **Passo 4:** mesmo comando do passo 2. Esperado: PASS (inclusive `test_csv_para_o_excel_em_portugues`, byte a
  byte, sem mudança).

- [ ] **Passo 5: tela.** `resources/js/relatorios.js` (a lista sai de `Relatorios/Index.vue`, que passa a importar
  `RELATORIOS` daqui):

```js
/** Os relatórios do menu: título e descrição (tela de lista e cabeçalho de cada relatório, antes de os dados chegarem). */
export const RELATORIOS = [
    { tipo: 'faturamento', titulo: 'Faturamento por período', descricao: 'Mês a mês: previsto, recebido e o que ficou em atraso.' },
    { tipo: 'clientes', titulo: 'Receita por cliente', descricao: 'Quanto cada cliente pagou, contratos ativos, valor médio e atrasos.' },
    { tipo: 'produtos', titulo: 'Receita por produto ou serviço', descricao: 'O que mais fatura, pelos itens das cobranças.' },
    { tipo: 'a-receber', titulo: 'Contas a receber', descricao: 'Pendentes por cliente: vencidas e o que vence em 30, 60 e 90 dias.' },
];

export const relatorio = (tipo) => RELATORIOS.find((item) => item.tipo === tipo);
```

  `resources/js/Pages/Relatorios/Ver.vue` inteiro:

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import EstadoDaLista from '../../Components/EstadoDaLista.vue';
import Paginador from '../../Components/Paginador.vue';
import { formatarCentavos } from '../../itens.js';
import { relatorio } from '../../relatorios.js';
import { usarListaPaginada } from '../../usarListaPaginada.js';

const props = defineProps({
    tipo: { type: String, required: true },
    filtros: { type: Object, required: true },
});

const cabecalho = relatorio(props.tipo);
const comPeriodo = props.tipo !== 'a-receber';
// O faturamento vem inteiro numa página: tem no máximo 36 linhas, e o gráfico precisa de todas.
const paginado = props.tipo !== 'faturamento';
const filtro = reactive({ ...props.filtros });
const { linhas, resumo, carregando, falha, erros, estado, irPara, filtrar, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: `/relatorios/${props.tipo}/dados`, filtro, componente: 'Relatorios/Ver', endereco: `/relatorios/${props.tipo}`,
});

const csv = computed(() => `/relatorios/${props.tipo}?${new URLSearchParams({ de: filtro.de, ate: filtro.ate, formato: 'csv' })}`);
const maximo = computed(() => Math.max(1, ...linhas.value.flatMap((linha) => [linha.previsto ?? 0, linha.recebido ?? 0])));
const largura = (centavos) => `${Math.round((centavos / maximo.value) * 100)}%`;
const alinhamento = (coluna) => (coluna.tipo === 'texto' ? 'text-left' : 'text-right tabular-nums');

function valor(coluna, conteudo) {
    if (conteudo === null || conteudo === undefined) {
        return '';
    }

    return coluna.tipo === 'dinheiro' ? `R$ ${formatarCentavos(conteudo)}` : conteudo;
}
</script>

<template>
    <CamadaApp>
        <Link href="/relatorios" class="font-estreita text-sm text-carbono underline">Voltar para relatórios</Link>
        <h1 class="mt-2 text-2xl font-semibold">{{ cabecalho.titulo }}</h1>
        <p class="mt-1 mb-6 max-w-3xl text-sm text-grafite/80">{{ resumo?.descricao ?? cabecalho.descricao }}</p>

        <div class="mb-6 flex flex-wrap items-end gap-4">
            <form v-if="comPeriodo" class="flex flex-wrap items-end gap-4" @submit.prevent="filtrar">
                <Campo id="de" rotulo="De" :erro="erros.de">
                    <input id="de" v-model="filtro.de" type="month" class="entrada" required @change="filtrar">
                </Campo>
                <Campo id="ate" rotulo="Até" :erro="erros.ate">
                    <input id="ate" v-model="filtro.ate" type="month" class="entrada" required :min="filtro.de" @change="filtrar">
                </Campo>
            </form>
            <a :href="csv" class="botao-secundario">Baixar CSV</a>
        </div>

        <EstadoDaLista
            :estado="estado"
            :falha="falha"
            carregando="Carregando relatório…"
            vazio="Nada para mostrar neste período."
            erro-filtro="Corrija o período acima para ver o relatório."
            @tentar="recarregar"
        >
            <figure v-if="tipo === 'faturamento'" class="mb-6 border border-linha bg-white p-4">
                <figcaption class="mb-3 flex flex-wrap gap-4 font-estreita text-xs text-grafite/80">
                    <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-linha" /> Previsto</span>
                    <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-tinta-verde" /> Recebido</span>
                </figcaption>
                <div aria-hidden="true" class="grid gap-2">
                    <div v-for="linha in linhas" :key="linha.mes" class="grid grid-cols-[4.5rem_1fr] items-center gap-3">
                        <span class="font-estreita text-xs tabular-nums">{{ linha.mes }}</span>
                        <span class="grid gap-1">
                            <span class="block h-2 bg-linha" :style="{ width: largura(linha.previsto) }" />
                            <span class="block h-2 bg-tinta-verde" :style="{ width: largura(linha.recebido) }" />
                        </span>
                    </div>
                </div>
            </figure>

            <Paginador v-if="paginado" id="paginador-topo" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
            <div class="relative overflow-x-auto border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ cabecalho.titulo }}</caption>
                    <thead class="font-estreita text-xs text-grafite/75">
                        <tr>
                            <th v-for="coluna in resumo.colunas" :key="coluna.chave" scope="col" class="px-3 py-2 font-medium" :class="alinhamento(coluna)">
                                {{ coluna.rotulo }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-linha">
                        <tr v-for="(linha, indice) in linhas" :key="indice">
                            <td v-for="coluna in resumo.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                                {{ valor(coluna, linha[coluna.chave]) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-linha font-semibold">
                        <tr>
                            <td v-for="coluna in resumo.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                                {{ valor(coluna, resumo.totais[coluna.chave]) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <Paginador v-if="paginado" id="paginador-base" :pagina="filtro.pagina" :por-pagina="filtro.por_pagina" :total="resumo.pagina.total" :carregando="carregando" @ir="irPara($event, { focar: true })" @por-pagina="escolheuPorPagina" />
        </EstadoDaLista>
    </CamadaApp>
</template>
```

  (Sem "Limpar filtros" nos relatórios: o período é sempre preenchido e não há outro filtro. O título vem de
  `relatorios.js`, então aparece antes dos dados.)

- [ ] **Passo 6:** `npm run build`; `vendor/bin/pint --dirty --format agent`; `php artisan test --compact`. Esperado:
  build ok; suíte verde.

- [ ] **Passo 7:** commit:

```bash
git add app/Http/Controllers/RelatorioController.php routes/web.php resources/js/relatorios.js resources/js/Pages/Relatorios tests/Feature/Relatorios
git commit -m "feat: relatórios por ajax (período ao escolher, paginação com totais do relatório inteiro, faturamento inteiro)"
```

### Tarefa T20.10: limpeza, teste "só lê", documentação, revisão e validação

**Arquivos:**
- Apagar: `resources/js/Components/Paginacao.vue`, `app/Support/Paginacao.php`
- Criar: `tests/Feature/DadosDasListasTest.php`
- Modificar: `.claude/skills/recibos/SKILL.md`, `.claude/plans/2026-10-10-roteiro-modulos.md`, este plano

- [ ] **Passo 1: teste de todos os `/dados` (deve passar já; é a rede de segurança).** `tests/Feature/DadosDasListasTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Todo `/dados` de lista: exige sessão (401 em JSON) e só lê — a geração preguiçosa fica nas páginas. */
class DadosDasListasTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array<string, array{string}> */
    public static function enderecos(): array
    {
        return [
            'cobranças' => ['/cobrancas/dados'],
            'clientes' => ['/clientes/dados'],
            'contratos' => ['/contratos/dados'],
            'produtos' => ['/produtos-servicos/dados'],
            'auditoria' => ['/auditoria/dados'],
            'usuários' => ['/admin/usuarios/dados'],
            'painel' => ['/painel/dados?lista=para_enviar'],
            'faturamento' => ['/relatorios/faturamento/dados'],
            'clientes (relatório)' => ['/relatorios/clientes/dados'],
            'produtos (relatório)' => ['/relatorios/produtos/dados'],
            'a receber' => ['/relatorios/a-receber/dados'],
        ];
    }

    #[DataProvider('enderecos')]
    public function test_sem_sessao_responde_401(string $endereco): void
    {
        $this->getJson($endereco)->assertUnauthorized();
    }

    #[DataProvider('enderecos')]
    public function test_so_le_mesmo_com_cobranca_devida(string $endereco): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $admin = User::factory()->admin()->create();
        Contrato::factory()->for($admin)->create(); // tem período devido: abrir uma página geraria a cobrança
        $escritas = [];
        DB::listen(function ($consulta) use (&$escritas) {
            if (preg_match('/^\s*(insert|update|delete)\b/i', $consulta->sql)) {
                $escritas[] = $consulta->sql;
            }
        });

        $this->actingAs($admin)->getJson($endereco)->assertOk();

        $this->assertSame([], $escritas);
        $this->assertSame(0, Cobranca::count());
    }
}
```

  Rodar: `php artisan test --compact tests/Feature/DadosDasListasTest.php`. Esperado: 22 passam. **Conferir que o
  teste pega o defeito:** numa cópia de trabalho, pôr `app(GeradorDeCobrancas::class)->gerarPara($request->user(), CarbonImmutable::today());`
  no começo de `ClienteController::dados` → o caso "clientes" falha; desfazer. Se um caso falhar por escrita de
  sessão do framework (não da aplicação), registrar no "Registro de execução" e filtrar a tabela `sessions` na
  expressão regular.

- [ ] **Passo 2: limpeza.** `grep -rn "Paginacao" app resources tests` deve mostrar só `Paginacao.vue` e
  `App\Support\Paginacao` (nenhum uso). Apagar os dois arquivos. `npm run build`; `php artisan test --compact`;
  `node --test resources/js/*.test.js`. Esperado: tudo verde.

- [ ] **Passo 3: documentação.** Na skill `recibos`:
  - padrão de usabilidade 12: "Lista longa carrega por ajax" vale para **todas as listas** (Fase 20), com a regra
    dos filtros (selects e datas na hora; texto 400 ms ou Enter; "Limpar filtros" = link para a tela sem parâmetros);
  - Acertos "Fase 20": `BlocoDePaginas` (contagem sem ORDER BY; somas antes de ordenar), `usarListaPaginada`,
    `EstadoDaLista`, `Etiqueta`, Painel (3 listas, 10 por página, paginador só embaixo), Relatórios (faturamento
    inteiro, totais do relatório inteiro, CSV inteiro), cobranças existentes numa consulta só;
  - pegadinhas e decisões que aparecerem no "Registro de execução".
  No roteiro, atualizar a seção "0. RETOMAR AQUI". Neste plano, estados das tarefas.

- [ ] **Passo 4: revisão final.** Pacote da branch desde o commit anterior à T20.1 (`git log --oneline`), revisão por
  subagente no modelo mais capaz (superpowers:requesting-code-review), com os "Pontos para a revisão final" deste
  plano. Critical/Important: uma rodada de correção com teste que falha antes; Minor: registrar como adiadas.

- [ ] **Passo 5: conferência no navegador (pede autorização nova: grava sessões no banco de dev).** Chrome de teste
  da porta 9333, usuário SMOKE, sem "dar baixa" a não ser com autorização à parte. Em cada tela: esqueleto, paginador
  (cache sem pedido novo, contado em `performance.getEntriesByType('resource')`), filtro de texto (1 pedido depois de
  parar de digitar), "Limpar filtros", F5, 1264 px e 390 px sem rolagem horizontal
  (`scrollWidth === clientWidth`). Prints com a aba ativada (`Target.activateTarget`).

- [ ] **Passo 6:** `git check-ignore -v .claude .claude/memory .claude/plans .claude/skills .claude/MEMORY.md` (saída
  vazia); commit e push:

```bash
git add -A .claude tests/Feature/DadosDasListasTest.php resources/js/Components app/Support
git commit -m "docs: Fase 20 na skill; limpeza da paginação antiga; teste de que os /dados só leem"
git push
```

- [ ] **Passo 7:** pedir a validação do usuário. Só ele muda as tarefas para **validada**.
