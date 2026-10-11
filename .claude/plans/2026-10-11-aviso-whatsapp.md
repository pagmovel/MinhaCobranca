# Fase 24 — Aviso de vencimento pelo WhatsApp — plano de implementação

> **Para quem executa:** REQUIRED SUB-SKILL: superpowers:executing-plans (nativa — é como esta sessão sempre
> executou). Passos com checkbox (`- [ ]`). Módulo: **Cobrança**.

**Objetivo:** o prestador cadastra modelos de mensagem com variáveis (`{cliente}`, `{itens}`, `{vencimento}`,
`{total}`, `{prestador}`) e avisa o cliente sobre cobranças vencendo pelo WhatsApp — um clique monta o link
`wa.me` com a mensagem pronta; a pessoa só toca em enviar. Sem API paga, sem envio automático.

**Arquitetura:** três peças novas de dado (`modelos_mensagem`, `users.dias_aviso`, `cobrancas.avisado_em`) servidas
por dois pontos de entrada na tela — o bloco "Vencendo nos próximos N dias" do Painel (agrupado por cliente) e a
ação "Avisar no WhatsApp" no menu "⋯" da lista de cobranças (uma cobrança só) — que abrem o mesmo componente modal
(`AvisarWhatsapp.vue`). Os dois buscam os dados em `GET /cobrancas/aviso?ids[]=…` (isolado por usuário, só
pendentes) e montam o texto final num módulo JS puro (`mensagemWhatsapp.js`, testável sem Vue). Confirmar o envio
grava `avisado_em` via `POST /cobrancas/avisadas`.

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia v3, Vue 3, PHPUnit 12, `node --test` para módulos JS puros.

**Spec:** `docs/superpowers/specs/2026-10-11-aviso-whatsapp-design.md`

## Global Constraints

- Sem API paga do WhatsApp: só o link "clique para conversar" (`wa.me`), aberto no toque da pessoa.
- Link: `https://wa.me/<DDI+DDD+número, só dígitos, sem "+">?text=<mensagem codificada com encodeURIComponent>`
  (quebra de linha fica `%0A` pela própria `encodeURIComponent`). Confirmado nesta sessão via busca na web (não é
  mais `[A VERIFICAR]` como a spec registrava): múltiplas fontes independentes concordam no formato; truncamento
  prático em torno de ~1000 caracteres em alguns clientes — por isso a mensagem final avisa se passar disso
  (Review Focus item 3).
- Número do cliente: `clientes.telefone` (texto livre, sem normalização no banco). Celular válido = 11 dígitos
  (DDD de 2 dígitos + 9 dígitos começando em "9"). Sem celular válido: a janela deixa digitar (com a mesma máscara
  de `resources/js/mascaras.js::telefone`) ou oferece "Copiar mensagem". Número digitado nunca é gravado no cliente.
- Modelos (`modelos_mensagem`) isolados por usuário via relação + `findOrFail`/404 no `authorize()` (padrão de
  `ChavePixRequest`/`FeriadoRequest`), nunca por policy. Texto até 2000 caracteres. Exatamente um `padrao = true`
  por usuário quando houver modelos — trocar o padrão desmarca o anterior, na mesma transação.
- `users.dias_aviso`: inteiro 1–30, padrão 5 — mesmo padrão de `dias_antecedencia` (campo do formulário principal
  do perfil, não do bloco de modelos).
- `avisado_em` não é auditoria (não entra em `auditorias`); pode ser gravado de novo (reaviso).
- Fora do escopo (não implementar): envio automático/agendado/em massa sem toque manual; API paga; anexar o PDF
  pelo link (o link do WhatsApp só leva texto); gravar o número digitado na janela no cadastro do cliente.
- PORTÃO-BANCO: as 3 migrações novas (`modelos_mensagem`, `users.dias_aviso`, `cobrancas.avisado_em`) só aplicam no
  banco de dev depois de "sim" explícito — mas os testes (SQLite em memória, `LazilyRefreshDatabase`) rodam com as
  migrações do repositório sem precisar desse "sim"; só a aplicação real no Postgres de dev exige autorização,
  numa única rodada de `migrate --no-interaction` perto do fim (smoke test), como na Fase 23.

## Review Focus

1. **Cliente sem telefone, ou telefone cadastrado que não é celular válido.** A janela não pode travar sem saída:
   sempre precisa deixar digitar o número ou oferecer "Copiar mensagem". Teste no módulo puro (`numeroWhatsapp`
   devolve `null` para telefone inválido/vazio) — T24.4.
2. **Ids de cobrança de clientes diferentes misturados no aviso em lote.** `/cobrancas/aviso` nunca pode montar uma
   mensagem com itens de dois clientes juntos — 422 nesse caso. Teste em T24.5.
3. **Mensagem final muito longa** (cliente com várias cobranças no aviso, texto do modelo grande). Função pura
   `mensagemMuitoLonga(texto)` (limite ~1000) usada para mostrar um aviso na tela, sem travar o envio. Teste em
   T24.4.
4. **Cobrança sem nenhum item** (cadastrada antes do catálogo, ou avulsa simples). `{itens}` não pode sair vazio
   nem com uma linha estranha — usa a primeira linha da descrição, ou "Serviço" se também estiver vazia. Teste em
   T24.4 (módulo) e T24.5 (endpoint que monta os itens).
5. **Variável desconhecida no texto do modelo** (erro de digitação tipo `{clinte}`, ou uma chave que nunca vai
   existir). Fica literal no texto — nunca dá erro, nunca some o texto ao redor. Teste em T24.4.

---

## Mapa de arquivos

| Arquivo | Responsabilidade |
|---|---|
| `database/migrations/..._create_modelos_mensagem_table.php` | Tabela nova |
| `database/migrations/..._add_dias_aviso_to_users_table.php` | Coluna nova em `users` |
| `database/migrations/..._add_avisado_em_to_cobrancas_table.php` | Coluna nova em `cobrancas` |
| `app/Models/ModeloMensagem.php` | Model + factory |
| `app/Http/Requests/ModeloMensagemRequest.php` | Validação + isolamento (404) |
| `app/Http/Controllers/ModeloMensagemController.php` | CRUD (store/update/destroy) |
| `app/Http/Controllers/AvisoWhatsappController.php` | `GET /cobrancas/aviso`, `POST /cobrancas/avisadas` |
| `app/Models/User.php` | `modelosMensagem()`, `opcoesDeModelosMensagem()`, `dias_aviso` |
| `app/Models/Cobranca.php` | Cast `avisado_em` |
| `app/Http/Requests/PerfilRequest.php` | Regra de `dias_aviso` |
| `app/Http/Controllers/PerfilController.php` | Campo + prop `modelosMensagem` |
| `app/Http/Controllers/PainelController.php` | Bloco "vencendo" agrupado por cliente |
| `app/Http/Controllers/CobrancaController.php` | Props `modelosMensagem`/`prestadorNome` |
| `resources/js/mensagemWhatsapp.js` | Módulo puro: variáveis, datas, telefone, link, aviso de tamanho |
| `resources/js/Components/MensagensWhatsapp.vue` | Seção do perfil (cadastro de modelos) |
| `resources/js/Components/AvisarWhatsapp.vue` | Modal de envio (Painel e lista de cobranças) |
| `resources/js/acoesCobranca.js` | Ação "avisar" no menu "⋯" |
| `resources/js/Pages/Perfil/Editar.vue` | Campo `dias_aviso` + `<MensagensWhatsapp>` |
| `resources/js/Pages/Painel.vue` | Bloco "Vencendo nos próximos N dias" |
| `resources/js/Pages/Cobrancas/Index.vue` | Modal acionado pelo menu "⋯" |
| `routes/web.php` | Rotas novas |

---

### Task 1: `ModeloMensagem` — migração, model, CRUD isolado com "um padrão só"

**Files:**
- Create: `database/migrations/2026_10_11_150000_create_modelos_mensagem_table.php`
- Create: `app/Models/ModeloMensagem.php`
- Create: `database/factories/ModeloMensagemFactory.php`
- Create: `app/Http/Requests/ModeloMensagemRequest.php`
- Create: `app/Http/Controllers/ModeloMensagemController.php`
- Modify: `app/Models/User.php` (relação + helper)
- Modify: `routes/web.php`
- Test: `tests/Feature/ModelosMensagemTest.php`

