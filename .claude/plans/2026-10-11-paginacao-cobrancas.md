# Fase 19 — Lista de cobranças paginada por ajax, com totais por situação — plano de implementação

> **Para quem executar:** superpowers:executing-plans (inline), tarefa por tarefa, com TDD
> (superpowers:test-driven-development). Os passos usam checkbox (`- [ ]`). Os scripts `task-start`/`task-done` procuram
> "Task N"; o registro fica **na tabela de estado e no "Registro de execução"** abaixo, e nos commits.

**Objetivo:** a tela de cobranças carrega os dados por ajax, em blocos de 4 páginas guardados no navegador. Ganha um
paginador acima e outro abaixo da tabela, e um rodapé com os totais por situação do resultado inteiro.

**Arquitetura:**
- `GET /cobrancas` (Inertia) abre a tela: roda a geração preguiçosa e manda os filtros.
- `GET /cobrancas/dados` (JSON, só leitura) recebe filtros e página e devolve o bloco, os totais e a contagem. A
  montagem fica no serviço novo `ListaDeCobrancas`: lista leve, totais, fatia e só depois carrega as linhas completas
  do bloco.
- No navegador, `Index.vue` busca o JSON com `buscarJson` e guarda as páginas por chave de filtros. A URL é atualizada
  com `router.replace`, e a lista é recarregada no evento `success` do Inertia, que vem depois de uma ação.

**Tecnologias:** Laravel 13, PHPUnit 12, Inertia v3 (core 3.9.0), Vue 3, Tailwind 4, `node --test`.

**Especificação:** [docs/superpowers/specs/2026-10-11-paginacao-cobrancas-design.md](../../docs/superpowers/specs/2026-10-11-paginacao-cobrancas-design.md)
(aprovada pelo usuário em 2026-10-11: "estou de acordo").

Branch: `feat/modulo-cobranca-catalogo` (continua). **Sem migração, sem escrita em banco** (testes em SQLite).

## Restrições gerais
- Por página: **25, 50 ou 100** (padrão 25). Cada pedido traz um bloco de **4 páginas**.
- Ordem dos totais: `vencida`, `vence_hoje`, `a_vencer`, `prevista`, `paga`, `cancelada`. Situação sem linha não
  aparece. O **total geral não soma as canceladas**.
- O JSON **não grava nada**: a geração preguiçosa roda só em `GET /cobrancas`.
- Mensagens do período iguais às da Fase 18 e dos relatórios. No JSON, o erro volta como **422**.
- Os textos novos da tela são:
  - "Mostrando X–Y de N";
  - "Anterior" e "Próxima";
  - "Por página";
  - "Carregando cobranças…";
  - "Tentar de novo";
  - "Total" e "Total (sem canceladas)".
- pt-BR. Commits com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Depois de mexer em PHP, rodar
  `vendor/bin/pint --dirty --format agent`. Antes de commitar front-end, rodar `npm run build`.

## Pontos para a revisão final (casos que os testes não cobrem)
1. **Ação na última página que a deixa vazia.** Ex.: dar baixa na única cobrança da página 3 com o filtro
   "Pendentes". O recarregamento precisa cair na nova última página, sem tela vazia nem laço de pedidos.
2. **Cliques rápidos.** Vários cliques em páginas ou filtros: só a resposta mais recente vale (contador de pedidos).
3. **Evento `success` ao chegar na tela por um link.** Ele chega logo depois de montar a página. A guarda
   `!carregando` evita o pedido em dobro. Conferir no navegador.
4. **F5 e voltar.** A URL tem de levar `pagina` e `por_pagina`, e reabrir no mesmo lugar. O `router.replace` não pode
   empilhar uma entrada no histórico por página.
5. **Foco e leitor de tela.** Trocar de página leva o foco ao paginador de cima. A página atual tem
   `aria-current="page"`. O indicador de carregamento é anunciado (`role="status"`).

## Estado das tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T19.1 | `GET /cobrancas/dados` + serviço `ListaDeCobrancas` (lista leve, totais, bloco) + `valor_centavos` em `paraLista` | testada (RED 8× 404 → GREEN; Cobrancas 65/65; JSON sem sessão = 401) |
| T19.2 | `GET /cobrancas` sem `cobrancas`; filtros com `pagina`/`por_pagina`; testes antigos lendo o JSON | testada (RED: as 2 asserções da página falharam como esperado → GREEN; suíte inteira 340/340) |
| T19.3 | JS puro: `paginacao.js` (páginas visíveis, chave do cache, fatiar bloco) e `buscarJson` em `http.js` | testada (RED: módulo e export inexistentes → GREEN; JS 32/32) |
| T19.4 | Tela: carga por ajax com cache, `Paginador.vue` acima/abaixo, rodapé de totais, carregando/erro, URL, recarga após ação | concluída (build ok; JS 32/32; PHP 340/340); conferência no navegador na T19.5 |
| T19.5 | Skill, revisão final, conferência no navegador (com autorização), validação do usuário | pendente |

## Registro de execução (decisões tomadas durante a execução)
- **Verificado antes do plano** (o que estava marcado [A VERIFICAR] na especificação):
  - **Ações não remontam a página.** A documentação do Inertia v3 diz que `post`, `put`, `patch`, `delete` e `reload`
    usam `preserveState: true` por padrão. As ações da lista usam `form.post`/`put` e `router.post`. Por isso a
    recarga vem do evento global `success`.
  - **O evento `success` só sai de resposta do servidor.** No `@inertiajs/core` 3.9.0, `fireSuccessEvent` só é chamado
    depois da resposta (`dist/index.js:2715`) e não em erro de validação. As visitas feitas só no navegador
    (`router.replace` → `performClientVisit`) não disparam o evento.
  - **URL sem pedido ao servidor.** `router.replace({ url, props })` é a "client-side visit" da v3.
- **Pedido do usuário durante a execução (2026-10-11):** "se ficar bom, vamos implementar isto em todas as telas que
  contêm listas de dados". Depois da validação desta fase, abrir uma fase própria para levar o mesmo padrão às outras
  listas (ajax, cache por bloco, paginador acima e abaixo, totais quando houver valor). Entra junto com a T17.18
  (clientes e contratos em tabela).
