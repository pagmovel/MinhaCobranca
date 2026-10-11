# Módulo Cobrança — catálogo, itens, filtros, consulta CNPJ/CEP e relatórios — Plano de implementação

> **Para agentes executores:** SUB-SKILL OBRIGATÓRIA: use superpowers:executing-plans (ou
> superpowers:subagent-driven-development) para executar este plano tarefa a tarefa. Os passos usam
> checkbox (`- [ ]`) para acompanhamento.

**Objetivo:** contratos e cobranças montados por itens de um catálogo de produtos e serviços (valor = soma), reajuste de
preço por ação explícita, filtros e paginação em Clientes e Contratos, preenchimento por CNPJ/CEP a partir de serviços
públicos gratuitos e quatro relatórios (com CSV), com "recebido = pago no mês" também no painel.

**Arquitetura:** segue o projeto (Laravel 13 + Inertia v3 + Vue 3). Contas de item em `App\Support\ItensDeVenda` (pura,
só inteiros); regras de itens num trait de FormRequest (`RegrasDeItens`), no mesmo padrão de `RegrasDePagamento`; o
servidor recalcula sempre o valor e os códigos fiscais. Cobrança guarda **cópia** dos itens (como o recibo guarda
snapshot). Consultas externas em classes por provedor, chamadas só pelo servidor, com cache e limite. Relatórios num
serviço que devolve tabelas genéricas (colunas + linhas + totais), usadas pela tela e pelo CSV.

**Stack:** PHP 8.4 · Laravel 13.35 · Inertia v3 · Vue 3.5 · Tailwind 4 · PHPUnit 12 · Node 22 (`node --test`) ·
Carbon 3.14 (`diffInDays` com sinal) · nenhuma dependência nova.

**Spec:** [docs/superpowers/specs/2026-10-10-modulo-cobranca-catalogo-relatorios-design.md](../../docs/superpowers/specs/2026-10-10-modulo-cobranca-catalogo-relatorios-design.md)
— base: [2026-10-09-sistema-recibos-design.md](../../docs/superpowers/specs/2026-10-09-sistema-recibos-design.md) e
[2026-10-10-pagamento-e-formularios-design.md](../../docs/superpowers/specs/2026-10-10-pagamento-e-formularios-design.md).
Skill do domínio: `.claude/skills/recibos/SKILL.md` (**ler inteira antes de começar**). Roteiro do produto:
`.claude/plans/2026-10-10-roteiro-modulos.md`.

**Branch:** `feat/modulo-cobranca-catalogo` (saído do `main` em `d557f25`).

---

## Acompanhamento

Estados: `pendente` → `concluída` (código pronto) → `testada` (verificação rodada e passando) →
`validada` (usuário confirmou). Atualizar esta tabela **e** o campo **Estado** da tarefa no commit da tarefa.

| Fase | Tarefa | Estado |
|---|---|---|
| 12 | T12.1 Catálogo de produtos e serviços | testada (suíte 200/200; build ok) |
| 12 | T12.2 Itens no contrato | testada (suíte 213/213; build ok) |
| 12 | T12.3 Itens na cobrança (avulsa, edição e geração) | testada (suíte 219/219; build ok) |
| 12 | T12.4 Reajustar contratos | testada (suíte 224/224; build ok) |
| 12 | T12.5 PORTÃO-BANCO + fechamento da fase 12 | concluída (migrações no lote 4 com autorização; merge na main) |
| 13 | T13.1 Filtros e paginação de Clientes | testada (suíte 228/228; build ok) |
| 13 | T13.2 Filtros e paginação de Contratos | testada (suíte 231/231; JS 10/10; build ok) |
| 13 | T13.3 Fechamento da fase 13 | concluída (sem banco; merge na main) |
| 14 | T14.1 Consulta de CNPJ e CEP no servidor | testada (suíte 239/239) |
| 14 | T14.2 Botão "Buscar dados do CNPJ" e CEP automático | testada (JS 13/13; build ok; suíte 239/239; navegador na T15.5) |
| 14 | T14.3 Fechamento da fase 14 | concluída (sem banco; merge na main) |
| 15 | T15.1 Painel: recebido = pago no mês | testada (suíte 240/240) |
| 15 | T15.2 Relatórios: estrutura, faturamento por período e CSV | testada (suíte 245/245; build ok) |
| 15 | T15.3 Relatórios: receita por cliente e por produto/serviço | testada (suíte 248/248) |
| 15 | T15.4 Relatório: contas a receber | pendente |
| 15 | T15.5 Smoke test, revisão final e fechamento | pendente |

### Pendências para o final
- [ ] **V5** — conferência visual: seção de itens (contrato e cobrança), telas de catálogo e reajuste, filtros, e os
  4 relatórios (inclusive o gráfico de barras). — pendente
- (continuam valendo V1–V4, L1, M1–M7 dos planos `2026-10-09-sistema-recibos.md` e `2026-10-10-pagamento-e-formularios.md`)

---

## Restrições globais

Valem para todas as tarefas, além das dos planos anteriores (pt-BR, dinheiro em centavos, SQL portátil, isolamento por
relação + `findOrFail`, models com `#[Fillable]`, Inertia v3, context-mode, PowerShell com `;`, nunca supor):

- **Testes** no SQLite em memória (`phpunit.xml`), `LazilyRefreshDatabase`, `$this->travelTo()`. JS: `node --test
  resources/js/mascaras.test.js resources/js/itens.test.js resources/js/consultas.test.js` (os que existirem).
- **Commit por tarefa** só com `php artisan test` verde **e** `npm run build` sem erro; antes,
  `vendor/bin/pint --dirty --format agent`. Estado da tarefa (tabela + campo) e acertos/pegadinhas na skill `recibos`
  **no mesmo commit**.
- **Scripts `task-start`/`task-done`** procuram "Task N"; este plano usa "Tarefa": controlar BASE e ledger à mão.
- **Escrita no banco real** (PostgreSQL de desenvolvimento `207.180.205.211:1`, banco `recibos`, usuário
  `recibos_app`): só pelo PORTÃO-BANCO — `db:show --counts` e `migrate:status` (leitura), mostrar conexão, contagens,
  migrações pendentes e o comando literal, esperar o "sim" **daquele** comando, recontar em comando separado logo
  antes, executar e mostrar o resultado. Se a contagem mudou, parar e pedir de novo.
- **Fechamento de fase:** com todas as tarefas da fase `testada`: `git checkout main; git merge --no-ff
  feat/modulo-cobranca-catalogo -m "<mensagem>"; git push origin main; git checkout feat/modulo-cobranca-catalogo`
  (autorização permanente, memória `merge-e-push-por-fase`). Nunca force push.
- **Valor sempre calculado pelo servidor:** `valor_centavos` de contrato e de cobrança com itens = soma dos totais;
  qualquer `valor` enviado pela tela é ignorado. Total do item = `intdiv(milésimos × centavos + 500, 1000)`.
- **Requests com id na rota** dão 404 no `authorize()` **antes** da validação (pegadinha da skill, T10.1).
- **Toda FK escolhida pelo usuário** (`cliente_id`, `chave_pix_id`, `produto_servico_id`) validada com
  `Rule::exists(...)->where('user_id', ...)`.
- **Default de coluna** novo também em `$attributes` do model.
- **Serviços externos:** só ViaCEP e BrasilAPI, gratuitos e sem chave, `Http::timeout(5)`, chamados pelo servidor;
  **CPF nunca é consultado**; testes com `Http::preventStrayRequests()` + `Http::fake()` (nenhum teste vai à internet).
  O quadro de sócios (QSA) da resposta do CNPJ é descartado (dado pessoal).
- **Hook de design:** sem `border-l-4`; avisos com borda inteira; sem "·" como separador de metadados.
- **Comparação de datas no banco** com objetos Carbon (início/fim do dia), como o `PainelController` faz: no SQLite a
  coluna `date` guarda `Y-m-d H:i:s` e comparar com `'2026-10-31'` (texto) deixa o último dia de fora.

## Foco de revisão

1. **Quantidade fracionária e arredondamento** (1,5 × R$ 0,01; 0,333 × R$ 3,00; quantidade com 4 casas, `+1`, `1e3`)
   → total por item meio-para-cima, recusa clara do que não é número com até 3 casas, e soma = valor do contrato = valor
   da cobrança gerada — testes em **T12.2** (unitário + feature) e **T12.3**.
2. **Item de outro usuário, item repetido, item inativo já usado** → recusa (404 ou erro no campo); item inativo
   continua aceito ao editar o contrato que já o usa — testes em **T12.2**.
3. **Total zero ou acima do limite** (todos os preços 0; 99.999 × R$ 99.999.999,99; reajuste que estoura) → mensagem
   no formulário, nada gravado, sem erro 500 — testes em **T12.2** e **T12.4**.
4. **Serviço externo lento, fora do ar, 404, 500 com HTML ou JSON sem os campos** → "Não foi possível consultar agora;
   preencha manualmente.", nada vai para o cache e a próxima tentativa consulta de novo — testes em **T14.1**.
5. **Nome de cliente começando com `=`, `+`, `-` ou `@` no CSV** → célula vai como texto (sem fórmula no Excel) —
   teste em **T15.3**. E **relatórios batem com a lista**: pago em mês diferente do vencimento, cancelada fora, cobrança
   sem itens na linha "Sem item" — testes em **T15.1**, **T15.2** e **T15.3**.

---

## Fase 12 — Catálogo e itens (3 migrações)

### Tarefa T12.1: Catálogo de produtos e serviços

**Estado:** testada (suíte 200/200; build ok)

**Arquivos:**
- Criar: `app/Enums/TipoItem.php`, `app/Models/ProdutoServico.php`, `database/factories/ProdutoServicoFactory.php`,
  `database/migrations/2026_10_10_110000_create_produtos_servicos_table.php`, `app/Http/Requests/ProdutoServicoRequest.php`,
  `app/Http/Controllers/ProdutoServicoController.php`, `app/Support/Busca.php`, `app/Support/Paginacao.php`,
  `resources/js/Components/Paginacao.vue`, `resources/js/Pages/ProdutosServicos/Index.vue`,
  `resources/js/Pages/ProdutosServicos/Form.vue`
- Modificar: `app/Models/User.php`, `app/Rules/ValorEmReais.php`, `app/Providers/AppServiceProvider.php`,
  `routes/web.php`, `lang/pt_BR/validation.php`, `resources/js/Layouts/CamadaApp.vue`
- Teste: `tests/Feature/ProdutosServicosTest.php`, `tests/Feature/ValorEmReaisTest.php`

**Interfaces:**
- Produz: `enum TipoItem: string { Servico = 'servico'; Produto = 'produto' }` com `rotulo()`, `opcoes()`.
- Produz: `ProdutoServico` (tabela `produtos_servicos`; `preco_centavos` int, `ativo` bool, `tipo` TipoItem), `User::produtosServicos(): HasMany`.
- Produz: `ValorEmReais::MAXIMO_CENTAVOS` (público) e `new ValorEmReais(aceitaZero: true)`.
- Produz: `Busca::contem(string $termo): string` (padrão para `lower(col) like ? escape '\'`).
- Produz: `Paginacao::dados(LengthAwarePaginator): array{atual, ultima, total, anterior, proxima}` e o componente `Paginacao.vue` (prop `paginacao`).
- Produz: rotas `GET /produtos-servicos/busca?q=` → `[{id, nome, unidade, preco}]` (só ativos) e resource `produtos-servicos` (parâmetro `produto`).

- [ ] **Passo 1: testes que falham** — `tests/Feature/ProdutosServicosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\TipoItem;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProdutosServicosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'nome' => 'Hospedagem de site',
            'tipo' => 'servico',
            'unidade' => 'mês',
            'preco' => '89,90',
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'ativo' => true,
        ], $extra);
    }

    public function test_cria_item_convertendo_o_preco(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->post('/produtos-servicos', $this->dados())->assertRedirect('/produtos-servicos');

        $item = $usuario->produtosServicos()->sole();
        $this->assertSame(8990, $item->preco_centavos);
        $this->assertSame(TipoItem::Servico, $item->tipo);
        $this->assertSame('mês', $item->unidade);
        $this->assertTrue($item->ativo);
    }

    public function test_preco_zero_e_aceito_mas_negativo_e_texto_nao(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/produtos-servicos', $this->dados(['preco' => '0,00']))->assertRedirect('/produtos-servicos');
        $this->assertSame(0, $usuario->produtosServicos()->sole()->preco_centavos);

        $this->post('/produtos-servicos', $this->dados(['nome' => 'Outro', 'preco' => '-5,00']))
            ->assertSessionHasErrors(['preco' => 'Informe um valor válido, como 260,60.']);
        $this->post('/produtos-servicos', $this->dados(['nome' => 'Outro', 'preco' => 'abc']))
            ->assertSessionHasErrors('preco');
    }

    public function test_nome_repetido_so_conta_no_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem de site']);
        ProdutoServico::factory()->create(['nome' => 'Suporte mensal']);
        $this->actingAs($usuario);

        $this->post('/produtos-servicos', $this->dados())
            ->assertSessionHasErrors(['nome' => 'Este nome já está cadastrado.']);
        $this->post('/produtos-servicos', $this->dados(['nome' => 'Suporte mensal']))
            ->assertRedirect('/produtos-servicos');
    }

    public function test_lista_filtra_e_pagina_so_os_itens_do_usuario(): void
    {
        $usuario = User::factory()->create();
        ProdutoServico::factory()->for($usuario)->count(26)
            ->sequence(fn ($sequencia) => ['nome' => sprintf('Serviço %02d', $sequencia->index)])
            ->create();
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Caneta 50%', 'tipo' => TipoItem::Produto, 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Serviço de outro']);
        $this->actingAs($usuario);

        $this->get('/produtos-servicos')->assertInertia(fn (Assert $page) => $page
            ->component('ProdutosServicos/Index')
            ->has('itens', 25)
            ->where('itens.0.nome', 'Caneta 50%')
            ->where('paginacao.total', 27)
            ->where('paginacao.ultima', 2));
        $this->get('/produtos-servicos?page=2')->assertInertia(fn (Assert $page) => $page->has('itens', 2));
        $this->get('/produtos-servicos?tipo=produto&situacao=inativos')
            ->assertInertia(fn (Assert $page) => $page->has('itens', 1)->where('itens.0.nome', 'Caneta 50%'));
        // % digitado é texto, não curinga.
        $this->get('/produtos-servicos?nome=50%25')
            ->assertInertia(fn (Assert $page) => $page->has('itens', 1)->where('filtros.nome', '50%'));
        $this->get('/produtos-servicos?situacao=todos')->assertSessionHasErrors('situacao');
    }

    public function test_busca_traz_so_os_ativos_do_usuario(): void
    {
        $usuario = User::factory()->create();
        $ativo = ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem de site', 'preco_centavos' => 8990]);
        ProdutoServico::factory()->for($usuario)->create(['nome' => 'Hospedagem antiga', 'ativo' => false]);
        ProdutoServico::factory()->create(['nome' => 'Hospedagem de outro']);
        $this->actingAs($usuario);

        $this->getJson('/produtos-servicos/busca?q=HOSPED')->assertExactJson([
            ['id' => $ativo->id, 'nome' => 'Hospedagem de site', 'unidade' => 'mês', 'preco' => '89,90'],
        ]);
        $this->getJson('/produtos-servicos/busca?q=h')->assertExactJson([]);
    }

    public function test_edita_desativa_exclui_e_isola_item_alheio(): void
    {
        $usuario = User::factory()->create();
        $item = ProdutoServico::factory()->for($usuario)->create();
        $semUso = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->get("/produtos-servicos/{$item->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->component('ProdutosServicos/Form')->where('item.preco', '260,60'));
        $this->put("/produtos-servicos/{$item->id}", $this->dados(['ativo' => false]))->assertRedirect('/produtos-servicos');
        $this->assertFalse($item->fresh()->ativo);
        $this->delete("/produtos-servicos/{$semUso->id}")->assertRedirect('/produtos-servicos');
        $this->assertModelMissing($semUso);

        $this->get("/produtos-servicos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/produtos-servicos/{$alheio->id}", $this->dados(['nome' => '']))->assertNotFound();
        $this->delete("/produtos-servicos/{$alheio->id}")->assertNotFound();
        $this->assertModelExists($alheio);
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/produtos-servicos')->assertRedirect('/entrar');
        $this->getJson('/produtos-servicos/busca?q=ho')->assertUnauthorized();
    }
}
```

E em `tests/Feature/ValorEmReaisTest.php`, acrescentar:

```php
    public function test_zero_so_quando_aceito(): void
    {
        $this->assertFalse(validator(['valor' => '0,00'], ['valor' => [new ValorEmReais]])->passes());
        $this->assertTrue(validator(['valor' => '0,00'], ['valor' => [new ValorEmReais(aceitaZero: true)]])->passes());
    }
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/ProdutosServicosTest.php tests/Feature/ValorEmReaisTest.php`
  Esperado: FAIL (rotas 404, classe `ProdutoServico` inexistente, construtor de `ValorEmReais` sem parâmetro).

- [ ] **Passo 3: implementação**

`app/Enums/TipoItem.php`:

```php
<?php

namespace App\Enums;

enum TipoItem: string
{
    case Servico = 'servico';
    case Produto = 'produto';

    public function rotulo(): string
    {
        return match ($this) {
            self::Servico => 'Serviço',
            self::Produto => 'Produto',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }
}
```

`database/migrations/2026_10_10_110000_create_produtos_servicos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos_servicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('servico');
            $table->string('unidade', 20)->default('un');
            $table->unsignedBigInteger('preco_centavos')->default(0);
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos_servicos');
    }
};
```

`app/Models/ProdutoServico.php`:

```php
<?php

namespace App\Models;

use App\Enums\TipoItem;
use Database\Factories\ProdutoServicoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'nome', 'tipo', 'unidade', 'preco_centavos', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional', 'ativo',
])]
class ProdutoServico extends Model
{
    /** @use HasFactory<ProdutoServicoFactory> */
    use HasFactory;

    protected $table = 'produtos_servicos';

    /** Mesmos defaults da migração (objeto recém-criado precisa deles antes de ser relido). */
    protected $attributes = ['tipo' => 'servico', 'unidade' => 'un', 'preco_centavos' => 0, 'ativo' => true];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'preco_centavos' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`database/factories/ProdutoServicoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipoItem;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProdutoServico>
 */
class ProdutoServicoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => 'Serviço '.fake()->unique()->numberBetween(1, 9_999_999),
            'tipo' => TipoItem::Servico,
            'unidade' => 'mês',
            'preco_centavos' => 26060,
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'ativo' => true,
        ];
    }
}
```

`app/Models/User.php` — acrescentar depois de `recibos()`:

```php
    public function produtosServicos(): HasMany
    {
        return $this->hasMany(ProdutoServico::class);
    }
```

`app/Rules/ValorEmReais.php` (arquivo inteiro):

```php
<?php

namespace App\Rules;

use App\Support\Dinheiro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValorEmReais implements ValidationRule
{
    public const MAXIMO_CENTAVOS = 9_999_999_999; // R$ 99.999.999,99

    /** Preço de item do catálogo pode ser zero (item incluso, brinde); valor de cobrança não. */
    public function __construct(private bool $aceitaZero = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $centavos = is_string($value) ? Dinheiro::paraCentavos($value) : null;
        $minimo = $this->aceitaZero ? 0 : 1;

        if ($centavos === null || $centavos < $minimo || $centavos > self::MAXIMO_CENTAVOS) {
            $fail('Informe um valor válido, como 260,60.');
        }
    }
}
```

`app/Support/Busca.php`:

```php
<?php

namespace App\Support;