**Interfaces:**
- Produces: `User::modelosMensagem(): HasMany`, `User::opcoesDeModelosMensagem(): array{id,nome,texto,padrao}[]`
  (consumidos por T24.3, T24.7, T24.8).
- Produces: rotas `POST /perfil/mensagens`, `PUT /perfil/mensagens/{mensagem}`, `DELETE /perfil/mensagens/{mensagem}`.

- [ ] **Step 1: Migração**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos_mensagem', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('texto', 2000);
            $table->boolean('padrao')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_mensagem');
    }
};
```

- [ ] **Step 2: Model + factory**

`app/Models/ModeloMensagem.php`:
```php
<?php

namespace App\Models;

use Database\Factories\ModeloMensagemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nome', 'texto', 'padrao'])]
class ModeloMensagem extends Model
{
    /** @use HasFactory<ModeloMensagemFactory> */
    use HasFactory;

    protected $table = 'modelos_mensagem';

    protected function casts(): array
    {
        return ['padrao' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

`database/factories/ModeloMensagemFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\ModeloMensagem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeloMensagem>
 */
class ModeloMensagemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nome' => 'Aviso padrão',
            'texto' => 'Olá {cliente}! Passando para avisar: {itens}. Vencimento: {vencimento}. Total: {total}.'."\n\n".'{prestador}',
            'padrao' => false,
        ];
    }
}
```

- [ ] **Step 3: `User::modelosMensagem()` e `opcoesDeModelosMensagem()`**

Em `app/Models/User.php`, depois de `feriados()`:
```php
    public function modelosMensagem(): HasMany
    {
        return $this->hasMany(ModeloMensagem::class)->orderBy('id');
    }

    /** @return list<array{id: int, nome: string, texto: string, padrao: bool}> */
    public function opcoesDeModelosMensagem(): array
    {
        return $this->modelosMensagem()->get()
            ->map(fn (ModeloMensagem $modelo) => ['id' => $modelo->id, 'nome' => $modelo->nome, 'texto' => $modelo->texto, 'padrao' => $modelo->padrao])
            ->all();
    }
```
Sem `use` novo: `ModeloMensagem` está no mesmo namespace `App\Models` de `User` (mesmo caso de `Feriado`, que também não
tem `use` próprio no arquivo).

- [ ] **Step 4: Request**

`app/Http/Requests/ModeloMensagemRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModeloMensagemRequest extends FormRequest
{
    /** Modelo de outro usuário vira 404 antes da validação (padrão de FeriadoRequest/ChavePixRequest). */
    public function authorize(): bool
    {
        $mensagem = $this->route('mensagem');

        abort_if($mensagem !== null && ! $this->user()->modelosMensagem()->whereKey($mensagem)->exists(), 404);

        return true;
    }

    /** Checkbox desmarcado não manda nada: sem isto, `validated()` nem traria a chave e update() deixaria o padrão antigo intacto. */
    protected function prepareForValidation(): void
    {
        $this->merge(['padrao' => $this->boolean('padrao')]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'texto' => ['required', 'string', 'max:2000'],
            'padrao' => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Step 5: Controller**

`app/Http/Controllers/ModeloMensagemController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModeloMensagemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeloMensagemController extends Controller
{
    public function store(ModeloMensagemRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            if ($request->boolean('padrao')) {
                $request->user()->modelosMensagem()->update(['padrao' => false]);
            }
            $request->user()->modelosMensagem()->create($request->validated());
        });

        return back()->with('sucesso', 'Modelo cadastrado.');
    }

    public function update(ModeloMensagemRequest $request, int $mensagem): RedirectResponse
    {
        DB::transaction(function () use ($request, $mensagem) {
            if ($request->boolean('padrao')) {
                $request->user()->modelosMensagem()->whereKeyNot($mensagem)->update(['padrao' => false]);
            }
            $request->user()->modelosMensagem()->findOrFail($mensagem)->update($request->validated());
        });

        return back()->with('sucesso', 'Modelo atualizado.');
    }

    public function destroy(Request $request, int $mensagem): RedirectResponse
    {
        $request->user()->modelosMensagem()->findOrFail($mensagem)->delete();

        return back()->with('sucesso', 'Modelo excluído.');
    }
}
```

- [ ] **Step 6: Rotas**

Em `routes/web.php`, no grupo autenticado, perto das rotas de `/perfil/feriados`:
```php
    Route::post('/perfil/mensagens', [ModeloMensagemController::class, 'store']);
    Route::put('/perfil/mensagens/{mensagem}', [ModeloMensagemController::class, 'update']);
    Route::delete('/perfil/mensagens/{mensagem}', [ModeloMensagemController::class, 'destroy']);
```
Acrescentar o `use App\Http\Controllers\ModeloMensagemController;` no topo.

- [ ] **Step 7: Teste (RED → GREEN)**

`tests/Feature/ModelosMensagemTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\ModeloMensagem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ModelosMensagemTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->actingAs($this->usuario);
    }

    public function test_cadastra_edita_e_exclui_isolado_por_usuario(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Aviso', 'texto' => 'Olá {cliente}!'])
            ->assertRedirect()->assertSessionHas('sucesso', 'Modelo cadastrado.');
        $modelo = $this->usuario->modelosMensagem()->sole();
        $this->assertSame(['Aviso', 'Olá {cliente}!', false], [$modelo->nome, $modelo->texto, $modelo->padrao]);

        $this->put("/perfil/mensagens/{$modelo->id}", ['nome' => 'Aviso editado', 'texto' => 'Oi {cliente}!'])
            ->assertRedirect()->assertSessionHas('sucesso', 'Modelo atualizado.');
        $this->assertSame('Aviso editado', $modelo->fresh()->nome);

        $this->delete("/perfil/mensagens/{$modelo->id}")->assertRedirect()->assertSessionHas('sucesso', 'Modelo excluído.');
        $this->assertSame(0, $this->usuario->modelosMensagem()->count());
    }

    public function test_marcar_padrao_desmarca_o_anterior(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $um = $this->usuario->modelosMensagem()->sole();

        $this->post('/perfil/mensagens', ['nome' => 'Dois', 'texto' => 'y', 'padrao' => true]);
        $dois = $this->usuario->modelosMensagem()->where('nome', 'Dois')->sole();

        $this->assertSame([false, true], [$um->fresh()->padrao, $dois->fresh()->padrao]);

        $this->put("/perfil/mensagens/{$um->id}", ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $this->assertSame([true, false], [$um->fresh()->padrao, $dois->fresh()->padrao]);
    }

    public function test_desmarcar_o_padrao_funciona_mesmo_sem_mandar_outro(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Um', 'texto' => 'x', 'padrao' => true]);
        $modelo = $this->usuario->modelosMensagem()->sole();

        // Checkbox desmarcado: o navegador não manda a chave "padrao" (não "padrao=false").
        $this->put("/perfil/mensagens/{$modelo->id}", ['nome' => 'Um', 'texto' => 'x']);

        $this->assertFalse($modelo->fresh()->padrao);
    }

    public function test_modelo_de_outro_usuario_da_404(): void
    {
        $alheio = ModeloMensagem::factory()->create();

        $this->put("/perfil/mensagens/{$alheio->id}", ['nome' => 'x', 'texto' => 'y'])->assertNotFound();
        $this->delete("/perfil/mensagens/{$alheio->id}")->assertNotFound();
        $this->assertNotNull($alheio->fresh());
    }

    public function test_nome_e_texto_obrigatorios_e_texto_ate_2000(): void
    {
        $this->post('/perfil/mensagens', ['nome' => '', 'texto' => ''])->assertSessionHasErrors(['nome', 'texto']);
        $this->post('/perfil/mensagens', ['nome' => 'x', 'texto' => str_repeat('a', 2001)])->assertSessionHasErrors('texto');
    }
}
```

Run: `php artisan test --filter=ModelosMensagemTest`
Expected: RED (classes/rotas ainda não existem) → implementar os passos acima → GREEN, 5/5.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/ModeloMensagem.php database/factories/ModeloMensagemFactory.php app/Http/Requests/ModeloMensagemRequest.php app/Http/Controllers/ModeloMensagemController.php app/Models/User.php routes/web.php tests/Feature/ModelosMensagemTest.php
git commit -m "feat: modelos de mensagem do WhatsApp, isolados por usuário, com um padrão só (T24.1)"
```