- **Pegadinha (T19.4):** ao copiar o rodapé do plano por script, o filtro `'<tfoot' in bloco` pegou o bloco do
  esqueleto, que só cita `<tfoot>` num comentário. O build passou com o template errado. Corrigido escolhendo o bloco
  que **começa** com `<tfoot`, conferindo `count('<tfoot') == 1` e restaurando o arquivo do commit antes de refazer.
- **419 "Page Expired" (relato do usuário, 2026-10-11, excluir produto em `/produtos-servicos/6/edit`):** a exclusão
  estava certa; a sessão tinha trocado de id (às 08:29, a sessão de 01:26 venceu e o "lembrar de mim" fez o login de
  novo; de 08:31 em diante, 9 sessões novas em 2 minutos, todas do mesmo Chrome), e a página aberta ficou com um token
  vencido. **Correção aprovada:** `bootstrap/app.php` → `respond()`. Com 419 fora de JSON, volta para a mesma página
  com `erro` = "A página expirou. Tente de novo." (padrão da documentação do Inertia v3). JSON continua 419. Teste:
  `tests/Feature/PaginaExpiradaTest.php`. Observado no navegador com autorização (Chrome de teste, usuário SMOKE, banco de desenvolvimento):
  - no uso normal e com a sessão vencida e um pedido por vez, sai uma sessão só, e o token continua válido (sonda 404);
  - **com 3 pedidos em paralelo sem sessão, saem 3 sessões novas** de 184 bytes, o mesmo tamanho das do usuário: é o
    "lembrar de mim" refazendo o login em cada pedido;
  - o 419 em si não foi reproduzido **[INCONCLUSIVO]**; a correção da tela cobre o caso.

  O teste criou 5 linhas em `sessions` (usuário 2). Elas expiram sozinhas e não foram apagadas, porque apagar também
  é escrita no banco.
- **Pedido do usuário:** "Carregando cobranças…" com o indicador girando no fim do texto e uma tabela-esqueleto, como
  no YouTube (aprovado: "SIM"). Ajuste da T19.4.
- **Rótulos do rodapé no plural e em texto** ("Vencidas", "Previstas"…), não o carimbo rotacionado: a especificação
  diz "rótulo do carimbo", mas uma pilha de carimbos no rodapé pesa demais. Se o usuário preferir o carimbo, a troca
  é só no template.

---

### Tarefa T19.1: endpoint de dados e serviço `ListaDeCobrancas`

**Arquivos:**
- Criar: `app/Services/ListaDeCobrancas.php`
- Modificar: `app/Http/Controllers/CobrancaController.php` (métodos `dados`, `filtros`, `filtrosParaTela`; `index`
  passa a usar `filtros`)
- Modificar: `app/Models/Cobranca.php` (`paraLista` ganha `valor_centavos`)
- Modificar: `routes/web.php` (rota antes de `/cobrancas/create`)
- Teste: `tests/Feature/Cobrancas/DadosDaListaTest.php`

**Interfaces:**
- Consome (Fase 18): `PrevisaoDeCobrancas::entre(User, CarbonImmutable $de, CarbonImmutable $ate): list<array>`.
- Produz:
  - **`GET /cobrancas/dados?mes&ate&status&cliente_id&canceladas&pagina&por_pagina`** responde:
    `{ linhas, totais: [{situacao, quantidade, soma_centavos}], total_geral: {quantidade, soma_centavos}, pagina: {inicio, por_pagina, total}, filtros }`
  - **`ListaDeCobrancas::montar(User $usuario, array $filtros, CarbonImmutable $hoje): array`**
  - **`ListaDeCobrancas::PAGINAS_POR_BLOCO = 4`**
  - **`CobrancaController::filtros(Request, CarbonImmutable): array`** devolve:

    ```
    array{
        mes: CarbonImmutable,
        ate: CarbonImmutable,
        status: ?string,
        cliente_id: ?int,
        canceladas: bool,
        pagina: int,
        por_pagina: int,
    }
    ```