final class Busca
{
    /** Padrão para `lower(coluna) like ? escape '\'`: o texto digitado vale literalmente (% e _ não são curinga). */
    public static function contem(string $termo): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(trim($termo))).'%';
    }
}
```

`app/Support/Paginacao.php`:

```php
<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class Paginacao
{
    /** @return array{atual: int, ultima: int, total: int, anterior: ?string, proxima: ?string} */
    public static function dados(LengthAwarePaginator $pagina): array
    {
        return [
            'atual' => $pagina->currentPage(),
            'ultima' => $pagina->lastPage(),
            'total' => $pagina->total(),
            'anterior' => $pagina->previousPageUrl(),
            'proxima' => $pagina->nextPageUrl(),
        ];
    }
}
```

`app/Http/Requests/ProdutoServicoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\TipoItem;
use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProdutoServicoRequest extends FormRequest
{
    /** Item de outro usuário vira 404 antes da validação (o erro não pode revelar que ele existe). */
    public function authorize(): bool
    {
        $produto = $this->route('produto');

        abort_if($produto !== null && ! $this->user()->produtosServicos()->whereKey($produto)->exists(), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required', 'string', 'max:120',
                Rule::unique('produtos_servicos', 'nome')->where('user_id', $this->user()->id)->ignore($this->route('produto')),
            ],
            'tipo' => ['required', Rule::enum(TipoItem::class)],
            'unidade' => ['required', 'string', 'max:20'],
            'preco' => ['required', new ValorEmReais(aceitaZero: true)],
            'codigo_servico' => ['nullable', 'string', 'max:255'],
            'codigo_nbs' => ['nullable', 'string', 'max:20'],
            'codigo_tributacao_nacional' => ['nullable', 'string', 'max:20'],
            'ativo' => ['boolean'],
        ];
    }

    public function dados(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['preco']) + [
            'preco_centavos' => Dinheiro::paraCentavos($dados['preco']),
            'ativo' => $this->boolean('ativo', true),
        ];
    }
}
```

`app/Http/Controllers/ProdutoServicoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\TipoItem;
use App\Http\Requests\ProdutoServicoRequest;
use App\Models\ProdutoServico;
use App\Support\Busca;
use App\Support\Dinheiro;
use App\Support\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProdutoServicoController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'nome' => ['nullable', 'string', 'max:120'],
            'tipo' => ['nullable', Rule::enum(TipoItem::class)],
            'situacao' => ['nullable', Rule::in(['ativos', 'inativos'])],
        ]);

        $consulta = $request->user()->produtosServicos()->orderBy('nome');

        if (filled($filtros['nome'] ?? null)) {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($filtros['nome'])]);
        }
        if (filled($filtros['tipo'] ?? null)) {
            $consulta->where('tipo', $filtros['tipo']);
        }
        if (filled($filtros['situacao'] ?? null)) {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('ProdutosServicos/Index', [
            'itens' => collect($pagina->items())->map(fn (ProdutoServico $item) => [
                'id' => $item->id,
                'nome' => $item->nome,
                'tipo' => $item->tipo->rotulo(),
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
                'ativo' => $item->ativo,
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'nome' => $filtros['nome'] ?? '',
                'tipo' => $filtros['tipo'] ?? '',
                'situacao' => $filtros['situacao'] ?? '',
            ],
            'tipos' => TipoItem::opcoes(),
        ]);
    }

    /** Autosugestão dos formulários de contrato e cobrança: só itens ativos do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $itens = $request->user()->produtosServicos()->where('ativo', true)
            ->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($termo)])
            ->orderBy('nome')->limit(10)->get();

        return response()->json($itens->map(fn (ProdutoServico $item) => [
            'id' => $item->id,
            'nome' => $item->nome,
            'unidade' => $item->unidade,
            'preco' => Dinheiro::formatar($item->preco_centavos),
        ]));
    }

    public function create(): Response
    {
        return $this->formulario(null);
    }

    public function store(ProdutoServicoRequest $request): RedirectResponse
    {
        $request->user()->produtosServicos()->create($request->dados());

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço salvo.');
    }

    public function edit(Request $request, int $produto): Response
    {
        return $this->formulario($request->user()->produtosServicos()->findOrFail($produto));
    }

    public function update(ProdutoServicoRequest $request, int $produto): RedirectResponse
    {
        $request->user()->produtosServicos()->findOrFail($produto)->update($request->dados());

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço salvo. Contratos existentes mantêm o preço deles.');
    }

    public function destroy(Request $request, int $produto): RedirectResponse
    {
        $request->user()->produtosServicos()->findOrFail($produto)->delete();

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço excluído.');
    }

    private function formulario(?ProdutoServico $item): Response
    {
        return Inertia::render('ProdutosServicos/Form', [
            'item' => $item ? [
                'id' => $item->id,
                'nome' => $item->nome,
                'tipo' => $item->tipo->value,
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
                'codigo_servico' => $item->codigo_servico ?? '',
                'codigo_nbs' => $item->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $item->codigo_tributacao_nacional ?? '',
                'ativo' => $item->ativo,
            ] : null,
            'tipos' => TipoItem::opcoes(),
        ]);
    }
}
```

`app/Providers/AppServiceProvider.php` — incluir `'produto'` na lista de padrões numéricos:

```php
        foreach (['cliente', 'contrato', 'cobranca', 'recibo', 'usuario', 'nota', 'chave', 'produto'] as $parametro) {
```

`routes/web.php` — `use App\Http\Controllers\ProdutoServicoController;` e, logo depois da linha do resource `contratos`:

```php
    Route::get('/produtos-servicos/busca', [ProdutoServicoController::class, 'busca']);
    Route::resource('produtos-servicos', ProdutoServicoController::class)
        ->except('show')->parameters(['produtos-servicos' => 'produto']);
```

`lang/pt_BR/validation.php` — em `attributes`, acrescentar:

```php
        'preco' => 'preço',
        'unidade' => 'unidade',
        'situacao' => 'situação',
```

`resources/js/Layouts/CamadaApp.vue` — no array `itens`, depois de Clientes:

```js
    { href: '/produtos-servicos', rotulo: 'Produtos e serviços' },
```

`resources/js/Components/Paginacao.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ paginacao: { type: Object, required: true } });
</script>

<template>
    <nav v-if="paginacao.ultima > 1" aria-label="Paginação" class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <Link v-if="paginacao.anterior" :href="paginacao.anterior" class="botao-secundario">Página anterior</Link>
        <span v-else />
        <span class="font-estreita text-grafite/80">Página {{ paginacao.atual }} de {{ paginacao.ultima }} ({{ paginacao.total }} no total)</span>
        <Link v-if="paginacao.proxima" :href="paginacao.proxima" class="botao-secundario">Próxima página</Link>
        <span v-else />
    </nav>
</template>
```

`resources/js/Pages/ProdutosServicos/Index.vue`:

```vue
<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    itens: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    tipos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });

const filtrar = () => router.get('/produtos-servicos', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Produtos e serviços</h1>
            <Link href="/produtos-servicos/create" class="botao">Novo produto ou serviço</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="filtrar">
            <Campo id="nome" rotulo="Nome" class="sm:col-span-2">
                <input id="nome" v-model="filtro.nome" type="search" class="entrada">
            </Campo>
            <Campo id="tipo" rotulo="Tipo">
                <select id="tipo" v-model="filtro.tipo" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="situacao" rotulo="Situação">
                <select id="situacao" v-model="filtro.situacao" class="entrada">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="inativos">Inativos</option>
                </select>
            </Campo>
            <div class="sm:col-span-4">
                <button class="botao-secundario">Filtrar</button>
            </div>
        </form>

        <p v-if="!itens.length" class="border border-linha bg-white px-4 py-3 text-sm">
            Nenhum produto ou serviço com estes filtros. Cadastre o que você vende para montar contratos por itens.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="item in itens" :key="item.id" :class="{ 'opacity-60': !item.ativo }">
                <Link :href="`/produtos-servicos/${item.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ item.nome }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ item.preco }} por {{ item.unidade }}</span>
                    <span class="font-estreita text-xs text-grafite/70">{{ item.tipo }}{{ item.ativo ? '' : ', inativo' }}</span>
                </Link>
            </li>
        </ul>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
```

`resources/js/Pages/ProdutosServicos/Form.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    item: { type: Object, default: null },
    tipos: { type: Array, required: true },
});

const form = useForm(props.item ?? {
    nome: '',
    tipo: 'servico',
    unidade: 'mês',
    preco: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    ativo: true,
});

const confirmandoExclusao = ref(false);

const salvar = () => (props.item ? form.put(`/produtos-servicos/${props.item.id}`) : form.post('/produtos-servicos'));
const excluir = () => router.delete(`/produtos-servicos/${props.item.id}`);
</script>

<template>
    <CamadaApp>
        <Link href="/produtos-servicos" class="font-estreita text-sm text-carbono underline">Voltar para produtos e serviços</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ item ? 'Editar produto ou serviço' : 'Novo produto ou serviço' }}</h1>

        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="nome" rotulo="Nome" :erro="form.errors.nome" class="sm:col-span-4">
                <input id="nome" v-model="form.nome" maxlength="120" class="entrada" required>
            </Campo>
            <Campo id="tipo" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="tipo" v-model="form.tipo" class="entrada">
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="preco" rotulo="Preço sugerido (R$)" :erro="form.errors.preco" class="sm:col-span-3">
                <input id="preco" v-model="form.preco" v-mascara="'valor'" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="unidade" rotulo="Unidade (ex.: mês, hora, un)" :erro="form.errors.unidade" class="sm:col-span-3">
                <input id="unidade" v-model="form.unidade" maxlength="20" class="entrada" required>
            </Campo>
            <Campo id="codigo_servico" rotulo="Serviço prestado (ex.: 0102 - Programação)" :erro="form.errors.codigo_servico" class="sm:col-span-6">
                <input id="codigo_servico" v-model="form.codigo_servico" class="entrada">
            </Campo>
            <Campo id="codigo_nbs" rotulo="Código NBS" :erro="form.errors.codigo_nbs" class="sm:col-span-3">
                <input id="codigo_nbs" v-model="form.codigo_nbs" class="entrada">
            </Campo>
            <Campo id="codigo_tributacao_nacional" rotulo="Código de tributação nacional" :erro="form.errors.codigo_tributacao_nacional" class="sm:col-span-3">
                <input id="codigo_tributacao_nacional" v-model="form.codigo_tributacao_nacional" class="entrada">
            </Campo>
            <label class="flex items-center gap-2 text-sm sm:col-span-6">
                <input v-model="form.ativo" type="checkbox"> Ativo (desmarque para não oferecer mais em contratos e cobranças novos)
            </label>
            <div class="flex flex-wrap gap-3 sm:col-span-6">
                <button class="botao" :disabled="form.processing">Salvar</button>
                <template v-if="item">
                    <button v-if="!confirmandoExclusao" type="button" class="botao-perigo" @click="confirmandoExclusao = true">Excluir</button>
                    <template v-else>
                        <button type="button" class="botao-perigo-confirmar" @click="excluir">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmandoExclusao = false">Manter</button>
                    </template>
                </template>
            </div>
        </form>
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/ProdutosServicosTest.php tests/Feature/ValorEmReaisTest.php`
  Esperado: PASS. Depois a suíte inteira (`php artisan test --compact`) e `npm run build`. Esperado: tudo verde.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; na skill `recibos`, acerto "T12.1: catálogo
  `produtos_servicos` (único por usuário+nome), preço sugerido pode ser 0 (`ValorEmReais(aceitaZero: true)`), busca só
  de ativos, `Busca::contem` e `Paginacao::dados` compartilhados". Pint, depois:

```bash
git add -A
git commit -m "feat: catálogo de produtos e serviços com filtros e paginação (módulo Cobrança)"
```

---
### Tarefa T12.2: Itens no contrato

**Estado:** testada (suíte 213/213; build ok)

**Arquivos:**
- Criar: `database/migrations/2026_10_10_110100_create_contrato_itens_table.php`, `app/Models/ContratoItem.php`,
  `app/Support/ItensDeVenda.php`, `app/Http/Requests/Concerns/RegrasDeItens.php`, `resources/js/itens.js`,
  `resources/js/itens.test.js`, `resources/js/Components/SecaoItens.vue`, `tests/Concerns/ComItens.php`
- Renomear: `resources/js/Components/BuscaCliente.vue` → `resources/js/Components/BuscaAutosugestao.vue` (`git mv`)
- Modificar: `app/Models/Contrato.php`, `app/Models/ProdutoServico.php`, `app/Http/Requests/ContratoRequest.php`,
  `app/Http/Controllers/ContratoController.php`, `app/Http/Controllers/ProdutoServicoController.php`,
  `lang/pt_BR/validation.php`, `resources/js/Pages/Contratos/Form.vue`, `resources/js/Pages/Cobrancas/Form.vue`
  (só o import/tag renomeado), `resources/js/Pages/Cobrancas/Index.vue` (só o import/tag renomeado),
  `tests/Feature/ContratosTest.php`, `tests/Feature/Cobrancas/ProtecoesTest.php`, `tests/Feature/FormaPagamentoTest.php`
- Teste: `tests/Unit/ItensDeVendaTest.php`, `tests/Feature/ItensDoContratoTest.php`

**Interfaces:**
- Consome (T12.1): `ProdutoServico`, `User::produtosServicos()`, `ValorEmReais::MAXIMO_CENTAVOS`, `new ValorEmReais(aceitaZero: true)`.
- Produz: `ItensDeVenda::milesimos(string|int|float): ?int`, `::quantidade(int): string` (`"1.500"`),
  `::formatarQuantidade(int): string` (`"1,5"`), `::total(int $milesimos, int $centavos): int`,
  `::indicePrincipal(list<array{total_centavos:int, principal:bool}>): int`.
- Produz: trait `RegrasDeItens` com `prepararItens(): void`, `regrasDeItens(): array`, `after(): array`,
  `itensCalculados(): list<array{produto_servico_id:int, nome:string, unidade:string, quantidade:string,
  preco_unitario_centavos:int, total_centavos:int, principal:bool, ordem:int}>`, `resumoDosItens(): array{valor_centavos:int,
  codigo_servico:?string, codigo_nbs:?string, codigo_tributacao_nacional:?string}`, `itensDoContrato(): list<array>`.
- Produz: `ContratoItem` (tabela `contrato_itens`, `quantidade` cast `decimal:3` → string `"1.500"`, `total(): int`),
  `Contrato::itens(): HasMany` (ordenado por `ordem`), `ProdutoServico::contratoItens(): HasMany`.
- Produz: formato dos itens no formulário (props e envio): `{produto_servico_id, nome, unidade, quantidade: "1,5", preco: "260,60", principal: bool}`.
- Produz: `BuscaAutosugestao.vue` (props novas `url`, `nenhum`, `limparAoEscolher`; evento `escolhido(opcao)`; slot `opcao`)
  e `SecaoItens.vue` (prop `form` com `form.itens`).
- Produz (teste): trait `Tests\Concerns\ComItens` com `itensPara(Cliente $cliente, string $preco = '260,60', string $quantidade = '1'): array`.

- [ ] **Passo 1: testes que falham**

`tests/Unit/ItensDeVendaTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\ItensDeVenda;
use PHPUnit\Framework\TestCase;

class ItensDeVendaTest extends TestCase
{
    public function test_le_quantidade_com_virgula_ou_ponto_e_ate_tres_casas(): void
    {
        $this->assertSame(1500, ItensDeVenda::milesimos('1,5'));
        $this->assertSame(1500, ItensDeVenda::milesimos('1.5'));
        $this->assertSame(2000, ItensDeVenda::milesimos(2));
        $this->assertSame(333, ItensDeVenda::milesimos('0,333'));
        $this->assertSame(1500, ItensDeVenda::milesimos('1.500'));
        $this->assertNull(ItensDeVenda::milesimos('1,2345'));
        $this->assertNull(ItensDeVenda::milesimos('-1'));
        $this->assertNull(ItensDeVenda::milesimos('+1'));
        $this->assertNull(ItensDeVenda::milesimos('1e3'));
        $this->assertNull(ItensDeVenda::milesimos('abc'));
    }

    public function test_total_arredonda_meio_centavo_para_cima_so_com_inteiros(): void
    {
        $this->assertSame(39090, ItensDeVenda::total(1500, 26060)); // 1,5 × 260,60 = 390,90
        $this->assertSame(2, ItensDeVenda::total(1500, 1));          // 1,5 × 0,01 = 0,015 → 0,02
        $this->assertSame(1, ItensDeVenda::total(1499, 1));          // 0,01499 → 0,01
        $this->assertSame(100, ItensDeVenda::total(333, 300));       // 0,333 × 3,00 = 0,999 → 1,00
        $this->assertSame(0, ItensDeVenda::total(1000, 0));
    }

    public function test_quantidade_para_o_banco_e_para_a_tela(): void
    {
        $this->assertSame('1.500', ItensDeVenda::quantidade(1500));
        $this->assertSame('0.033', ItensDeVenda::quantidade(33));
        $this->assertSame('1,5', ItensDeVenda::formatarQuantidade(1500));
        $this->assertSame('2', ItensDeVenda::formatarQuantidade(2000));
        $this->assertSame('0,033', ItensDeVenda::formatarQuantidade(33));
    }

    public function test_principal_e_o_primeiro_marcado_ou_o_de_maior_total(): void
    {
        $item = fn (int $total, bool $principal = false) => ['total_centavos' => $total, 'principal' => $principal];

        $this->assertSame(1, ItensDeVenda::indicePrincipal([$item(500), $item(100, true), $item(900, true)]));
        $this->assertSame(2, ItensDeVenda::indicePrincipal([$item(500), $item(100), $item(900)]));
        $this->assertSame(0, ItensDeVenda::indicePrincipal([$item(500), $item(500)]));
    }
}
```

`tests/Concerns/ComItens.php`:

```php
<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\ProdutoServico;

trait ComItens
{
    /** Um item novo no catálogo do dono do cliente, no formato que os formulários enviam. */
    protected function itensPara(Cliente $cliente, string $preco = '260,60', string $quantidade = '1'): array
    {
        $item = ProdutoServico::factory()->create(['user_id' => $cliente->user_id]);

        return [['produto_servico_id' => $item->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => true]];
    }
}
```

`tests/Feature/ItensDoContratoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItensDoContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const TOTAL_FORA = 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.';

    private const QUANTIDADE = 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).';

    /** @return array{0: User, 1: Cliente} */
    private function usuarioComCliente(): array
    {
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    private function dados(Cliente $cliente, array $itens, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id,
            'descricao' => 'Hospedagem e suporte',
            'periodicidade' => 'mensal',
            'modalidade' => 'prepago',
            'dia_vencimento' => 10,
            'data_inicio' => '2026-10-01',
            'ativo' => true,
            'itens' => $itens,
        ], $extra);
    }

    private function item(ProdutoServico $produto, string $quantidade, string $preco, bool $principal = false): array
    {
        return ['produto_servico_id' => $produto->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => $principal];
    }

    public function test_valor_e_a_soma_dos_itens_e_codigos_vem_do_principal(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $hospedagem = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => '0103 - Hospedagem', 'codigo_nbs' => '111']);
        $suporte = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => '0107 - Suporte', 'codigo_nbs' => '222']);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($hospedagem, '1', '89,90'),
            $this->item($suporte, '1,5', '120,00', principal: true),
        ], ['valor' => '1,00']))->assertRedirect('/contratos'); // o valor enviado pela tela é ignorado

        $contrato = $usuario->contratos()->sole();
        $this->assertSame(8990 + 18000, $contrato->valor_centavos);
        $this->assertSame('0107 - Suporte', $contrato->codigo_servico);
        $this->assertSame('222', $contrato->codigo_nbs);
        $this->assertSame(['1.000', '1.500'], $contrato->itens->pluck('quantidade')->all());
        $this->assertSame([false, true], $contrato->itens->pluck('principal')->all());
    }

    public function test_sem_principal_marcado_vale_o_de_maior_total(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $barato = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => 'barato']);
        $caro = ProdutoServico::factory()->for($usuario)->create(['codigo_servico' => 'caro']);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($barato, '1', '89,90'),
            $this->item($caro, '2', '120,00'),
        ]))->assertRedirect('/contratos');

        $contrato = $usuario->contratos()->sole();
        $this->assertSame('caro', $contrato->codigo_servico);
        $this->assertSame([false, true], $contrato->itens->pluck('principal')->all());
    }

    public function test_arredonda_cada_item_meio_centavo_para_cima(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $hora = ProdutoServico::factory()->for($usuario)->create();
        $copia = ProdutoServico::factory()->for($usuario)->create();

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            $this->item($hora, '1,5', '0,01'),
            $this->item($copia, '0,333', '3,00'),
        ]))->assertRedirect('/contratos');

        $this->assertSame(2 + 100, $usuario->contratos()->sole()->valor_centavos);
    }

    public function test_recusa_lista_vazia_item_alheio_repetido_e_quantidade_invalida(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $proprio = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, []))
            ->assertSessionHasErrors(['itens' => 'Inclua pelo menos um produto ou serviço.']);
        $this->post('/contratos', $this->dados($cliente, [$this->item($alheio, '1', '10,00')]))
            ->assertSessionHasErrors(['itens.0.produto_servico_id' => 'Escolha um produto ou serviço do seu catálogo.']);
        $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, '1', '10,00'), $this->item($proprio, '2', '10,00')]))
            ->assertSessionHasErrors(['itens.1.produto_servico_id' => 'Este produto ou serviço já está na lista.']);
        foreach (['0', '-1', '1,2345', '+1', '1e3', 'abc', '100000'] as $quantidade) {
            $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, $quantidade, '10,00')]))
                ->assertSessionHasErrors(['itens.0.quantidade' => self::QUANTIDADE]);
        }
        $this->post('/contratos', $this->dados($cliente, [$this->item($proprio, '1', '-1,00')]))
            ->assertSessionHasErrors(['itens.0.preco' => 'Informe um valor válido, como 260,60.']);

        $this->assertSame(0, Contrato::count());
    }

    public function test_total_zero_ou_acima_do_limite_e_recusado(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->dados($cliente, [$this->item($produto, '1', '0,00')]))
            ->assertSessionHasErrors(['itens' => self::TOTAL_FORA]);
        $this->post('/contratos', $this->dados($cliente, [$this->item($produto, '99999', '99.999.999,99')]))
            ->assertSessionHasErrors(['itens' => self::TOTAL_FORA]);

        $this->assertSame(0, Contrato::count());
    }

    public function test_item_inativo_continua_aceito_e_editar_troca_os_itens(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $antigo = ProdutoServico::factory()->for($usuario)->create(['ativo' => false]);
        $novo = ProdutoServico::factory()->for($usuario)->create(['nome' => 'Suporte por hora', 'unidade' => 'hora']);
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, [$this->item($antigo, '1', '50,00')]))
            ->assertRedirect('/contratos');
        $this->assertSame(5000, $contrato->fresh()->valor_centavos);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, [$this->item($novo, '2', '10,00')]))
            ->assertRedirect('/contratos');
        $this->assertSame([$novo->id], $contrato->itens()->pluck('produto_servico_id')->all());
        $this->assertSame(2000, $contrato->fresh()->valor_centavos);

        $this->get("/contratos/{$contrato->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('contrato.itens.0.nome', 'Suporte por hora')
            ->where('contrato.itens.0.unidade', 'hora')
            ->where('contrato.itens.0.quantidade', '2')
            ->where('contrato.itens.0.preco', '10,00')
            ->where('contrato.itens.0.principal', true));
    }

    public function test_contrato_antigo_sem_itens_abre_com_lista_vazia_e_exige_itens_ao_salvar(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $this->actingAs($usuario);

        $this->get("/contratos/{$contrato->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('contrato.itens', [])
            ->where('contrato.valor', '260,60'));
        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, []))->assertSessionHasErrors('itens');

        $this->assertSame(26060, $contrato->fresh()->valor_centavos);
    }

    public function test_contrato_alheio_da_404_antes_da_validacao(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $proprio = Contrato::factory()->for($usuario)->for($cliente)->create();
        $alheio = Contrato::factory()->create();
        $this->actingAs($usuario);

        $this->put("/contratos/{$proprio->id}", $this->dados($cliente, [$this->item($produto, '1', '10,00')]))
            ->assertRedirect('/contratos');
        // Dados inválidos: sem o 404 no authorize, a resposta seria 302 com erros (revelaria que o contrato existe).
        $this->put("/contratos/{$alheio->id}", $this->dados($cliente, []))->assertNotFound();
    }

    public function test_item_usado_em_contrato_nao_e_excluido(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $produto = ProdutoServico::factory()->for($usuario)->create();
        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [$this->item($produto, '1', '10,00')]));

        $this->delete("/produtos-servicos/{$produto->id}")
            ->assertSessionHas('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        $this->assertModelExists($produto);
    }
}
```

`resources/js/itens.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatarCentavos, milesimos, totalDoItem } from './itens.js';

test('quantidade com vírgula ou ponto, até 3 casas', () => {
    assert.equal(milesimos('1,5'), 1500);
    assert.equal(milesimos('2'), 2000);
    assert.equal(milesimos('0.333'), 333);
    assert.equal(milesimos('1,2345'), null);
    assert.equal(milesimos(''), null);
});

test('total igual ao do servidor (meio centavo para cima)', () => {
    assert.equal(totalDoItem('1,5', '260,60'), 39090);
    assert.equal(totalDoItem('1,5', '0,01'), 2);
    assert.equal(totalDoItem('0,333', '3,00'), 100);
    assert.equal(totalDoItem('abc', '3,00'), 0);
    assert.equal(totalDoItem('99999', '99.999.999,99'), 999989999900001);
});

test('formata centavos como a tela', () => {
    assert.equal(formatarCentavos(39090), '390,90');
    assert.equal(formatarCentavos(5), '0,05');
    assert.equal(formatarCentavos(123456789), '1.234.567,89');
});
```

(Conferência do último total: 99.999.000 milésimos × 9.999.999.999 centavos = 999.989.999.900.001.000; + 500; ÷ 1000 = 999.989.999.900.001.)

Nos testes existentes, trocar o valor direto por itens (o contrato passou a exigir itens):

`tests/Feature/ContratosTest.php` — `use Tests\Concerns\ComItens;` no topo, `use LazilyRefreshDatabase, ComItens;` na
classe; em `dados()`, trocar as linhas `'codigo_servico' ...`, `'codigo_nbs' ...`, `'codigo_tributacao_nacional' ...` e
`'valor' => '260,60',` por:

```php
            'itens' => $this->itensPara($cliente),