---

### Task 2: `users.dias_aviso`

**Files:**
- Create: `database/migrations/2026_10_11_150100_add_dias_aviso_to_users_table.php`
- Modify: `app/Models/User.php` (`$fillable`, `casts()`, `$attributes`)
- Modify: `app/Http/Requests/PerfilRequest.php`
- Modify: `app/Http/Controllers/PerfilController.php` (`CAMPOS`)
- Modify: `resources/js/Pages/Perfil/Editar.vue` (campo no formulário)
- Test: `tests/Feature/PerfilTest.php` (estende o existente)

**Interfaces:**
- Consumes: nada de outra tarefa.
- Produces: `$usuario->dias_aviso` (inteiro), consumido por T24.7 (janela do bloco "vencendo").

- [ ] **Step 1: Migração**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_aviso')->default(5)->after('dias_antecedencia');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dias_aviso');
        });
    }
};
```

- [ ] **Step 2: `User.php`**

No atributo `#[Fillable([...])]`, acrescentar `'dias_aviso'` depois de `'dias_antecedencia'`. Em `$attributes`,
acrescentar `'dias_aviso' => 5,`. Em `casts()`, acrescentar `'dias_aviso' => 'integer',`.

- [ ] **Step 3: `PerfilRequest.php`**

Em `rules()`, depois de `'dias_antecedencia' => ['required', 'integer', 'between:0,60'],`:
```php
            'dias_aviso' => ['required', 'integer', 'between:1,30'],
```

- [ ] **Step 4: `PerfilController.php`**

Em `CAMPOS`, acrescentar `'dias_aviso',` depois de `'dias_antecedencia',`.

- [ ] **Step 5: Teste (RED → GREEN)**

Em `tests/Feature/PerfilTest.php`, no helper `dados()`, acrescentar `'dias_aviso' => 8,` depois de
`'dias_antecedencia' => 7,`. Em `test_salva_e_normaliza_os_dados`, depois de
`$this->assertSame(7, $usuario->dias_antecedencia);`:
```php
        $this->assertSame(8, $usuario->dias_aviso);
```
Acrescentar um teste novo:
```php
    public function test_dias_aviso_fora_de_1_a_30_e_recusado(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/perfil', $this->dados(['dias_aviso' => 0]))->assertSessionHasErrors('dias_aviso');
        $this->post('/perfil', $this->dados(['dias_aviso' => 31]))->assertSessionHasErrors('dias_aviso');
    }
```

Run: `php artisan test --filter=PerfilTest`
Expected: RED (coluna/regra não existem) → GREEN.

- [ ] **Step 6: Campo na tela**

Em `resources/js/Pages/Perfil/Editar.vue`, depois do `<Campo id="dias_antecedencia" ...>` (linha ~65-67):
```html
                <Campo id="dias_aviso" rotulo="Avisar sobre vencimento com quantos dias de antecedência" :erro="form.errors.dias_aviso" class="sm:col-span-3">
                    <input id="dias_aviso" v-model="form.dias_aviso" type="number" min="1" max="30" class="entrada">
                </Campo>
```

- [ ] **Step 7: Pint, build, commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
git add database/migrations app/Models/User.php app/Http/Requests/PerfilRequest.php app/Http/Controllers/PerfilController.php resources/js/Pages/Perfil/Editar.vue tests/Feature/PerfilTest.php
git commit -m "feat: dias de antecedência do aviso de WhatsApp (users.dias_aviso) (T24.2)"
```

---

### Task 3: Seção "Mensagens de WhatsApp" no Perfil (tela)

**Files:**
- Create: `resources/js/Components/MensagensWhatsapp.vue`
- Modify: `app/Http/Controllers/PerfilController.php` (prop `modelosMensagem` em `edit()`)
- Modify: `resources/js/Pages/Perfil/Editar.vue` (prop + componente)
- Test: `tests/Feature/ModelosMensagemTest.php` (estende — a prova de que a prop chega certa)

**Interfaces:**
- Consumes: `User::opcoesDeModelosMensagem()` (T24.1).
- Produces: nada consumido por tarefa posterior (ponto de entrada de tela).

- [ ] **Step 1: Prop no controller**

Em `PerfilController::edit()`, dentro do array do `Inertia::render('Perfil/Editar', [...])`, depois de
`'feriadosNacionais' => ...,`:
```php
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
```

- [ ] **Step 2: Teste do prop (RED → GREEN)**

Em `tests/Feature/ModelosMensagemTest.php`, acrescentar:
```php
    public function test_tela_do_perfil_traz_os_modelos_do_usuario(): void
    {
        $this->post('/perfil/mensagens', ['nome' => 'Aviso', 'texto' => 'Olá {cliente}!', 'padrao' => true]);

        $this->get('/perfil')->assertInertia(fn ($pagina) => $pagina
            ->has('modelosMensagem', 1)
            ->where('modelosMensagem.0.nome', 'Aviso')
            ->where('modelosMensagem.0.padrao', true));
    }
```
(Precisa de `use Inertia\Testing\AssertableInertia as Assert;` se o closure usar o tipo — seguir o padrão de
`PerfilTest.php`, que já importa isso; ou tipar `Assert $pagina` igual aos outros testes do arquivo.)

Run: `php artisan test --filter=ModelosMensagemTest`
Expected: RED (prop ainda não existe) → GREEN.

- [ ] **Step 3: Componente `MensagensWhatsapp.vue`**

Modelo: mesma estrutura de `resources/js/Components/Feriados.vue` (lista editável na linha, `editando`/`confirmando`,
exclusão em dois cliques) — sem a parte de ajuste em lote, que não existe aqui.

```vue
<script setup>
import { nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    modelos: { type: Array, required: true },
});

const VARIAVEIS = [
    { token: '{cliente}', legenda: 'nome do cliente' },
    { token: '{itens}', legenda: 'lista dos itens vencendo' },
    { token: '{vencimento}', legenda: 'data(s) de vencimento' },
    { token: '{total}', legenda: 'valor total' },
    { token: '{prestador}', legenda: 'seu nome ou razão social' },
];

const editando = ref(null); // id do modelo em edição, 'novo' para inclusão
const confirmando = ref(null);
const textarea = ref(null);
const form = useForm({ nome: '', texto: '', padrao: false });

const linhas = () => (editando.value === 'novo' ? [...props.modelos, { id: 'novo' }] : props.modelos);

async function abrir(modelo = null) {
    form.clearErrors();
    form.nome = modelo?.nome ?? '';
    form.texto = modelo?.texto ?? '';
    form.padrao = modelo?.padrao ?? false;
    confirmando.value = null;
    editando.value = modelo ? modelo.id : 'novo';
    await nextTick();
    document.getElementById('mensagem_nome')?.focus();
}