- [ ] **Passo 1: testes que falham** (`tests/Feature/Cobrancas/DadosDaListaTest.php`)

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DadosDaListaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    /** Cobranças avulsas de outubro, uma por dia a partir de 16/10 (todas "a vencer"), de R$ 1,00, 2,00, 3,00… */
    private function avulsas(int $quantidade): void
    {
        for ($dia = 0; $dia < $quantidade; $dia++) {
            Cobranca::factory()->for($this->usuario)->for($this->cliente)->create([
                'vencimento' => CarbonImmutable::parse('2026-10-16')->addDays(intdiv($dia, 4))->toDateString(),
                'valor_centavos' => ($dia + 1) * 100,
            ]);
        }
    }

    private function dados(string $consulta = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->usuario)->getJson('/cobrancas/dados'.$consulta);
    }

    public function test_devolve_um_bloco_de_quatro_paginas_a_partir_da_pedida(): void
    {
        $this->avulsas(60);

        $this->dados('?por_pagina=25')->assertOk()
            ->assertJsonCount(60, 'linhas') // 4 páginas de 25 = 100, mas só existem 60
            ->assertJsonPath('pagina', ['inicio' => 1, 'por_pagina' => 25, 'total' => 60]);

        $resposta = $this->dados('?por_pagina=25&pagina=2')->assertOk();
        $resposta->assertJsonCount(35, 'linhas')->assertJsonPath('pagina.inicio', 2);
        $this->assertSame('26,00', $resposta->json('linhas.0.valor'));
    }

    public function test_pagina_alem_da_ultima_devolve_a_ultima(): void
    {
        $this->avulsas(30);

        $this->dados('?por_pagina=25&pagina=9')->assertOk()
            ->assertJsonPath('pagina.inicio', 2)
            ->assertJsonCount(5, 'linhas');
        $this->dados('?por_pagina=25&pagina=1')->assertJsonPath('pagina.inicio', 1);
    }

    public function test_por_pagina_e_pagina_sao_validados(): void
    {
        $this->dados('?por_pagina=30')->assertUnprocessable()->assertJsonValidationErrors('por_pagina');
        $this->dados('?pagina=0')->assertUnprocessable()->assertJsonValidationErrors('pagina');
    }

    public function test_totais_por_situacao_somam_o_resultado_inteiro_sem_canceladas_no_total(): void
    {
        $this->avulsas(30); // R$ 1,00 + … + 30,00 = R$ 465,00, todas a vencer
        $paga = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-05', 'valor_centavos' => 1000]);
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-05'])->save();
        Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-10', 'valor_centavos' => 2000]); // vencida
        $cancelada = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(['vencimento' => '2026-10-11', 'valor_centavos' => 4000]);
        $cancelada->forceFill(['status' => StatusCobranca::Cancelada])->save();

        $this->dados('?canceladas=1&por_pagina=25')->assertOk()
            ->assertJsonCount(33, 'linhas')
            ->assertJsonPath('totais', [
                ['situacao' => 'vencida', 'quantidade' => 1, 'soma_centavos' => 2000],
                ['situacao' => 'a_vencer', 'quantidade' => 30, 'soma_centavos' => 46500],
                ['situacao' => 'paga', 'quantidade' => 1, 'soma_centavos' => 1000],
                ['situacao' => 'cancelada', 'quantidade' => 1, 'soma_centavos' => 4000],
            ])
            ->assertJsonPath('total_geral', ['quantidade' => 32, 'soma_centavos' => 49500]);

        // A página 2 mostra só uma parte, mas os totais continuam sendo do resultado inteiro.
        $this->dados('?canceladas=1&por_pagina=25&pagina=2')->assertJsonPath('total_geral.soma_centavos', 49500);
    }

    public function test_previstas_entram_mescladas_e_somadas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(); // dia 21, R$ 260,60, nada gerado aqui

        $resposta = $this->dados('?mes=2026-10&ate=2026-12')->assertOk();

        $this->assertSame(['prevista', 'prevista', 'prevista'], array_column($resposta->json('linhas'), 'situacao'));
        $resposta->assertJsonPath('totais', [['situacao' => 'prevista', 'quantidade' => 3, 'soma_centavos' => 78180]]);
    }

    public function test_linha_real_traz_valor_em_centavos_e_filtro_de_cliente_vale(): void
    {
        $this->avulsas(1);
        $outro = Cliente::factory()->for($this->usuario)->create();
        Cobranca::factory()->for($this->usuario)->for($outro)->create(['vencimento' => '2026-10-20', 'valor_centavos' => 999]);

        $this->dados("?cliente_id={$outro->id}")->assertOk()
            ->assertJsonCount(1, 'linhas')
            ->assertJsonPath('linhas.0.valor_centavos', 999)
            ->assertJsonPath('filtros.cliente_id', $outro->id);
    }

    public function test_so_dados_do_proprio_usuario_e_nada_gravado(): void
    {
        // Antes do actingAs (ele vale até o fim do teste): sem sessão, o JSON não responde.
        $this->getJson('/cobrancas/dados')->assertUnauthorized();
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        Cobranca::factory()->create(['vencimento' => '2026-10-20']); // de outro usuário
        $antes = [Cobranca::count(), Recibo::count()];

        $this->dados('?mes=2026-09&ate=2026-12')->assertOk()->assertJsonPath('pagina.total', 3); // 3 previstas, sem a alheia

        $this->assertSame($antes, [Cobranca::count(), Recibo::count()]);
    }

    public function test_periodo_invalido_responde_422_com_a_mensagem(): void
    {
        $this->dados('?mes=2026-10&ate=2026-09')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->dados('?mes=2026-01&ate=2029-01')->assertUnprocessable()
            ->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->dados('?ate=outubro')->assertUnprocessable()->assertJsonValidationErrors('ate');
    }
}
```

O `assertUnauthorized` vale se a rota `/cobrancas/dados` estiver no mesmo grupo autenticado de `/cobrancas`. Antes de
afirmar, conferir como o middleware `auth` responde a um pedido JSON sem sessão: `getJson` manda
`Accept: application/json`, e o padrão do Laravel é responder 401. Se o projeto redirecionar, ajustar a asserção para
o que o grupo faz e registrar a decisão.

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas/DadosDaListaTest.php`
Esperado: FAIL. A rota não existe: 404 em vez de 200 ou 422.

- [ ] **Passo 3: rota** (`routes/web.php`, logo depois de `Route::get('/cobrancas', …)`)

```php
    Route::get('/cobrancas/dados', [CobrancaController::class, 'dados']);
```

- [ ] **Passo 4: `paraLista`** (`app/Models/Cobranca.php`, depois da chave `'valor'`)

```php
            'valor_centavos' => $this->valor_centavos,
```

- [ ] **Passo 5: serviço** (`app/Services/ListaDeCobrancas.php`)