```

e, em `test_datas_dia_de_vencimento_e_valor_invalidos`, trocar as duas últimas linhas por:

```php
        $this->post('/contratos', $this->dados($cliente, ['itens' => $this->itensPara($cliente, '0,00')]))
            ->assertSessionHasErrors(['itens' => 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.']);
```

`tests/Feature/Cobrancas/ProtecoesTest.php` — `use Tests\Concerns\ComItens;`, `use LazilyRefreshDatabase, ComItens;`; em
`$dados`, trocar `'valor' => '300,00',` por `'itens' => $this->itensPara($contrato->cliente, '300,00'),`.

`tests/Feature/FormaPagamentoTest.php` — `use Tests\Concerns\ComItens;`, `use LazilyRefreshDatabase, ComItens;`; em
`contrato()`, trocar `'valor' => '260,60',` por `'itens' => $this->itensPara($cliente),` (o `avulsa()` muda na T12.3).

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Unit/ItensDeVendaTest.php tests/Feature/ItensDoContratoTest.php tests/Feature/ContratosTest.php` e `node --test resources/js/itens.test.js`
  Esperado: FAIL (`ItensDeVenda` e `itens.js` inexistentes; tabela `contrato_itens` ausente).

- [ ] **Passo 3: implementação**

`app/Support/ItensDeVenda.php`:

```php
<?php

namespace App\Support;

/** Contas dos itens de contrato e cobrança: só inteiros (quantidade em milésimos, preço em centavos). */
final class ItensDeVenda
{
    /** "1,5", "1.5", "2" ou 2 → milésimos (1500, 2000). Null se não for número com até 3 casas. */
    public static function milesimos(string|int|float $quantidade): ?int
    {
        $texto = str_replace(',', '.', trim((string) $quantidade));

        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $texto, $partes)) {
            return null;
        }

        return (int) $partes[1] * 1000 + (int) str_pad($partes[2] ?? '0', 3, '0');
    }

    /** Milésimos → "1.500" (coluna decimal(10,3)). */
    public static function quantidade(int $milesimos): string
    {
        return intdiv($milesimos, 1000).'.'.str_pad((string) ($milesimos % 1000), 3, '0', STR_PAD_LEFT);
    }

    /** Milésimos → "1,5", "2", "0,033" (como a pessoa digita). */
    public static function formatarQuantidade(int $milesimos): string
    {
        $decimais = rtrim(str_pad((string) ($milesimos % 1000), 3, '0', STR_PAD_LEFT), '0');

        return intdiv($milesimos, 1000).($decimais === '' ? '' : ','.$decimais);
    }

    /** Quantidade × preço em centavos, meio centavo para cima. */
    public static function total(int $milesimos, int $precoCentavos): int
    {
        return intdiv($milesimos * $precoCentavos + 500, 1000);
    }

    /**
     * Item principal: o primeiro marcado; sem marcado, o de maior total (empate: o primeiro).
     *
     * @param  list<array{total_centavos: int, principal: bool}>  $itens
     */
    public static function indicePrincipal(array $itens): int
    {
        foreach ($itens as $indice => $item) {
            if ($item['principal']) {
                return $indice;
            }
        }

        $maior = 0;
        foreach ($itens as $indice => $item) {
            if ($item['total_centavos'] > $itens[$maior]['total_centavos']) {
                $maior = $indice;
            }
        }

        return $maior;
    }
}
```

`database/migrations/2026_10_10_110100_create_contrato_itens_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_servico_id')->constrained('produtos_servicos')->restrictOnDelete();
            $table->decimal('quantidade', 10, 3);
            $table->unsignedBigInteger('preco_unitario_centavos');
            $table->boolean('principal')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_itens');
    }
};
```

`app/Models/ContratoItem.php`:

```php
<?php

namespace App\Models;

use App\Support\ItensDeVenda;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produto_servico_id', 'quantidade', 'preco_unitario_centavos', 'principal', 'ordem'])]
class ContratoItem extends Model
{
    protected $table = 'contrato_itens';

    /** Mesmos defaults da migração. */
    protected $attributes = ['principal' => false, 'ordem' => 0];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario_centavos' => 'integer',
            'principal' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function produtoServico(): BelongsTo
    {
        return $this->belongsTo(ProdutoServico::class);
    }

    public function total(): int
    {
        return ItensDeVenda::total(ItensDeVenda::milesimos($this->quantidade), $this->preco_unitario_centavos);
    }
}
```

`app/Models/Contrato.php` — acrescentar depois de `cobrancas()`:

```php
    public function itens(): HasMany
    {
        return $this->hasMany(ContratoItem::class)->orderBy('ordem');
    }
```

(`DELETE` no PostgreSQL não leva o `ORDER BY` da relação: `Grammar::compileDeleteWithoutJoins` monta só `delete from … where …` —
conferido no framework instalado.)

`app/Models/ProdutoServico.php` — `use Illuminate\Database\Eloquent\Relations\HasMany;` e, depois de `user()`:

```php
    public function contratoItens(): HasMany
    {
        return $this->hasMany(ContratoItem::class);
    }
```

`app/Http/Requests/Concerns/RegrasDeItens.php`:

```php
<?php

namespace App\Http\Requests\Concerns;

use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Itens do catálogo: iguais no contrato e na cobrança. Valor e códigos fiscais saem sempre daqui, nunca da tela. */
trait RegrasDeItens
{
    /** @var list<array<string, mixed>>|null */
    private ?array $itensCalculados = null;

    /** @var array{codigo_servico: ?string, codigo_nbs: ?string, codigo_tributacao_nacional: ?string} */
    private array $codigosDoPrincipal = ['codigo_servico' => null, 'codigo_nbs' => null, 'codigo_tributacao_nacional' => null];

    /** Quantidade digitada com vírgula ("1,5") vira ponto antes da validação numérica. */
    protected function prepararItens(): void
    {
        $itens = $this->input('itens');

        if (! is_array($itens)) {
            return;
        }

        $this->merge(['itens' => array_map(
            fn ($item) => is_array($item) && is_scalar($item['quantidade'] ?? null)
                ? ['quantidade' => str_replace(',', '.', trim((string) $item['quantidade']))] + $item
                : $item,
            $itens,
        )]);
    }

    protected function regrasDeItens(): array
    {
        return [
            'itens' => ['required', 'array', 'min:1', 'max:50'],
            'itens.*.produto_servico_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('produtos_servicos', 'id')->where('user_id', $this->user()->id),
            ],
            'itens.*.quantidade' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,5}(\.\d{1,3})?$/'],
            'itens.*.preco' => ['required', new ValorEmReais(aceitaZero: true)],
            'itens.*.principal' => ['nullable', 'boolean'],
        ];
    }

    /** O total precisa caber num valor em reais: nem zero, nem acima do máximo. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $soma = array_sum(array_column($this->itensCalculados(), 'total_centavos'));

                if ($soma < 1 || $soma > ValorEmReais::MAXIMO_CENTAVOS) {
                    $validator->errors()->add('itens', 'O total dos itens deve ficar entre R$ 0,01 e R$ 99.999.999,99.');
                }
            },
        ];
    }

    /** @return list<array{produto_servico_id: int, nome: string, unidade: string, quantidade: string, preco_unitario_centavos: int, total_centavos: int, principal: bool, ordem: int}> */
    public function itensCalculados(): array
    {
        if ($this->itensCalculados !== null) {
            return $this->itensCalculados;
        }

        $itens = array_values($this->input('itens', []));
        $catalogo = $this->user()->produtosServicos()
            ->findMany(array_column($itens, 'produto_servico_id'))
            ->keyBy('id');

        $linhas = [];
        foreach ($itens as $ordem => $item) {
            $produto = $catalogo[(int) $item['produto_servico_id']];
            $milesimos = ItensDeVenda::milesimos($item['quantidade']);
            $preco = Dinheiro::paraCentavos((string) $item['preco']);

            $linhas[] = [
                'produto_servico_id' => $produto->id,
                'nome' => $produto->nome,
                'unidade' => $produto->unidade,
                'quantidade' => ItensDeVenda::quantidade($milesimos),
                'preco_unitario_centavos' => $preco,
                'total_centavos' => ItensDeVenda::total($milesimos, $preco),
                'principal' => filter_var($item['principal'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'ordem' => $ordem,
            ];
        }

        $principal = ItensDeVenda::indicePrincipal($linhas);
        foreach (array_keys($linhas) as $indice) {
            $linhas[$indice]['principal'] = $indice === $principal;
        }

        $this->codigosDoPrincipal = $catalogo[$linhas[$principal]['produto_servico_id']]
            ->only(['codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional']);

        return $this->itensCalculados = $linhas;
    }

    /** Valor = soma dos itens; códigos fiscais = os do item principal no catálogo. */
    protected function resumoDosItens(): array
    {
        $itens = $this->itensCalculados();

        return ['valor_centavos' => array_sum(array_column($itens, 'total_centavos'))] + $this->codigosDoPrincipal;
    }

    /** Linhas para `contrato_itens`: o contrato guarda quantidade e preço; nome e unidade ficam no catálogo. */
    public function itensDoContrato(): array
    {
        return array_map(
            fn (array $item) => Arr::only($item, ['produto_servico_id', 'quantidade', 'preco_unitario_centavos', 'principal', 'ordem']),
            $this->itensCalculados(),
        );
    }
}
```

`app/Http/Requests/ContratoRequest.php` (arquivo inteiro):

```php
<?php

namespace App\Http\Requests;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use App\Http\Requests\Concerns\RegrasDeItens;
use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\LinhasMaximas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ContratoRequest extends FormRequest
{
    use RegrasDeItens, RegrasDePagamento;

    /** Contrato de outro usuário vira 404 antes da validação (o erro não pode revelar que ele existe). */
    public function authorize(): bool
    {
        $contrato = $this->route('contrato');

        abort_if($contrato !== null && ! $this->user()->contratos()->whereKey($contrato)->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
        $this->prepararItens();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['required', 'string', 'max:2000', new LinhasMaximas(25)],
            'periodicidade' => ['required', Rule::enum(Periodicidade::class)],
            'modalidade' => ['required', Rule::enum(Modalidade::class)],
            'dia_vencimento' => ['required', 'integer', 'between:1,31'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'ativo' => ['boolean'],
            ...$this->regrasDeItens(),
            ...$this->regrasDePagamento(false),
        ];
    }

    public function dadosContrato(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['itens', 'forma_pagamento', 'chave_pix_id'])
            + ['ativo' => $this->boolean('ativo', true)]
            + $this->resumoDosItens()
            + $this->dadosDePagamento($dados);
    }
}
```

`app/Http/Controllers/ContratoController.php` — `use App\Models\ContratoItem;`, `use App\Support\ItensDeVenda;`,
`use Illuminate\Support\Facades\DB;`; trocar `store`, a linha `$registro->update($dados);` de `update` e `formulario` por:

```php
    public function store(ContratoRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $contrato = $request->user()->contratos()->create($request->dadosContrato());
            $contrato->itens()->createMany($request->itensDoContrato());
        });

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As cobranças aparecem no painel quando chegar a hora.');
    }
```

```php
        // em update, no lugar de `$registro->update($dados);`
        DB::transaction(function () use ($registro, $dados, $request) {
            $registro->update($dados);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDoContrato());
        });
```

```php
    private function formulario(Request $request, ?Contrato $contrato): Response
    {
        return Inertia::render('Contratos/Form', [
            'contrato' => $contrato ? [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'descricao' => $contrato->descricao,
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'itens' => $contrato->itens()->with('produtoServico:id,nome,unidade')->get()
                    ->map(fn (ContratoItem $item) => [
                        'produto_servico_id' => $item->produto_servico_id,
                        'nome' => $item->produtoServico->nome,
                        'unidade' => $item->produtoServico->unidade,
                        'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item->quantidade)),
                        'preco' => Dinheiro::formatar($item->preco_unitario_centavos),
                        'principal' => $item->principal,
                    ])->all(),
                'periodicidade' => $contrato->periodicidade->value,
                'modalidade' => $contrato->modalidade->value,
                'dia_vencimento' => $contrato->dia_vencimento,
                'data_inicio' => $contrato->data_inicio->toDateString(),
                'data_fim' => $contrato->data_fim?->toDateString() ?? '',
                'ativo' => $contrato->ativo,
                'forma_pagamento' => $contrato->forma_pagamento->value,
                'chave_pix_id' => $contrato->chave_pix_id ?? '',
            ] : null,
            'temClientes' => $request->user()->clientes()->exists(),
            'clienteAtual' => $contrato?->cliente()->first(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
            'modalidades' => Modalidade::opcoes(),
            'chavesPix' => $request->user()->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
        ]);
    }
```

`app/Http/Controllers/ProdutoServicoController.php` — `destroy`:

```php
    public function destroy(Request $request, int $produto): RedirectResponse
    {
        $registro = $request->user()->produtosServicos()->findOrFail($produto);

        if ($registro->contratoItens()->exists()) {
            return back()->with('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        }

        $registro->delete();

        return redirect('/produtos-servicos')->with('sucesso', 'Produto ou serviço excluído.');
    }
```

`lang/pt_BR/validation.php` — mensagens (na raiz) que faltam:

```php
    'array' => 'O campo :attribute é inválido.',
    'distinct' => 'O campo :attribute tem um valor repetido.',
    'gt' => [
        'numeric' => 'O campo :attribute deve ser maior que :value.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
```

a chave `'array'` dentro de `min` (`'Escolha pelo menos :min em :attribute.'`) e de `max` (`'Use no máximo :max em :attribute.'`),
e em `custom`:

```php
        'itens' => [
            'required' => 'Inclua pelo menos um produto ou serviço.',
            'array' => 'Inclua pelo menos um produto ou serviço.',
            'min' => 'Inclua pelo menos um produto ou serviço.',
            'max' => 'Use no máximo 50 itens.',
        ],
        'itens.*.produto_servico_id' => [
            'required' => 'Escolha um produto ou serviço do seu catálogo.',
            'integer' => 'Escolha um produto ou serviço do seu catálogo.',
            'exists' => 'Escolha um produto ou serviço do seu catálogo.',
            'distinct' => 'Este produto ou serviço já está na lista.',
        ],
        'itens.*.quantidade' => [
            'required' => 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).',
            'numeric' => 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).',
            'gt' => 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).',
            'regex' => 'Informe a quantidade, como 1 ou 1,5 (até 3 casas decimais).',
        ],
```

e em `attributes`: `'itens' => 'itens',` e `'quantidade' => 'quantidade',`.

`resources/js/itens.js`:

```js
/** Mesmas contas do servidor (App\Support\ItensDeVenda), só para mostrar o total enquanto digita. */
export function milesimos(texto) {
    const partes = String(texto ?? '').trim().replace(',', '.').match(/^(\d+)(?:\.(\d{1,3}))?$/);

    return partes ? Number(partes[1]) * 1000 + Number((partes[2] ?? '').padEnd(3, '0')) : null;
}

/** "1.234,56" (máscara de valor) → 123456. */
export function centavos(texto) {
    const numeros = String(texto ?? '').replace(/\D/g, '');

    return numeros === '' ? null : Number(numeros);
}

/** BigInt: quantidade × preço passa do inteiro exato do JS (2^53) em valores grandes. */
export function totalDoItem(quantidade, preco) {
    const q = milesimos(quantidade);
    const p = centavos(preco);

    return q === null || p === null ? 0 : Number((BigInt(q) * BigInt(p) + 500n) / 1000n);
}

export function formatarCentavos(valor) {
    const texto = String(valor).padStart(3, '0');

    return `${texto.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${texto.slice(-2)}`;
}
```

`resources/js/Components/BuscaAutosugestao.vue` (depois do `git mv`; arquivo inteiro):

```vue
<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, required: true },
    modelValue: { type: [Number, String], default: '' },
    inicial: { type: Object, default: null },
    obrigatorio: { type: Boolean, default: false },
    desativado: { type: Boolean, default: false },
    vazio: { type: String, default: 'Digite parte do nome ou do CPF/CNPJ' },
    url: { type: String, default: '/clientes/busca' },
    nenhum: { type: String, default: 'Nenhum cliente encontrado.' },
    limparAoEscolher: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'escolhido']);

const texto = ref(props.inicial?.nome ?? '');
const sugestoes = ref([]);
const ativo = ref(-1);
const aberto = ref(false);
let espera = null;
let controle = null;

async function buscar(termo) {
    controle?.abort();
    if (termo.trim().length < 2) {
        sugestoes.value = [];
        aberto.value = false;
        return;
    }
    controle = new AbortController();
    try {
        const resposta = await fetch(`${props.url}?q=${encodeURIComponent(termo)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controle.signal,
        });
        sugestoes.value = resposta.ok ? await resposta.json() : [];
        ativo.value = sugestoes.value.length ? 0 : -1;
        aberto.value = true;
    } catch (falha) {
        if (falha.name !== 'AbortError') {
            sugestoes.value = [];
        }
    }
}

function digitou() {
    emit('update:modelValue', ''); // o texto mudou: a escolha anterior deixa de valer
    clearTimeout(espera);
    espera = setTimeout(() => buscar(texto.value), 250);
}

function escolher(opcao) {
    texto.value = props.limparAoEscolher ? '' : opcao.nome;
    emit('update:modelValue', opcao.id);
    emit('escolhido', opcao);
    aberto.value = false;
}

function teclado(evento) {
    if (!aberto.value || !sugestoes.value.length) {
        return;
    }
    const total = sugestoes.value.length;
    if (evento.key === 'ArrowDown') {
        evento.preventDefault();
        ativo.value = (ativo.value + 1) % total;
    } else if (evento.key === 'ArrowUp') {
        evento.preventDefault();
        ativo.value = (ativo.value - 1 + total) % total;
    } else if (evento.key === 'Enter') {
        evento.preventDefault();
        escolher(sugestoes.value[ativo.value]);
    } else if (evento.key === 'Escape') {
        aberto.value = false;
    }
}

watch(() => props.inicial, (opcao) => {
    texto.value = opcao?.nome ?? '';
});

onBeforeUnmount(() => {
    clearTimeout(espera);
    controle?.abort();
});
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            v-model="texto"
            type="text"
            role="combobox"
            autocomplete="off"
            aria-autocomplete="list"
            class="entrada"
            :aria-expanded="aberto"
            :aria-controls="`${id}-lista`"
            :aria-activedescendant="aberto && ativo >= 0 ? `${id}-opcao-${ativo}` : undefined"
            :placeholder="vazio"
            :required="obrigatorio"
            :disabled="desativado"
            @input="digitou"
            @keydown="teclado"
            @blur="aberto = false"
        >
        <ul
            v-show="aberto"
            :id="`${id}-lista`"
            role="listbox"
            class="absolute z-10 mt-1 max-h-72 w-full overflow-auto border border-linha bg-white"
        >
            <li
                v-for="(opcao, indice) in sugestoes"
                :id="`${id}-opcao-${indice}`"
                :key="opcao.id"
                role="option"
                :aria-selected="indice === ativo"
                class="cursor-pointer px-3 py-2 text-sm"
                :class="indice === ativo ? 'bg-papel text-carbono' : ''"
                @mousedown.prevent="escolher(opcao)"
                @mouseenter="ativo = indice"
            >
                <slot name="opcao" :opcao="opcao">
                    <span class="font-semibold">{{ opcao.nome }}</span>
                    <span class="ml-2 font-estreita text-xs text-grafite/70">{{ opcao.documento }}</span>
                </slot>
            </li>
            <li v-if="!sugestoes.length" class="px-3 py-2 text-sm text-grafite/70">{{ nenhum }}</li>
        </ul>
    </div>
</template>
```

Em `resources/js/Pages/Cobrancas/Form.vue` e `resources/js/Pages/Cobrancas/Index.vue`: trocar
`import BuscaCliente from '../../Components/BuscaCliente.vue';` por
`import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';` e a tag `<BuscaCliente` por `<BuscaAutosugestao`.

`resources/js/Components/SecaoItens.vue`:

```vue
<script setup>
import { computed } from 'vue';
import BuscaAutosugestao from './BuscaAutosugestao.vue';
import { formatarCentavos, totalDoItem } from '../itens.js';

const props = defineProps({ form: { type: Object, required: true } });

const total = computed(() => props.form.itens.reduce((soma, item) => soma + totalDoItem(item.quantidade, item.preco), 0));
const erro = (indice, campo) => props.form.errors[`itens.${indice}.${campo}`];

function adicionar(opcao) {
    if (props.form.itens.some((item) => item.produto_servico_id === opcao.id)) {
        return;
    }
    props.form.itens.push({
        produto_servico_id: opcao.id,
        nome: opcao.nome,
        unidade: opcao.unidade,
        quantidade: '1',
        preco: opcao.preco,
        principal: props.form.itens.length === 0,
    });
}

function marcarPrincipal(indice) {
    props.form.itens.forEach((item, posicao) => {
        item.principal = posicao === indice;
    });
}
</script>

<template>
    <fieldset class="flex flex-col gap-3 sm:col-span-6">
        <legend class="mb-2 text-sm font-semibold">Produtos e serviços</legend>
        <p v-if="form.errors.itens" class="text-xs text-tinta-vermelha">{{ form.errors.itens }}</p>

        <div v-if="form.itens.length" class="overflow-x-auto border border-linha bg-white">
            <table class="w-full text-sm">
                <thead class="font-estreita text-xs text-grafite/75">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Item</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Quantidade</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Preço unitário (R$)</th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">Total (R$)</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium">Principal</th>
                        <th scope="col" class="px-3 py-2"><span class="sr-only">Remover</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linha">
                    <tr v-for="(item, indice) in form.itens" :key="item.produto_servico_id">
                        <td class="px-3 py-2">
                            {{ item.nome }} <span class="font-estreita text-xs text-grafite/70">({{ item.unidade }})</span>
                            <p v-if="erro(indice, 'produto_servico_id')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'produto_servico_id') }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <input v-model="item.quantidade" inputmode="decimal" class="entrada w-24" :aria-label="`Quantidade de ${item.nome}`" required>
                            <p v-if="erro(indice, 'quantidade')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'quantidade') }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <input v-model="item.preco" v-mascara="'valor'" inputmode="decimal" class="entrada w-32" :aria-label="`Preço unitário de ${item.nome}`" required>
                            <p v-if="erro(indice, 'preco')" class="text-xs text-tinta-vermelha">{{ erro(indice, 'preco') }}</p>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ formatarCentavos(totalDoItem(item.quantidade, item.preco)) }}</td>
                        <td class="px-3 py-2">
                            <input type="radio" name="item_principal" :checked="item.principal" :aria-label="`${item.nome} é o item principal`" @change="marcarPrincipal(indice)">
                        </td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" class="botao-secundario px-2 py-1" @click="form.itens.splice(indice, 1)">Remover</button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" colspan="3" class="px-3 py-2 text-right font-medium">Total</th>
                        <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ formatarCentavos(total) }}</td>
                        <td colspan="2" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <div>
            <label for="novo_item" class="font-estreita text-xs font-medium text-grafite/75">Incluir produto ou serviço</label>
            <BuscaAutosugestao
                id="novo_item"
                url="/produtos-servicos/busca"
                vazio="Digite parte do nome do produto ou serviço"
                nenhum="Nenhum produto ou serviço ativo encontrado."
                limpar-ao-escolher
                @escolhido="adicionar"
            >
                <template #opcao="{ opcao }">
                    <span class="font-semibold">{{ opcao.nome }}</span>
                    <span class="ml-2 font-estreita text-xs text-grafite/70">R$ {{ opcao.preco }} por {{ opcao.unidade }}</span>
                </template>
            </BuscaAutosugestao>
            <p class="mt-1 text-xs text-grafite/70">
                O item principal define os códigos fiscais do recibo; sem marcação, vale o de maior total.
                O valor é a soma dos itens, recalculada ao salvar.
            </p>
        </div>
    </fieldset>
</template>
```

`resources/js/Pages/Contratos/Form.vue` (arquivo inteiro):

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';
import SecaoItens from '../../Components/SecaoItens.vue';