/** Insere o token na posição do cursor — o foco some ao clicar no botão, então guarda a posição antes. */
function inserir(token) {
    const campo = textarea.value;
    const inicio = campo?.selectionStart ?? form.texto.length;
    const fim = campo?.selectionEnd ?? form.texto.length;
    form.texto = form.texto.slice(0, inicio) + token + form.texto.slice(fim);
    nextTick(() => {
        campo?.focus();
        campo?.setSelectionRange(inicio + token.length, inicio + token.length);
    });
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'novo') {
        form.post('/perfil/mensagens', opcoes);
    } else {
        form.put(`/perfil/mensagens/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/mensagens/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});
</script>

<template>
    <section id="mensagens-whatsapp" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-mensagens-whatsapp">
        <h2 id="titulo-mensagens-whatsapp" class="text-sm font-semibold">Mensagens de WhatsApp</h2>
        <p class="text-sm text-grafite/80">
            Modelos usados para avisar clientes sobre cobranças vencendo. O marcado como padrão abre pronto na janela de envio.
        </p>

        <ul v-if="linhas().length" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="modelo in linhas()" :key="modelo.id" :class="editando === modelo.id ? 'bg-papel' : ''">
                <form
                    v-if="editando === modelo.id"
                    class="grid gap-4 px-4 py-4"
                    :aria-label="modelo.id === 'novo' ? 'Novo modelo' : `Editar modelo ${modelo.nome}`"
                    @submit.prevent="salvar"
                    @keydown.esc="editando = null"
                >
                    <p class="text-sm font-semibold">{{ modelo.id === 'novo' ? 'Novo modelo' : 'Editando este modelo' }}</p>
                    <div>
                        <label for="mensagem_nome" class="mb-1 block text-xs font-medium text-grafite/75">Nome do modelo</label>
                        <input id="mensagem_nome" v-model="form.nome" maxlength="60" class="entrada" required>
                        <p v-if="form.errors.nome" class="mt-1 text-xs text-tinta-vermelha">{{ form.errors.nome }}</p>
                    </div>
                    <div>
                        <label for="mensagem_texto" class="mb-1 block text-xs font-medium text-grafite/75">Texto</label>
                        <textarea id="mensagem_texto" ref="textarea" v-model="form.texto" maxlength="2000" rows="5" class="entrada" required />
                        <p v-if="form.errors.texto" class="mt-1 text-xs text-tinta-vermelha">{{ form.errors.texto }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button v-for="variavel in VARIAVEIS" :key="variavel.token" type="button" class="botao-secundario text-xs" :title="variavel.legenda" @click="inserir(variavel.token)">
                                {{ variavel.token }}
                            </button>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.padrao" type="checkbox"> Usar como modelo padrão
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button class="botao" :disabled="form.processing">{{ modelo.id === 'novo' ? 'Cadastrar modelo' : 'Salvar modelo' }}</button>
                        <button type="button" class="botao-secundario" @click="editando = null">Cancelar</button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">
                            {{ modelo.nome }}
                            <span v-if="modelo.padrao" class="ml-1 font-estreita text-xs text-grafite/70">(padrão)</span>
                        </p>
                        <p class="line-clamp-1 font-estreita text-sm text-grafite/80">{{ modelo.texto }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="botao-secundario" @click="abrir(modelo)">Editar</button>
                        <button v-if="confirmando !== modelo.id" type="button" class="botao-perigo" @click="confirmando = modelo.id">Excluir</button>
                        <template v-else>
                            <button type="button" class="botao-perigo-confirmar" @click="excluir(modelo.id)">Confirmar exclusão</button>
                            <button type="button" class="botao-secundario" @click="confirmando = null">Manter modelo</button>
                        </template>
                    </div>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-grafite/80">Nenhum modelo cadastrado — a janela de envio sugere um texto.</p>

        <div v-if="editando !== 'novo'">
            <button type="button" class="botao-secundario" @click="abrir()">Novo modelo</button>
        </div>
    </section>
</template>
```

- [ ] **Step 4: Inclusão em `Editar.vue`**

Acrescentar prop `modelosMensagem: { type: Array, required: true }`, importar `MensagensWhatsapp` e incluir depois
de `<Feriados ...>`:
```html
        <MensagensWhatsapp class="mt-10" :modelos="modelosMensagem" />
```

- [ ] **Step 5: Build + commit**

```bash
npm run build
git add app/Http/Controllers/PerfilController.php resources/js/Components/MensagensWhatsapp.vue resources/js/Pages/Perfil/Editar.vue tests/Feature/ModelosMensagemTest.php
git commit -m "feat: tela de cadastro dos modelos de mensagem no perfil (T24.3)"
```

---

### Task 4: `mensagemWhatsapp.js` — módulo puro (variáveis, datas, telefone, link, aviso de tamanho)

**Files:**
- Create: `resources/js/mensagemWhatsapp.js`
- Test: `resources/js/mensagemWhatsapp.test.js`

**Interfaces:**
- Consumes: nada (módulo puro, sem dependência de servidor nem Vue).
- Produces: `montarMensagem(texto, dados)`, `numeroWhatsapp(telefone)`, `montarLinkWaMe(numero, texto)`,
  `mensagemMuitoLonga(texto)` — consumidos por T24.6 (`AvisarWhatsapp.vue`).

- [ ] **Step 1: Teste — substituição de variáveis (RED)**

`resources/js/mensagemWhatsapp.test.js`:
```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from './mensagemWhatsapp.js';

const dados = (extra) => ({
    cliente: 'Padaria do João',
    itens: [{ nome: 'Hospedagem de site', valorCentavos: 8990 }],
    vencimentos: ['2026-10-15'],
    totalCentavos: 8990,
    prestador: 'Marcos Ronaldo',
    ...extra,
});

test('substitui todas as variáveis conhecidas', () => {
    const texto = montarMensagem('Olá {cliente}! {itens} Vence em {vencimento}. Total: {total}. — {prestador}', dados());
    assert.equal(texto, 'Olá Padaria do João! - Hospedagem de site: R$ 89,90 Vence em 15/10/2026. Total: R$ 89,90. — Marcos Ronaldo');
});

test('variável desconhecida fica literal no texto', () => {
    const texto = montarMensagem('Oi {cliente}, {algumacoisa} aqui.', dados());
    assert.equal(texto, 'Oi Padaria do João, {algumacoisa} aqui.');
});

test('cobrança sem itens usa a descricao (primeira linha) ou "Serviço"', () => {
    const texto = montarMensagem('{itens}', dados({ itens: [{ nome: 'Serviço', valorCentavos: 10000 }] }));
    assert.equal(texto, '- Serviço: R$ 100,00');
});

test('mais de um item: uma linha por item', () => {
    const texto = montarMensagem('{itens}', dados({
        itens: [{ nome: 'Hospedagem', valorCentavos: 8990 }, { nome: 'Domínio', valorCentavos: 4000 }],
    }));
    assert.equal(texto, '- Hospedagem: R$ 89,90\n- Domínio: R$ 40,00');
});

test('duas datas: "e" antes da última, só a última com o ano', () => {
    assert.equal(montarMensagem('{vencimento}', dados({ vencimentos: ['2026-10-15', '2026-10-20'] })), '15/10 e 20/10/2026');
});

test('três datas ou mais: vírgula entre as primeiras, "e" antes da última', () => {
    assert.equal(
        montarMensagem('{vencimento}', dados({ vencimentos: ['2026-10-10', '2026-10-15', '2026-10-20'] })),
        '10/10, 15/10 e 20/10/2026',
    );
});

test('numeroWhatsapp: celular válido (11 dígitos, 9 depois do DDD)', () => {
    assert.equal(numeroWhatsapp('(79) 99999-0000'), '5579999990000');
});

test('numeroWhatsapp: fixo (10 dígitos) não é válido', () => {
    assert.equal(numeroWhatsapp('(79) 3214-0000'), null);
});

test('numeroWhatsapp: vazio, incompleto ou sem o 9 depois do DDD não é válido', () => {
    assert.equal(numeroWhatsapp(''), null);
    assert.equal(numeroWhatsapp('(79) 9999-000'), null);
    assert.equal(numeroWhatsapp('79812340000'), null); // 3º dígito não é 9
});

test('montarLinkWaMe: número com DDI e texto codificado', () => {
    const link = montarLinkWaMe('5579999990000', 'Olá! Tudo bem?');
    assert.equal(link, 'https://wa.me/5579999990000?text=Ol%C3%A1!%20Tudo%20bem%3F');
});

test('mensagemMuitoLonga: true a partir de 1000 caracteres', () => {
    assert.equal(mensagemMuitoLonga('a'.repeat(999)), false);
    assert.equal(mensagemMuitoLonga('a'.repeat(1001)), true);
});
```

Run: `node --test resources/js/mensagemWhatsapp.test.js`
Expected: FAIL — `./mensagemWhatsapp.js` não existe.

- [ ] **Step 2: Implementação (GREEN)**

`resources/js/mensagemWhatsapp.js`:
```js
import { formatarCentavos } from './itens.js';

/** Limite prático observado em clientes do WhatsApp: acima disso, alguns cortam a mensagem. */
const LIMITE_PRATICO = 1000;

function linhaDoItem(item) {
    return `- ${item.nome}: R$ ${formatarCentavos(item.valorCentavos)}`;
}

/** "2026-10-15" (ISO, sem hora) → "15/10/2026" ou "15/10" sem o ano. */
function formatarData(iso, comAno) {
    const [ano, mes, dia] = iso.split('-');

    return comAno ? `${dia}/${mes}/${ano}` : `${dia}/${mes}`;
}

/** Uma data: dia/mês/ano. Duas ou mais: "a, b e c" — só a última com o ano. */
function juntarVencimentos(vencimentos) {
    if (vencimentos.length === 1) {
        return formatarData(vencimentos[0], true);
    }
    const curtas = vencimentos.slice(0, -1).map((data) => formatarData(data, false));
    const ultima = formatarData(vencimentos.at(-1), true);

    return `${curtas.join(', ')} e ${ultima}`;
}

/**
 * Substitui as variáveis conhecidas por valores reais; o que não reconhece fica literal no texto (erro de
 * digitação no modelo não pode travar nem sumir pedaço da mensagem).
 */
export function montarMensagem(texto, { cliente, itens, vencimentos, totalCentavos, prestador }) {
    return texto
        .replaceAll('{cliente}', cliente)
        .replaceAll('{itens}', itens.map(linhaDoItem).join('\n'))
        .replaceAll('{vencimento}', juntarVencimentos(vencimentos))
        .replaceAll('{total}', `R$ ${formatarCentavos(totalCentavos)}`)
        .replaceAll('{prestador}', prestador);
}

/** Celular válido (DDI 55 + DDD + 9 dígitos começando em "9") para o link do WhatsApp; senão, null. */
export function numeroWhatsapp(telefone) {
    const digitos = String(telefone ?? '').replace(/\D/g, '');

    return digitos.length === 11 && digitos[2] === '9' ? `55${digitos}` : null;
}

export function montarLinkWaMe(numero, texto) {
    return `https://wa.me/${numero}?text=${encodeURIComponent(texto)}`;
}

export function mensagemMuitoLonga(texto) {
    return texto.length > LIMITE_PRATICO;
}
```

Run: `node --test resources/js/mensagemWhatsapp.test.js`
Expected: PASS, 13/13.

- [ ] **Step 3: Commit**

```bash
git add resources/js/mensagemWhatsapp.js resources/js/mensagemWhatsapp.test.js
git commit -m "feat: módulo puro da mensagem de WhatsApp — variáveis, datas, telefone, link (T24.4)"
```

---

### Task 5: `AvisoWhatsappController` — dados do aviso e marcar "avisada"

**Files:**
- Create: `database/migrations/2026_10_11_150200_add_avisado_em_to_cobrancas_table.php`
- Modify: `app/Models/Cobranca.php` (cast)
- Create: `app/Http/Controllers/AvisoWhatsappController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AvisoWhatsappTest.php`

**Interfaces:**
- Consumes: `Cliente::exibicao()` (já existe), `CobrancaItem` (já existe).
- Produces: `GET /cobrancas/aviso` → `{cliente, telefone, itens, vencimentos, total_centavos}`;
  `POST /cobrancas/avisadas` → grava `avisado_em`. Consumidos por T24.6, T24.7, T24.8.

- [ ] **Step 1: Migração**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->timestamp('avisado_em')->nullable()->after('enviado_em');
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas', function (Blueprint $table) {
            $table->dropColumn('avisado_em');
        });
    }
};
```

- [ ] **Step 2: Cast em `Cobranca.php`**

Em `casts()`, acrescentar `'avisado_em' => 'immutable_datetime',` (junto de `'enviado_em' => 'immutable_datetime',`).

- [ ] **Step 3: Teste (RED)**

`tests/Feature/AvisoWhatsappTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AvisoWhatsappTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create(['telefone' => '(79) 99999-0000']);
        $this->actingAs($this->usuario);
    }

    private function cobranca(array $extra = []): Cobranca
    {
        return Cobranca::factory()->for($this->usuario)->for($this->cliente)->create(array_merge(['status' => StatusCobranca::Pendente], $extra));
    }

    public function test_monta_os_dados_com_os_itens_da_cobranca(): void
    {
        $cobranca = $this->cobranca(['vencimento' => '2026-10-15', 'valor_centavos' => 8990]);
        CobrancaItem::factory()->for($cobranca)->create(['nome' => 'Hospedagem', 'total_centavos' => 8990]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")->assertOk()->assertJson([
            'cliente' => $this->cliente->exibicao(),
            'telefone' => '(79) 99999-0000',
            'itens' => [['nome' => 'Hospedagem', 'valor_centavos' => 8990]],
            'vencimentos' => ['2026-10-15'],
            'total_centavos' => 8990,
        ]);
    }

    public function test_cobranca_sem_itens_usa_a_primeira_linha_da_descricao(): void
    {
        $cobranca = $this->cobranca(['descricao' => "Manutenção mensal\nDetalhe que não entra", 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Manutenção mensal', 'valor_centavos' => 5000]]]);
    }

    public function test_cobranca_sem_itens_e_sem_descricao_usa_servico(): void
    {
        $cobranca = $this->cobranca(['descricao' => '', 'valor_centavos' => 5000]);

        $this->getJson("/cobrancas/aviso?ids[]={$cobranca->id}")
            ->assertJson(['itens' => [['nome' => 'Serviço', 'valor_centavos' => 5000]]]);
    }

    public function test_junta_varias_cobrancas_do_mesmo_cliente(): void
    {
        $uma = $this->cobranca(['vencimento' => '2026-10-15', 'valor_centavos' => 3000]);
        $outra = $this->cobranca(['vencimento' => '2026-10-20', 'valor_centavos' => 4000]);

        $this->getJson("/cobrancas/aviso?ids[]={$uma->id}&ids[]={$outra->id}")->assertJson([
            'vencimentos' => ['2026-10-15', '2026-10-20'],
            'total_centavos' => 7000,
        ]);
    }

    public function test_ids_de_clientes_diferentes_e_recusado(): void
    {
        $outroCliente = Cliente::factory()->for($this->usuario)->create();
        $uma = $this->cobranca();
        $deOutroCliente = Cobranca::factory()->for($this->usuario)->for($outroCliente)->create(['status' => StatusCobranca::Pendente]);

        $this->getJson("/cobrancas/aviso?ids[]={$uma->id}&ids[]={$deOutroCliente->id}")->assertStatus(422);
    }

    public function test_ids_alheios_ou_ja_pagos_sao_ignorados_e_so_resta_nenhum_da_404(): void
    {
        $alheia = Cobranca::factory()->create(['status' => StatusCobranca::Pendente]);
        $this->getJson("/cobrancas/aviso?ids[]={$alheia->id}")->assertNotFound();

        $paga = $this->cobranca(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);
        $this->getJson("/cobrancas/aviso?ids[]={$paga->id}")->assertNotFound();
    }

    public function test_id_nao_numerico_e_recusado_sem_erro_500(): void
    {
        $this->getJson('/cobrancas/aviso?ids[]=abc')->assertUnprocessable()->assertJsonValidationErrors('ids.0');
    }

    public function test_avisadas_grava_so_nas_proprias_pendentes(): void
    {
        $minha = $this->cobranca();
        $paga = $this->cobranca(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);
        $alheia = Cobranca::factory()->create(['status' => StatusCobranca::Pendente]);

        $this->post('/cobrancas/avisadas', ['ids' => [$minha->id, $paga->id, $alheia->id]])->assertRedirect();

        $this->assertNotNull($minha->fresh()->avisado_em);
        $this->assertNull($paga->fresh()->avisado_em);
        $this->assertNull($alheia->fresh()->avisado_em);
    }
}
```

Run: `php artisan test --filter=AvisoWhatsappTest`
Expected: FAIL (rotas e controller ainda não existem).

- [ ] **Step 4: Implementação (GREEN)**

`app/Http/Controllers/AvisoWhatsappController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\CobrancaItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AvisoWhatsappController extends Controller
{
    /** Dados para montar a mensagem (servidor nunca manda itens de dois clientes juntos). */
    public function dados(Request $request): JsonResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['integer']]);

        $cobrancas = $request->user()->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereIn('id', $dados['ids'])->with(['cliente', 'itens'])->get();

        abort_if($cobrancas->isEmpty(), 404);
        abort_if($cobrancas->pluck('cliente_id')->unique()->count() > 1, 422, 'As cobranças escolhidas são de clientes diferentes.');

        $itens = $cobrancas->flatMap(fn (Cobranca $cobranca) => $cobranca->itens->isNotEmpty()
            ? $cobranca->itens->map(fn (CobrancaItem $item) => ['nome' => $item->nome, 'valor_centavos' => $item->total_centavos])
            : collect([['nome' => (string) (Str::of($cobranca->descricao)->before("\n")->trim() ?: 'Serviço'), 'valor_centavos' => $cobranca->valor_centavos]]));

        return response()->json([
            'cliente' => $cobrancas->first()->cliente->exibicao(),
            'telefone' => $cobrancas->first()->cliente->telefone ?? '',
            'itens' => $itens->values(),
            'vencimentos' => $cobrancas->pluck('vencimento')->unique()->sort()->map(fn ($data) => $data->toDateString())->values(),
            'total_centavos' => $cobrancas->sum('valor_centavos'),
        ]);
    }

    /** Marca como avisadas: só as próprias e pendentes (ids alheios, pagas ou canceladas são ignorados). */
    public function avisadas(Request $request): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['integer']]);

        $quantas = $request->user()->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereIn('id', $dados['ids'])->update(['avisado_em' => now()]);

        return back()->with('sucesso', $quantas === 1 ? '1 cobrança marcada como avisada.' : "{$quantas} cobranças marcadas como avisadas.");
    }
}
```

Em `routes/web.php`, perto das rotas de `/cobrancas`:
```php
    Route::get('/cobrancas/aviso', [AvisoWhatsappController::class, 'dados']);
    Route::post('/cobrancas/avisadas', [AvisoWhatsappController::class, 'avisadas']);
```
(`use App\Http\Controllers\AvisoWhatsappController;` no topo.)

Run: `php artisan test --filter=AvisoWhatsappTest`
Expected: PASS, 8/8.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations app/Models/Cobranca.php app/Http/Controllers/AvisoWhatsappController.php routes/web.php tests/Feature/AvisoWhatsappTest.php
git commit -m "feat: dados do aviso de WhatsApp e marcação de avisada, isolados por usuário (T24.5)"
```

---

### Task 6: `AvisarWhatsapp.vue` — modal de envio

**Files:**
- Create: `resources/js/Components/AvisarWhatsapp.vue`

**Interfaces:**
- Consumes: `montarMensagem`, `numeroWhatsapp`, `montarLinkWaMe`, `mensagemMuitoLonga` (T24.4); `GET /cobrancas/aviso`,
  `POST /cobrancas/avisadas` (T24.5); `Modal.vue` (já existe).
- Produces: componente `AvisarWhatsapp` com props `{ aberto: Boolean, ids: Array, modelos: Array, prestador: String }`
  e evento `fechar`. Consumido por T24.7 e T24.8.

Sem teste automatizado (projeto não tem harness de teste para `.vue`, só módulos JS puros — confirmado na Fase 23);
verificado por `npm run build` e, depois, pela conferência no navegador (T24.9).

- [ ] **Step 1: Componente**

`resources/js/Components/AvisarWhatsapp.vue`:
```vue
<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import { formatadores } from '../mascaras.js';
import { mensagemMuitoLonga, montarLinkWaMe, montarMensagem, numeroWhatsapp } from '../mensagemWhatsapp.js';

const props = defineProps({
    aberto: { type: Boolean, required: true },
    ids: { type: Array, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});
const emit = defineEmits(['fechar']);

const SUGESTAO = 'Olá {cliente}! Passando para avisar que {itens} está{s} vencendo em {vencimento}, no valor de {total}. '
    + 'Qualquer dúvida, estou à disposição.\n\n{prestador}';

const carregando = ref(false);
const erro = ref('');
const dados = ref(null);
const modeloEscolhido = ref(null);
const texto = ref('');
const telefone = ref('');
const enviando = ref(false);

const padrao = computed(() => props.modelos.find((modelo) => modelo.padrao) ?? null);
const textoDoModeloEscolhido = computed(() => props.modelos.find((modelo) => modelo.id === modeloEscolhido.value)?.texto
    ?? padrao.value?.texto ?? SUGESTAO.replace('{s}', dados.value?.itens.length > 1 ? 'm' : ''));

function atualizarTexto() {
    if (!dados.value) {
        return;
    }
    texto.value = montarMensagem(textoDoModeloEscolhido.value, {
        cliente: dados.value.cliente,
        itens: dados.value.itens.map((item) => ({ nome: item.nome, valorCentavos: item.valor_centavos })),
        vencimentos: dados.value.vencimentos,
        totalCentavos: dados.value.total_centavos,
        prestador: props.prestador,
    });
}

watch(() => props.aberto, async (aberto) => {
    if (!aberto) {
        return;
    }
    carregando.value = true;
    erro.value = '';
    dados.value = null;
    try {
        const parametros = new URLSearchParams(props.ids.map((id) => ['ids[]', id]));
        const resposta = await fetch(`/cobrancas/aviso?${parametros}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!resposta.ok) {
            throw new Error('falhou');
        }
        dados.value = await resposta.json();
        modeloEscolhido.value = padrao.value?.id ?? null;
        telefone.value = numeroWhatsapp(dados.value.telefone) ? formatadores.telefone(dados.value.telefone) : '';
        atualizarTexto();
    } catch {
        erro.value = 'Não foi possível carregar os dados do aviso. Tente de novo.';
    } finally {
        carregando.value = false;
    }
});
watch(modeloEscolhido, atualizarTexto);

const numeroValido = computed(() => numeroWhatsapp(telefone.value));
const avisoDeTamanho = computed(() => mensagemMuitoLonga(texto.value));

function marcarAvisadas() {
    enviando.value = true;
    router.post('/cobrancas/avisadas', { ids: props.ids }, {
        preserveScroll: true,
        onFinish: () => { enviando.value = false; emit('fechar'); },
    });
}

function abrirWhatsapp() {
    window.open(montarLinkWaMe(numeroValido.value, texto.value), '_blank');
    marcarAvisadas();
}

async function copiarMensagem() {
    await navigator.clipboard.writeText(texto.value);
    marcarAvisadas();
}
</script>

<template>
    <Modal :aberto="aberto" titulo="Avisar no WhatsApp" largura="max-w-xl" @fechar="emit('fechar')">
        <p v-if="carregando" class="text-sm text-grafite/80">Carregando…</p>
        <p v-else-if="erro" role="alert" class="text-sm text-tinta-vermelha">{{ erro }}</p>
        <div v-else-if="dados" class="grid gap-4">
            <div v-if="modelos.length">
                <label for="aviso_modelo" class="mb-1 block text-xs font-medium text-grafite/75">Modelo</label>
                <select id="aviso_modelo" v-model="modeloEscolhido" class="entrada">
                    <option :value="null">Texto sugerido</option>
                    <option v-for="modelo in modelos" :key="modelo.id" :value="modelo.id">{{ modelo.nome }}</option>
                </select>
            </div>
            <div>
                <label for="aviso_texto" class="mb-1 block text-xs font-medium text-grafite/75">Mensagem</label>
                <textarea id="aviso_texto" v-model="texto" rows="6" class="entrada" />
                <p v-if="avisoDeTamanho" class="mt-1 text-xs text-grafite/70">
                    Mensagem longa: alguns aparelhos podem cortar o final.
                </p>
                <p v-if="!modelos.length" class="mt-1 text-xs text-grafite/70">
                    Sem modelo cadastrado.
                    <a href="/perfil#mensagens-whatsapp" class="underline">Salvar como modelo</a>
                </p>
            </div>
            <div>
                <label for="aviso_telefone" class="mb-1 block text-xs font-medium text-grafite/75">Número do WhatsApp</label>
                <input id="aviso_telefone" v-model="telefone" @input="telefone = formatadores.telefone(telefone)" class="entrada" placeholder="(00) 00000-0000">
                <p v-if="!numeroValido" class="mt-1 text-xs text-grafite/70">Sem número de celular válido: digite um, ou copie a mensagem.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="botao" :disabled="!numeroValido || enviando" @click="abrirWhatsapp">Abrir WhatsApp</button>
                <button type="button" class="botao-secundario" :disabled="enviando" @click="copiarMensagem">Copiar mensagem</button>
            </div>
        </div>
    </Modal>
</template>
```

- [ ] **Step 2: Build**

```bash
npm run build
```
Expected: sem erro.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/AvisarWhatsapp.vue
git commit -m "feat: janela de envio do aviso de WhatsApp — modelo, prévia editável, telefone com fallback (T24.6)"
```

---

### Task 7: Painel — bloco "Vencendo nos próximos N dias"

**Files:**
- Modify: `app/Http/Controllers/PainelController.php` (branch `vencendo` em `dados()`; props novas em `__invoke()`)
- Create: `resources/js/Components/BlocoVencendo.vue`
- Modify: `resources/js/Pages/Painel.vue`
- Test: `tests/Feature/AvisoWhatsappTest.php` (estende — cobre `/painel/dados?lista=vencendo`)

**Interfaces:**
- Consumes: `User::dias_aviso` (T24.2), `User::opcoesDeModelosMensagem()` (T24.1), `AvisarWhatsapp.vue` (T24.6).

- [ ] **Step 1: Teste (RED)**

Em `tests/Feature/AvisoWhatsappTest.php`, acrescentar:
```php
    public function test_painel_vencendo_agrupa_por_cliente_dentro_da_janela_de_dias(): void
    {
        $this->usuario->forceFill(['dias_aviso' => 5])->save();
        $daJanela = $this->cobranca(['vencimento' => '2026-10-14', 'valor_centavos' => 8990]); // hoje + 3
        $this->cobranca(['vencimento' => '2026-10-20']); // hoje + 9, fora dos 5 dias
        $this->cobranca(['vencimento' => '2026-10-13', 'status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01']);

        $this->getJson('/painel/dados?lista=vencendo')->assertOk()->assertJson([
            'linhas' => [[
                'cliente' => $this->cliente->exibicao(),
                'quantidade' => 1,
                'vencimentos' => ['14/10/2026'],
                'total_centavos' => 8990,
                'ids' => [$daJanela->id],
            ]],
            'total_geral' => ['quantidade' => 1, 'soma_centavos' => 8990],
        ]);
    }

    public function test_painel_vencendo_mostra_avisado_em_mais_recente_do_grupo(): void
    {
        $antiga = $this->cobranca(['vencimento' => '2026-10-13', 'avisado_em' => '2026-10-05 10:00:00']);
        $this->cobranca(['vencimento' => '2026-10-14', 'avisado_em' => '2026-10-08 10:00:00']);

        $this->getJson('/painel/dados?lista=vencendo')->assertJsonPath('linhas.0.avisado_em', '08/10');
    }
```

Run: `php artisan test --filter=AvisoWhatsappTest`
Expected: FAIL (branch `vencendo` ainda não existe).

- [ ] **Step 2: `PainelController.php`**

Em `dados()`, acrescentar `'vencendo'` ao `Rule::in([...])` e tratar antes do `match` comum (a forma dos dados é
diferente — grupo por cliente, não cobrança por linha):
```php
    public function dados(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'lista' => ['required', Rule::in(['para_enviar', 'aguardando', 'recebidas', 'vencendo'])],
            ...BlocoDePaginas::regras([10, 25, 50]),
        ]);
        $hoje = CarbonImmutable::today();

        if ($filtros['lista'] === 'vencendo') {
            return $this->dadosDeVencendo($request, $hoje, (int) ($filtros['pagina'] ?? 1), (int) ($filtros['por_pagina'] ?? 10));
        }

        $consulta = $request->user()->cobrancas();
        // ... (resto do método como já está)
```

Acrescentar o método novo (privado) na classe:
```php
    private function dadosDeVencendo(Request $request, CarbonImmutable $hoje, int $pagina, int $porPagina): JsonResponse
    {
        $usuario = $request->user();
        $fim = $hoje->addDays($usuario->dias_aviso);

        $grupos = $usuario->cobrancas()->where('status', StatusCobranca::Pendente)
            ->whereBetween('vencimento', [$hoje, $fim])
            ->with('cliente:id,nome,apelido')
            ->orderBy('vencimento')
            ->get()
            ->groupBy('cliente_id')
            ->map(function (Collection $cobrancas) {
                $centavos = (int) $cobrancas->sum('valor_centavos');

                return [
                    'cliente_id' => $cobrancas->first()->cliente_id,
                    'cliente' => $cobrancas->first()->cliente->exibicao(),
                    'quantidade' => $cobrancas->count(),
                    'vencimentos' => $cobrancas->pluck('vencimento')->unique()->sort()->map(fn ($data) => $data->format('d/m/Y'))->values()->all(),
                    'total_centavos' => $centavos,
                    'total' => Dinheiro::formatar($centavos),
                    'avisado_em' => $cobrancas->pluck('avisado_em')->filter()->sort()->last()?->format('d/m'),
                    'ids' => $cobrancas->pluck('id')->all(),
                ];
            })
            ->values();

        $bloco = BlocoDePaginas::de($grupos, $pagina, $porPagina);

        return response()->json([
            'linhas' => $bloco['itens'],
            'total_geral' => ['quantidade' => $grupos->count(), 'soma_centavos' => (int) $grupos->sum('total_centavos')],
            'pagina' => $bloco['pagina'],
        ]);
    }
```
Acrescentar `use Illuminate\Support\Collection;` ao topo.

Em `__invoke()`, acrescentar ao array do `Inertia::render('Painel', [...])`:
```php
            'diasAviso' => $usuario->dias_aviso,
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
            'prestadorNome' => $usuario->nome_fantasia ?: $usuario->name,
```

Run: `php artisan test --filter=AvisoWhatsappTest`
Expected: PASS.

- [ ] **Step 3: `BlocoVencendo.vue`**

Mesma estrutura de `ListaDoPainel.vue`, mas a linha é um grupo (cliente), não uma cobrança — não reaproveita
`LinhaCobranca.vue`:
```vue
<script setup>
import { reactive, ref } from 'vue';
import AvisarWhatsapp from './AvisarWhatsapp.vue';
import EstadoDaLista from './EstadoDaLista.vue';
import Paginador from './Paginador.vue';
import { paginadorVisivel } from '../paginacao.js';
import { usarListaPaginada } from '../usarListaPaginada.js';

defineProps({
    diasAviso: { type: Number, required: true },
    modelos: { type: Array, required: true },
    prestador: { type: String, required: true },
});

const OPCOES = [10, 25, 50];
const filtro = reactive({ lista: 'vencendo', pagina: 1, por_pagina: 10 });
const { linhas, resumo, carregando, falha, estado, irPara, recarregar, escolheuPorPagina } = usarListaPaginada({
    url: '/painel/dados', filtro, componente: 'Painel', idDoFoco: 'lista-vencendo',
});

const avisando = ref(null);
</script>

<template>
    <section class="mt-8" aria-labelledby="titulo-vencendo">
        <h2 id="titulo-vencendo" class="mb-2 text-lg font-semibold">
            Vencendo nos próximos {{ diasAviso }} dias
            <span v-if="resumo" class="font-estreita font-normal text-grafite/70">({{ resumo.total_geral.quantidade }})</span>
        </h2>
        <EstadoDaLista :estado="estado" :falha="falha" carregando="Carregando…" vazio="Nada vencendo nos próximos dias." :linhas="2" @tentar="recarregar">
            <ul id="lista-vencendo" tabindex="-1" class="divide-y divide-linha border border-linha bg-white transition-opacity focus:outline-none" :class="{ 'opacity-60': carregando }" :aria-busy="carregando">
                <li v-for="grupo in linhas" :key="grupo.cliente_id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ grupo.cliente }}</p>
                        <p class="font-estreita text-grafite/80">
                            {{ grupo.quantidade === 1 ? '1 cobrança' : `${grupo.quantidade} cobranças` }} · {{ grupo.vencimentos.join(', ') }} · R$ {{ grupo.total }}
                            <template v-if="grupo.avisado_em"> · Avisado em {{ grupo.avisado_em }}</template>
                        </p>
                    </div>
                    <button type="button" class="botao-secundario" @click="avisando = grupo">Avisar no WhatsApp</button>
                </li>
            </ul>
            <Paginador
                v-if="paginadorVisivel(resumo.pagina.total, OPCOES)"
                id="paginador-vencendo"
                :pagina="filtro.pagina"
                :por-pagina="filtro.por_pagina"
                :total="resumo.pagina.total"
                :carregando="carregando"
                :opcoes="OPCOES"
                rotulo="Páginas: Vencendo"
                @ir="irPara($event, { focar: true })"
                @por-pagina="escolheuPorPagina"
            />
        </EstadoDaLista>
        <AvisarWhatsapp :aberto="Boolean(avisando)" :ids="avisando?.ids ?? []" :modelos="modelos" :prestador="prestador" @fechar="avisando = null" />
    </section>
</template>
```

- [ ] **Step 4: `Painel.vue`**

Acrescentar props `diasAviso`, `modelosMensagem`, `prestadorNome`; importar `BlocoVencendo` e incluir depois do
aviso de dia não útil (ou onde fizer sentido visualmente — antes das três `ListaDoPainel` existentes):
```html
        <BlocoVencendo :dias-aviso="diasAviso" :modelos="modelosMensagem" :prestador="prestadorNome" />
```

- [ ] **Step 5: Pint, build, commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
php artisan test --compact
git add app/Http/Controllers/PainelController.php resources/js/Components/BlocoVencendo.vue resources/js/Pages/Painel.vue tests/Feature/AvisoWhatsappTest.php
git commit -m "feat: bloco \"Vencendo nos próximos N dias\" no Painel, agrupado por cliente (T24.7)"
```

---

### Task 8: Lista de cobranças — ação "Avisar no WhatsApp" no menu "⋯"

**Files:**
- Modify: `resources/js/acoesCobranca.js`
- Modify: `resources/js/acoesCobranca.test.js`
- Modify: `resources/js/Pages/Cobrancas/Index.vue`
- Modify: `app/Http/Controllers/CobrancaController.php` (props `modelosMensagem`/`prestadorNome`)

**Interfaces:**
- Consumes: `AvisarWhatsapp.vue` (T24.6), `User::opcoesDeModelosMensagem()` (T24.1).

- [ ] **Step 1: Teste (RED) — `acoesCobranca.test.js`**

Atualizar as asserções existentes (a ação nova muda a posição das demais) e acrescentar casos novos:
```js
test('em aberto e não enviada: enviar é a principal', () => {
    assert.deepEqual(acoesDaCobranca(cobranca()), {
        principal: 'enviar',
        menu: ['baixa', 'pdf', 'avisar', 'pagamento', 'editar', 'cancelar'],
    });
});

test('em aberto e já enviada: dar baixa é a principal, sem editar', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'vencida', enviado: true, editavel: false })), {
        principal: 'baixa',
        menu: ['enviar', 'pdf', 'avisar', 'pagamento', 'cancelar'],
    });
});
```
(trocar as duas asserções existentes por estas — `'avisar'` entra depois de `'pdf'`). E:
```js
test('sem recibo não oferece PDF nem envio, mas avisar continua disponível', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ recibo: null })), {
        principal: 'baixa',
        menu: ['avisar', 'pagamento', 'editar', 'cancelar'],
    });
});
```
(trocar a asserção existente de `'sem recibo...'`). E acrescentar:
```js
test('paga, cancelada e prevista não oferecem avisar', () => {
    assert.ok(!acoesDaCobranca(cobranca({ situacao: 'paga', enviado: true })).menu.includes('avisar'));
    assert.ok(!acoesDaCobranca(cobranca({ situacao: 'cancelada' })).menu.includes('avisar'));
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'prevista', recibo: null, editavel: false })), { principal: null, menu: [] });
});

test('rótulo de avisar', () => {
    assert.equal(rotuloDaAcao('avisar', cobranca()), 'Avisar no WhatsApp');
});
```

Run: `node --test resources/js/acoesCobranca.test.js`
Expected: FAIL (as asserções atualizadas não batem com o código atual, que ainda não tem `'avisar'`).

- [ ] **Step 2: `acoesCobranca.js` (GREEN)**

```js
    return [...inicio, ...pdf, 'avisar', 'pagamento', ...(cobranca.editavel ? ['editar'] : []), 'cancelar'];
```
(troca a linha `return [...inicio, ...pdf, 'pagamento', ...` existente). E em `ROTULOS`:
```js
    avisar: 'Avisar no WhatsApp',
```

Run: `node --test resources/js/acoesCobranca.test.js`
Expected: PASS.

- [ ] **Step 3: `CobrancaController.php`**

Em `index()`, acrescentar ao array do `Inertia::render('Cobrancas/Index', [...])`:
```php
            'modelosMensagem' => $usuario->opcoesDeModelosMensagem(),
            'prestadorNome' => $usuario->nome_fantasia ?: $usuario->name,
```

- [ ] **Step 4: `Cobrancas/Index.vue`**

Acrescentar props `modelosMensagem: { type: Array, required: true }` e `prestadorNome: { type: String, required: true }`.
Importar `AvisarWhatsapp` e um `ref` novo:
```js
const avisando = ref(null);
```
No template, dentro do `<MenuAcoes>` (antes do `<button v-else ...>` genérico, depois do `v-else-if="acao === 'enviar'"`):
```html
                                            <button v-else-if="acao === 'avisar'" type="button" :class="itemMenu" @click="fechar(); avisando = cobranca">
                                                Avisar no WhatsApp
                                            </button>
```
E, fora da tabela (perto do fim do template, como um elemento solto — padrão de modal controlado por `ref`):
```html
        <AvisarWhatsapp :aberto="Boolean(avisando)" :ids="avisando ? [avisando.id] : []" :modelos="modelosMensagem" :prestador="prestadorNome" @fechar="avisando = null" />
```

- [ ] **Step 5: Pint, build, commit**

```bash
vendor/bin/pint --dirty --format agent
npm run build
node --test resources/js/*.test.js
php artisan test --compact
git add resources/js/acoesCobranca.js resources/js/acoesCobranca.test.js resources/js/Pages/Cobrancas/Index.vue app/Http/Controllers/CobrancaController.php
git commit -m "feat: ação \"Avisar no WhatsApp\" no menu de cada cobrança pendente (T24.8)"
```

---

### Task 9: Conferência final — suíte completa, migração no dev, smoke no navegador

Sem arquivos novos. Checklist de fechamento (mesmo padrão da Fase 23):

- [ ] **Step 1: Suíte completa**

```bash
php artisan test --compact
node --test resources/js/*.test.js
npm run build
```
Expected: tudo verde, sem avisos de risky test (saída com código 0).

- [ ] **Step 2: Migração no banco de dev — PORTÃO-BANCO**

Antes de rodar, mostrar ao usuário: conexão (host/porta/banco/usuário), que são 3 migrações novas e aditivas
(`modelos_mensagem`, `users.dias_aviso`, `cobrancas.avisado_em`), o comando literal
(`php artisan migrate --no-interaction`), e esperar o "sim" — autorização específica para este comando, não
herdada de autorizações anteriores.

- [ ] **Step 3: Conferência no navegador (browser-agent, nunca Playwright)**

Roteiro mínimo: cadastrar um modelo com variáveis no perfil; abrir "Avisar no WhatsApp" numa cobrança pendente da
lista (prévia confere, telefone pré-preenchido quando o cliente tem celular válido); abrir o bloco "Vencendo" no
Painel com pelo menos um cliente agrupado; confirmar que o link `wa.me` abre numa aba nova com o texto certo
(sem precisar realmente enviar); confirmar "Copiar mensagem"; confirmar que "avisado em" aparece depois.

- [ ] **Step 4: Revisão final (fresh reviewer, opus) + documentação**

Mesma disciplina da Fase 23: `review-package` do merge-base da branch `feat/aviso-whatsapp` até o HEAD, subagente
opus, corrigir Critical/Important com TDD, Minor para decisão do usuário. Atualizar
`.claude/skills/recibos/SKILL.md` (Acertos/Pegadinhas da Fase 24) e `.claude/plans/2026-10-10-roteiro-modulos.md`
(seção 0) ao final, antes do merge na `main`.