```php
<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Lista da tela de cobranças (reais + previstas) em blocos de páginas, com os totais do resultado inteiro. */
final class ListaDeCobrancas
{
    /** Páginas mandadas por pedido: o navegador guarda o bloco e troca de página sem pedir de novo. */
    public const PAGINAS_POR_BLOCO = 4;

    private const ORDEM = ['vencida', 'vence_hoje', 'a_vencer', 'prevista', 'paga', 'cancelada'];

    public function __construct(private PrevisaoDeCobrancas $previsao) {}

    /**
     * @param  array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, canceladas: bool, pagina: int, por_pagina: int}  $filtros
     * @return array{linhas: list<array<string, mixed>>, totais: list<array{situacao: string, quantidade: int, soma_centavos: int}>, total_geral: array{quantidade: int, soma_centavos: int}, pagina: array{inicio: int, por_pagina: int, total: int}}
     */
    public function montar(User $usuario, array $filtros, CarbonImmutable $hoje): array
    {
        $status = $filtros['status'];
        $consulta = $usuario->cobrancas()->orderBy('vencimento')->orderBy('id');

        // "Vencendo hoje" e "Vencidas" valem para qualquer mês: a vencida de setembro precisa aparecer em outubro.
        if (! in_array($status, ['vence_hoje', 'vencida'], true)) {
            $consulta->whereBetween('vencimento', [$filtros['mes'], $filtros['ate']->endOfMonth()]);
        }

        match ($status) {
            // "Todas" deixa as canceladas de fora, salvo com "Incluir canceladas" marcado (pedido do usuário).
            null => $filtros['canceladas'] ? null : $consulta->where('status', '!=', StatusCobranca::Cancelada),
            'vence_hoje' => $consulta->where('status', StatusCobranca::Pendente)->whereBetween('vencimento', [$hoje, $hoje->endOfDay()]),
            'vencida' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje),
            'a_vencer' => $consulta->where('status', StatusCobranca::Pendente)->where('vencimento', '>', $hoje->endOfDay()),
            default => $consulta->where('status', $status),
        };

        // Previstas (calculadas pelos contratos, nada gravado) só onde entram as em aberto e só depois de hoje.
        $previstas = in_array($status, [null, 'a_vencer', 'pendente'], true)
            ? $this->previsao->entre($usuario, $filtros['mes']->max($hoje->addDay()), $filtros['ate']->endOfMonth())
            : [];
        if ($filtros['cliente_id'] !== null) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
            $previstas = array_filter($previstas, fn (array $prevista) => $prevista['cliente_id'] === $filtros['cliente_id']);
        }

        // ponytail: a lista leve fica inteira na memória (até 36 meses); agregar no SQL se passar de dezenas de milhares de linhas.
        $leves = $consulta->get(['id', 'vencimento', 'status', 'valor_centavos'])
            ->map(fn (Cobranca $cobranca) => [
                'id' => $cobranca->id,
                'vencimento' => $cobranca->vencimento->toDateString(),
                'situacao' => $cobranca->situacao($hoje),
                'valor_centavos' => $cobranca->valor_centavos,
                'prevista' => null,
            ])
            ->concat(array_map(fn (array $prevista) => [
                'id' => $prevista['id'],
                'vencimento' => CarbonImmutable::createFromFormat('!d/m/Y', $prevista['vencimento'])->toDateString(),
                'situacao' => 'prevista',
                'valor_centavos' => $prevista['valor_centavos'],
                'prevista' => $prevista,
            ], array_values($previstas)))
            ->sortBy('vencimento') // estável: no mesmo dia, a real (que veio antes) fica antes da prevista
            ->values();

        $totais = collect(self::ORDEM)
            ->map(fn (string $situacao) => [
                'situacao' => $situacao,
                'quantidade' => $leves->where('situacao', $situacao)->count(),
                'soma_centavos' => (int) $leves->where('situacao', $situacao)->sum('valor_centavos'),
            ])
            ->where('quantidade', '>', 0)
            ->values()
            ->all();
        // Cancelada não é dinheiro a receber: fica fora do total.
        $aReceber = $leves->where('situacao', '!=', 'cancelada');

        $porPagina = $filtros['por_pagina'];
        $inicio = min($filtros['pagina'], max(1, (int) ceil($leves->count() / $porPagina)));
        $bloco = $leves->slice(($inicio - 1) * $porPagina, self::PAGINAS_POR_BLOCO * $porPagina)->values();

        // Só as linhas do bloco carregam recibo, cliente e histórico.
        $completas = $usuario->cobrancas()->with(['cliente:id,nome', 'recibo', 'auditorias'])
            ->findMany($bloco->whereNull('prevista')->pluck('id'))
            ->keyBy('id');

        return [
            'linhas' => $bloco->map(fn (array $leve) => $leve['prevista'] ?? $completas[$leve['id']]->paraLista($hoje))->all(),
            'totais' => $totais,
            'total_geral' => ['quantidade' => $aReceber->count(), 'soma_centavos' => (int) $aReceber->sum('valor_centavos')],
            'pagina' => ['inicio' => $inicio, 'por_pagina' => $porPagina, 'total' => $leves->count()],
        ];
    }
}
```

- [ ] **Passo 6: controller.** Acrescentar os imports `use App\Services\ListaDeCobrancas;`,
  `use Illuminate\Http\JsonResponse;` e `use Illuminate\Validation\ValidationException;`. Depois do `index`,
  acrescentar:

```php
    /** Dados da lista (JSON, só leitura): um bloco de páginas e os totais do resultado inteiro. */
    public function dados(Request $request, ListaDeCobrancas $lista): JsonResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $this->filtros($request, $hoje);

        // Mesmas mensagens dos relatórios; 422 em JSON, e a tela mostra embaixo do "Até".
        if ($filtros['mes']->gt($filtros['ate'])) {
            throw ValidationException::withMessages(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        }
        if ($filtros['mes']->diffInMonths($filtros['ate']) >= 36) {
            throw ValidationException::withMessages(['ate' => 'Escolha um período de até 36 meses.']);
        }

        return response()->json($lista->montar($request->user(), $filtros, $hoje) + ['filtros' => $this->filtrosParaTela($filtros)]);
    }

    /**
     * Filtros da lista validados só no formato (o período é conferido no JSON).
     *
     * @return array{mes: CarbonImmutable, ate: CarbonImmutable, status: ?string, cliente_id: ?int, canceladas: bool, pagina: int, por_pagina: int}
     */
    private function filtros(Request $request, CarbonImmutable $hoje): array
    {
        $validados = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'ate' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['pendente', 'vence_hoje', 'vencida', 'a_vencer', 'paga', 'cancelada'])],
            'cliente_id' => ['nullable', 'integer'],
            'canceladas' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'por_pagina' => ['nullable', Rule::in([25, 50, 100])],
        ]);
        $mes = CarbonImmutable::createFromFormat('Y-m-d', ($validados['mes'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        return [
            'mes' => $mes,
            'ate' => isset($validados['ate']) ? CarbonImmutable::createFromFormat('Y-m-d', $validados['ate'].'-01')->startOfDay() : $mes,
            'status' => $validados['status'] ?? null,
            'cliente_id' => empty($validados['cliente_id']) ? null : (int) $validados['cliente_id'],
            'canceladas' => (bool) ($validados['canceladas'] ?? false),
            'pagina' => (int) ($validados['pagina'] ?? 1),
            'por_pagina' => (int) ($validados['por_pagina'] ?? 25),
        ];
    }

    /** @return array{mes: string, ate: string, status: string, cliente_id: int|string, canceladas: bool, pagina: int, por_pagina: int} */
    private function filtrosParaTela(array $filtros): array
    {
        return [
            'mes' => $filtros['mes']->format('Y-m'),
            'ate' => $filtros['ate']->format('Y-m'),
            'status' => $filtros['status'] ?? '',
            'cliente_id' => $filtros['cliente_id'] ?? '',
            'canceladas' => $filtros['canceladas'],
            'pagina' => $filtros['pagina'],
            'por_pagina' => $filtros['por_pagina'],
        ];
    }
```