const props = defineProps({
    contrato: { type: Object, default: null },
    temClientes: { type: Boolean, required: true },
    clienteAtual: { type: Object, default: null },
    periodicidades: { type: Array, required: true },
    modalidades: { type: Array, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const form = useForm(props.contrato ?? {
    cliente_id: '',
    descricao: '',
    itens: [],
    periodicidade: 'mensal',
    modalidade: 'prepago',
    dia_vencimento: 10,
    data_inicio: new Date().toLocaleDateString('sv-SE'),
    data_fim: '',
    ativo: true,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
});

const semItens = Boolean(props.contrato && !props.contrato.itens.length);

const salvar = () => (props.contrato ? form.put(`/contratos/${props.contrato.id}`) : form.post('/contratos'));
</script>

<template>
    <CamadaApp>
        <Link href="/contratos" class="font-estreita text-sm text-carbono underline">Voltar para contratos</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ contrato ? 'Editar contrato' : 'Novo contrato' }}</h1>

        <p v-if="!temClientes" class="border border-linha bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>

        <form v-else class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <BuscaAutosugestao id="cliente_id" v-model="form.cliente_id" :inicial="clienteAtual" obrigatorio />
            </Campo>
            <Campo id="descricao" rotulo="Descrição dos serviços (como sai no recibo, até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="6" class="entrada" required />
            </Campo>
            <p v-if="semItens" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                Este contrato ainda não tem itens (valor atual: R$ {{ contrato.valor }}). Inclua pelo menos um; ao salvar, o
                valor passa a ser a soma dos itens.
            </p>
            <SecaoItens :form="form" />
            <Campo id="periodicidade" rotulo="Periodicidade" :erro="form.errors.periodicidade" class="sm:col-span-3">
                <select id="periodicidade" v-model="form.periodicidade" class="entrada">
                    <option v-for="p in periodicidades" :key="p.valor" :value="p.valor">{{ p.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="dia_vencimento" rotulo="Dia do vencimento" :erro="form.errors.dia_vencimento" class="sm:col-span-3">
                <input id="dia_vencimento" v-model="form.dia_vencimento" type="number" min="1" max="31" class="entrada" required>
            </Campo>
            <fieldset class="sm:col-span-6">
                <legend class="mb-2 font-estreita text-xs font-medium text-grafite/75">Modalidade</legend>
                <div class="flex flex-wrap gap-4">
                    <label v-for="modalidade in modalidades" :key="modalidade.valor" class="flex items-center gap-2 text-sm">
                        <input v-model="form.modalidade" type="radio" name="modalidade" :value="modalidade.valor"> {{ modalidade.rotulo }}
                    </label>
                </div>
                <p class="mt-1 text-xs text-grafite/70">
                    {{ form.modalidade === 'pospago'
                        ? 'Vence no dia escolhido depois que o período termina (serviço de outubro vence em novembro).'
                        : 'Vence no dia escolhido do mês em que o período começa.' }}
                </p>
                <p v-if="form.errors.modalidade" class="text-xs text-tinta-vermelha">{{ form.errors.modalidade }}</p>
            </fieldset>
            <Campo id="data_inicio" rotulo="Início do primeiro período" :erro="form.errors.data_inicio" class="sm:col-span-3">
                <input id="data_inicio" v-model="form.data_inicio" type="date" class="entrada" required>
            </Campo>
            <Campo id="data_fim" rotulo="Fim do contrato (opcional)" :erro="form.errors.data_fim" class="sm:col-span-3">
                <input id="data_fim" v-model="form.data_fim" type="date" class="entrada">
            </Campo>
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />
            <label class="flex items-center gap-2 text-sm sm:col-span-6">
                <input v-model="form.ativo" type="checkbox"> Contrato ativo (desmarque para encerrar)
            </label>
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">Salvar contrato</button>
            </div>
        </form>
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Unit/ItensDeVendaTest.php tests/Feature/ItensDoContratoTest.php tests/Feature/ContratosTest.php tests/Feature/Cobrancas/ProtecoesTest.php tests/Feature/FormaPagamentoTest.php` e `node --test resources/js/mascaras.test.js resources/js/itens.test.js`
  Esperado: PASS. Depois `php artisan test --compact` (suíte inteira) e `npm run build`. Esperado: verde.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T12.2: valor do contrato =
  soma dos itens recalculada no servidor (`RegrasDeItens::after` recusa total 0 ou acima do máximo); quantidade em
  milésimos, total `intdiv(q×p+500, 1000)`; códigos fiscais do item principal (marcado ou o de maior total); contrato
  antigo sem itens continua valendo e exige itens ao editar; `ContratoRequest::authorize` dá 404 para contrato alheio;
  `BuscaCliente` virou `BuscaAutosugestao` (url, slot `opcao`, evento `escolhido`)". Pegadinha: "`numeric` aceita
  `+1` e `1e3`; a regex `^\d{1,5}(\.\d{1,3})?$` fecha o formato antes de `ItensDeVenda::milesimos`". Pint, depois:

```bash
git add -A
git commit -m "feat: contrato montado por itens do catálogo, valor pela soma (módulo Cobrança)"
```

---

### Tarefa T12.3: Itens na cobrança (avulsa, edição e geração)

**Estado:** testada (suíte 219/219; build ok)

**Arquivos:**
- Criar: `database/migrations/2026_10_10_110200_create_cobranca_itens_table.php`, `app/Models/CobrancaItem.php`
- Modificar: `app/Models/Cobranca.php`, `app/Models/ProdutoServico.php`, `app/Http/Requests/Concerns/RegrasDeItens.php`,
  `app/Http/Requests/CobrancaRequest.php`, `app/Http/Controllers/CobrancaController.php`,
  `app/Http/Controllers/ProdutoServicoController.php`, `app/Services/GeradorDeCobrancas.php`,
  `resources/js/Pages/Cobrancas/Form.vue`, `tests/Feature/Cobrancas/AcoesCobrancaTest.php`, `tests/Feature/FormaPagamentoTest.php`
- Teste: `tests/Feature/Cobrancas/ItensDaCobrancaTest.php`

**Interfaces:**
- Consome (T12.2): trait `RegrasDeItens` (`prepararItens`, `regrasDeItens`, `after`, `itensCalculados`, `resumoDosItens`),
  `ContratoItem::total()`, `Contrato::itens()`, `ItensDeVenda`, `SecaoItens.vue`, `BuscaAutosugestao.vue`, `ComItens::itensPara()`.
- Produz: `RegrasDeItens::itensDaCobranca(): list<array{produto_servico_id, nome, unidade, quantidade, preco_unitario_centavos, total_centavos, principal}>`.
- Produz: `CobrancaItem` (tabela `cobranca_itens`: cópia de nome, unidade, quantidade, preço, total, principal;
  `produto_servico_id` nulo com `nullOnDelete`), `Cobranca::itens(): HasMany` (por `id`), `ProdutoServico::cobrancaItens(): HasMany`.
- Produz (para T15.3): cobranças com itens têm `sum(cobranca_itens.total_centavos) = cobrancas.valor_centavos`.

- [ ] **Passo 1: testes que falham** — `tests/Feature/Cobrancas/ItensDaCobrancaTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItensDaCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create(['dias_antecedencia' => 10]);
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function produto(array $extra = []): ProdutoServico
    {
        return ProdutoServico::factory()->for($this->usuario)->create($extra);
    }

    private function item(ProdutoServico $produto, string $quantidade, string $preco, bool $principal = false): array
    {
        return ['produto_servico_id' => $produto->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => $principal];
    }

    private function avulsa(array $itens): array
    {
        return [
            'cliente_id' => $this->cliente->id,
            'descricao' => 'Manutenção extra do site',
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-20',
            'itens' => $itens,
        ];
    }

    private function contrato(array $itens): array
    {
        return [
            'cliente_id' => $this->cliente->id, 'descricao' => 'Hospedagem', 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true,
            'itens' => $itens,
        ];
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: int, 4: int, 5: bool}> */
    private function copias(Cobranca $cobranca): array
    {
        return $cobranca->itens()->get()->map(fn (CobrancaItem $item) => [
            $item->nome, $item->unidade, $item->quantidade, $item->preco_unitario_centavos, $item->total_centavos, $item->principal,
        ])->all();
    }

    public function test_avulsa_copia_os_itens_e_o_valor_e_a_soma(): void
    {
        $site = $this->produto(['nome' => 'Manutenção de site', 'unidade' => 'hora', 'codigo_servico' => '0107 - Suporte']);
        $dominio = $this->produto(['nome' => 'Registro de domínio', 'unidade' => 'un', 'codigo_servico' => '0103 - Hospedagem']);

        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([
            $this->item($site, '2,5', '80,00', principal: true),
            $this->item($dominio, '1', '40,00'),
        ]))->assertRedirect('/cobrancas');

        $cobranca = $this->usuario->cobrancas()->with('recibo')->sole();
        $this->assertSame(20000 + 4000, $cobranca->valor_centavos);
        $this->assertSame('0107 - Suporte', $cobranca->codigo_servico);
        $this->assertSame(24000, $cobranca->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame([
            ['Manutenção de site', 'hora', '2.500', 8000, 20000, true],
            ['Registro de domínio', 'un', '1.000', 4000, 4000, false],
        ], $this->copias($cobranca));
    }

    public function test_geracao_copia_os_itens_do_contrato_e_mudancas_depois_nao_alteram_a_cobranca(): void
    {
        $hospedagem = $this->produto(['nome' => 'Hospedagem']);
        $this->actingAs($this->usuario)->post('/contratos', $this->contrato([$this->item($hospedagem, '1', '89,90')]))
            ->assertRedirect('/contratos');
        $contrato = $this->usuario->contratos()->sole();

        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $cobranca = $contrato->cobrancas()->sole();
        $this->assertSame(8990, $cobranca->valor_centavos);
        $this->assertSame([['Hospedagem', 'mês', '1.000', 8990, 8990, true]], $this->copias($cobranca));
        $this->assertSame($hospedagem->id, $cobranca->itens()->sole()->produto_servico_id);

        // Renomear o item e reajustar o contrato valem só para as próximas cobranças.
        $hospedagem->update(['nome' => 'Hospedagem premium']);
        $this->put("/contratos/{$contrato->id}", $this->contrato([$this->item($hospedagem, '1', '120,00')]))
            ->assertRedirect('/contratos');

        $this->assertSame([['Hospedagem', 'mês', '1.000', 8990, 8990, true]], $this->copias($cobranca));
        $this->assertSame(8990, $cobranca->fresh()->valor_centavos);
    }

    public function test_contrato_antigo_sem_itens_gera_cobranca_sem_itens(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)
            ->create(['data_inicio' => '2026-10-01', 'dia_vencimento' => 10]);

        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        $cobranca = $contrato->cobrancas()->sole();
        $this->assertSame(26060, $cobranca->valor_centavos);
        $this->assertSame(0, $cobranca->itens()->count());
    }

    public function test_editar_nao_enviada_troca_os_itens_e_mantem_o_numero(): void
    {
        $antigo = $this->produto();
        $novo = $this->produto(['nome' => 'Consultoria', 'unidade' => 'hora']);
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($antigo, '1', '150,00')]));
        $cobranca = $this->usuario->cobrancas()->sole();

        $this->put("/cobrancas/{$cobranca->id}", $this->avulsa([$this->item($novo, '3', '100,00')]))
            ->assertRedirect('/cobrancas');

        $cobranca->refresh()->load('recibo');
        $this->assertSame(30000, $cobranca->valor_centavos);
        $this->assertSame([['Consultoria', 'hora', '3.000', 10000, 30000, true]], $this->copias($cobranca));
        $this->assertSame(1, $cobranca->recibo->numero);

        $this->get("/cobrancas/{$cobranca->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('cobranca.itens.0.nome', 'Consultoria')
            ->where('cobranca.itens.0.quantidade', '3')
            ->where('cobranca.itens.0.preco', '100,00')
            ->where('cobranca.itens.0.principal', true));
    }

    public function test_item_usado_so_em_cobranca_nao_e_excluido(): void
    {
        $produto = $this->produto();
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($produto, '1', '10,00')]));

        $this->delete("/produtos-servicos/{$produto->id}")
            ->assertSessionHas('erro', 'Este item está em contratos ou cobranças. Desative-o em vez de excluir.');
        $this->assertModelExists($produto);
    }

    public function test_cobranca_alheia_da_404_antes_da_validacao(): void
    {
        $produto = $this->produto();
        $this->actingAs($this->usuario)->post('/cobrancas', $this->avulsa([$this->item($produto, '1', '10,00')]));
        $propria = $this->usuario->cobrancas()->sole();
        $alheia = Cobranca::factory()->create();

        $this->put("/cobrancas/{$propria->id}", $this->avulsa([$this->item($produto, '2', '10,00')]))->assertRedirect('/cobrancas');
        $this->put("/cobrancas/{$alheia->id}", $this->avulsa([]))->assertNotFound();
    }
}
```

Nos testes existentes (a cobrança passou a exigir itens):

`tests/Feature/Cobrancas/AcoesCobrancaTest.php` — `use Tests\Concerns\ComItens;`, `use LazilyRefreshDatabase, ComItens;`; em
`dados()`, trocar as linhas `'codigo_servico' ...`, `'codigo_nbs' ...`, `'codigo_tributacao_nacional' ...` e
`'valor' => '150,00',` por `'itens' => $this->itensPara($cliente, '150,00'),`; nas três chamadas
`$this->dados($cliente, ['valor' => '300,00'])`, trocar o extra por `['itens' => $this->itensPara($cliente, '300,00')]`.
(O `'valor' => '999,99'` do teste de `/pagamento` fica: ele prova que essa rota ignora o valor.)

`tests/Feature/FormaPagamentoTest.php` — em `avulsa()`, trocar `'valor' => '150,00',` por
`'itens' => $this->itensPara($cliente, '150,00'),`.

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/Cobrancas/ItensDaCobrancaTest.php tests/Feature/Cobrancas/AcoesCobrancaTest.php`
  Esperado: FAIL (tabela `cobranca_itens` e `CobrancaItem` inexistentes).

- [ ] **Passo 3: implementação**

`database/migrations/2026_10_10_110200_create_cobranca_itens_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranca_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranca_id')->constrained()->cascadeOnDelete();
            // Referência: o que vale é a cópia abaixo (o item pode mudar de nome ou de preço depois).
            $table->foreignId('produto_servico_id')->nullable()->constrained('produtos_servicos')->nullOnDelete();
            $table->string('nome', 120);
            $table->string('unidade', 20);
            $table->decimal('quantidade', 10, 3);
            $table->unsignedBigInteger('preco_unitario_centavos');
            $table->unsignedBigInteger('total_centavos');
            $table->boolean('principal')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_itens');
    }
};
```

`app/Models/CobrancaItem.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produto_servico_id', 'nome', 'unidade', 'quantidade', 'preco_unitario_centavos', 'total_centavos', 'principal'])]
class CobrancaItem extends Model
{
    protected $table = 'cobranca_itens';

    /** Mesmo default da migração. */
    protected $attributes = ['principal' => false];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario_centavos' => 'integer',
            'total_centavos' => 'integer',
            'principal' => 'boolean',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
```

`app/Models/Cobranca.php` — `use Illuminate\Database\Eloquent\Relations\HasMany;` e, depois de `recibo()`:

```php
    public function itens(): HasMany
    {
        return $this->hasMany(CobrancaItem::class)->orderBy('id');
    }
```

`app/Models/ProdutoServico.php` — depois de `contratoItens()`:

```php
    public function cobrancaItens(): HasMany
    {
        return $this->hasMany(CobrancaItem::class);
    }
```

`app/Http/Controllers/ProdutoServicoController.php` — em `destroy`, a condição passa a ser:

```php
        if ($registro->contratoItens()->exists() || $registro->cobrancaItens()->exists()) {
```

`app/Http/Requests/Concerns/RegrasDeItens.php` — acrescentar depois de `itensDoContrato()`:

```php
    /** Linhas para `cobranca_itens`: cópia completa (nome, unidade e total), que não muda se o catálogo mudar. */
    public function itensDaCobranca(): array
    {
        return array_map(fn (array $item) => Arr::except($item, ['ordem']), $this->itensCalculados());
    }
```

`app/Http/Requests/CobrancaRequest.php` (arquivo inteiro):

```php
<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDeItens;
use App\Http\Requests\Concerns\RegrasDePagamento;
use App\Rules\LinhasMaximas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CobrancaRequest extends FormRequest
{
    use RegrasDeItens, RegrasDePagamento;

    /** Cobrança de outro usuário vira 404 antes da validação (o erro não pode revelar que ela existe). */
    public function authorize(): bool
    {
        $cobranca = $this->route('cobranca');

        abort_if($cobranca !== null && ! $this->user()->cobrancas()->whereKey($cobranca)->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
        $this->prepararItens();
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['required', 'string', 'max:2000', new LinhasMaximas(25)],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'vencimento' => ['required', 'date'],
            ...$this->regrasDeItens(),
            ...$this->regrasDePagamento(true),
        ];
    }

    public function dadosCobranca(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['itens', 'forma_pagamento', 'chave_pix_id', 'linha_digitavel'])
            + $this->resumoDosItens()
            + $this->dadosDePagamento($dados);
    }
}
```

`app/Http/Controllers/CobrancaController.php` — `use App\Models\CobrancaItem;` e `use App\Support\ItensDeVenda;`;
no `formulario`, trocar as chaves `'codigo_servico'`, `'codigo_nbs'` e `'codigo_tributacao_nacional'` de `'cobranca'` por:

```php
                'itens' => $cobranca->itens->map(fn (CobrancaItem $item) => [
                    'produto_servico_id' => $item->produto_servico_id,
                    'nome' => $item->nome,
                    'unidade' => $item->unidade,
                    'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($item->quantidade)),
                    'preco' => Dinheiro::formatar($item->preco_unitario_centavos),
                    'principal' => $item->principal,
                ])->all(),
```

(o `'valor'` continua: a tela mostra o valor atual de cobrança antiga sem itens); em `store`, a transação passa a ser:

```php
        DB::transaction(function () use ($usuario, $request, $emissor) {
            $cobranca = $usuario->cobrancas()->create($request->dadosCobranca());
            $cobranca->itens()->createMany($request->itensDaCobranca());
            $emissor->emitir($cobranca);
        });
```

e, em `update`:

```php
        DB::transaction(function () use ($registro, $dados, $emissor, $request) {
            $registro->update($dados);
            $registro->itens()->delete();
            $registro->itens()->createMany($request->itensDaCobranca());
            $emissor->refazer($registro->recibo, $registro->wasChanged('cliente_id'));
        });
```

`app/Services/GeradorDeCobrancas.php` — `use App\Models\ContratoItem;`; a consulta dos contratos carrega os itens:

```php
        foreach ($usuario->contratos()->where('ativo', true)->with('itens.produtoServico')->get() as $contrato) {
```

e, dentro da transação, logo depois do `create` da cobrança (antes de `emitir`):

```php
                    // Cópia: mudar o contrato ou o catálogo depois não altera esta cobrança.
                    $cobranca->itens()->createMany($contrato->itens->map(fn (ContratoItem $item) => [
                        'produto_servico_id' => $item->produto_servico_id,
                        'nome' => $item->produtoServico->nome,
                        'unidade' => $item->produtoServico->unidade,
                        'quantidade' => $item->quantidade,
                        'preco_unitario_centavos' => $item->preco_unitario_centavos,
                        'total_centavos' => $item->total(),
                        'principal' => $item->principal,
                    ])->all());
```

`resources/js/Pages/Cobrancas/Form.vue` (arquivo inteiro):

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import CamposPagamento from '../../Components/CamposPagamento.vue';
import SecaoItens from '../../Components/SecaoItens.vue';

const props = defineProps({
    cobranca: { type: Object, default: null },
    temClientes: { type: Boolean, required: true },
    clienteAtual: { type: Object, default: null },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const hoje = new Date().toLocaleDateString('sv-SE');

const form = useForm(props.cobranca ?? {
    cliente_id: '',
    descricao: '',
    itens: [],
    periodo_inicio: hoje,
    periodo_fim: hoje,
    vencimento: hoje,
    forma_pagamento: 'nenhuma',
    chave_pix_id: '',
    linha_digitavel: '',
});

const travado = Boolean(props.cobranca?.de_contrato);
const semItens = Boolean(props.cobranca && !props.cobranca.itens.length);

const salvar = () => (props.cobranca ? form.put(`/cobrancas/${props.cobranca.id}`) : form.post('/cobrancas'));
</script>

<template>
    <CamadaApp>
        <Link href="/cobrancas" class="font-estreita text-sm text-carbono underline">Voltar para cobranças</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">{{ cobranca ? 'Editar cobrança' : 'Nova cobrança avulsa' }}</h1>
        <p class="mb-6 text-sm text-grafite/80">
            {{ cobranca ? 'O recibo mantém o número e é refeito com os dados novos.' : 'O recibo é emitido junto, com o modelo do cliente.' }}
        </p>

        <p v-if="!temClientes" class="border border-linha bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>

        <form v-else class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <BuscaAutosugestao id="cliente_id" v-model="form.cliente_id" :inicial="clienteAtual" :desativado="travado" obrigatorio />
            </Campo>
            <p v-if="travado" class="font-estreita text-xs text-grafite/70 sm:col-span-6">Cliente e período vêm do contrato.</p>
            <Campo id="descricao" rotulo="Descrição dos serviços (até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="5" class="entrada" required />
            </Campo>
            <p v-if="semItens" role="status" class="border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                Esta cobrança é anterior ao catálogo (valor atual: R$ {{ cobranca.valor }}). Inclua pelo menos um item; ao salvar,
                o valor passa a ser a soma dos itens.
            </p>
            <SecaoItens :form="form" />
            <Campo id="vencimento" rotulo="Vencimento" :erro="form.errors.vencimento" class="sm:col-span-2">
                <input id="vencimento" v-model="form.vencimento" type="date" class="entrada" required>
            </Campo>
            <Campo id="periodo_inicio" rotulo="Início do período" :erro="form.errors.periodo_inicio" class="sm:col-span-2">
                <input id="periodo_inicio" v-model="form.periodo_inicio" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <Campo id="periodo_fim" rotulo="Fim do período" :erro="form.errors.periodo_fim" class="sm:col-span-2">
                <input id="periodo_fim" v-model="form.periodo_fim" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" com-linha-digitavel />
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ cobranca ? 'Salvar cobrança' : 'Criar cobrança e recibo' }}</button>
            </div>
        </form>
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/Cobrancas tests/Feature/FormaPagamentoTest.php`
  Esperado: PASS. Depois `php artisan test --compact` e `npm run build`. Esperado: verde.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T12.3: cobrança guarda
  **cópia** dos itens (`cobranca_itens`: nome, unidade, quantidade, preço, total, principal); geração copia do contrato,
  avulsa/edição gravam os escolhidos; mudar contrato ou catálogo depois não altera cobrança existente; cobrança antiga
  sem itens continua valendo; `CobrancaRequest::authorize` dá 404 para cobrança alheia; item usado em contrato **ou**
  cobrança não é excluído". Pint, depois:

```bash
git add -A
git commit -m "feat: cobranças guardam cópia dos itens (avulsa, edição e geração)"
```

---

### Tarefa T12.4: Reajustar contratos

**Estado:** testada (suíte 224/224; build ok)

**Arquivos:**
- Criar: `resources/js/Pages/ProdutosServicos/Reajuste.vue`
- Modificar: `app/Http/Controllers/ProdutoServicoController.php` (métodos `reajuste` e `reajustar`), `routes/web.php`,
  `lang/pt_BR/validation.php`, `resources/js/Pages/ProdutosServicos/Form.vue` (link "Reajustar contratos")
- Teste: `tests/Feature/ReajusteTest.php`

**Interfaces:**
- Consome: `ProdutoServico::contratoItens()`, `Contrato::itens()`, `ContratoItem::total()`, `ItensDeVenda::formatarQuantidade`,
  `ValorEmReais::MAXIMO_CENTAVOS`.
- Produz: `GET /produtos-servicos/{produto}/reajuste` (página `ProdutosServicos/Reajuste` com `item` e `contratos`:
  `[{id, cliente, resumo, quantidade, preco}]`, só contratos **ativos** que usam o item) e
  `POST /produtos-servicos/{produto}/reajuste` (`contratos: int[]`, `preco: "120,00"`).

O 404 vem antes da validação sem FormRequest: o controller faz `findOrFail` do item e só depois chama `$request->validate()`.

- [ ] **Passo 1: testes que falham** — `tests/Feature/ReajusteTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReajusteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function contratoCom(User $usuario, array $itens, array $extra = []): Contrato
    {
        $contrato = Contrato::factory()->for($usuario)->create($extra);
        foreach ($itens as $ordem => [$produto, $quantidade, $preco]) {
            $contrato->itens()->create([
                'produto_servico_id' => $produto->id, 'quantidade' => $quantidade,
                'preco_unitario_centavos' => $preco, 'principal' => $ordem === 0, 'ordem' => $ordem,
            ]);
        }
        $contrato->update(['valor_centavos' => $contrato->itens()->get()->sum(fn ($item) => $item->total())]);

        return $contrato;
    }

    public function test_lista_so_contratos_ativos_que_usam_o_item(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create(['preco_centavos' => 12000]);
        $outro = ProdutoServico::factory()->for($usuario)->create();
        $usa = $this->contratoCom($usuario, [[$suporte, '2.000', 10000]]);
        $this->contratoCom($usuario, [[$suporte, '1.000', 10000]], ['ativo' => false]);
        $this->contratoCom($usuario, [[$outro, '1.000', 5000]]);

        $this->actingAs($usuario)->get("/produtos-servicos/{$suporte->id}/reajuste")->assertInertia(fn (Assert $page) => $page
            ->component('ProdutosServicos/Reajuste')
            ->where('item.preco', '120,00')
            ->has('contratos', 1)
            ->where('contratos.0.id', $usa->id)
            ->where('contratos.0.quantidade', '2')
            ->where('contratos.0.preco', '100,00'));
    }

    public function test_reajusta_so_os_escolhidos_e_recalcula_o_valor_sem_mexer_em_cobranca(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($usuario)->create();
        $escolhido = $this->contratoCom($usuario, [[$suporte, '2.000', 10000], [$hospedagem, '1.000', 5000]]);
        $deFora = $this->contratoCom($usuario, [[$suporte, '1.000', 10000]]);
        $cobranca = Cobranca::factory()->for($usuario)->create([
            'cliente_id' => $escolhido->cliente_id, 'contrato_id' => $escolhido->id, 'valor_centavos' => 25000,
        ]);

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$escolhido->id], 'preco' => '120,00',
        ])->assertRedirect('/produtos-servicos')
            ->assertSessionHas('sucesso', 'Preço reajustado em 1 contrato(s). Vale para as próximas cobranças.');

        $this->assertSame(2 * 12000 + 5000, $escolhido->fresh()->valor_centavos);
        $this->assertSame([12000, 5000], $escolhido->itens()->pluck('preco_unitario_centavos')->all());
        $this->assertSame(10000, $deFora->fresh()->valor_centavos);
        $this->assertSame(25000, $cobranca->fresh()->valor_centavos);
    }

    public function test_contrato_inativo_alheio_ou_sem_o_item_e_ignorado(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $inativo = $this->contratoCom($usuario, [[$suporte, '1.000', 10000]], ['ativo' => false]);
        $alheio = Contrato::factory()->create();

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$inativo->id, $alheio->id], 'preco' => '120,00',
        ])->assertSessionHasErrors(['contratos' => 'Escolha pelo menos um contrato ativo que usa este item.']);

        $this->assertSame(10000, $inativo->itens()->sole()->preco_unitario_centavos);
    }

    public function test_preco_que_estoura_o_limite_desfaz_tudo(): void
    {
        $usuario = User::factory()->create();
        $suporte = ProdutoServico::factory()->for($usuario)->create();
        $pequeno = $this->contratoCom($usuario, [[$suporte, '1.000', 100]]);
        $grande = $this->contratoCom($usuario, [[$suporte, '99999.000', 100]]);

        $this->actingAs($usuario)->post("/produtos-servicos/{$suporte->id}/reajuste", [
            'contratos' => [$pequeno->id, $grande->id], 'preco' => '99.999.999,99',
        ])->assertSessionHasErrors(['preco' => 'Com este preço, o total de algum contrato ficaria fora do limite (R$ 0,01 a R$ 99.999.999,99).']);

        $this->assertSame(100, $pequeno->itens()->sole()->preco_unitario_centavos);
        $this->assertSame(100, $pequeno->fresh()->valor_centavos);
    }

    public function test_item_alheio_da_404(): void
    {
        $usuario = User::factory()->create();
        $proprio = ProdutoServico::factory()->for($usuario)->create();
        $alheio = ProdutoServico::factory()->create();
        $this->actingAs($usuario);

        $this->get("/produtos-servicos/{$proprio->id}/reajuste")->assertOk();
        $this->get("/produtos-servicos/{$alheio->id}/reajuste")->assertNotFound();
        $this->post("/produtos-servicos/{$alheio->id}/reajuste", ['contratos' => [], 'preco' => ''])->assertNotFound();
    }
}
```

(`$pequeno` vem antes de `$grande` na transação: o primeiro é atualizado, o segundo estoura, e o teste prova que o rollback
desfaz o primeiro.)

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/ReajusteTest.php`
  Esperado: FAIL (rotas inexistentes → 404 / 405).