O `index` fica como está nesta tarefa e muda na T19.2.

- [ ] **Passo 7: rodar e ver passar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas`
Esperado: os 8 testes novos passam e os antigos continuam verdes, porque o `index` ainda não mudou.

- [ ] **Passo 8: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/ListaDeCobrancas.php app/Http/Controllers/CobrancaController.php app/Models/Cobranca.php routes/web.php tests/Feature/Cobrancas/DadosDaListaTest.php
git commit -m "feat: dados da lista de cobranças em JSON, em blocos de páginas e com totais por situação (T19.1)"
```

---

### Tarefa T19.2: página sem a lista; testes antigos lendo o JSON

**Arquivos:**
- Modificar: `app/Http/Controllers/CobrancaController.php` (`index` e a assinatura sem `PrevisaoDeCobrancas`)
- Modificar os testes:
  - `tests/Feature/Cobrancas/FiltroSituacaoTest.php:49`
  - `tests/Feature/Cobrancas/PrevisaoNaListaTest.php` (o helper `linhas` e os testes de período)
  - `tests/Feature/Cobrancas/TelasCobrancaTest.php:40-78`
  - `tests/Feature/AuditoriaTest.php:182-183`

**Interfaces:**
- Consome (T19.1): `filtros`, `filtrosParaTela`, `GET /cobrancas/dados`.
- Produz: `GET /cobrancas` com as props `filtros` (com `pagina` e `por_pagina`), `chavesPix`, `formasPagamento` e
  `clienteFiltro`. A prop `cobrancas` deixa de existir.

- [ ] **Passo 1: ajustar os testes para o comportamento novo (vão falhar).** Os testes que liam a lista da prop passam
  a abrir a página primeiro, porque é ela que gera as cobranças, e depois leem `linhas` do JSON.

`FiltroSituacaoTest::valores`:

```php
    private function valores(string $url): array
    {
        $this->actingAs($this->usuario)->get($url)->assertOk(); // a página gera as cobranças devidas
        $cobrancas = $this->getJson(str_replace('/cobrancas', '/cobrancas/dados', $url))->assertOk()->json('linhas');

        return array_map(fn (array $cobranca) => $cobranca['valor'], $cobrancas);
    }
```

`PrevisaoNaListaTest::linhas`:

```php
    private function linhas(string $consulta): array
    {
        $this->actingAs($this->usuario)->get('/cobrancas'.$consulta)->assertOk(); // a página gera as cobranças devidas

        return array_map(
            fn (array $linha) => [$linha['vencimento'], $linha['situacao']],
            $this->getJson('/cobrancas/dados'.$consulta)->assertOk()->json('linhas'),
        );
    }
```

Em `PrevisaoNaListaTest::test_ate_antes_do_mes_ou_mais_de_36_meses_e_recusado`, o período passa a ser recusado pelo
JSON, e a página não recusa mais:

```php
    public function test_ate_antes_do_mes_ou_mais_de_36_meses_e_recusado_no_json_sem_derrubar_a_pagina(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/cobrancas?mes=2026-10&ate=2026-09')->assertOk()->assertInertia(fn ($page) => $page->where('filtros.ate', '2026-09'));
        $this->getJson('/cobrancas/dados?mes=2026-10&ate=2026-09')->assertJsonValidationErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->getJson('/cobrancas/dados?mes=2026-01&ate=2029-01')->assertJsonValidationErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/cobrancas?ate=outubro')->assertSessionHasErrors('ate');
        $this->getJson('/cobrancas/dados?mes=2026-01&ate=2028-12')->assertOk();
    }
```

`TelasCobrancaTest`: trocar cada `->assertInertia(fn (Assert $page) => $page->has('cobrancas', N)->where('cobrancas.0.id', X))`
de `GET /cobrancas{?…}` por `GET` da página seguido de
`$this->getJson('/cobrancas/dados{?…}')->assertJsonCount(N, 'linhas')->assertJsonPath('linhas.0.id', X)`. O
`test_lista_do_mes_atual_so_do_usuario` também afirma que a página não manda mais a lista:
`->assertInertia(fn (Assert $page) => $page->component('Cobrancas/Index')->missing('cobrancas')->where('filtros.pagina', 1)->where('filtros.por_pagina', 25))`.

`AuditoriaTest:182-183`:

```php
        $this->getJson('/cobrancas/dados?mes=2026-05')->assertJsonPath('linhas.0.historico', ['10/10/2026 · Pagamento corrigido (antes: 17/06/2026)']);
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas tests/Feature/AuditoriaTest.php`
Esperado: só falham os testes da página: `missing('cobrancas')`, `filtros.pagina` e o período que ainda é recusado na
página. Os que leem o JSON passam, porque a T19.1 já entregou o endpoint.

- [ ] **Passo 3: `index` novo**

```php
    public function index(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $gerador->gerarPara($usuario, $hoje);

        // A lista vem por ajax de /cobrancas/dados; a página só abre a tela com os filtros (o período é conferido lá).
        $filtros = $this->filtros($request, $hoje);

        return Inertia::render('Cobrancas/Index', [
            'filtros' => $this->filtrosParaTela($filtros),
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'clienteFiltro' => $filtros['cliente_id'] === null ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
        ]);
    }
```

Remover os imports que ficarem sem uso: `PrevisaoDeCobrancas` e, se o Pint apontar, `RedirectResponse`. Este último
ainda é usado em `edit`, então só sai se o Pint indicar.

- [ ] **Passo 4: rodar e ver passar**

Rodar: `php artisan test --compact`
Esperado: a suíte inteira passa.

- [ ] **Passo 5: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CobrancaController.php tests/Feature
git commit -m "feat: página de cobranças abre só com os filtros; a lista vem do JSON (T19.2)"
```

---

### Tarefa T19.3: JS puro do paginador e busca de JSON

**Arquivos:**
- Criar: `resources/js/paginacao.js` e `resources/js/paginacao.test.js`
- Modificar: `resources/js/http.js` e `resources/js/http.test.js`

**Interfaces:**
- Produz:
  - `paginasVisiveis(atual: number, ultima: number): (number|'…')[]`
  - `chaveDoFiltro(filtro): string`
  - `fatiarBloco(linhas: array, inicio: number, porPagina: number): Record<number, array>`
  - `buscarJson(url): Promise<{dados}|{erros}|{mensagem}>`

- [ ] **Passo 1: testes que falham**

`resources/js/paginacao.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { chaveDoFiltro, fatiarBloco, paginasVisiveis } from './paginacao.js';

test('páginas visíveis: primeira, última, atual ±1 e reticências nos buracos', () => {
    assert.deepEqual(paginasVisiveis(1, 1), [1]);
    assert.deepEqual(paginasVisiveis(1, 3), [1, 2, 3]);
    assert.deepEqual(paginasVisiveis(5, 12), [1, '…', 4, 5, 6, '…', 12]);
    assert.deepEqual(paginasVisiveis(2, 12), [1, 2, 3, '…', 12]);
    assert.deepEqual(paginasVisiveis(12, 12), [1, '…', 11, 12]);
    assert.deepEqual(paginasVisiveis(3, 5), [1, 2, 3, 4, 5]);
});

test('chave do cache muda com os filtros e o por página, não com a página', () => {
    const base = { mes: '2026-10', ate: '2026-12', status: '', cliente_id: '', canceladas: false, por_pagina: 25, pagina: 1 };
    assert.equal(chaveDoFiltro(base), chaveDoFiltro({ ...base, pagina: 3 }));
    assert.notEqual(chaveDoFiltro(base), chaveDoFiltro({ ...base, por_pagina: 50 }));
    assert.notEqual(chaveDoFiltro(base), chaveDoFiltro({ ...base, canceladas: true }));
});

test('bloco do servidor vira linhas por página a partir da página inicial', () => {
    const linhas = Array.from({ length: 7 }, (_, indice) => indice + 1);
    assert.deepEqual(fatiarBloco(linhas, 5, 3), { 5: [1, 2, 3], 6: [4, 5, 6], 7: [7] });
    assert.deepEqual(fatiarBloco([], 1, 25), {});
});
```

No fim de `resources/js/http.test.js` (o import passa a ser `import { buscarJson, enviarJson } from './http.js';`):

```js
test('buscarJson faz GET sem corpo e avisa a falha como "carregar"', async () => {
    let pedido;
    globalThis.fetch = async (url, opcoes) => {
        pedido = { url, ...opcoes };
        return new Response(JSON.stringify({ linhas: [] }), { status: 200 });
    };

    assert.deepEqual(await buscarJson('/cobrancas/dados?pagina=2'), { dados: { linhas: [] } });
    assert.equal(pedido.method, 'GET');
    assert.equal(pedido.body, undefined);

    globalThis.fetch = async () => { throw new TypeError('sem rede'); };
    assert.deepEqual(await buscarJson('/x'), { mensagem: 'Não foi possível carregar agora. Tente de novo.' });
});
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `node --test resources/js/paginacao.test.js resources/js/http.test.js`
Esperado: FAIL. O módulo `paginacao.js` não existe e `buscarJson` não é exportado.

- [ ] **Passo 3: implementar**

`resources/js/paginacao.js`:

```js
/** Páginas do paginador: primeira, última e a atual ±1, com '…' onde pula. */
export function paginasVisiveis(atual, ultima) {
    const paginas = [...new Set([1, atual - 1, atual, atual + 1, ultima])]
        .filter((pagina) => pagina >= 1 && pagina <= ultima)
        .sort((a, b) => a - b);

    return paginas.flatMap((pagina, indice) => (indice > 0 && pagina - paginas[indice - 1] > 1 ? ['…', pagina] : [pagina]));
}

/** Chave do cache da lista: os filtros que mudam o resultado e o "por página" (a página não entra). */
export function chaveDoFiltro({ mes, ate, status, cliente_id: clienteId, canceladas, por_pagina: porPagina }) {
    return JSON.stringify([mes, ate, status, clienteId, Boolean(canceladas), Number(porPagina)]);
}

/** Bloco que o servidor mandou → linhas por página ({ 5: [...], 6: [...] }). */
export function fatiarBloco(linhas, inicio, porPagina) {
    const paginas = {};
    for (let indice = 0; indice < linhas.length; indice += porPagina) {
        paginas[inicio + indice / porPagina] = linhas.slice(indice, indice + porPagina);
    }

    return paginas;
}
```

Em `http.js`, extrair o corpo de `enviarJson` para uma função interna e acrescentar `buscarJson`:

```js
const FALHA = 'Não foi possível salvar agora. Tente de novo.';
const FALHA_AO_CARREGAR = 'Não foi possível carregar agora. Tente de novo.';

async function pedirJson(url, metodo, corpo, falha) {
    try {
        const resposta = await fetch(url, {
            method: metodo,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                ...(corpo === undefined ? {} : { 'Content-Type': 'application/json' }),
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': tokenCsrf(),
            },
            body: corpo,
        });
        // (o restante do corpo de enviarJson igual, trocando `FALHA` por `falha`)
    } catch {
        return { mensagem: falha };
    }
}

/**
 * Envia JSON sem sair da página (cadastros rápidos em modal). Devolve { dados }, { erros } (422, um texto por campo)
 * ou { mensagem }; nunca lança, para o formulário de fundo continuar intacto.
 */
export function enviarJson(url, dados, metodo = 'POST') {
    return pedirJson(url, metodo, JSON.stringify(dados), FALHA);
}

/** Busca JSON (GET) sem sair da página: listas carregadas por ajax. Mesmo retorno de enviarJson. */
export function buscarJson(url) {
    return pedirJson(url, 'GET', undefined, FALHA_AO_CARREGAR);
}
```

- [ ] **Passo 4: rodar e ver passar**

Rodar: `node --test resources/js/*.test.js`
Esperado: todos passam (28 + 4 = 32).

- [ ] **Passo 5: commit**

```bash
git add resources/js/paginacao.js resources/js/paginacao.test.js resources/js/http.js resources/js/http.test.js
git commit -m "feat: páginas do paginador, cache por filtro e busca de JSON (T19.3)"
```

---

### Tarefa T19.4: tela com carga por ajax, paginador e rodapé de totais

**Arquivos:**
- Criar: `resources/js/Components/Paginador.vue`
- Modificar: `resources/js/Pages/Cobrancas/Index.vue` (script e template)