- [ ] **Passo 3: implementação**

`routes/web.php` — logo depois do resource `produtos-servicos`:

```php
    Route::get('/produtos-servicos/{produto}/reajuste', [ProdutoServicoController::class, 'reajuste']);
    Route::post('/produtos-servicos/{produto}/reajuste', [ProdutoServicoController::class, 'reajustar']);
```

`app/Http/Controllers/ProdutoServicoController.php` — imports `App\Models\ContratoItem`, `App\Rules\ValorEmReais`,
`App\Support\ItensDeVenda`, `Illuminate\Support\Facades\DB`, `Illuminate\Validation\ValidationException`; métodos:

```php
    public function reajuste(Request $request, int $produto): Response
    {
        $item = $request->user()->produtosServicos()->findOrFail($produto);

        $usos = $item->contratoItens()
            ->whereHas('contrato', fn ($contrato) => $contrato->where('user_id', $request->user()->id)->where('ativo', true))
            ->with('contrato.cliente:id,nome')
            ->get();

        return Inertia::render('ProdutosServicos/Reajuste', [
            'item' => [
                'id' => $item->id,
                'nome' => $item->nome,
                'unidade' => $item->unidade,
                'preco' => Dinheiro::formatar($item->preco_centavos),
            ],
            'contratos' => $usos->map(fn (ContratoItem $uso) => [
                'id' => $uso->contrato_id,
                'cliente' => $uso->contrato->cliente->nome,
                'resumo' => strtok($uso->contrato->descricao, "\n"),
                'quantidade' => ItensDeVenda::formatarQuantidade(ItensDeVenda::milesimos($uso->quantidade)),
                'preco' => Dinheiro::formatar($uso->preco_unitario_centavos),
            ])->sortBy('cliente')->values(),
        ]);
    }

    /** Novo preço só nos contratos escolhidos (ativos e que usam o item); cobranças já geradas não mudam. */
    public function reajustar(Request $request, int $produto): RedirectResponse
    {
        $item = $request->user()->produtosServicos()->findOrFail($produto);
        $dados = $request->validate([
            'contratos' => ['required', 'array', 'min:1'],
            'contratos.*' => ['integer'],
            'preco' => ['required', new ValorEmReais(aceitaZero: true)],
        ]);
        $preco = Dinheiro::paraCentavos($dados['preco']);

        $contratos = $request->user()->contratos()->where('ativo', true)->whereIn('id', $dados['contratos'])
            ->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $item->id))
            ->with('itens')->orderBy('id')->get();

        if ($contratos->isEmpty()) {
            throw ValidationException::withMessages(['contratos' => 'Escolha pelo menos um contrato ativo que usa este item.']);
        }

        DB::transaction(function () use ($contratos, $item, $preco) {
            foreach ($contratos as $contrato) {
                $contrato->itens->where('produto_servico_id', $item->id)
                    ->each->update(['preco_unitario_centavos' => $preco]);
                $soma = $contrato->itens->sum(fn (ContratoItem $linha) => $linha->total());

                if ($soma < 1 || $soma > ValorEmReais::MAXIMO_CENTAVOS) {
                    throw ValidationException::withMessages([
                        'preco' => 'Com este preço, o total de algum contrato ficaria fora do limite (R$ 0,01 a R$ 99.999.999,99).',
                    ]);
                }

                $contrato->update(['valor_centavos' => $soma]);
            }
        });

        return redirect('/produtos-servicos')
            ->with('sucesso', "Preço reajustado em {$contratos->count()} contrato(s). Vale para as próximas cobranças.");
    }
```

`lang/pt_BR/validation.php` — em `custom`:

```php
        'contratos' => [
            'required' => 'Escolha pelo menos um contrato.',
            'min' => 'Escolha pelo menos um contrato.',
        ],
```

`resources/js/Pages/ProdutosServicos/Reajuste.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    item: { type: Object, required: true },
    contratos: { type: Array, required: true },
});

const form = useForm({ contratos: props.contratos.map((contrato) => contrato.id), preco: props.item.preco });

const salvar = () => form.post(`/produtos-servicos/${props.item.id}/reajuste`);
</script>

<template>
    <CamadaApp>
        <Link :href="`/produtos-servicos/${item.id}/edit`" class="font-estreita text-sm text-carbono underline">Voltar para {{ item.nome }}</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">Reajustar contratos</h1>
        <p class="mb-6 max-w-3xl text-sm text-grafite/80">
            {{ item.nome }}: preço do catálogo R$ {{ item.preco }} por {{ item.unidade }}. O novo preço vale para as
            próximas cobranças dos contratos marcados; as cobranças já geradas não mudam.
        </p>

        <p v-if="!contratos.length" class="border border-linha bg-white px-4 py-3 text-sm">Nenhum contrato ativo usa este item.</p>

        <form v-else class="flex max-w-3xl flex-col gap-6" @submit.prevent="salvar">
            <fieldset class="border border-linha bg-white">
                <legend class="sr-only">Contratos ativos que usam este item</legend>
                <label
                    v-for="contrato in contratos"
                    :key="contrato.id"
                    class="flex items-start gap-3 border-b border-linha px-4 py-3 text-sm last:border-b-0"
                >
                    <input v-model="form.contratos" type="checkbox" :value="contrato.id" class="mt-1">
                    <span>
                        <span class="font-semibold">{{ contrato.cliente }}</span>, {{ contrato.resumo }}
                        <span class="block font-estreita text-xs text-grafite/70">{{ contrato.quantidade }} × R$ {{ contrato.preco }} hoje</span>
                    </span>
                </label>
            </fieldset>
            <p v-if="form.errors.contratos" class="text-xs text-tinta-vermelha">{{ form.errors.contratos }}</p>
            <Campo id="preco" rotulo="Novo preço unitário (R$)" :erro="form.errors.preco" class="max-w-xs">
                <input id="preco" v-model="form.preco" v-mascara="'valor'" inputmode="decimal" class="entrada" required>
            </Campo>
            <div>
                <button class="botao" :disabled="form.processing || !form.contratos.length">Aplicar o novo preço</button>
            </div>
        </form>
    </CamadaApp>
</template>
```

`resources/js/Pages/ProdutosServicos/Form.vue` — dentro de `<template v-if="item">`, antes do botão "Excluir":

```vue
                    <Link :href="`/produtos-servicos/${item.id}/reajuste`" class="botao-secundario">Reajustar contratos</Link>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/ReajusteTest.php`. Esperado: PASS.
  Depois `php artisan test --compact` e `npm run build`. Esperado: verde.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T12.4: reajuste só nos
  contratos ativos escolhidos que usam o item; recalcula `valor_centavos`; total fora do limite desfaz a transação
  inteira (`ValidationException` dentro de `DB::transaction`); cobranças geradas não mudam; preço do catálogo não muda
  (é só sugestão)". Pint, depois:

```bash
git add -A
git commit -m "feat: reajuste de preço do item nos contratos escolhidos"
```

---

### Tarefa T12.5: PORTÃO-BANCO + fechamento da fase 12

**Estado:** concluída (2026-10-10: `php artisan migrate` autorizado; lote 4; contratos 4 e cobranças 8 inalterados)

- [ ] `php artisan db:show --counts` e `php artisan migrate:status` (leitura); mostrar ao usuário: conexão (PostgreSQL de
  **desenvolvimento** `207.180.205.211:1`, banco `recibos`, usuário `recibos_app`), as contagens de `contratos` e
  `cobrancas`, as 3 migrações pendentes (`2026_10_10_110000_create_produtos_servicos_table`,
  `2026_10_10_110100_create_contrato_itens_table`, `2026_10_10_110200_create_cobranca_itens_table`) e o comando literal
  `php artisan migrate`. Efeito: 3 tabelas novas e vazias; nenhuma linha existente muda; contratos e cobranças antigos
  ficam sem itens (continuam valendo).
- [ ] Esperar o "sim" **deste** comando. Recontar `contratos` e `cobrancas` em comando separado; se mudou, parar e avisar.
- [ ] Executar `php artisan migrate` e mostrar o resultado e o `migrate:status`.
- [ ] Merge + push: `git checkout main; git merge --no-ff feat/modulo-cobranca-catalogo -m "merge: fase 12 — catálogo
  de produtos e serviços e itens em contratos e cobranças (módulo Cobrança)"; git push origin main; git checkout feat/modulo-cobranca-catalogo`.
- [ ] Tabela, estado da tarefa e ledger atualizados; avisar o usuário que contratos antigos pedem itens na próxima edição.

---

## Fase 13 — Filtros e paginação (sem mudança no banco)

### Tarefa T13.1: Filtros e paginação de Clientes

**Estado:** testada (suíte 228/228; build ok)

**Arquivos:**
- Modificar: `app/Models/Cliente.php` (escopo `comTermo`), `app/Http/Controllers/ClienteController.php` (`index`, `busca`),
  `resources/js/Pages/Clientes/Index.vue`, `lang/pt_BR/validation.php`
- Teste: `tests/Feature/FiltrosClientesTest.php` (os testes de busca em `tests/Feature/ClientesTest.php` continuam valendo)

**Interfaces:**
- Consome (T12.1): `Busca::contem()`, `Paginacao::dados()`, `Paginacao.vue`.
- Produz: `Cliente::scopeComTermo(Builder, string)` (nome contém ou documento começa com), usado por `index` e `busca`.
- Produz: `GET /clientes?q=&cidade=&uf=&modelo=&contrato=com|sem&page=` → props `clientes` (array, como antes),
  `paginacao`, `filtros`, `ufs`, `modelos`.

- [ ] **Passo 1: testes que falham** — `tests/Feature/FiltrosClientesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FiltrosClientesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
    }

    private function cliente(array $dados): Cliente
    {
        return Cliente::factory()->for($this->usuario)->create($dados);
    }

    /** @return list<string> */
    private function nomes(string $url): array
    {
        return array_column($this->actingAs($this->usuario)->get($url)->assertOk()->inertiaProps('clientes'), 'nome');
    }

    public function test_pagina_de_25_em_25_mantendo_os_filtros(): void
    {
        foreach (range(1, 30) as $numero) {
            $this->cliente(['nome' => sprintf('Cliente %02d', $numero), 'cidade' => 'Araci']);
        }
        $this->cliente(['nome' => 'Cliente de Aracaju', 'cidade' => 'Aracaju']);

        $resposta = $this->actingAs($this->usuario)->get('/clientes?cidade=araci');

        $this->assertCount(25, $resposta->inertiaProps('clientes'));
        $this->assertSame(30, $resposta->inertiaProps('paginacao.total'));
        $this->assertStringContainsString('cidade=araci', $resposta->inertiaProps('paginacao.proxima'));
        $this->assertStringContainsString('page=2', $resposta->inertiaProps('paginacao.proxima'));
        $this->assertSame(
            ['Cliente 26', 'Cliente 27', 'Cliente 28', 'Cliente 29', 'Cliente 30'],
            $this->nomes('/clientes?cidade=araci&page=2'),
        );
    }

    public function test_filtra_por_nome_ou_documento_cidade_uf_e_modelo(): void
    {
        $this->cliente([
            'nome' => 'Padaria Pão Quente', 'documento' => Documento::cnpjComDv('998887770001'),
            'cidade' => 'Araci', 'uf' => 'BA', 'modelo' => FamiliaModelo::Webiss,
        ]);
        $this->cliente([
            'nome' => 'Mercado Bom Preço', 'documento' => Documento::cnpjComDv('445556660001'),
            'cidade' => 'Aracaju', 'uf' => 'SE', 'modelo' => FamiliaModelo::Danfse,
        ]);
        Cliente::factory()->create(['nome' => 'Padaria de outro usuário']);

        $this->assertSame(['Padaria Pão Quente'], $this->nomes('/clientes?q=PADARIA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?q=44.555'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?cidade=aracaju'));
        $this->assertSame(['Padaria Pão Quente'], $this->nomes('/clientes?uf=BA'));
        $this->assertSame(['Mercado Bom Preço'], $this->nomes('/clientes?modelo=danfse'));
        $this->assertSame(['Mercado Bom Preço', 'Padaria Pão Quente'], $this->nomes('/clientes'));
    }

    public function test_com_e_sem_contrato_ativo(): void
    {
        $ativo = $this->cliente(['nome' => 'A com contrato ativo']);
        Contrato::factory()->for($this->usuario)->for($ativo)->create();
        $encerrado = $this->cliente(['nome' => 'B só com contrato encerrado']);
        Contrato::factory()->for($this->usuario)->for($encerrado)->create(['ativo' => false]);
        $this->cliente(['nome' => 'C sem contrato']);

        $this->assertSame(['A com contrato ativo'], $this->nomes('/clientes?contrato=com'));
        $this->assertSame(['B só com contrato encerrado', 'C sem contrato'], $this->nomes('/clientes?contrato=sem'));
    }

    public function test_filtro_invalido_e_recusado(): void
    {
        $this->actingAs($this->usuario)->get('/clientes?uf=XX')->assertSessionHasErrors('uf');
        $this->get('/clientes?contrato=talvez')->assertSessionHasErrors('contrato');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/FiltrosClientesTest.php`
  Esperado: FAIL (lista sem paginação nem filtros: 31 clientes, `paginacao` ausente).

- [ ] **Passo 3: implementação**

`app/Models/Cliente.php` — imports `App\Support\Busca`, `App\Support\Documento`, `Illuminate\Database\Eloquent\Builder`; depois de `cobrancas()`:

```php
    /** Nome contém o termo (sem diferença de maiúsculas) ou o documento começa com ele. Lista e autosugestão. */
    public function scopeComTermo(Builder $consulta, string $termo): void
    {
        $documento = Documento::normalizar($termo);

        $consulta->where(function (Builder $consulta) use ($termo, $documento) {
            $consulta->whereRaw("lower(nome) like ? escape '\\'", [Busca::contem($termo)]);

            if ($documento !== '') {
                $consulta->orWhere('documento', 'like', "{$documento}%");
            }
        });
    }
```

`app/Http/Controllers/ClienteController.php` — imports `App\Support\Busca`, `App\Support\Paginacao`,
`Illuminate\Validation\Rule`; `index` e `busca` passam a ser:

```php
    public function index(Request $request): Response
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'modelo' => ['nullable', Rule::enum(FamiliaModelo::class)],
            'contrato' => ['nullable', Rule::in(['com', 'sem'])],
        ]);

        $consulta = $request->user()->clientes()->orderBy('nome')->orderBy('id');

        if (filled($filtros['q'] ?? null)) {
            $consulta->comTermo($filtros['q']);
        }
        if (filled($filtros['cidade'] ?? null)) {
            $consulta->whereRaw("lower(cidade) like ? escape '\\'", [Busca::contem($filtros['cidade'])]);
        }
        if (filled($filtros['uf'] ?? null)) {
            $consulta->where('uf', $filtros['uf']);
        }
        if (filled($filtros['modelo'] ?? null)) {
            $consulta->where('modelo', $filtros['modelo']);
        }
        if (filled($filtros['contrato'] ?? null)) {
            $ativo = fn ($contratos) => $contratos->where('ativo', true);

            if ($filtros['contrato'] === 'com') {
                $consulta->whereHas('contratos', $ativo);
            } else {
                $consulta->whereDoesntHave('contratos', $ativo);
            }
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('Clientes/Index', [
            'clientes' => collect($pagina->items())->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => Documento::formatar($cliente->documento),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'q' => $filtros['q'] ?? '',
                'cidade' => $filtros['cidade'] ?? '',
                'uf' => $filtros['uf'] ?? '',
                'modelo' => $filtros['modelo'] ?? '',
                'contrato' => $filtros['contrato'] ?? '',
            ],
            'ufs' => Ufs::TODAS,
            'modelos' => FamiliaModelo::opcoes(),
        ]);
    }

    /** Autosugestão: nome (sem diferença de maiúsculas) ou começo do documento; só clientes do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $clientes = $request->user()->clientes()->comTermo($termo)
            ->orderBy('nome')->limit(10)->get(['id', 'nome', 'documento']);

        return response()->json($clientes->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'documento' => Documento::formatar($cliente->documento),
        ]));
    }
```

`lang/pt_BR/validation.php` — em `attributes`: `'q' => 'busca',` e `'contrato' => 'contrato',`.

`resources/js/Pages/Clientes/Index.vue` (arquivo inteiro):

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    clientes: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    ufs: { type: Array, required: true },
    modelos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => Object.values(props.filtros).some((valor) => valor !== ''));

const filtrar = () => router.get('/clientes', filtro, { preserveState: true, replace: true });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-6" @submit.prevent="filtrar">
            <Campo id="q" rotulo="Nome ou CPF/CNPJ" class="sm:col-span-3">
                <input id="q" v-model="filtro.q" type="search" class="entrada">
            </Campo>
            <Campo id="cidade" rotulo="Cidade" class="sm:col-span-2">
                <input id="cidade" v-model="filtro.cidade" type="search" class="entrada">
            </Campo>
            <Campo id="uf" rotulo="UF">
                <select id="uf" v-model="filtro.uf" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
                </select>
            </Campo>
            <Campo id="modelo" rotulo="Modelo do recibo" class="sm:col-span-3">
                <select id="modelo" v-model="filtro.modelo" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="contrato" rotulo="Contrato" class="sm:col-span-3">
                <select id="contrato" v-model="filtro.contrato" class="entrada">
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

        <p v-if="!clientes.length" class="border border-linha bg-white px-4 py-3 text-sm">
            {{ filtrando ? 'Nenhum cliente com estes filtros.' : 'Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.' }}
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="cliente in clientes" :key="cliente.id">
                <Link :href="`/clientes/${cliente.id}/edit`" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ cliente.nome }}</span>
                    <span class="font-estreita text-sm tabular-nums text-grafite/80">{{ cliente.documento }}</span>
                    <span class="w-full font-estreita text-xs text-grafite/70">{{ cliente.cidade || 'Sem cidade' }}, recibo {{ cliente.modelo }}</span>
                </Link>
            </li>
        </ul>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/FiltrosClientesTest.php tests/Feature/ClientesTest.php`
  Esperado: PASS (inclusive os testes de busca, agora pelo escopo). Depois suíte inteira e `npm run build`.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T13.1: listas paginadas de
  25 com `paginate(25)->withQueryString()` (filtros sobrevivem à troca de página e ao link copiado); `orderBy('id')` de
  desempate para a paginação não repetir nem pular registro; escopo `Cliente::comTermo` compartilhado entre lista e
  autosugestão; a prop da lista continua sendo array (testes antigos valem) e a paginação vai em `paginacao`". Pint, depois:

```bash
git add -A
git commit -m "feat: filtros e paginação na lista de clientes"
```

---

### Tarefa T13.2: Filtros e paginação de Contratos

**Estado:** testada (suíte 231/231; JS 10/10; build ok)

**Arquivos:**
- Modificar: `app/Http/Controllers/ContratoController.php` (`index`), `resources/js/Pages/Contratos/Index.vue`, `lang/pt_BR/validation.php`
- Teste: `tests/Feature/FiltrosContratosTest.php`

**Interfaces:**
- Consome: `Paginacao`, `BuscaAutosugestao.vue` (T12.2), `Contrato::itens()` (T12.2), `Modalidade::opcoes()`, `FormaPagamento::opcoes()`.
- Produz: `GET /contratos?cliente_id=&situacao=ativos|encerrados&modalidade=&forma_pagamento=&produto_servico_id=&page=` →
  props `contratos` (array), `paginacao`, `filtros`, `clienteFiltro`, `modalidades`, `formasPagamento`, `produtos` (`[{id, nome}]`).

- [ ] **Passo 1: testes que falham** — `tests/Feature/FiltrosContratosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\Modalidade;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FiltrosContratosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
    }

    /** @return list<int> */
    private function ids(string $url): array
    {
        return array_column($this->actingAs($this->usuario)->get($url)->assertOk()->inertiaProps('contratos'), 'id');
    }

    public function test_pagina_de_25_em_25(): void
    {
        Contrato::factory()->for($this->usuario)->count(26)->create();

        $resposta = $this->actingAs($this->usuario)->get('/contratos');

        $this->assertCount(25, $resposta->inertiaProps('contratos'));
        $this->assertSame(2, $resposta->inertiaProps('paginacao.ultima'));
        $this->assertCount(1, $this->ids('/contratos?page=2'));
    }

    public function test_filtra_por_cliente_situacao_modalidade_forma_e_produto(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $produto = ProdutoServico::factory()->for($this->usuario)->create();
        $doCliente = Contrato::factory()->for($this->usuario)->for($cliente)->create();
        $encerrado = Contrato::factory()->for($this->usuario)->create(['ativo' => false]);
        $posPago = Contrato::factory()->for($this->usuario)->create(['modalidade' => Modalidade::Pospago]);
        $comPix = Contrato::factory()->for($this->usuario)->create([
            'forma_pagamento' => FormaPagamento::Pix,
            'chave_pix_id' => ChavePix::factory()->for($this->usuario)->create()->id,
        ]);
        $comItem = Contrato::factory()->for($this->usuario)->create();
        $comItem->itens()->create([
            'produto_servico_id' => $produto->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 26060,
            'principal' => true, 'ordem' => 0,
        ]);
        Contrato::factory()->create();

        $this->assertSame([$doCliente->id], $this->ids("/contratos?cliente_id={$cliente->id}"));
        $this->assertSame([$encerrado->id], $this->ids('/contratos?situacao=encerrados'));
        $this->assertCount(4, $this->ids('/contratos?situacao=ativos'));
        $this->assertSame([$posPago->id], $this->ids('/contratos?modalidade=pospago'));
        $this->assertSame([$comPix->id], $this->ids('/contratos?forma_pagamento=pix'));
        $this->assertSame([$comItem->id], $this->ids("/contratos?produto_servico_id={$produto->id}"));
        $this->assertCount(5, $this->ids('/contratos'));
    }

    public function test_cliente_alheio_nao_traz_nada_e_filtro_invalido_e_recusado(): void
    {
        $alheio = Contrato::factory()->create();
        Contrato::factory()->for($this->usuario)->create();

        $this->assertSame([], $this->ids("/contratos?cliente_id={$alheio->cliente_id}"));
        $this->actingAs($this->usuario)->get('/contratos?situacao=todos')->assertSessionHasErrors('situacao');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/FiltrosContratosTest.php`
  Esperado: FAIL (26 contratos numa página; filtros ignorados).

- [ ] **Passo 3: implementação**

`app/Http/Controllers/ContratoController.php` — imports `App\Support\Paginacao` e `Illuminate\Validation\Rule`; `index`:

```php
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $filtros = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'situacao' => ['nullable', Rule::in(['ativos', 'encerrados'])],
            'modalidade' => ['nullable', Rule::enum(Modalidade::class)],
            'forma_pagamento' => ['nullable', Rule::enum(FormaPagamento::class)],
            'produto_servico_id' => ['nullable', 'integer'],
        ]);

        $consulta = $usuario->contratos()->with('cliente:id,nome')
            ->orderByDesc('ativo')->orderBy('data_inicio')->orderBy('id');

        foreach (['cliente_id', 'modalidade', 'forma_pagamento'] as $campo) {
            if (filled($filtros[$campo] ?? null)) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }
        if (filled($filtros['situacao'] ?? null)) {
            $consulta->where('ativo', $filtros['situacao'] === 'ativos');
        }
        if (filled($filtros['produto_servico_id'] ?? null)) {
            $consulta->whereHas('itens', fn ($itens) => $itens->where('produto_servico_id', $filtros['produto_servico_id']));
        }

        $pagina = $consulta->paginate(25)->withQueryString();

        return Inertia::render('Contratos/Index', [
            'contratos' => collect($pagina->items())->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => strtok($contrato->descricao, "\n"),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'modalidade' => $contrato->modalidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'ativo' => $contrato->ativo,
            ]),
            'paginacao' => Paginacao::dados($pagina),
            'filtros' => [
                'cliente_id' => isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : '',
                'situacao' => $filtros['situacao'] ?? '',
                'modalidade' => $filtros['modalidade'] ?? '',
                'forma_pagamento' => $filtros['forma_pagamento'] ?? '',
                'produto_servico_id' => isset($filtros['produto_servico_id']) ? (int) $filtros['produto_servico_id'] : '',
            ],
            'clienteFiltro' => empty($filtros['cliente_id']) ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
            'modalidades' => Modalidade::opcoes(),
            'formasPagamento' => FormaPagamento::opcoes(),
            'produtos' => $usuario->produtosServicos()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