**Interfaces:**
- Consome:
  - T19.1/T19.2: o JSON `{ linhas, totais, total_geral, pagina, filtros }` e a prop `filtros`, com `pagina` e
    `por_pagina`;
  - T19.3: `paginasVisiveis`, `chaveDoFiltro`, `fatiarBloco` e `buscarJson`;
  - `formatarCentavos` (`resources/js/itens.js`).
- Produz: o componente `Paginador` com as props `id`, `pagina`, `porPagina`, `total` e `carregando`, e os eventos
  `ir(pagina)` e `por-pagina(n)`.

Esta tarefa é de tela: a lógica com casos de borda já foi testada na T19.3. A verificação daqui é o build mais a
conferência no navegador da T19.5.

- [ ] **Passo 1: `Paginador.vue`**

```vue
<script setup>
import { computed } from 'vue';
import { paginasVisiveis } from '../paginacao.js';

/** Paginador da lista: "Mostrando X–Y de N", Anterior/números/Próxima e "Por página". Usado acima e abaixo da tabela. */
const props = defineProps({
    id: { type: String, required: true },
    pagina: { type: Number, required: true },
    porPagina: { type: Number, required: true },
    total: { type: Number, required: true },
    carregando: { type: Boolean, default: false },
});
const emit = defineEmits(['ir', 'por-pagina']);

const ultima = computed(() => Math.max(1, Math.ceil(props.total / props.porPagina)));
const de = computed(() => (props.pagina - 1) * props.porPagina + 1);
const ate = computed(() => Math.min(props.pagina * props.porPagina, props.total));
</script>

<template>
    <nav :id="id" tabindex="-1" aria-label="Páginas" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm focus:outline-none">
        <p class="flex items-center gap-2 font-estreita text-xs text-grafite/75">
            Mostrando {{ de }}–{{ ate }} de {{ total }}
            <span v-if="carregando" class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-carbono/25 border-t-carbono" aria-hidden="true" />
            <span v-if="carregando" class="sr-only" role="status">Carregando…</span>
        </p>
        <div v-if="ultima > 1" class="flex flex-wrap items-center gap-1">
            <button type="button" class="botao-secundario px-3" :disabled="pagina <= 1" @click="emit('ir', pagina - 1)">Anterior</button>
            <template v-for="(item, indice) in paginasVisiveis(pagina, ultima)" :key="`${indice}-${item}`">
                <span v-if="item === '…'" class="px-1 text-grafite/60" aria-hidden="true">…</span>
                <button
                    v-else
                    type="button"
                    :class="[item === pagina ? 'botao' : 'botao-secundario', 'min-w-10 px-2 tabular-nums']"
                    :aria-current="item === pagina ? 'page' : undefined"
                    :aria-label="`Página ${item}`"
                    @click="emit('ir', item)"
                >
                    {{ item }}
                </button>
            </template>
            <button type="button" class="botao-secundario px-3" :disabled="pagina >= ultima" @click="emit('ir', pagina + 1)">Próxima</button>
        </div>
        <label class="flex items-center gap-2 font-estreita text-xs text-grafite/75">
            Por página
            <select :value="porPagina" class="entrada w-auto py-1" @change="emit('por-pagina', Number($event.target.value))">
                <option v-for="opcao in [25, 50, 100]" :key="opcao" :value="opcao">{{ opcao }}</option>
            </select>
        </label>
    </nav>
</template>
```

- [ ] **Passo 2: script do `Index.vue`.** Tirar a prop `cobrancas`. Trocar `filtrar`, `escolheuPeriodo` e as
  `computed` que liam `props.cobrancas` (`linhas` e `temPrevistas`) pelo bloco abaixo. `escolheuMes` e `escolheuAte`
  continuam chamando `escolheuPeriodo`. Acrescentar ao import do Vue `onMounted` e `onUnmounted`, e incluir os
  imports de `buscarJson`, `chaveDoFiltro`, `fatiarBloco`, `formatarCentavos` e `Paginador`.

```js
// A lista vem de /cobrancas/dados em blocos de páginas; o navegador guarda as páginas por filtro e desenha só a atual.
const linhasDaPagina = ref([]);
const resumo = ref(null); // { totais, total_geral, pagina: { inicio, por_pagina, total } }
const carregando = ref(false);
const carregou = ref(false);
const falha = ref('');
const erroAte = ref('');
let cache = { chave: null, paginas: {} };
let pedido = 0; // só a resposta do pedido mais recente vale (cliques rápidos)

const parametros = (pagina) => new URLSearchParams(
    Object.entries({ ...filtro, canceladas: filtro.canceladas ? 1 : '', pagina })
        .filter(([, valor]) => valor !== '' && valor !== null && valor !== undefined),
).toString();

function mostrar(pagina, focar) {
    filtro.pagina = pagina;
    linhasDaPagina.value = cache.paginas[pagina] ?? [];
    faixa.value = null;
    // Filtros e página na URL (F5, voltar, link), sem pedido ao servidor nem entrada nova no histórico.
    router.replace({ url: `/cobrancas?${parametros(pagina)}`, props: (atuais) => ({ ...atuais, filtros: { ...filtro } }), preserveState: true, preserveScroll: true });
    if (focar) {
        nextTick(() => {
            const topo = document.getElementById('paginador-topo');
            topo?.scrollIntoView({ block: 'start' });
            topo?.focus({ preventScroll: true });
        });
    }
}

async function irPara(pagina, { focar = false } = {}) {
    const chave = chaveDoFiltro(filtro);
    if (cache.chave !== chave) {
        cache = { chave, paginas: {} };
    }
    if (cache.paginas[pagina]) {
        mostrar(pagina, focar);
        return;
    }

    const meu = ++pedido;
    carregando.value = true;
    const { dados, erros, mensagem } = await buscarJson(`/cobrancas/dados?${parametros(pagina)}`);
    if (meu !== pedido) {
        return; // chegou depois de um pedido mais novo: descarta
    }
    carregando.value = false;
    erroAte.value = erros?.ate ?? '';
    falha.value = erros ? '' : (mensagem ?? '');
    if (!dados) {
        return;
    }
    cache.paginas = { ...cache.paginas, ...fatiarBloco(dados.linhas, dados.pagina.inicio, dados.pagina.por_pagina) };
    resumo.value = dados;
    carregou.value = true;
    mostrar(dados.pagina.inicio, focar);
}

// Filtro novo: o cache dele começa vazio na página 1.
const filtrar = () => irPara(1);
const recarregar = () => {
    cache = { chave: null, paginas: {} };
    irPara(filtro.pagina);
};
function escolheuPeriodo() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrar();
}
function escolheuPorPagina(quantidade) {
    filtro.por_pagina = quantidade;
    filtrar();
}

// As ações (baixa, cancelar, enviar…) respondem com o redirect do Inertia, que preserva o estado da página (não
// remonta): o evento "success" só sai de resposta do servidor, então é a deixa para buscar a lista de novo.
let pararDeOuvir = () => {};
onMounted(() => {
    irPara(filtro.pagina);
    pararDeOuvir = router.on('success', (evento) => {
        if (evento.detail.page.component === 'Cobrancas/Index' && !carregando.value) {
            recarregar();
        }
    });
});
onUnmounted(() => pararDeOuvir());

const linhas = computed(() => linhasDaPagina.value.map((cobranca) => ({ ...cobranca, acoes: acoesDaCobranca(cobranca) })));
const temPrevistas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'prevista')));
const ROTULO_DO_TOTAL = { vencida: 'Vencidas', vence_hoje: 'Vencendo hoje', a_vencer: 'A vencer', prevista: 'Previstas', paga: 'Pagas', cancelada: 'Canceladas' };
const temCanceladas = computed(() => Boolean(resumo.value?.totais.some((total) => total.situacao === 'cancelada')));
```

O `vazio` continua lendo `props.filtros.status`, que o `router.replace` mantém atualizado.

- [ ] **Passo 3: template do `Index.vue`**
  1. **Campo "Até".** `:erro="erroAte || $page.props.errors?.ate"`.
  2. **No lugar do `<p v-if="!cobrancas.length">` e do `<div v-else …>` da tabela:**

```html
        <p v-if="!carregou && !falha" class="border border-linha bg-white px-4 py-3 text-sm text-grafite/75" role="status">
            Carregando cobranças…
        </p>
        <div v-else-if="falha" class="flex flex-wrap items-center gap-3 border border-tinta-vermelha bg-white px-4 py-3 text-sm">
            <span>{{ falha }}</span>
            <button type="button" class="botao-secundario" @click="recarregar">Tentar de novo</button>
        </div>
        <p v-else-if="!resumo.pagina.total" class="border border-carbono bg-white px-4 py-3 text-sm">
            {{ vazio }}
        </p>

        <template v-else>
            <Paginador
                id="paginador-topo"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
            <div class="border border-linha bg-white transition-opacity" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <!-- o TransitionGroup atual, sem mudança nas linhas, com o <tfoot> do passo 3 antes de </TransitionGroup> -->
            </div>
            <Paginador
                id="paginador-base"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </template>
```

  3. **Rodapé**, como último filho do `TransitionGroup` (filhos precisam de `key`):

```html
                <tfoot key="rodape" class="block border-t-2 border-carbono/20 font-estreita xl:table-footer-group">
                    <tr v-for="total in resumo.totais" :key="total.situacao" class="flex justify-between px-4 py-1.5 xl:table-row xl:p-0">
                        <td colspan="3" class="xl:px-4 xl:py-1.5">{{ ROTULO_DO_TOTAL[total.situacao] }} <span class="text-grafite/60">({{ total.quantidade }})</span></td>
                        <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-1.5">R$ {{ formatarCentavos(total.soma_centavos) }}</td>
                        <td colspan="3" class="hidden xl:table-cell" />
                    </tr>
                    <tr class="flex justify-between border-t border-linha px-4 py-2 font-semibold xl:table-row xl:p-0">
                        <td colspan="3" class="xl:px-4 xl:py-2">{{ temCanceladas ? 'Total (sem canceladas)' : 'Total' }} <span class="font-normal text-grafite/60">({{ resumo.total_geral.quantidade }})</span></td>
                        <td class="text-right whitespace-nowrap tabular-nums xl:px-3 xl:py-2">R$ {{ formatarCentavos(resumo.total_geral.soma_centavos) }}</td>
                        <td colspan="3" class="hidden xl:table-cell" />
                    </tr>
                </tfoot>
```

  A legenda de previstas fica como está e lê o novo `temPrevistas`.

- [ ] **Passo 4: build e suítes**

Rodar: `npm run build`, `node --test resources/js/*.test.js` e `php artisan test --compact`
Esperado: o build sem erro e as duas suítes verdes.

- [ ] **Passo 5: commit**

```bash
git add resources/js/Components/Paginador.vue resources/js/Pages/Cobrancas/Index.vue
git commit -m "feat: cobranças carregadas por ajax com paginador acima e abaixo e totais por situação (T19.4)"
```

---

### Tarefa T19.5: documentação, revisão e validação

- [ ] **Passo 1: skill.** Em `.claude/skills/recibos/SKILL.md`, registrar um "Acerto" da Fase 19:
  - JSON em blocos de 4 páginas, com totais do resultado inteiro;
  - a página só abre a tela e roda a geração; o JSON só lê;
  - recarga pela ação via evento `success`;
  - `router.replace` para a URL;
  - corrida entre pedidos resolvida com contador;
  - as pegadinhas encontradas.

  No padrão de usabilidade 12, acrescentar "paginação com cache por bloco" ao padrão de listas. Atualizar a T17.16:
  o rodapé de totais foi entregue aqui e as abas continuam pendentes.
- [ ] **Passo 2: revisão final.** Um subagente revisor, no modelo mais capaz, lê o diff desde `f053936`. Ele recebe a
  especificação, este plano e a seção "Pontos para a revisão final". Corrigir os achados Critical e Important com TDD.
- [ ] **Passo 3: conferência no navegador**, a 1280 e a 390 px, **com autorização**:
  - os paginadores;
  - o cache: ir da 1 à 4 sem pedido novo;
  - o rodapé;
  - dar baixa e ver a lista atualizar;
  - F5 numa página.
- [ ] **Passo 4: commit e push.** O push na branch tem autorização. Depois, pedir a validação do usuário.