```

`lang/pt_BR/validation.php` — em `attributes`: `'produto_servico_id' => 'produto ou serviço',`.

`resources/js/Pages/Contratos/Index.vue` (arquivo inteiro):

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import BuscaAutosugestao from '../../Components/BuscaAutosugestao.vue';
import Paginacao from '../../Components/Paginacao.vue';

const props = defineProps({
    contratos: { type: Array, required: true },
    paginacao: { type: Object, required: true },
    filtros: { type: Object, required: true },
    clienteFiltro: { type: Object, default: null },
    modalidades: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    produtos: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });
const filtrando = computed(() => Object.values(props.filtros).some((valor) => valor !== ''));

const filtrar = () => router.get('/contratos', filtro, { preserveState: true, replace: true });
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
                <select id="situacao" v-model="filtro.situacao" class="entrada">
                    <option value="">Todos</option>
                    <option value="ativos">Ativos</option>
                    <option value="encerrados">Encerrados</option>
                </select>
            </Campo>
            <Campo id="modalidade" rotulo="Modalidade" class="sm:col-span-2">
                <select id="modalidade" v-model="filtro.modalidade" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="modalidade in modalidades" :key="modalidade.valor" :value="modalidade.valor">{{ modalidade.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="forma_pagamento" rotulo="Forma de pagamento" class="sm:col-span-2">
                <select id="forma_pagamento" v-model="filtro.forma_pagamento" class="entrada">
                    <option value="">Todas</option>
                    <option v-for="forma in formasPagamento" :key="forma.valor" :value="forma.valor">{{ forma.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="produto_servico_id" rotulo="Produto ou serviço" class="sm:col-span-2">
                <select id="produto_servico_id" v-model="filtro.produto_servico_id" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="produto in produtos" :key="produto.id" :value="produto.id">{{ produto.nome }}</option>
                </select>
            </Campo>
            <div class="flex flex-wrap items-center gap-3 sm:col-span-6">
                <button class="botao-secundario">Filtrar</button>
                <Link v-if="filtrando" href="/contratos" class="font-estreita text-sm text-carbono underline">Limpar filtros</Link>
            </div>
        </form>

        <p v-if="!contratos.length" class="border border-linha bg-white px-4 py-3 text-sm">
            {{ filtrando
                ? 'Nenhum contrato com estes filtros.'
                : 'Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.' }}
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="contrato in contratos" :key="contrato.id" :class="{ 'opacity-60': !contrato.ativo }">
                <Link :href="`/contratos/${contrato.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ contrato.cliente }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ contrato.valor }}</span>
                    <span class="text-sm text-grafite/80">{{ contrato.resumo }}</span>
                    <span class="font-estreita text-xs text-grafite/70 sm:text-right">
                        {{ contrato.periodicidade }}, {{ contrato.modalidade }}, vence dia {{ contrato.dia_vencimento }}{{ contrato.ativo ? '' : ', encerrado' }}
                    </span>
                </Link>
            </li>
        </ul>

        <Paginacao :paginacao="paginacao" />
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/FiltrosContratosTest.php tests/Feature/ContratosTest.php`
  Esperado: PASS. Depois suíte inteira e `npm run build`.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T13.2: filtros de
  contratos (cliente por autosugestão, situação, modalidade, forma de pagamento, produto via `whereHas('itens')`);
  `cliente_id` alheio não traz nada (consulta sempre pela relação do usuário)". Pint, depois:

```bash
git add -A
git commit -m "feat: filtros e paginação na lista de contratos"
```

---

### Tarefa T13.3: Fechamento da fase 13

**Estado:** concluída (sem banco; merge na main)

- [ ] Suíte inteira, testes JS e `npm run build` verdes (sem banco: nenhuma migração nesta fase).
- [ ] Merge + push: `git checkout main; git merge --no-ff feat/modulo-cobranca-catalogo -m "merge: fase 13 — filtros e
  paginação de clientes e contratos (módulo Cobrança)"; git push origin main; git checkout feat/modulo-cobranca-catalogo`.
- [ ] Tabela, estado e ledger atualizados.

---

## Fase 14 — Consulta de dados públicos: CNPJ e CEP (sem mudança no banco)

Respostas reais conferidas em 2026-10-10 (só leitura, CNPJ público `00.000.000/0001-91` e CEP `01001-000`):
- BrasilAPI CNPJ: `razao_social`, `nome_fantasia`, `descricao_tipo_de_logradouro`, `logradouro`, `numero`, `complemento`,
  `bairro`, `municipio`, `uf`, `cep` (só dígitos), além de `qsa` (sócios, com nomes de pessoas). Não encontrado: **404**
  `{"message": "CNPJ … não encontrado.", "type": "not_found"}` (vale também para o CNPJ alfanumérico fictício).
- ViaCEP: `logradouro`, `bairro`, `localidade`, `uf`; CEP inexistente responde **200** com `{"erro": "true"}`.
- BrasilAPI CEP v1: `street`, `neighborhood`, `city`, `state`.
- **A BrasilAPI responde 403 ao User-Agent padrão do Python** e 200 a `GuzzleHttp/7` (o do cliente HTTP do Laravel).

### Tarefa T14.1: Consulta de CNPJ e CEP no servidor

**Estado:** testada (suíte 239/239)

**Arquivos:**
- Criar: `app/Services/Consultas/BrasilApiCnpj.php`, `app/Services/Consultas/ViaCep.php`,
  `app/Services/Consultas/BrasilApiCep.php`, `app/Http/Controllers/ConsultaController.php`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/ConsultasPublicasTest.php`

**Interfaces:**
- Produz: `GET /consultas/cnpj/{cnpj}` → 200 `{nome, nome_fantasia, logradouro, numero, complemento, bairro, cidade, uf, cep}`
  | 422 `{mensagem}` (CPF ou CNPJ inválido) | 404 `{mensagem: "Não foi possível consultar agora; preencha manualmente."}`.
- Produz: `GET /consultas/cep/{cep}` → 200 `{logradouro, bairro, cidade, uf}` | 422 `{mensagem}` | 404 `{mensagem}`.
- Limite: `throttle:30,1` (por usuário logado: `ThrottleRequests::resolveRequestSignature` usa o id do usuário). Cache de
  30 dias por documento/CEP, **só de respostas boas**.

- [ ] **Passo 1: testes que falham** — `tests/Feature/ConsultasPublicasTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultasPublicasTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Banco do Brasil: cadastro público. */
    private const CNPJ = '00000000000191';

    private const FALHA = 'Não foi possível consultar agora; preencha manualmente.';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
    }

    private function respostaCnpj(array $extra = []): array
    {
        return array_merge([
            'cnpj' => self::CNPJ,
            'razao_social' => 'BANCO DO BRASIL SA',
            'nome_fantasia' => 'DIRECAO GERAL',
            'descricao_tipo_de_logradouro' => 'QUADRA',
            'logradouro' => 'SAUN QUADRA 5 BLOCO B TORRE I, II, III',
            'numero' => 'SN',
            'complemento' => 'ANDAR T I',
            'bairro' => 'ASA NORTE',
            'municipio' => 'BRASILIA',
            'uf' => 'DF',
            'cep' => '70040912',
            'qsa' => [['nome_socio' => 'FULANO DE TAL', 'cnpj_cpf_do_socio' => '***550179**']],
        ], $extra);
    }

    public function test_cnpj_traz_so_os_dados_da_empresa(): void
    {
        Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response($this->respostaCnpj())]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk()->assertExactJson([
            'nome' => 'BANCO DO BRASIL SA',
            'nome_fantasia' => 'DIRECAO GERAL',
            'logradouro' => 'SAUN QUADRA 5 BLOCO B TORRE I, II, III',
            'numero' => 'SN',
            'complemento' => 'ANDAR T I',
            'bairro' => 'ASA NORTE',
            'cidade' => 'BRASILIA',
            'uf' => 'DF',
            'cep' => '70040912',
        ]);
    }

    public function test_tipo_de_logradouro_entra_quando_falta_no_texto(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaCnpj([
            'descricao_tipo_de_logradouro' => 'RUA', 'logradouro' => 'VIRGILIO OLIVEIRA LIMA',
        ]))]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertJsonPath('logradouro', 'RUA VIRGILIO OLIVEIRA LIMA');
    }

    public function test_cpf_e_cnpj_invalido_nao_sao_consultados(): void
    {
        Http::fake();

        $this->getJson('/consultas/cnpj/52998224725')->assertStatus(422)
            ->assertJsonPath('mensagem', 'Informe um CNPJ válido para buscar os dados.');
        $this->getJson('/consultas/cnpj/11222333000180')->assertStatus(422);
        $this->getJson('/consultas/cep/1234')->assertStatus(422)->assertJsonPath('mensagem', 'Informe um CEP com 8 números.');

        Http::assertNothingSent();
    }

    public function test_falha_nao_encontrado_ou_resposta_ruim_dao_mensagem_e_nao_ficam_no_cache(): void
    {
        Http::fakeSequence('brasilapi.com.br/*')
            ->push(['message' => 'CNPJ não encontrado.', 'type' => 'not_found'], 404)
            ->push('<html>erro</html>', 500)
            ->push([], 200)
            ->pushFailedConnection()
            ->push($this->respostaCnpj());

        foreach (range(1, 4) as $tentativa) {
            $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertNotFound()->assertJsonPath('mensagem', self::FALHA);
        }
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk()->assertJsonPath('nome', 'BANCO DO BRASIL SA');
    }

    public function test_resposta_boa_fica_no_cache_por_30_dias(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaCnpj())]);

        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        Http::assertSentCount(1);

        $this->travel(31)->days();
        $this->getJson('/consultas/cnpj/'.self::CNPJ)->assertOk();
        Http::assertSentCount(2);
    }

    public function test_cep_usa_o_viacep_e_cai_na_brasilapi(): void
    {
        Http::fake([
            'viacep.com.br/ws/01001000/json/' => Http::response([
                'cep' => '01001-000', 'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'localidade' => 'São Paulo', 'uf' => 'SP',
            ]),
            'viacep.com.br/ws/49160000/json/' => Http::response(['erro' => 'true']),
            'brasilapi.com.br/api/cep/v1/49160000' => Http::response([
                'cep' => '49160000', 'state' => 'SE', 'city' => 'Nossa Senhora do Socorro', 'neighborhood' => '', 'street' => '',
            ]),
        ]);

        $this->getJson('/consultas/cep/01001-000')->assertOk()->assertExactJson([
            'logradouro' => 'Praça da Sé', 'bairro' => 'Sé', 'cidade' => 'São Paulo', 'uf' => 'SP',
        ]);
        $this->getJson('/consultas/cep/49160000')->assertOk()
            ->assertJsonPath('cidade', 'Nossa Senhora do Socorro')
            ->assertJsonPath('uf', 'SE');
    }

    public function test_limite_de_30_consultas_por_minuto(): void
    {
        Http::fake(['*' => Http::response(['erro' => 'true'])]);

        foreach (range(1, 30) as $tentativa) {
            $this->getJson('/consultas/cep/01001000')->assertNotFound();
        }
        $this->getJson('/consultas/cep/01001000')->assertTooManyRequests();
    }

    public function test_visitante_nao_consulta(): void
    {
        auth()->logout();

        $this->getJson('/consultas/cep/01001000')->assertUnauthorized();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/ConsultasPublicasTest.php`
  Esperado: FAIL (rotas inexistentes → 404 em tudo, inclusive onde se espera 200/422/429).

- [ ] **Passo 3: implementação**

`app/Services/Consultas/BrasilApiCnpj.php`:

```php
<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * CNPJ na BrasilAPI (gratuita, sem chave; README: "em beta", termos em elaboração; sem crawling).
 * Só dados da empresa: o quadro de sócios (QSA) traz nomes de pessoas e é descartado.
 */
final class BrasilApiCnpj
{
    /** @return array{nome: string, nome_fantasia: string, logradouro: string, numero: string, complemento: string, bairro: string, cidade: string, uf: string, cep: string}|null */
    public function buscar(string $cnpj): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;

        if (! is_array($dados) || blank($dados['razao_social'] ?? null)) {
            return null;
        }

        $tipo = trim((string) ($dados['descricao_tipo_de_logradouro'] ?? ''));
        $logradouro = trim((string) ($dados['logradouro'] ?? ''));

        return [
            'nome' => (string) $dados['razao_social'],
            'nome_fantasia' => (string) ($dados['nome_fantasia'] ?? ''),
            // "RUA" + "VIRGILIO OLIVEIRA LIMA"; não repete quando o tipo já está no texto ("SAUN QUADRA 5").
            'logradouro' => $tipo !== '' && $logradouro !== '' && ! str_contains(mb_strtoupper($logradouro), mb_strtoupper($tipo))
                ? "{$tipo} {$logradouro}"
                : $logradouro,
            'numero' => (string) ($dados['numero'] ?? ''),
            'complemento' => (string) ($dados['complemento'] ?? ''),
            'bairro' => (string) ($dados['bairro'] ?? ''),
            'cidade' => (string) ($dados['municipio'] ?? ''),
            'uf' => (string) ($dados['uf'] ?? ''),
            'cep' => (string) ($dados['cep'] ?? ''),
        ];
    }
}
```

`app/Services/Consultas/ViaCep.php`:

```php
<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** ViaCEP ("Webservice gratuito"; bloqueia uso massivo): consulta principal de CEP. */
final class ViaCep
{
    /** @return array{logradouro: string, bairro: string, cidade: string, uf: string}|null */
    public function buscar(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://viacep.com.br/ws/{$cep}/json/");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;

        // CEP inexistente responde 200 com {"erro": "true"}.
        if (! is_array($dados) || isset($dados['erro']) || blank($dados['localidade'] ?? null)) {
            return null;
        }

        return [
            'logradouro' => (string) ($dados['logradouro'] ?? ''),
            'bairro' => (string) ($dados['bairro'] ?? ''),
            'cidade' => (string) $dados['localidade'],
            'uf' => (string) ($dados['uf'] ?? ''),
        ];
    }
}
```

`app/Services/Consultas/BrasilApiCep.php`:

```php
<?php

namespace App\Services\Consultas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** BrasilAPI CEP v1: reserva quando o ViaCEP falha ou não conhece o CEP. */
final class BrasilApiCep
{
    /** @return array{logradouro: string, bairro: string, cidade: string, uf: string}|null */
    public function buscar(string $cep): ?array
    {
        try {
            $resposta = Http::timeout(5)->acceptJson()->get("https://brasilapi.com.br/api/cep/v1/{$cep}");
        } catch (ConnectionException) {
            return null;
        }

        $dados = $resposta->successful() ? $resposta->json() : null;

        if (! is_array($dados) || blank($dados['city'] ?? null)) {
            return null;
        }

        return [
            'logradouro' => (string) ($dados['street'] ?? ''),
            'bairro' => (string) ($dados['neighborhood'] ?? ''),
            'cidade' => (string) $dados['city'],
            'uf' => (string) ($dados['state'] ?? ''),
        ];
    }
}
```

`app/Http/Controllers/ConsultaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\Consultas\BrasilApiCep;
use App\Services\Consultas\BrasilApiCnpj;
use App\Services\Consultas\ViaCep;
use App\Support\Documento;
use App\Support\Ufs;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** Preenchimento a partir de dados públicos (decisão LGPD na skill): só CNPJ e CEP, nunca CPF. */
class ConsultaController extends Controller
{
    private const DIAS_DE_CACHE = 30;

    /** Mesmos limites dos formulários de cliente e perfil. */
    private const LIMITES = ['numero' => 20];

    public function cnpj(string $cnpj, BrasilApiCnpj $brasilApi): JsonResponse
    {
        $cnpj = Documento::normalizar($cnpj);

        if (strlen($cnpj) !== 14 || ! Documento::valido($cnpj)) {
            return response()->json(['mensagem' => 'Informe um CNPJ válido para buscar os dados.'], 422);
        }

        return $this->responder("consulta:cnpj:{$cnpj}", fn () => $brasilApi->buscar($cnpj));
    }

    public function cep(string $cep, ViaCep $viaCep, BrasilApiCep $brasilApi): JsonResponse
    {
        $cep = (string) preg_replace('/\D/', '', $cep);

        if (strlen($cep) !== 8) {
            return response()->json(['mensagem' => 'Informe um CEP com 8 números.'], 422);
        }

        return $this->responder("consulta:cep:{$cep}", fn () => $viaCep->buscar($cep) ?? $brasilApi->buscar($cep));
    }

    /** Só resposta boa vai para o cache: falha ou "não encontrado" consulta de novo na próxima vez. */
    private function responder(string $chave, Closure $consultar): JsonResponse
    {
        $dados = Cache::get($chave);

        if ($dados === null) {
            $dados = $consultar();

            if ($dados === null) {
                return response()->json(['mensagem' => 'Não foi possível consultar agora; preencha manualmente.'], 404);
            }

            $dados = $this->limpar($dados);
            Cache::put($chave, $dados, now()->addDays(self::DIAS_DE_CACHE));
        }

        return response()->json($dados);
    }

    /** O que vem de fora cabe nos campos do formulário: texto aparado e cortado, UF conhecida, CEP com 8 dígitos. */
    private function limpar(array $dados): array
    {
        $limpos = [];
        foreach ($dados as $campo => $valor) {
            $limpos[$campo] = mb_substr(trim((string) $valor), 0, self::LIMITES[$campo] ?? 255);
        }

        if (array_key_exists('uf', $limpos)) {
            $uf = mb_strtoupper($limpos['uf']);
            $limpos['uf'] = in_array($uf, Ufs::TODAS, true) ? $uf : '';
        }

        if (array_key_exists('cep', $limpos)) {
            $cep = (string) preg_replace('/\D/', '', $limpos['cep']);
            $limpos['cep'] = strlen($cep) === 8 ? $cep : '';
        }

        return $limpos;
    }
}
```

`routes/web.php` — `use App\Http\Controllers\ConsultaController;` e, depois das rotas de reajuste:

```php
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/consultas/cnpj/{cnpj}', [ConsultaController::class, 'cnpj'])->where('cnpj', '[0-9A-Za-z]{1,20}');
        Route::get('/consultas/cep/{cep}', [ConsultaController::class, 'cep'])->where('cep', '[0-9\-]{1,10}');
    });
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/ConsultasPublicasTest.php`. Esperado:
  PASS, sem nenhuma chamada real (o `preventStrayRequests` derruba o teste se alguma escapar). Depois a suíte inteira.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T14.1: `/consultas/cnpj` e
  `/consultas/cep` no servidor, `throttle:30,1` por usuário, cache de 30 dias só de sucesso, QSA descartado, CPF recusado
  com 422; ViaCEP principal e BrasilAPI reserva para CEP" e pegadinhas "ViaCEP responde 200 com `{\"erro\": \"true\"}` para
  CEP inexistente; BrasilAPI responde 403 ao User-Agent padrão do Python (o do Laravel passa); o CNPJ `11.222.333/0001-81`
  usado nos testes antigos pertence a uma empresa real — nos testes de consulta usar só `Http::fake()`". Pint, depois:

```bash
git add -A
git commit -m "feat: consulta de CNPJ e CEP em serviços públicos gratuitos, com cache e limite"
```

---

### Tarefa T14.2: Botão "Buscar dados do CNPJ" e CEP automático

**Estado:** testada (JS 13/13; build ok; suíte 239/239; navegador na T15.5)

**Arquivos:**
- Criar: `resources/js/consultas.js`, `resources/js/consultas.test.js`, `resources/js/Components/BuscaCnpj.vue`
- Modificar: `resources/js/Components/CamposEndereco.vue`, `resources/js/Pages/Clientes/Form.vue`, `resources/js/Pages/Perfil/Editar.vue`

**Interfaces:**
- Consome (T14.1): `GET /consultas/cnpj/{cnpj}` e `GET /consultas/cep/{cep}`.
- Produz: `preencherVazios(form, dados, mapa): number` e `consultar(url): Promise<{dados}|{mensagem}>` (nunca lança).
- Produz: `BuscaCnpj.vue` (props `form`, `campoNome` = `'nome'` no cliente, `'name'` no perfil); aparece quando o
  documento tem 14 caracteres (a validade é conferida pelo servidor, que responde "Informe um CNPJ válido…").

Ruling registrado: a spec pede o botão para "CNPJ completo e válido"; o botão aparece com 14 caracteres e a validação
do dígito fica no servidor (uma regra só, sem copiar o cálculo do DV para o JS). Custo se errado: um clique que
responde "Informe um CNPJ válido para buscar os dados.".

- [ ] **Passo 1: testes que falham** — `resources/js/consultas.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { consultar, preencherVazios } from './consultas.js';

test('preenche só campos vazios e só com o que veio preenchido', () => {
    const form = { nome: '', logradouro: 'Rua já digitada', cidade: '  ', uf: '' };

    const total = preencherVazios(
        form,
        { nome: 'BANCO DO BRASIL SA', logradouro: 'SAUN QUADRA 5', cidade: 'BRASILIA', uf: '' },
        { nome: 'nome', logradouro: 'logradouro', cidade: 'cidade', uf: 'uf' },
    );

    assert.equal(total, 2);
    assert.deepEqual(form, { nome: 'BANCO DO BRASIL SA', logradouro: 'Rua já digitada', cidade: 'BRASILIA', uf: '' });
});

test('campo de destino com outro nome (o perfil usa name)', () => {
    const form = { name: '' };

    preencherVazios(form, { nome: 'EMPRESA X' }, { nome: 'name' });

    assert.equal(form.name, 'EMPRESA X');
});

test('consultar nunca lança: falha vira mensagem', async () => {
    const falha = 'Não foi possível consultar agora; preencha manualmente.';

    globalThis.fetch = async () => { throw new TypeError('sem rede'); };
    assert.deepEqual(await consultar('/x'), { mensagem: falha });

    globalThis.fetch = async () => new Response(JSON.stringify({ mensagem: 'Informe um CNPJ válido para buscar os dados.' }), { status: 422 });
    assert.deepEqual(await consultar('/x'), { mensagem: 'Informe um CNPJ válido para buscar os dados.' });

    globalThis.fetch = async () => new Response('<html>muitas</html>', { status: 429 });
    assert.deepEqual(await consultar('/x'), { mensagem: falha });

    globalThis.fetch = async () => new Response(JSON.stringify({ cidade: 'São Paulo' }), { status: 200 });
    assert.deepEqual(await consultar('/x'), { dados: { cidade: 'São Paulo' } });
});
```

- [ ] **Passo 2: rodar e ver falhar** — `node --test resources/js/consultas.test.js`. Esperado: FAIL (módulo inexistente).

- [ ] **Passo 3: implementação**

`resources/js/consultas.js`:

```js
const FALHA = 'Não foi possível consultar agora; preencha manualmente.';

/** Copia para o formulário só o que veio preenchido e só para campos vazios. Devolve quantos campos preencheu. */
export function preencherVazios(form, dados, mapa) {
    let preenchidos = 0;

    for (const [origem, destino] of Object.entries(mapa)) {
        const valor = String(dados?.[origem] ?? '').trim();
        if (valor !== '' && String(form[destino] ?? '').trim() === '') {
            form[destino] = valor;
            preenchidos++;
        }
    }

    return preenchidos;
}

/** GET com a sessão do usuário. Devolve { dados } ou { mensagem }; nunca lança (o formulário segue manual). */
export async function consultar(url) {
    try {
        const resposta = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const corpo = await resposta.json().catch(() => ({}));

        return resposta.ok ? { dados: corpo } : { mensagem: corpo.mensagem ?? FALHA };
    } catch {
        return { mensagem: FALHA };
    }
}
```

`resources/js/Components/BuscaCnpj.vue`:

```vue
<script setup>
import { computed, ref } from 'vue';
import { consultar, preencherVazios } from '../consultas.js';

const props = defineProps({
    form: { type: Object, required: true },
    campoNome: { type: String, default: 'nome' },
});

const ENDERECO = { logradouro: 'logradouro', numero: 'numero', complemento: 'complemento', bairro: 'bairro', cidade: 'cidade', uf: 'uf', cep: 'cep' };

const cnpj = computed(() => String(props.form.documento ?? '').toUpperCase().replace(/[^0-9A-Z]/g, ''));
const buscando = ref(false);
const aviso = ref('');

async function buscar() {
    buscando.value = true;
    aviso.value = '';
    const { dados, mensagem } = await consultar(`/consultas/cnpj/${cnpj.value}`);
    buscando.value = false;

    if (!dados) {
        aviso.value = mensagem;
        return;
    }

    const mapa = { nome: props.campoNome, ...ENDERECO, ...('nome_fantasia' in props.form ? { nome_fantasia: 'nome_fantasia' } : {}) };
    const total = preencherVazios(props.form, dados, mapa);
    aviso.value = total === 0
        ? 'Os campos já estavam preenchidos; nada foi alterado.'
        : `${total === 1 ? '1 campo preenchido' : `${total} campos preenchidos`} com os dados públicos do CNPJ. Confira antes de salvar.`;
}
</script>

<template>
    <div v-if="cnpj.length === 14" class="flex flex-wrap items-center gap-3 sm:col-span-6">
        <button type="button" class="botao-secundario" :disabled="buscando" @click="buscar">
            {{ buscando ? 'Buscando…' : 'Buscar dados do CNPJ' }}
        </button>
        <p v-if="aviso" role="status" class="text-sm text-grafite/80">{{ aviso }}</p>
    </div>
</template>
```

`resources/js/Components/CamposEndereco.vue` (arquivo inteiro):

```vue
<script setup>
import { ref, watch } from 'vue';
import Campo from './Campo.vue';
import { consultar, preencherVazios } from '../consultas.js';

const props = defineProps({
    form: { type: Object, required: true },
    ufs: { type: Array, required: true },
    obrigatorio: { type: Boolean, default: false },
});

const aviso = ref('');
const digitos = (texto) => String(texto ?? '').replace(/\D/g, '');
let ultimoCep = digitos(props.form.cep); // o CEP que já veio salvo não é consultado de novo

watch(() => digitos(props.form.cep), async (cep) => {
    if (cep.length !== 8 || cep === ultimoCep) {
        return;
    }
    ultimoCep = cep;
    aviso.value = '';

    const { dados, mensagem } = await consultar(`/consultas/cep/${cep}`);
    if (!dados) {
        aviso.value = mensagem;
        return;
    }
    preencherVazios(props.form, dados, { logradouro: 'logradouro', bairro: 'bairro', cidade: 'cidade', uf: 'uf' });
});
</script>

<template>
    <fieldset class="grid gap-4 sm:grid-cols-6">
        <legend class="mb-2 text-sm font-semibold">Endereço</legend>
        <Campo id="cep" rotulo="CEP (preenche o endereço)" :erro="form.errors.cep" class="sm:col-span-2">
            <input id="cep" v-model="form.cep" v-mascara="'cep'" inputmode="numeric" autocomplete="postal-code" class="entrada" :required="obrigatorio">
        </Campo>
        <p v-if="aviso" role="status" class="self-end text-xs text-grafite/80 sm:col-span-4">{{ aviso }}</p>
        <Campo id="logradouro" rotulo="Logradouro" :erro="form.errors.logradouro" class="sm:col-span-4 sm:col-start-1">
            <input id="logradouro" v-model="form.logradouro" autocomplete="address-line1" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="numero" rotulo="Número" :erro="form.errors.numero" class="sm:col-span-2">
            <input id="numero" v-model="form.numero" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="complemento" rotulo="Complemento" :erro="form.errors.complemento" class="sm:col-span-3">
            <input id="complemento" v-model="form.complemento" autocomplete="address-line2" class="entrada">
        </Campo>
        <Campo id="bairro" rotulo="Bairro" :erro="form.errors.bairro" class="sm:col-span-3">
            <input id="bairro" v-model="form.bairro" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="cidade" rotulo="Cidade" :erro="form.errors.cidade" class="sm:col-span-4">
            <input id="cidade" v-model="form.cidade" autocomplete="address-level2" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="uf" rotulo="UF" :erro="form.errors.uf" class="sm:col-span-2">
            <select id="uf" v-model="form.uf" class="entrada" :required="obrigatorio">
                <option value="">—</option>
                <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
            </select>
        </Campo>
    </fieldset>
</template>
```

(O CEP sobe para o primeiro campo: quem digita o CEP primeiro recebe o resto preenchido.)

`resources/js/Pages/Clientes/Form.vue` — `import BuscaCnpj from '../../Components/BuscaCnpj.vue';` e, logo depois do
`Campo` do documento:

```vue
                <BuscaCnpj :form="form" />
```

`resources/js/Pages/Perfil/Editar.vue` — `import BuscaCnpj from '../../Components/BuscaCnpj.vue';` e, logo depois do
`Campo` do documento:

```vue
                <BuscaCnpj :form="form" campo-nome="name" />
```

- [ ] **Passo 4: rodar e ver passar** — `node --test resources/js/mascaras.test.js resources/js/itens.test.js resources/js/consultas.test.js`
  e `npm run build`. Esperado: tudo verde. Suíte PHP inteira (nada de PHP mudou; confirma que nada quebrou).

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada (navegador na T15.5)`; skill `recibos`, acerto
  "T14.2: `preencherVazios` nunca sobrescreve o que a pessoa digitou; CEP automático ao completar 8 dígitos (o CEP já
  salvo não é consultado de novo); botão do CNPJ com 14 caracteres, validade conferida pelo servidor; o perfil usa
  `campo-nome=\"name\"`". Pint, depois:

```bash
git add -A
git commit -m "feat: preencher cliente e perfil pelo CNPJ e endereço pelo CEP"
```

---

### Tarefa T14.3: Fechamento da fase 14

**Estado:** concluída (sem banco; merge na main)

- [ ] Suíte inteira, testes JS e `npm run build` verdes (sem banco).
- [ ] Merge + push: `git checkout main; git merge --no-ff feat/modulo-cobranca-catalogo -m "merge: fase 14 — preenchimento
  por CNPJ e CEP com dados públicos (módulo Cobrança)"; git push origin main; git checkout feat/modulo-cobranca-catalogo`.
- [ ] Tabela, estado e ledger atualizados.

---

## Fase 15 — Relatórios e "recebido = pago no mês" (sem mudança no banco)

Definições (spec, seção 7; "recebido" = decisão D1, regime de caixa):
- **Previsto** do mês = cobranças **não canceladas** com **vencimento** no mês.
- **Recebido** do mês = cobranças **pagas** com **`pago_em`** no mês (independe do vencimento).
- **Em atraso no fim do mês** = não canceladas com vencimento antes do dia de corte e ainda sem pagamento nesse dia
  (`pago_em` nulo ou depois do corte); corte = último dia do mês ou hoje, o que vier antes; meses futuros = 0. É um
  saldo (não se soma entre meses): sem total.
- Período padrão: os últimos 12 meses (mês atual inclusive); máximo de 36 meses.

### Tarefa T15.1: Painel: recebido = pago no mês

**Estado:** testada (suíte 240/240)

**Arquivos:**
- Modificar: `app/Http/Controllers/PainelController.php`
- Teste: `tests/Feature/PainelTest.php`

- [ ] **Passo 1: teste que falha** — em `tests/Feature/PainelTest.php`, acrescentar:

```php
    public function test_recebido_e_o_que_foi_pago_no_mes_qualquer_que_seja_o_vencimento(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $paga = function (string $vencimento, string $pagoEm, int $valor) use ($usuario, $cliente) {
            $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
            $cobranca->save();
        };
        $paga('2026-09-25', '2026-10-02', 10000); // venceu em setembro, pago em outubro: entra
        $paga('2026-10-20', '2026-10-05', 5000);  // adiantado: entra
        $paga('2026-10-03', '2026-09-30', 7000);  // pago em setembro: não entra

        $this->actingAs($usuario)->get('/painel')->assertInertia(fn (Assert $page) => $page
            ->where('resumo.recebido', '150,00')
            ->where('resumo.previsto', '120,00'));
    }
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/PainelTest.php`
  Esperado: FAIL em `resumo.recebido` (hoje dá `120,00`: só as pagas que vencem no mês).

- [ ] **Passo 3: implementação** — em `app/Http/Controllers/PainelController.php`, a linha do recebido passa a ser:

```php
                // Regime de caixa (D1): o que entrou no mês, qualquer que seja o vencimento.
                'recebido' => Dinheiro::formatar((int) $usuario->cobrancas()->where('status', StatusCobranca::Paga)
                    ->whereBetween('pago_em', [$inicio, $fim])->sum('valor_centavos')),
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/PainelTest.php`. Esperado: PASS (o teste
  antigo de blocos continua com `100,00`: a paga dele vence e é paga em outubro). Depois a suíte inteira.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; marcar D1 como **implementada** no roteiro
  (`.claude/plans/2026-10-10-roteiro-modulos.md`) e no plano `2026-10-09-sistema-recibos.md`; skill: "T15.1: painel
  soma `pago_em` no mês; a frase pode mostrar recebido maior que previsto (esperado)". Pint, depois:

```bash
git add -A
git commit -m "feat: painel mostra como recebido o que foi pago no mês (regime de caixa)"
```

---

### Tarefa T15.2: Relatórios: estrutura, faturamento por período e CSV

**Estado:** testada (suíte 245/245; build ok)

**Arquivos:**
- Criar: `app/Services/Relatorios.php`, `app/Http/Controllers/RelatorioController.php`,
  `resources/js/Pages/Relatorios/Index.vue`, `resources/js/Pages/Relatorios/Ver.vue`
- Modificar: `routes/web.php`, `resources/js/Layouts/CamadaApp.vue`, `lang/pt_BR/validation.php`
- Teste: `tests/Feature/Relatorios/FaturamentoTest.php`

**Interfaces:**
- Produz: formato de tabela usado por todos os relatórios, na tela e no CSV:
  `array{titulo: string, descricao: string, colunas: list<array{chave: string, rotulo: string, tipo: 'texto'|'dinheiro'|'numero'|'dias', soma: bool}>, linhas: list<array<string, int|string>>, totais: array<string, int|string|null>}`
  (dinheiro em centavos, inteiro; a primeira coluna do total é `'Total'`; coluna com `soma = false` fica nula no total).
- Produz: `Relatorios::faturamento(User, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array`
  (linhas `{mes: '10/2026', previsto, recebido, inadimplencia}`).
- Produz: `RelatorioController::TIPOS` (lista que as tarefas seguintes ampliam) e as rotas `GET /relatorios`,
  `GET /relatorios/{tipo}?de=AAAA-MM&ate=AAAA-MM[&formato=csv]`.

- [ ] **Passo 1: testes que falham** — `tests/Feature/Relatorios/FaturamentoTest.php`:

```php
<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FaturamentoTest extends TestCase
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

    private function cobranca(string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        if ($pagoEm !== null) {
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
        }
        if ($cancelada) {
            $cobranca->status = StatusCobranca::Cancelada;
        }
        $cobranca->save();

        return $cobranca;
    }

    public function test_previsto_recebido_e_atraso_por_mes(): void
    {
        $this->cobranca('2026-08-10', 10000, '2026-09-05');  // atrasada no fim de agosto; recebida em setembro
        $this->cobranca('2026-09-10', 20000);                // em aberto desde setembro
        $this->cobranca('2026-09-20', 5000, cancelada: true); // cancelada: fora de tudo
        $this->cobranca('2026-10-10', 30000, '2026-10-10');  // paga em dia
        $this->cobranca('2026-11-10', 40000, '2026-10-12');  // de novembro, paga adiantada em outubro
        Cobranca::factory()->create(['vencimento' => '2026-09-10', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-08&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Relatorios/Ver')
                ->where('tipo', 'faturamento')
                ->has('tabela.linhas', 4)
                ->where('tabela.linhas.0', ['mes' => '08/2026', 'previsto' => 10000, 'recebido' => 0, 'inadimplencia' => 10000])
                ->where('tabela.linhas.1', ['mes' => '09/2026', 'previsto' => 20000, 'recebido' => 10000, 'inadimplencia' => 20000])
                ->where('tabela.linhas.2', ['mes' => '10/2026', 'previsto' => 30000, 'recebido' => 70000, 'inadimplencia' => 20000])
                ->where('tabela.linhas.3', ['mes' => '11/2026', 'previsto' => 40000, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.totais', ['mes' => 'Total', 'previsto' => 100000, 'recebido' => 80000, 'inadimplencia' => null]));
    }

    public function test_periodo_padrao_e_os_ultimos_12_meses(): void
    {
        $this->actingAs($this->usuario)->get('/relatorios/faturamento')->assertInertia(fn (Assert $page) => $page
            ->has('tabela.linhas', 12)
            ->where('tabela.linhas.0.mes', '11/2025')
            ->where('tabela.linhas.11.mes', '10/2026')
            ->where('filtros', ['de' => '2025-11', 'ate' => '2026-10']));
    }

    public function test_periodo_invalido_e_tipo_desconhecido(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/relatorios/faturamento?de=2026-10&ate=2026-01')
            ->assertSessionHasErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->get('/relatorios/faturamento?de=2020-01&ate=2026-10')
            ->assertSessionHasErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/relatorios/faturamento?de=outubro')->assertSessionHasErrors('de');
        $this->get('/relatorios/inexistente')->assertNotFound();
    }

    public function test_csv_para_o_excel_em_portugues(): void
    {
        $this->cobranca('2026-10-10', 123456, '2026-10-10');

        $resposta = $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-10&ate=2026-10&formato=csv');

        $resposta->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertDownload('relatorio-faturamento-2026-10-a-2026-10.csv');
        $this->assertSame(
            "\xEF\xBB\xBF"
            ."Mês;Previsto;Recebido;\"Em atraso no fim do mês\"\n"
            ."10/2026;1.234,56;1.234,56;0,00\n"
            ."Total;1.234,56;1.234,56;\n",
            $resposta->streamedContent(),
        );
    }

    public function test_menu_de_relatorios_e_visitante(): void
    {
        $this->actingAs($this->usuario)->get('/relatorios')->assertInertia(fn (Assert $page) => $page->component('Relatorios/Index'));

        auth()->logout();
        $this->get('/relatorios/faturamento')->assertRedirect('/entrar');
    }
}
```

(Conferência das linhas — hoje = 15/10/2026: agosto, corte 31/08: a de 10/08 foi paga em 05/09 → em atraso. Setembro,
corte 30/09: a de 10/08 já paga; a de 10/09 aberta → 200,00. Outubro, corte = hoje 15/10: a de 10/09 continua aberta; a
de 10/10 foi paga em 10/10 → 200,00; recebido = 300,00 + 400,00 adiantados. Novembro começa depois de hoje → 0.)

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/Relatorios/FaturamentoTest.php`
  Esperado: FAIL (rotas `/relatorios` inexistentes → 404).

- [ ] **Passo 3: implementação**

`app/Services/Relatorios.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Relatórios do módulo Cobrança: cada método devolve uma tabela (colunas, linhas e totais) para a tela e para o CSV. */
final class Relatorios
{
    public function faturamento(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $fim = $ate->endOfMonth();
        $hoje = $hoje->startOfDay();

        // ponytail: soma em PHP sobre as cobranças do usuário até o fim do período; agrupar no SQL se passar de dezenas de milhares.
        $cobrancas = $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->where(fn ($consulta) => $consulta->where('vencimento', '<=', $fim)->orWhereBetween('pago_em', [$de, $fim]))
            ->get(['vencimento', 'pago_em', 'status', 'valor_centavos']);

        $linhas = [];
        for ($mes = $de; $mes->lte($ate); $mes = $mes->addMonthNoOverflow()) {
            $inicioMes = $mes->startOfMonth();
            $fimMes = $mes->endOfMonth();
            $corte = $fimMes->startOfDay()->min($hoje);
            $linha = ['mes' => $mes->format('m/Y'), 'previsto' => 0, 'recebido' => 0, 'inadimplencia' => 0];

            foreach ($cobrancas as $cobranca) {
                /** @var Cobranca $cobranca */
                if ($cobranca->vencimento->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['previsto'] += $cobranca->valor_centavos;
                }
                if ($cobranca->status === StatusCobranca::Paga && $cobranca->pago_em?->betweenIncluded($inicioMes, $fimMes)) {
                    $linha['recebido'] += $cobranca->valor_centavos;
                }
                if ($inicioMes->lte($hoje) && $cobranca->vencimento->lt($corte)
                    && ($cobranca->pago_em === null || $cobranca->pago_em->gt($corte))) {
                    $linha['inadimplencia'] += $cobranca->valor_centavos;
                }
            }

            $linhas[] = $linha;
        }

        return $this->tabela(
            'Faturamento por período',
            'Mês a mês: previsto (vencimentos), recebido (pagamentos do mês) e o que estava em atraso no fim do mês.',
            [
                self::coluna('mes', 'Mês', 'texto'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('inadimplencia', 'Em atraso no fim do mês', soma: false),
            ],
            $linhas,
        );
    }

    /** @return array{chave: string, rotulo: string, tipo: string, soma: bool} */
    private static function coluna(string $chave, string $rotulo, string $tipo = 'dinheiro', bool $soma = true): array
    {
        return ['chave' => $chave, 'rotulo' => $rotulo, 'tipo' => $tipo, 'soma' => $soma];
    }

    /**
     * @param  list<array{chave: string, rotulo: string, tipo: string, soma: bool}>  $colunas
     * @param  list<array<string, int|string>>  $linhas
     */
    private function tabela(string $titulo, string $descricao, array $colunas, array $linhas): array
    {
        $totais = [];
        foreach ($colunas as $indice => $coluna) {
            $totais[$coluna['chave']] = match (true) {
                $indice === 0 => 'Total',
                $coluna['soma'] && in_array($coluna['tipo'], ['dinheiro', 'numero'], true) => array_sum(array_column($linhas, $coluna['chave'])),
                default => null,
            };
        }

        return ['titulo' => $titulo, 'descricao' => $descricao, 'colunas' => $colunas, 'linhas' => $linhas, 'totais' => $totais];
    }
}
```

`app/Http/Controllers/RelatorioController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\Relatorios;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public const TIPOS = ['faturamento'];

    public function index(): Response
    {
        return Inertia::render('Relatorios/Index');
    }

    public function show(Request $request, string $tipo, Relatorios $relatorios): Response|RedirectResponse|StreamedResponse
    {
        $hoje = CarbonImmutable::today();
        $filtros = $request->validate([
            'de' => ['nullable', 'date_format:Y-m'],
            'ate' => ['nullable', 'date_format:Y-m'],
            'formato' => ['nullable', Rule::in(['csv'])],
        ]);

        $de = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['de'] ?? $hoje->startOfMonth()->subMonthsNoOverflow(11)->format('Y-m')).'-01')->startOfDay();
        $ate = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['ate'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        if ($de->gt($ate)) {
            return back()->withErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        }
        if ($de->diffInMonths($ate) >= 36) {
            return back()->withErrors(['ate' => 'Escolha um período de até 36 meses.']);
        }

        $usuario = $request->user();
        $tabela = match ($tipo) {
            'faturamento' => $relatorios->faturamento($usuario, $de, $ate, $hoje),
        };

        if (($filtros['formato'] ?? null) === 'csv') {
            return $this->csv($tabela, "relatorio-{$tipo}-{$de->format('Y-m')}-a-{$ate->format('Y-m')}.csv");
        }

        return Inertia::render('Relatorios/Ver', [
            'tipo' => $tipo,
            'tabela' => $tabela,
            'filtros' => ['de' => $de->format('Y-m'), 'ate' => $ate->format('Y-m')],
        ]);
    }

    /** CSV para o Excel em português: `;` separa, BOM marca o UTF-8, dinheiro com vírgula decimal. */
    private function csv(array $tabela, string $nome): StreamedResponse
    {
        return response()->streamDownload(function () use ($tabela) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");

            // PHP 8.4 avisa se o `escape` de fputcsv não for informado: '' desliga o escape fora do padrão RFC 4180.
            $escrever = fn (array $valores) => fputcsv($saida, array_map($this->celula(...), $valores), ';', '"', '');

            $escrever(array_column($tabela['colunas'], 'rotulo'));
            foreach ($tabela['linhas'] as $linha) {
                $escrever($this->valores($tabela['colunas'], $linha));
            }
            $escrever($this->valores($tabela['colunas'], $tabela['totais']));

            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return list<string> */
    private function valores(array $colunas, array $linha): array
    {
        return array_map(function (array $coluna) use ($linha) {
            $valor = $linha[$coluna['chave']] ?? null;

            return $coluna['tipo'] === 'dinheiro' && is_int($valor) ? Dinheiro::formatar($valor) : (string) ($valor ?? '');
        }, $colunas);
    }

    /** Texto que começa com = + - @ viraria fórmula no Excel (injeção de CSV): vai como texto, com apóstrofo. */
    private function celula(string $valor): string
    {
        return preg_match('/^[=+\-@\t\r]/', $valor) ? "'".$valor : $valor;
    }
}
```

`routes/web.php` — `use App\Http\Controllers\RelatorioController;` e, depois das rotas de consulta:

```php
    Route::get('/relatorios', [RelatorioController::class, 'index']);
    Route::get('/relatorios/{tipo}', [RelatorioController::class, 'show'])->whereIn('tipo', RelatorioController::TIPOS);
```

`lang/pt_BR/validation.php` — em `attributes`: `'de' => 'início do período',`, `'ate' => 'fim do período',`, `'formato' => 'formato',`.

`resources/js/Layouts/CamadaApp.vue` — no array `itens`, depois de "Produtos e serviços":

```js
    { href: '/relatorios', rotulo: 'Relatórios' },
```

`resources/js/Pages/Relatorios/Index.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

const relatorios = [
    { tipo: 'faturamento', titulo: 'Faturamento por período', descricao: 'Mês a mês: previsto, recebido e o que ficou em atraso.' },
    { tipo: 'clientes', titulo: 'Receita por cliente', descricao: 'Quanto cada cliente pagou, contratos ativos, valor médio e atrasos.' },
    { tipo: 'produtos', titulo: 'Receita por produto ou serviço', descricao: 'O que mais fatura, pelos itens das cobranças.' },
    { tipo: 'a-receber', titulo: 'Contas a receber', descricao: 'Pendentes por cliente: vencidas e o que vence em 30, 60 e 90 dias.' },
];
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Relatórios</h1>
        <ul class="divide-y divide-linha border border-linha bg-white">
            <li v-for="relatorio in relatorios" :key="relatorio.tipo">
                <Link :href="`/relatorios/${relatorio.tipo}`" class="block px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ relatorio.titulo }}</span>
                    <span class="block text-sm text-grafite/80">{{ relatorio.descricao }}</span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
```

(As tarefas T15.3 e T15.4 habilitam `clientes`, `produtos` e `a-receber`; até lá esses links dão 404 — avisar ao
entregar, como manda a skill.)

`resources/js/Pages/Relatorios/Ver.vue`:

```vue
<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import { formatarCentavos } from '../../itens.js';

const props = defineProps({
    tipo: { type: String, required: true },
    tabela: { type: Object, required: true },
    filtros: { type: Object, required: true },
});

const filtro = reactive({ ...props.filtros });
const comPeriodo = computed(() => props.tipo !== 'a-receber');
const csv = computed(() => `/relatorios/${props.tipo}?${new URLSearchParams({ ...props.filtros, formato: 'csv' })}`);
const maximo = computed(() => Math.max(1, ...props.tabela.linhas.flatMap((linha) => [linha.previsto ?? 0, linha.recebido ?? 0])));

const atualizar = () => router.get(`/relatorios/${props.tipo}`, filtro, { preserveState: true, replace: true });
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
        <h1 class="mt-2 text-2xl font-semibold">{{ tabela.titulo }}</h1>
        <p class="mt-1 mb-6 max-w-3xl text-sm text-grafite/80">{{ tabela.descricao }}</p>

        <div class="mb-6 flex flex-wrap items-end gap-4">
            <form v-if="comPeriodo" class="flex flex-wrap items-end gap-4" @submit.prevent="atualizar">
                <Campo id="de" rotulo="De" :erro="$page.props.errors?.de">
                    <input id="de" v-model="filtro.de" type="month" class="entrada" required>
                </Campo>
                <Campo id="ate" rotulo="Até" :erro="$page.props.errors?.ate">
                    <input id="ate" v-model="filtro.ate" type="month" class="entrada" required>
                </Campo>
                <button class="botao-secundario">Atualizar</button>
            </form>
            <a :href="csv" class="botao-secundario">Baixar CSV</a>
        </div>

        <figure v-if="tipo === 'faturamento' && tabela.linhas.length" class="mb-6 border border-linha bg-white p-4">
            <figcaption class="mb-3 flex flex-wrap gap-4 font-estreita text-xs text-grafite/80">
                <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-linha" /> Previsto</span>
                <span class="flex items-center gap-2"><span class="inline-block h-2 w-4 bg-tinta-verde" /> Recebido</span>
            </figcaption>
            <div aria-hidden="true" class="grid gap-2">
                <div v-for="linha in tabela.linhas" :key="linha.mes" class="grid grid-cols-[4.5rem_1fr] items-center gap-3">
                    <span class="font-estreita text-xs tabular-nums">{{ linha.mes }}</span>
                    <span class="grid gap-1">
                        <span class="block h-2 bg-linha" :style="{ width: largura(linha.previsto) }" />
                        <span class="block h-2 bg-tinta-verde" :style="{ width: largura(linha.recebido) }" />
                    </span>
                </div>
            </div>
        </figure>

        <p v-if="!tabela.linhas.length" class="border border-linha bg-white px-4 py-3 text-sm">Nada para mostrar neste período.</p>

        <div v-else class="overflow-x-auto border border-linha bg-white">
            <table class="w-full text-sm">
                <caption class="sr-only">{{ tabela.titulo }}</caption>
                <thead class="font-estreita text-xs text-grafite/75">
                    <tr>
                        <th v-for="coluna in tabela.colunas" :key="coluna.chave" scope="col" class="px-3 py-2 font-medium" :class="alinhamento(coluna)">
                            {{ coluna.rotulo }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linha">
                    <tr v-for="(linha, indice) in tabela.linhas" :key="indice">
                        <td v-for="coluna in tabela.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                            {{ valor(coluna, linha[coluna.chave]) }}
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-linha font-semibold">
                    <tr>
                        <td v-for="coluna in tabela.colunas" :key="coluna.chave" class="px-3 py-2" :class="alinhamento(coluna)">
                            {{ valor(coluna, tabela.totais[coluna.chave]) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </CamadaApp>
</template>
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/Relatorios/FaturamentoTest.php`. Esperado:
  PASS. Depois suíte inteira e `npm run build`.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T15.2: relatório = tabela
  genérica (colunas com tipo e `soma`, linhas em centavos, totais) usada pela tela e pelo CSV; CSV com `;`, BOM UTF-8 e
  `fputcsv(..., ';', '"', '')`; célula que começa com `= + - @` ganha apóstrofo; período padrão 12 meses, máximo 36" e
  pegadinha "PHP 8.4 deprecia `fputcsv` sem o argumento `escape`; `fputcsv` põe aspas em campo com espaço". Pint, depois:

```bash
git add -A
git commit -m "feat: relatórios do módulo Cobrança — faturamento por período com CSV"
```

---

### Tarefa T15.3: Relatórios: receita por cliente e por produto/serviço

**Estado:** testada (suíte 248/248)

**Arquivos:**
- Modificar: `app/Services/Relatorios.php`, `app/Http/Controllers/RelatorioController.php`
- Teste: `tests/Feature/Relatorios/ReceitaTest.php`

**Interfaces:**
- Consome: `Relatorios::coluna()`/`tabela()` (T15.2), `CobrancaItem`, `ContratoItem` (T12), `Cobranca::itens()`.
- Produz: `Relatorios::porCliente(User, de, ate, hoje)` (linhas `{cliente, recebido, contratos, media, vencidas, atraso}`)
  e `Relatorios::porProduto(User, de, ate)` (linhas `{item, recebido, previsto, contratos}` + linha "Sem item").
- `RelatorioController::TIPOS = ['faturamento', 'clientes', 'produtos']`.

- [ ] **Passo 1: testes que falham** — `tests/Feature/Relatorios/ReceitaTest.php`:

```php
<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceitaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
    }

    private function cobranca(Cliente $cliente, string $vencimento, int $valor, ?string $pagoEm = null, bool $cancelada = false): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);
        if ($pagoEm !== null) {
            $cobranca->status = StatusCobranca::Paga;
            $cobranca->pago_em = $pagoEm;
        }
        if ($cancelada) {
            $cobranca->status = StatusCobranca::Cancelada;
        }
        $cobranca->save();

        return $cobranca;
    }

    public function test_receita_por_cliente(): void
    {
        $ana = Cliente::factory()->for($this->usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($this->usuario)->create(['nome' => 'Bruno Mecânica']);
        $carla = Cliente::factory()->for($this->usuario)->create(['nome' => 'Carla Antiga']);
        Contrato::factory()->for($this->usuario)->for($ana)->create();
        Contrato::factory()->for($this->usuario)->for($ana)->create(['ativo' => false]);
        $this->cobranca($ana, '2026-09-10', 10000, '2026-09-10');
        $this->cobranca($ana, '2026-10-10', 30000, '2026-10-11');
        $this->cobranca($bruno, '2026-10-05', 4000); // 10 dias de atraso
        $this->cobranca($bruno, '2026-09-25', 6000); // 20 dias de atraso
        $this->cobranca($carla, '2025-05-10', 9000, '2025-05-10'); // paga antes do período: fora
        Cobranca::factory()->create(['vencimento' => '2026-09-01', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($this->usuario)->get('/relatorios/clientes?de=2026-01&ate=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 2)
                ->where('tabela.linhas.0', ['cliente' => 'Ana Contabilidade', 'recebido' => 40000, 'contratos' => 1, 'media' => 20000, 'vencidas' => 0, 'atraso' => 0])
                ->where('tabela.linhas.1', ['cliente' => 'Bruno Mecânica', 'recebido' => 0, 'contratos' => 0, 'media' => 0, 'vencidas' => 2, 'atraso' => 15])
                ->where('tabela.totais', ['cliente' => 'Total', 'recebido' => 40000, 'contratos' => 1, 'media' => null, 'vencidas' => 2, 'atraso' => null]));
    }

    public function test_receita_por_produto_bate_com_o_total_pago(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create();
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        $suporte = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Suporte']);
        foreach ([true, true, false] as $ativo) {
            Contrato::factory()->for($this->usuario)->for($cliente)->create(['ativo' => $ativo])->itens()->create([
                'produto_servico_id' => $hospedagem->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 10000, 'principal' => true, 'ordem' => 0,
            ]);
        }
        $comItens = function (string $vencimento, ?string $pagoEm, array $itens, bool $cancelada = false) use ($cliente) {
            $cobranca = $this->cobranca($cliente, $vencimento, array_sum(array_column($itens, 1)), $pagoEm, $cancelada);
            foreach ($itens as [$produto, $total]) {
                $cobranca->itens()->create([
                    'produto_servico_id' => $produto->id, 'nome' => $produto->nome, 'unidade' => 'mês', 'quantidade' => '1.000',
                    'preco_unitario_centavos' => $total, 'total_centavos' => $total, 'principal' => true,
                ]);
            }
        };
        $comItens('2026-10-10', '2026-10-10', [[$hospedagem, 10000], [$suporte, 5000]]); // paga no período
        $comItens('2026-10-20', null, [[$hospedagem, 20000]]);                            // vence no período
        $comItens('2026-10-12', null, [[$suporte, 7000]], cancelada: true);               // cancelada: fora
        $this->cobranca($cliente, '2026-10-01', 7000, '2026-10-02');                      // anterior ao catálogo

        $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-10&ate=2026-10')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 3)
                ->where('tabela.linhas.0', ['item' => 'Hospedagem', 'recebido' => 10000, 'previsto' => 30000, 'contratos' => 2])
                ->where('tabela.linhas.1', ['item' => 'Suporte', 'recebido' => 5000, 'previsto' => 5000, 'contratos' => 0])
                ->where('tabela.linhas.2', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 7000, 'previsto' => 7000, 'contratos' => 0])
                ->where('tabela.totais.recebido', 22000));

        // Bate com a lista de cobranças: tudo o que foi pago no período.
        $this->assertSame(22000, (int) $this->usuario->cobrancas()->where('status', 'paga')->sum('valor_centavos'));
    }

    public function test_csv_neutraliza_formula_no_nome_do_cliente(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create(['nome' => '=HYPERLINK("http://golpe.example")']);
        $this->cobranca($cliente, '2026-10-10', 10000, '2026-10-10');

        $csv = $this->actingAs($this->usuario)->get('/relatorios/clientes?de=2026-10&ate=2026-10&formato=csv')->streamedContent();

        $this->assertStringContainsString("\n\"'=HYPERLINK(\"\"http://golpe.example\"\")\";100,00;", $csv);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/Relatorios/ReceitaTest.php`
  Esperado: FAIL (`/relatorios/clientes` e `/relatorios/produtos` → 404).

- [ ] **Passo 3: implementação**

`app/Services/Relatorios.php` — imports `App\Models\CobrancaItem`, `App\Models\ContratoItem`, `App\Models\ProdutoServico`,
`Illuminate\Support\Collection`; métodos:

```php
    public function porCliente(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array
    {
        $fim = $ate->endOfMonth();
        $hoje = $hoje->startOfDay();

        $pagas = $usuario->cobrancas()->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$de, $fim])
            ->selectRaw('cliente_id, sum(valor_centavos) as recebido, count(*) as quantidade')
            ->groupBy('cliente_id')->get()->keyBy('cliente_id');
        $contratos = $usuario->contratos()->where('ativo', true)
            ->selectRaw('cliente_id, count(*) as quantidade')->groupBy('cliente_id')->pluck('quantidade', 'cliente_id');
        $vencidas = $usuario->cobrancas()->where('status', StatusCobranca::Pendente)->where('vencimento', '<', $hoje)
            ->get(['cliente_id', 'vencimento'])->groupBy('cliente_id');

        $ids = $pagas->keys()->merge($contratos->keys())->merge($vencidas->keys())->unique()->all();

        $linhas = $usuario->clientes()->whereIn('id', $ids)->pluck('nome', 'id')
            ->map(function (string $nome, int $id) use ($pagas, $contratos, $vencidas, $hoje) {
                $recebido = (int) ($pagas[$id]->recebido ?? 0);
                $quantidade = (int) ($pagas[$id]->quantidade ?? 0);
                $atrasos = ($vencidas[$id] ?? collect())->map(fn (Cobranca $cobranca) => (int) $cobranca->vencimento->diffInDays($hoje));

                return [
                    'cliente' => $nome,
                    'recebido' => $recebido,
                    'contratos' => (int) ($contratos[$id] ?? 0),
                    'media' => $quantidade > 0 ? intdiv($recebido, $quantidade) : 0,
                    'vencidas' => $atrasos->count(),
                    'atraso' => $atrasos->isEmpty() ? 0 : (int) round($atrasos->avg()),
                ];
            })
            ->sortBy([['recebido', 'desc'], ['cliente', 'asc']])->values()->all();

        return $this->tabela(
            'Receita por cliente',
            'Recebido no período, contratos ativos, valor médio por cobrança paga e cobranças vencidas em aberto hoje.',
            [
                self::coluna('cliente', 'Cliente', 'texto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('contratos', 'Contratos ativos', 'numero'),
                self::coluna('media', 'Valor médio por cobrança paga', soma: false),
                self::coluna('vencidas', 'Vencidas em aberto', 'numero'),
                self::coluna('atraso', 'Atraso médio (dias)', 'dias', soma: false),
            ],
            $linhas,
        );
    }

    public function porProduto(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $fim = $ate->endOfMonth();
        $somaDosItens = fn () => CobrancaItem::query()
            ->join('cobrancas', 'cobrancas.id', '=', 'cobranca_itens.cobranca_id')
            ->where('cobrancas.user_id', $usuario->id)
            ->selectRaw('cobranca_itens.produto_servico_id as produto, sum(cobranca_itens.total_centavos) as soma')
            ->groupBy('cobranca_itens.produto_servico_id');

        $recebido = $somaDosItens()->where('cobrancas.status', StatusCobranca::Paga)
            ->whereBetween('cobrancas.pago_em', [$de, $fim])->pluck('soma', 'produto');
        $previsto = $somaDosItens()->where('cobrancas.status', '!=', StatusCobranca::Cancelada)
            ->whereBetween('cobrancas.vencimento', [$de, $fim])->pluck('soma', 'produto');
        $contratos = ContratoItem::query()
            ->join('contratos', 'contratos.id', '=', 'contrato_itens.contrato_id')
            ->where('contratos.user_id', $usuario->id)->where('contratos.ativo', true)
            ->selectRaw('contrato_itens.produto_servico_id as produto, count(distinct contrato_itens.contrato_id) as quantidade')
            ->groupBy('contrato_itens.produto_servico_id')->pluck('quantidade', 'produto');

        $ids = $recebido->keys()->merge($previsto->keys())->merge($contratos->keys())->unique()->all();

        $linhas = $usuario->produtosServicos()->whereIn('id', $ids)->get(['id', 'nome'])
            ->map(fn (ProdutoServico $produto) => [
                'item' => $produto->nome,
                'recebido' => (int) ($recebido[$produto->id] ?? 0),
                'previsto' => (int) ($previsto[$produto->id] ?? 0),
                'contratos' => (int) ($contratos[$produto->id] ?? 0),
            ])
            ->sortBy([['recebido', 'desc'], ['item', 'asc']])->values()->all();

        $semItens = fn () => $usuario->cobrancas()->whereDoesntHave('itens');
        $semItemRecebido = (int) $semItens()->where('status', StatusCobranca::Paga)->whereBetween('pago_em', [$de, $fim])->sum('valor_centavos');
        $semItemPrevisto = (int) $semItens()->where('status', '!=', StatusCobranca::Cancelada)->whereBetween('vencimento', [$de, $fim])->sum('valor_centavos');

        if ($semItemRecebido > 0 || $semItemPrevisto > 0) {
            $linhas[] = [
                'item' => 'Sem item (cobranças anteriores ao catálogo)',
                'recebido' => $semItemRecebido,
                'previsto' => $semItemPrevisto,
                'contratos' => 0,
            ];
        }

        return $this->tabela(
            'Receita por produto ou serviço',
            'Pelos itens das cobranças: recebido (pagas no período) e previsto (vencendo no período), com os contratos ativos que usam cada item.',
            [
                self::coluna('item', 'Produto ou serviço', 'texto'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('contratos', 'Contratos ativos', 'numero', soma: false),
            ],
            $linhas,
        );
    }
```

(`contratos` sem soma: um contrato com dois itens contaria duas vezes.)

`app/Http/Controllers/RelatorioController.php` — `TIPOS` e o `match`:

```php
    public const TIPOS = ['faturamento', 'clientes', 'produtos'];
```

```php
        $tabela = match ($tipo) {
            'faturamento' => $relatorios->faturamento($usuario, $de, $ate, $hoje),
            'clientes' => $relatorios->porCliente($usuario, $de, $ate, $hoje),
            'produtos' => $relatorios->porProduto($usuario, $de, $ate),
        };
```

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/Relatorios`. Esperado: PASS.
  Depois suíte inteira e `npm run build`.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T15.3: receita por produto
  soma `cobranca_itens.total_centavos` (cópia) — bate com o valor das cobranças porque valor = soma dos itens; cobranças
  anteriores ao catálogo entram na linha 'Sem item'; receita por cliente agrupa no SQL (`selectRaw` + `groupBy`) e calcula
  dias de atraso em PHP (`diffInDays` do Carbon 3 tem sinal)". Pint, depois:

```bash
git add -A
git commit -m "feat: relatórios de receita por cliente e por produto ou serviço"
```

---

### Tarefa T15.4: Relatório: contas a receber

**Estado:** pendente

**Arquivos:**
- Modificar: `app/Services/Relatorios.php`, `app/Http/Controllers/RelatorioController.php`
- Teste: `tests/Feature/Relatorios/ContasAReceberTest.php`

**Interfaces:**
- Produz: `Relatorios::aReceber(User, CarbonImmutable $hoje)` (linhas `{cliente, vencidas, atraso, ate30, ate60, ate90, depois}`;
  situação de **hoje**, sem período); `RelatorioController::TIPOS` completo.

- [ ] **Passo 1: testes que falham** — `tests/Feature/Relatorios/ContasAReceberTest.php`:

```php
<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContasAReceberTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pendentes_por_cliente_em_faixas_de_vencimento(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        $ana = Cliente::factory()->for($usuario)->create(['nome' => 'Ana Contabilidade']);
        $bruno = Cliente::factory()->for($usuario)->create(['nome' => 'Bruno Mecânica']);
        $pendente = fn (Cliente $cliente, string $vencimento, int $valor) => Cobranca::factory()->for($usuario)->for($cliente)
            ->create(['vencimento' => $vencimento, 'valor_centavos' => $valor]);

        $pendente($ana, '2026-10-05', 10000); // vencida há 10 dias
        $pendente($ana, '2026-09-15', 5000);  // vencida há 30 dias
        $pendente($ana, '2026-10-15', 2000);  // vence hoje: até 30 dias
        $pendente($ana, '2026-12-01', 3000);  // 47 dias
        $pendente($bruno, '2026-12-20', 1000); // 66 dias
        $pendente($bruno, '2027-01-20', 4000); // 97 dias
        $paga = $pendente($bruno, '2026-10-01', 8000);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-01';
        $paga->save();
        $cancelada = $pendente($bruno, '2026-10-02', 9000);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();
        Cobranca::factory()->create(['vencimento' => '2026-10-01', 'valor_centavos' => 99900]); // de outro usuário

        $this->actingAs($usuario)->get('/relatorios/a-receber')->assertInertia(fn (Assert $page) => $page
            ->has('tabela.linhas', 2)
            ->where('tabela.linhas.0', ['cliente' => 'Ana Contabilidade', 'vencidas' => 15000, 'atraso' => 30, 'ate30' => 2000, 'ate60' => 3000, 'ate90' => 0, 'depois' => 0])
            ->where('tabela.linhas.1', ['cliente' => 'Bruno Mecânica', 'vencidas' => 0, 'atraso' => 0, 'ate30' => 0, 'ate60' => 0, 'ate90' => 1000, 'depois' => 4000])
            ->where('tabela.totais', ['cliente' => 'Total', 'vencidas' => 15000, 'atraso' => null, 'ate30' => 2000, 'ate60' => 3000, 'ate90' => 1000, 'depois' => 4000]));
    }
}
```

(Contas: de 15/10/2026 a 01/12 = 16 + 30 + 1 = 47 dias; a 20/12 = 16 + 30 + 20 = 66; a 20/01/2027 = 16 + 30 + 31 + 20 = 97.
Soma das faixas = 25.000 = tudo o que está pendente.)

- [ ] **Passo 2: rodar e ver falhar** — `php artisan test --compact tests/Feature/Relatorios/ContasAReceberTest.php`
  Esperado: FAIL (`/relatorios/a-receber` → 404).

- [ ] **Passo 3: implementação**

`app/Services/Relatorios.php`:

```php
    public function aReceber(User $usuario, CarbonImmutable $hoje): array
    {
        $hoje = $hoje->startOfDay();

        $linhas = $usuario->cobrancas()->where('status', StatusCobranca::Pendente)->with('cliente:id,nome')
            ->get(['id', 'cliente_id', 'vencimento', 'valor_centavos'])
            ->groupBy('cliente_id')
            ->map(function (Collection $cobrancas) use ($hoje) {
                $linha = ['cliente' => $cobrancas->first()->cliente->nome, 'vencidas' => 0, 'atraso' => 0, 'ate30' => 0, 'ate60' => 0, 'ate90' => 0, 'depois' => 0];

                foreach ($cobrancas as $cobranca) {
                    $dias = (int) $hoje->diffInDays($cobranca->vencimento); // negativo = vencida
                    $faixa = match (true) {
                        $dias < 0 => 'vencidas',
                        $dias <= 30 => 'ate30',
                        $dias <= 60 => 'ate60',
                        $dias <= 90 => 'ate90',
                        default => 'depois',
                    };
                    $linha[$faixa] += $cobranca->valor_centavos;
                    $linha['atraso'] = max($linha['atraso'], -$dias);
                }

                return $linha;
            })
            ->sortBy('cliente')->values()->all();

        return $this->tabela(
            'Contas a receber',
            'Cobranças pendentes hoje, por cliente: vencidas (com o maior atraso) e o que vence em até 30, 60, 90 dias ou depois.',
            [
                self::coluna('cliente', 'Cliente', 'texto'),
                self::coluna('vencidas', 'Vencidas'),
                self::coluna('atraso', 'Maior atraso (dias)', 'dias', soma: false),
                self::coluna('ate30', 'Vence em até 30 dias'),
                self::coluna('ate60', 'De 31 a 60 dias'),
                self::coluna('ate90', 'De 61 a 90 dias'),
                self::coluna('depois', 'Depois de 90 dias'),
            ],
            $linhas,
        );
    }
```

(`max($linha['atraso'], -$dias)`: para quem não está vencida, `-$dias` é zero ou negativo e o maior atraso fica 0.)

`app/Http/Controllers/RelatorioController.php`:

```php
    public const TIPOS = ['faturamento', 'clientes', 'produtos', 'a-receber'];
```

e, no `match`, a linha `'a-receber' => $relatorios->aReceber($usuario, $hoje),`.

- [ ] **Passo 4: rodar e ver passar** — `php artisan test --compact tests/Feature/Relatorios`. Esperado: PASS. Depois
  suíte inteira, testes JS e `npm run build`.

- [ ] **Passo 5: documentação e commit** — tabela/estado `testada`; skill `recibos`, acerto "T15.4: contas a receber é
  a situação de hoje (sem período); faixas 0–30 (vence hoje inclusive), 31–60, 61–90 e depois de 90; a soma das faixas
  é o total pendente". Pint, depois:

```bash
git add -A
git commit -m "feat: relatório de contas a receber por cliente e faixa de vencimento"
```

---

### Tarefa T15.5: Smoke test, revisão final e fechamento

**Estado:** pendente

- [ ] **PORTÃO-BANCO para o smoke** com o usuário SMOKE (id 2): mostrar a conexão (PostgreSQL de desenvolvimento) e as
  contagens de `produtos_servicos`, `contrato_itens`, `cobranca_itens`, `contratos`, `cobrancas` e `recibos`; dizer o
  que será criado (2–3 produtos/serviços, 1 contrato por itens, 1 avulsa por itens, 1 reajuste, edição de 1 contrato
  antigo) — tudo preso ao `user_id` 2; esperar o "sim"; recontar em comando separado logo antes.
- [ ] Smoke com o **browser-agent** (Chrome dedicado na porta 9333, ver skill), em 1280 e 390 px:
  1. catálogo: cadastrar, filtrar, paginar (se houver 26+), desativar; busca só de ativos;
  2. contrato por itens: autosugestão do item, quantidade "1,5", preço com máscara, total ao vivo igual ao gravado,
     item principal, editar um contrato antigo (aviso + exigência de itens);
  3. cobrança avulsa por itens; editar não enviada; gerar cobrança de contrato e conferir os itens copiados;
  4. reajuste: marcar só um contrato, conferir o valor novo e a cobrança antiga intacta;
  5. filtros de clientes e contratos (link com filtros, voltar, página 2);
  6. CNPJ público (`00.000.000/0001-91`) preenche só os vazios; CEP `01001-000` preenche o endereço; CPF não mostra o botão;
  7. relatórios: os quatro, período, CSV aberto no Excel/LibreOffice (acentos e vírgula decimal);
  8. painel: "recebidos" = pagos no mês;
  9. chaves PIX: formulário dentro da linha clicada; "Excluir" vermelho, diferente de "Editar" (correções de 2026-10-10).
- [ ] Conferir no PostgreSQL (leitura) que `quantidade` volta como `1.500` e que as somas batem com a tela (o que os
  testes não cobrem: eles rodam em SQLite).
- [ ] Prints em `storage/app/private/verificacao/smoke-catalogo/`; V5 nas pendências.
- [ ] Revisão final do branch inteiro com revisor de contexto novo (skill executing-plans), passando o **Foco de
  revisão** deste plano; uma rodada de correções com teste que falha antes.
- [ ] Merge + push: `git checkout main; git merge --no-ff feat/modulo-cobranca-catalogo -m "merge: fase 15 — relatórios e
  recebido pelo regime de caixa (módulo Cobrança)"; git push origin main; git checkout feat/modulo-cobranca-catalogo`.
- [ ] Atualizar o roteiro (`.claude/plans/2026-10-10-roteiro-modulos.md`): módulo Cobrança concluído (pendências
  visuais à parte); próximo = brainstorming do **Contas a Pagar** (retomar a pergunta do custo mensal). Pedir ao usuário a
  limpeza dos dados SMOKE (PORTÃO-BANCO) quando ele quiser.
