# Sistema de cobranças e recibos — Plano de implementação

> **Para agentes executores:** SUB-SKILL OBRIGATÓRIA: use superpowers:subagent-driven-development (recomendado) ou superpowers:executing-plans para executar este plano tarefa a tarefa. Os passos usam checkbox (`- [ ]`) para acompanhamento.

**Objetivo:** sistema multiusuário em Laravel + Inertia + Vue que gera, a partir de contratos recorrentes, cobranças com recibo em PDF A4 fiel aos modelos de NFS-e (WebISS e DANFSe), para envio antecipado e baixa manual.

**Arquitetura:** Laravel 13 serve rotas, validação e regras de negócio; páginas Vue 3 renderizadas via Inertia v3 (sem API separada). Serviços pequenos em `app/Services` concentram a lógica (períodos, numeração, emissão, geração, renderização). PDF gerado na hora pelo dompdf a partir de um snapshot congelado + dados vivos da cobrança; template escolhido pela versão gravada no recibo.

**Stack:** PHP 8.4.24 · Laravel 13.35 · inertiajs/inertia-laravel ^3.5 · @inertiajs/vue3 ^3.9 · vue ^3.5 · @vitejs/plugin-vue ^6.0 · Vite 8 · Tailwind 4 · barryvdh/laravel-dompdf ^3.1.2 · PHPUnit 12 · SQLite (dev e testes em memória)

**Spec:** [docs/superpowers/specs/2026-10-09-sistema-recibos-design.md](../../docs/superpowers/specs/2026-10-09-sistema-recibos-design.md) — o executor lê a spec junto com este plano.

---

## Acompanhamento

Estados (regra global): `pendente` → `concluída` (código pronto) → `testada` (verificação rodada e passando) → `validada` (usuário confirmou). Atualizar esta tabela **e** o campo **Estado** da tarefa a cada mudança — não só no fim.

| Fase | Tarefa | Estado |
|---|---|---|
| 0 | T0.1 Git + Laravel Boost | concluída (sem teste automatizado: tarefa de instalação) |
| 0 | T0.2 Idioma, fuso e mensagens em pt-BR | testada |
| 0 | T0.3 Inertia + Vue + tokens visuais + página Entrar | testada |
| 0 | T0.4 dompdf + prova de conceito (acentos, rotação, imagem) | testada (rotação e posição do carimbo aguardam conferência visual do usuário) |
| 0 | T0.5 `.claude/` (skill, memória) + inventário no CLAUDE.md/README | concluída |
| 0 | T0.6 PORTÃO-BANCO: migrações padrão no SQLite de desenvolvimento | concluída (nada a gravar: migrações padrão já aplicadas no lote 1) |
| 1 | T1.1 Usuário: status, admin, dados do prestador | testada |
| 1 | T1.2 Cadastro com aprovação pendente | testada |
| 1 | T1.3 Entrar, sair, bloqueio por status, painel inicial | testada |
| 1 | T1.4 Administração de usuários | pendente |
| 1 | T1.5 Comando `app:criar-admin` | pendente |
| 1 | T1.6 PORTÃO-BANCO + criar admin + smoke test de contas | pendente |
| 2 | T2.1 CPF/CNPJ (inclusive CNPJ alfanumérico) | pendente |
| 2 | T2.2 Meu perfil (prestador, logo, modelo padrão, antecedência) | pendente |
| 2 | T2.3 Clientes | pendente |
| 3 | T3.1 Dinheiro e regra de valor em reais | pendente |
| 3 | T3.2 Periodicidade e calculadora de períodos | pendente |
| 3 | T3.3 Contratos | pendente |
| 3 | T3.4 Tabelas e models de cobranças e recibos | pendente |
| 3 | T3.5 Numeração e emissão de recibos (snapshot) | pendente |
| 3 | T3.6 Gerador de cobranças recorrentes | pendente |
| 3 | T3.7 Ações de cobrança (avulsa, editar, cancelar, baixa, enviada) | pendente |
| 3 | T3.8 PORTÃO-BANCO: migrações da fase 3 | pendente |
| 4 | T4.1 Valor por extenso | pendente |
| 4 | T4.2 Renderizador + rota do PDF + modelo `webiss-v1` | pendente |
| 4 | T4.3 Modelo `danfse-v1` | pendente |
| 4 | T4.4 Comparação visual com as notas originais | pendente |
| 5 | T5.1 Painel completo (três blocos, enviar, baixa) | pendente |
| 5 | T5.2 Telas de cobranças (lista com filtros, avulsa, edição) | pendente |
| 5 | T5.3 PORTÃO-BANCO + smoke test + revisão visual (frontend-design) | pendente |
| 6 | T6.0 Entrada: XMLs de exemplo em `MATERIAIS/` (BLOQUEADA) | pendente |
| 6 | T6.1 Tabela de notas fiscais + leitura segura de XML | pendente |
| 6 | T6.2 Leitores nacional e municipal (detalhar após T6.0) | pendente |
| 6 | T6.3 Conciliação, vínculo, citação no recibo, criar cliente | pendente |
| 6 | T6.4 PORTÃO-BANCO: migração da fase 6 | pendente |
| 7 | T7.1 Suíte contra PostgreSQL de teste declarado pelo usuário | pendente |

---

## Restrições globais

Valem para todas as tarefas, sem precisar repetir:

- **Idioma:** pt-BR em tudo — textos de tela, mensagens, comentários, commits, docs. Identificadores de domínio em português (`Cobranca`, `valor_centavos`), sem acento em nomes de código.
- **Dinheiro:** sempre `integer` em centavos (`*_centavos`). Nunca `float`/`decimal` em banco.
- **SQL portátil:** nada específico de SQLite (o destino é PostgreSQL). `orderByRaw` só com `CASE WHEN` padrão.
- **Datas:** fuso `America/Sao_Paulo`; colunas `date` com cast `immutable_date`; comparação de mês com `whereBetween` + `CarbonImmutable`.
- **Isolamento:** todo acesso a dado de negócio passa pela relação do usuário logado (`$request->user()->clientes()->findOrFail($id)`) — recurso de outro usuário vira 404. O admin não acessa dados de negócio de ninguém.
- **Models:** seguir o padrão Laravel 13 do projeto: atributos `#[Fillable([...])]`/`#[Hidden([...])]` e método `casts()` (ver `app/Models/User.php`). `status`, `is_admin`, `pago_em`, `enviado_em` e `logo` **não** são preenchíveis em massa — atribuir explicitamente.
- **Inertia v3** (não v2): root view com `<x-inertia::head>`/`<x-inertia::app>`; `Inertia::optional()` (não `lazy()`); `config/inertia.php` com `pages` e `testing.ensure_pages_exist` — todo componente afirmado em teste precisa existir em `resources/js/Pages`.
- **Testes:** PHPUnit, `RefreshDatabase`, banco SQLite **em memória** (`phpunit.xml` linhas 26–27) — autorizado pelo usuário. `tests/TestCase.php` chama `withoutVite()` (cobre `@vite` e `@fonts`).
- **Execução de comandos:** pelo context-mode (`ctx_execute`/`ctx_batch_execute`), inclusive `php artisan test` e `npm run build`. `Read` só para arquivo que será editado em seguida.
- **PowerShell:** encadear com `;`, nunca `&&`. Para "B só se A der certo": `A; if ($?) { B }`.
- **Commits:** só com autorização do usuário para commits nesta execução. Mensagem em pt-BR no formato `tipo: descrição`, terminando com a linha `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Documentar na hora:** ao concluir cada tarefa aprovada, registrar em `.claude/skills/recibos/SKILL.md` o que foi decidido, as pegadinhas encontradas e os acertos.
- **Nunca supor:** item marcado **[A VERIFICAR]** vira passo de verificação; se a verificação não decidir, registrar **[INCONCLUSIVO]** e perguntar ao usuário.

### PORTÃO-BANCO (procedimento obrigatório antes de qualquer escrita no banco real)

Vale para `migrate`, `app:criar-admin`, smoke test que cria dados ou sessões, e qualquer comando que grave em `database/database.sqlite` ou outro banco que não seja o SQLite em memória dos testes.

1. Mostrar a conexão: `php artisan db:show` → informar ao usuário driver, caminho/host, porta, banco e usuário, e dizer explicitamente se é produção (o `.env` atual usa `DB_CONNECTION=sqlite`, arquivo local — confirmar na saída, não presumir).
2. Contar o alvo: `php artisan db:show --counts` (linhas por tabela) e `php artisan migrate:status`.
3. Mostrar o efeito literal: para migração, `php artisan migrate --pretend` (SQL que seria executado) — **só se a tabela `migrations` já existir**: verificado em `MigrateCommand.php:140-146` que, sem ela, o `--pretend` cria a tabela antes de simular (é escrita). Sem a tabela, mostrar o conteúdo das migrações pendentes em vez do `--pretend`. Para outros comandos, o comando exato e as tabelas/linhas que vai criar ou alterar.
4. Perguntar ao usuário e **esperar o "sim" para aquele comando**. Autorização não é transitiva: um "sim" para `migrate` não autoriza `app:criar-admin` nem o smoke test.
5. Executar e mostrar o resultado (`migrate:status` ou contagem depois).

Se o usuário inserir dados de teste pelo smoke test, marcá-los com identificador próprio (ex.: e-mail `smoke-AAAAMMDD@teste.local`, cliente "SMOKE …") para limpeza **por esse identificador**, também só com autorização.

---

## Foco de revisão

Entradas e falhas que a spec implica e que os testes "do caminho feliz" não exercitariam. Cada linha tem teste na tarefa dona do código.

1. **Valor digitado em formatos variados** (`1.234,56`, `260,6`, `260`, `R$ 260,60`, `260.60`, vazio, negativo, texto) → converte para os centavos certos ou recusa com "Informe um valor válido, como 260,60." — testes em **T3.1**.
2. **Cliente com campos opcionais vazios** (sem inscrições, e-mail, complemento ou endereço) → o PDF mostra "-" em cada caixa, nunca vazio, "null" ou vírgulas soltas — teste em **T4.2**.
3. **Descrição longa ou com muitas linhas** → recusada acima de 2.000 caracteres ou 25 linhas, para não estourar a caixa do A4 — teste em **T3.3** (e conferência visual em T4.4).
4. **Datas de borda no contrato** (vencimento dia 29–31, início no futuro, fim antes do início, mudar início/periodicidade com cobranças já geradas) → períodos corretos, validação clara, sem duplicar períodos — testes em **T3.2** e **T3.3**.
5. **Excluir cliente que já tem contrato ou cobrança** → bloqueado com mensagem, nada apagado — teste em **T3.4**.

---

## Mapa de arquivos

```
app/
  Enums/        StatusUsuario, StatusCobranca, FamiliaModelo, ModeloRecibo, Periodicidade
  Support/      Documento, Dinheiro, Ufs, Periodo
  Rules/        DocumentoValido, ValorEmReais, LinhasMaximas
  Services/     CalculadoraDePeriodos, NumeradorDeRecibos, EmissorDeRecibos,
                GeradorDeCobrancas, ValorPorExtenso, RenderizadorDeRecibo,
                Nfse/ (fase 6: LeitorXmlSeguro, LeitorNfse, DadosNfse, Conciliador)
  Models/       User (alterado), Cliente, Contrato, Cobranca, Recibo, NotaFiscal (fase 6)
  Http/Middleware/  HandleInertiaRequests (gerado), GarantirContaAprovada
  Http/Requests/    PerfilRequest, ClienteRequest, ContratoRequest, CobrancaRequest
  Http/Controllers/ Auth/{LoginController,CadastroController}, Admin/UsuarioController,
                    PainelController, PerfilController, ClienteController,
                    ContratoController, CobrancaController, ReciboPdfController,
                    NotaFiscalController (fase 6)
  Providers/AppServiceProvider (Gate admin, padrões de rota)
database/migrations/  2026_10_09_100000 … (uma por tabela)
database/factories/   UserFactory (alterado), ClienteFactory, ContratoFactory, CobrancaFactory
lang/pt_BR/validation.php
resources/views/app.blade.php
resources/views/recibos/pdf/  _carimbo, webiss-v1, danfse-v1
resources/views/verificacao/prova-dompdf.blade.php
resources/css/app.css · resources/js/app.js
resources/js/Layouts/    CamadaPublica.vue, CamadaApp.vue
resources/js/Components/ Campo, CamposEndereco, Carimbo, BotaoEnviar, BaixaInline,
                         CancelarInline, LinhaCobranca
resources/js/Pages/      Auth/{Entrar,Cadastro}, Painel, Perfil/Editar, Admin/Usuarios,
                         Clientes/{Index,Form}, Contratos/{Index,Form},
                         Cobrancas/{Index,Form}, NotasFiscais/Index (fase 6)
routes/web.php · routes/console.php · bootstrap/app.php
tests/Unit/     DocumentoTest, DinheiroTest, CalculadoraDePeriodosTest, ValorPorExtensoTest
tests/Feature/  ConfiguracaoTest, ProvaDompdfTest, Contas/*, PerfilTest, ClientesTest,
                ContratosTest, Cobrancas/*, Recibos/*, PainelTest, Nfse/*
.claude/        plans/, skills/recibos/SKILL.md, MEMORY.md
```

---

## Fase 0 — Concluir instalação

### Tarefa T0.1: Git + Laravel Boost

**Estado:** concluída — Boost instalado com `-n --guidelines --skills --mcp` (Claude Code, Codex, Cursor, OpenCode); guidelines sem conflito com o plano; nova regra: pint antes de cada commit

**Arquivos:**
- Criar: repositório git na raiz
- Modificar: `composer.json`, `composer.lock` (pelo composer), `CLAUDE.md`/`AGENTS.md` (pelo `boost:install`)

**Interfaces:** nenhuma de código. Produz: repositório git e guidelines do Boost que as tarefas seguintes devem respeitar.

**Por quê:** o `CLAUDE.md`/`AGENTS.md` do projeto (idênticos) mandam instalar o Laravel Boost antes de qualquer mudança na aplicação e reler o `AGENTS.md` depois.

- [ ] **Passo 1: iniciar o repositório**

Run: `git init`
Esperado: `Initialized empty Git repository in D:/laragon/www/Recibos/.git/`

- [ ] **Passo 2: garantir que `.claude/` não é ignorado**

Run: `git check-ignore -v .claude .claude/memory .claude/plans .claude/skills .claude/MEMORY.md`
Esperado: nenhuma saída (código de saída 1). Qualquer linha = avisar o usuário e remover a regra do `.gitignore` antes de seguir. Nunca usar `git add -f`.

- [ ] **Passo 3: instalar o Boost**

Run: `composer require laravel/boost --dev`
Esperado: instala `laravel/boost` v2.10.x (verificado no Packagist: v2.10.3 aceita `illuminate/* ^13.0`).

- [ ] **Passo 4: descobrir se o `boost:install` roda sem interação**

Run: `php artisan boost:install --help`
Esperado: lista de opções. O shell do agente não responde prompts interativos.
- Se houver opções para rodar sem perguntas, usar essas opções escolhendo o agente **Claude Code** e as guidelines do projeto.
- Se não houver, **pedir ao usuário** para rodar `php artisan boost:install` no terminal dele e avisar quando terminar.

- [ ] **Passo 5: reler as guidelines geradas**

Ler `CLAUDE.md` e `AGENTS.md` inteiros (via `ctx_execute_file`). Se alguma guideline contradisser este plano (ex.: exigir Pest em vez de PHPUnit, outra organização de pastas), **parar e relatar ao usuário** antes de seguir; não decidir sozinho.

- [ ] **Passo 6: conferir de novo o `.gitignore`**

Run: `git check-ignore -v .claude .claude/memory .claude/plans .claude/skills .claude/MEMORY.md`
Esperado: nenhuma saída. (O Boost não pode ter adicionado regra que alcance `.claude/`.)

- [ ] **Passo 7: commit inicial (se autorizado)**

```powershell
git add -A
git commit -m "chore: estado inicial do projeto com spec, plano e Laravel Boost`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T0.2: Idioma, fuso e mensagens de validação em pt-BR

**Estado:** testada — ConfiguracaoTest 2/2; suíte verde

**Arquivos:**
- Modificar: `.env` (linhas `APP_NAME`, `APP_LOCALE`, `APP_FAKER_LOCALE`), `.env.example` (mesmas linhas), `config/app.php:68`
- Criar: `lang/pt_BR/validation.php`
- Teste: `tests/Feature/ConfiguracaoTest.php`

**Interfaces:** produz `config('app.timezone') === 'America/Sao_Paulo'`, `config('app.locale') === 'pt_BR'` e as mensagens de validação usadas por todas as tarefas.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/ConfiguracaoTest.php`:

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfiguracaoTest extends TestCase
{
    public function test_fuso_e_idioma_sao_brasileiros(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('pt_BR', config('app.locale'));
        $this->assertSame('Gerenciador de Cobranças', config('app.name'));
    }

    public function test_mensagens_de_validacao_saem_em_portugues(): void
    {
        $validador = validator(['email' => 'x', 'name' => ''], [
            'email' => ['required', 'email'],
            'name' => ['required'],
        ]);

        $this->assertSame('Informe um e-mail válido.', $validador->errors()->first('email'));
        $this->assertSame('Preencha o campo nome.', $validador->errors()->first('name'));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=ConfiguracaoTest`
Esperado: FAIL (`'UTC'` ≠ `'America/Sao_Paulo'`).

- [ ] **Passo 3: ajustar configuração**

Em `config/app.php`, linha 68: `'timezone' => 'UTC',` → `'timezone' => 'America/Sao_Paulo',`

Em `.env` e `.env.example`:

```dotenv
APP_NAME="Gerenciador de Cobranças"
APP_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR
```

(`APP_FALLBACK_LOCALE` continua `en`: regra sem tradução cai para o inglês que vem no framework.)

Criar `lang/pt_BR/validation.php`:

```php
<?php

return [
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'before_or_equal' => 'O campo :attribute deve ser uma data igual ou anterior a :date.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'date_format' => 'O campo :attribute deve estar no formato :format.',
    'digits' => 'O campo :attribute deve ter :digits dígitos.',
    'email' => 'Informe um e-mail válido.',
    'enum' => 'O valor escolhido para :attribute é inválido.',
    'exists' => 'O valor escolhido para :attribute é inválido.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'image' => 'O campo :attribute deve ser uma imagem.',
    'in' => 'O valor escolhido para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'max' => [
        'file' => 'O arquivo :attribute deve ter no máximo :max KB.',
        'numeric' => 'O campo :attribute deve ser no máximo :max.',
        'string' => 'O campo :attribute deve ter no máximo :max caracteres.',
    ],
    'mimes' => 'O arquivo :attribute deve ser do tipo: :values.',
    'min' => [
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
    ],
    'required' => 'Preencha o campo :attribute.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está cadastrado.',
    'uploaded' => 'Não foi possível enviar o arquivo :attribute.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'documento' => 'CPF/CNPJ',
        'nome' => 'nome',
        'nome_fantasia' => 'nome fantasia',
        'inscricao_municipal' => 'inscrição municipal',
        'inscricao_estadual' => 'inscrição estadual',
        'telefone' => 'telefone',
        'logradouro' => 'logradouro',
        'numero' => 'número',
        'bairro' => 'bairro',
        'cidade' => 'cidade',
        'uf' => 'UF',
        'cep' => 'CEP',
        'logo' => 'logo',
        'modelo' => 'modelo do recibo',
        'modelo_padrao' => 'modelo padrão',
        'dias_antecedencia' => 'dias de antecedência',
        'cliente_id' => 'cliente',
        'descricao' => 'descrição',
        'valor' => 'valor',
        'periodicidade' => 'periodicidade',
        'dia_vencimento' => 'dia do vencimento',
        'data_inicio' => 'início',
        'data_fim' => 'fim',
        'periodo_inicio' => 'início do período',
        'periodo_fim' => 'fim do período',
        'vencimento' => 'vencimento',
        'pago_em' => 'data do pagamento',
        'mes' => 'mês',
        'arquivos' => 'arquivos',
    ],
];
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan config:clear; php artisan test --filter=ConfiguracaoTest`
Esperado: PASS (2 testes).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add config/app.php .env.example lang tests/Feature/ConfiguracaoTest.php
git commit -m "feat: idioma pt-BR, fuso de Brasília e mensagens de validação`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

(O `.env` não é versionado — o `.gitignore` padrão do Laravel o ignora; confirmar com `git check-ignore -v .env`.)

---

### Tarefa T0.3: Inertia v3 + Vue 3 + tokens visuais + página Entrar

**Estado:** testada — PaginaEntrarTest 1/1; npm run build ok; inertia-laravel v3.5.1

**Arquivos:**
- Modificar: `composer.json`, `package.json` (via instaladores), `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`, `bootstrap/app.php`, `routes/web.php`, `tests/TestCase.php`
- Criar: `resources/views/app.blade.php`, `app/Http/Middleware/HandleInertiaRequests.php` (via artisan), `app/Http/Controllers/Auth/LoginController.php`, `resources/js/Layouts/CamadaPublica.vue`, `resources/js/Components/Campo.vue`, `resources/js/Pages/Auth/Entrar.vue`
- Remover: `resources/views/welcome.blade.php`, `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`
- Teste: `tests/Feature/Contas/PaginaEntrarTest.php`

**Interfaces:**
- Produz: rota `GET /entrar` nomeada `login`; props compartilhadas `auth.user` e `flash.sucesso`/`flash.erro`; classes CSS `entrada`, `botao`, `botao-secundario`; cores Tailwind `papel`, `grafite`, `carbono`, `linha`, `tinta-verde`, `tinta-vermelha`; fonte `font-estreita`; componente `Campo` (props `id`, `rotulo`, `erro`, slot).

- [ ] **Passo 1: instalar dependências**

```powershell
composer require inertiajs/inertia-laravel:^3.5
npm install
npm install vue@^3.5 @inertiajs/vue3@^3.9
npm install --save-dev @vitejs/plugin-vue@^6.0
php artisan inertia:middleware
```

Esperado: `app/Http/Middleware/HandleInertiaRequests.php` criado.

- [ ] **Passo 2: conferir as exportações do cliente Inertia usadas no plano**

Rodar via `ctx_execute` (shell): `grep -rhoE "export \{[^}]*\}" node_modules/@inertiajs/vue3/types/index.d.ts | tr ',' '\n' | grep -E "useForm|usePage|router|Link|createInertiaApp"`
Esperado: as cinco aparecem. Se faltar alguma, registrar **[INCONCLUSIVO]**, consultar `https://inertiajs.com/docs/v3` e ajustar o plano antes de seguir.

- [ ] **Passo 3: escrever o teste que falha**

`tests/TestCase.php`:

```php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Sem manifest do Vite nos testes; o fake também cobre a diretiva @fonts.
        $this->withoutVite();
    }
}
```

`tests/Feature/Contas/PaginaEntrarTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaginaEntrarTest extends TestCase
{
    public function test_pagina_entrar_renderiza_componente_vue(): void
    {
        $this->get('/entrar')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Entrar'));
    }
}
```

Apagar `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php` (testam a página de boas-vindas, que deixa de existir).

- [ ] **Passo 4: rodar e ver falhar**

Run: `php artisan test --filter=PaginaEntrarTest`
Esperado: FAIL com 404.

- [ ] **Passo 5: configurar Vite, CSS e entrada JS**

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Verificado no Bunny Fonts em 2026-10-09: Archivo e Archivo Narrow (estáticas).
                bunny('Archivo', { weights: [400, 500, 600, 700] }),
                bunny('Archivo Narrow', { weights: [500, 600] }),
            ],
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
```

`resources/css/app.css`:

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../js/**/*.vue';

@theme {
    --font-sans: 'Archivo', ui-sans-serif, system-ui, sans-serif;
    --font-estreita: 'Archivo Narrow', 'Archivo', ui-sans-serif, sans-serif;

    --color-papel: #F5F7F9;
    --color-grafite: #1F2933;
    --color-carbono: #2F3C9E;
    --color-linha: #C9D1DA;
    --color-tinta-verde: #2E7D4F;
    --color-tinta-vermelha: #B3261E;
}

@layer components {
    .entrada {
        @apply w-full rounded-sm border border-linha bg-white px-3 py-2 text-sm text-grafite tabular-nums focus:border-carbono focus:outline-2 focus:outline-carbono/30;
    }

    .botao {
        @apply inline-flex items-center justify-center rounded-sm bg-carbono px-4 py-2 text-sm font-semibold text-white hover:bg-carbono/90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono disabled:opacity-50;
    }

    .botao-secundario {
        @apply inline-flex items-center justify-center rounded-sm border border-linha bg-white px-4 py-2 text-sm font-semibold text-grafite hover:bg-papel focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-carbono disabled:opacity-50;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        transition-duration: 0s !important;
        animation-duration: 0s !important;
    }
}
```

`resources/js/app.js`:

```js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

`resources/views/app.blade.php`:

```blade
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-inertia::head>
            <title>{{ config('app.name') }}</title>
        </x-inertia::head>
    </head>
    <body class="bg-papel font-sans text-grafite antialiased">
        <x-inertia::app />
    </body>
</html>
```

Apagar `resources/views/welcome.blade.php`.

- [ ] **Passo 6: registrar o middleware e as props compartilhadas**

`bootstrap/app.php` — substituir o bloco `->withMiddleware(...)`:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->redirectUsersTo('/painel');
    })
```

Em `app/Http/Middleware/HandleInertiaRequests.php`, substituir o corpo do método `share` gerado por:

```php
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()?->only('id', 'name', 'email', 'is_admin'),
            ],
            'flash' => [
                'sucesso' => fn () => $request->session()->get('sucesso'),
                'erro' => fn () => $request->session()->get('erro'),
            ],
        ];
    }
```

(Se a assinatura gerada for diferente, manter a assinatura gerada e trocar só o corpo.)

- [ ] **Passo 7: rota e controller**

`app/Http/Controllers/Auth/LoginController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Entrar');
    }
}
```

`routes/web.php`:

```php
<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
});
```

- [ ] **Passo 8: componentes e página**

`resources/js/Components/Campo.vue`:

```vue
<script setup>
defineProps({
    id: { type: String, required: true },
    rotulo: { type: String, required: true },
    erro: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <label :for="id" class="font-estreita text-xs font-medium text-grafite/75">{{ rotulo }}</label>
        <slot />
        <p v-if="erro" :id="`${id}-erro`" class="text-xs text-tinta-vermelha">{{ erro }}</p>
    </div>
</template>
```

`resources/js/Layouts/CamadaPublica.vue`:

```vue
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({ titulo: { type: String, required: true } });

const flash = computed(() => usePage().props.flash);
</script>

<template>
    <main class="mx-auto flex min-h-screen w-full max-w-sm flex-col justify-center px-4 py-10">
        <p class="font-estreita text-sm text-grafite/70">Gerenciador de Cobranças</p>
        <h1 class="mt-1 mb-6 text-3xl font-semibold">{{ titulo }}</h1>
        <p v-if="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-white px-4 py-2 text-sm">
            {{ flash.sucesso }}
        </p>
        <p v-if="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-white px-4 py-2 text-sm">
            {{ flash.erro }}
        </p>
        <slot />
    </main>
</template>
```

`resources/js/Pages/Auth/Entrar.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaPublica from '../../Layouts/CamadaPublica.vue';
import Campo from '../../Components/Campo.vue';

const form = useForm({ email: '', password: '', lembrar: false });

const entrar = () => form.post('/entrar', { onFinish: () => form.reset('password') });
</script>

<template>
    <CamadaPublica titulo="Entrar">
        <form class="flex flex-col gap-4" @submit.prevent="entrar">
            <Campo id="email" rotulo="E-mail" :erro="form.errors.email">
                <input id="email" v-model="form.email" type="email" autocomplete="username" class="entrada" required>
            </Campo>
            <Campo id="password" rotulo="Senha" :erro="form.errors.password">
                <input id="password" v-model="form.password" type="password" autocomplete="current-password" class="entrada" required>
            </Campo>
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.lembrar" type="checkbox"> Manter conectado neste aparelho
            </label>
            <button class="botao" :disabled="form.processing">Entrar</button>
            <p class="text-sm">
                Ainda não tem conta?
                <Link href="/cadastro" class="font-semibold text-carbono underline">Criar conta</Link>
            </p>
        </form>
    </CamadaPublica>
</template>
```

- [ ] **Passo 9: rodar o teste e o build**

Run: `php artisan test --filter=PaginaEntrarTest`
Esperado: PASS.

Run: `npm run build`
Esperado: build sem erro, gerando `public/build/manifest.json`.

- [ ] **Passo 10: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: Inertia v3 com Vue 3, tokens visuais e página de entrada`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T0.4: dompdf + prova de conceito

**Estado:** testada — PDF gerado; extração de texto confirma acentos em DejaVu Sans e Helvetica (WinAnsiEncoding), imagem data URI embutida, 1 página. Rotação, absoluto dentro da caixa e rodapé fixo: [A VERIFICAR] pelo usuário

**Arquivos:**
- Modificar: `composer.json` (via composer)
- Criar: `resources/views/verificacao/prova-dompdf.blade.php`
- Teste: `tests/Feature/ProvaDompdfTest.php`

**Interfaces:** produz a confirmação (ou não) dos itens [A VERIFICAR] da spec: acentos em DejaVu Sans e Helvetica, `transform: rotate`, `position: absolute` dentro de bloco `relative`, imagem em data URI, rodapé `position: fixed`. As tarefas T4.2/T4.3 dependem desse resultado.

- [ ] **Passo 1: instalar e confirmar a fachada**

Run: `composer require barryvdh/laravel-dompdf:^3.1.2`
Depois, via `ctx_execute`: `grep -n "class Pdf\|function stream\|function setPaper" -r vendor/barryvdh/laravel-dompdf/src`
Esperado: `vendor/barryvdh/laravel-dompdf/src/Facade/Pdf.php` (fachada `Barryvdh\DomPDF\Facade\Pdf`) e os métodos `stream`/`setPaper` em `src/PDF.php`. Se o caminho for outro, usar o encontrado e corrigir este plano.

- [ ] **Passo 2: escrever o teste que falha**

`tests/Feature/ProvaDompdfTest.php`:

```php
<?php

namespace Tests\Feature;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProvaDompdfTest extends TestCase
{
    public function test_gera_pdf_de_prova_para_conferencia_visual(): void
    {
        $conteudo = Pdf::loadView('verificacao.prova-dompdf')->setPaper('a4', 'portrait')->output();

        $this->assertStringStartsWith('%PDF', $conteudo);

        // Arquivo real (não fake) para o usuário abrir e conferir; fica fora do git (storage/app/private).
        Storage::disk('local')->put('verificacao/prova-dompdf.pdf', $conteudo);
    }
}
```

- [ ] **Passo 3: rodar e ver falhar**

Run: `php artisan test --filter=ProvaDompdfTest`
Esperado: FAIL com `View [verificacao.prova-dompdf] not found`.

- [ ] **Passo 4: criar a view de prova**

`resources/views/verificacao/prova-dompdf.blade.php`:

```blade
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 18pt; }
    body { margin: 0; font-size: 11pt; }
    .dejavu { font-family: 'DejaVu Sans', sans-serif; }
    .helvetica { font-family: Helvetica, sans-serif; }
    .caixa { position: relative; height: 220pt; border: 0.75pt solid #000; margin-top: 10pt; }
    .carimbo { position: absolute; top: 80pt; left: 120pt; border: 3pt solid #2E7D4F; color: #2E7D4F;
               font-weight: bold; font-size: 28pt; padding: 4pt 12pt; transform: rotate(-35deg); }
    .rodape { position: fixed; bottom: 0; left: 0; right: 0; font-size: 7pt; border-top: 0.75pt solid #000; }
</style>
</head>
<body>
    <p class="dejavu">DejaVu Sans: Ação, prestação, município, informações, São João, ÇÃÕÉÍÚÂÊ — R$ 1.234,56</p>
    <p class="helvetica">Helvetica: Ação, prestação, município, informações, São João, ÇÃÕÉÍÚÂÊ — R$ 1.234,56</p>
    <p class="helvetica" style="font-weight: bold;">Helvetica negrito: RECIBO DE PRESTAÇÃO DE SERVIÇOS</p>
    <p class="dejavu">Imagem em data URI (quadrado vermelho de 1 px ampliado):
        <img alt="" style="width: 20pt; height: 20pt;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==">
    </p>
    <div class="caixa dejavu">
        Caixa com position: relative — o carimbo deve aparecer girado dentro dela.
        <div class="carimbo">PAGO</div>
    </div>
    <div class="rodape dejavu">Rodapé fixo: deve ficar no pé da página.</div>
</body>
</html>
```

- [ ] **Passo 5: rodar e ver passar**

Run: `php artisan test --filter=ProvaDompdfTest`
Esperado: PASS e arquivo `storage/app/private/verificacao/prova-dompdf.pdf` criado.

- [ ] **Passo 6: conferência visual pelo usuário**

Pedir ao usuário para abrir `storage/app/private/verificacao/prova-dompdf.pdf` e responder, item a item:
1. Acentos corretos na linha DejaVu Sans?
2. Acentos corretos nas duas linhas Helvetica?
3. Quadrado vermelho aparece?
4. "PAGO" aparece girado e **dentro** da caixa?
5. Rodapé no pé da página?

Registrar cada resposta na skill (T0.5) como acerto ou pegadinha. Consequências já previstas:
- Helvetica sem acentos → os dois modelos usam DejaVu Sans (T4.3 troca a fonte).
- Rotação não funciona → carimbo sem rotação, só com borda (T4.2/T4.3).
- Absoluto não respeita a caixa → carimbo posicionado em relação à página (`top` contado do topo da folha).

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add composer.json composer.lock resources/views/verificacao tests/Feature/ProvaDompdfTest.php
git commit -m "chore: dompdf e prova de conceito de fontes, rotação e imagem`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T0.5: `.claude/` do projeto + inventário de ferramentas

**Estado:** concluída — skill, memória (2 arquivos), inventário no CLAUDE.md, AGENTS.md e README; plugins conferidos: todos instalados

**Arquivos:**
- Criar: `.claude/skills/recibos/SKILL.md`, `.claude/MEMORY.md`, `.claude/memory/` (com o primeiro arquivo de memória)
- Modificar: `CLAUDE.md` (seção nova **fora** das tags `<laravel-boost-guidelines>`), `README.md` (substituir o README padrão do Laravel)

**Interfaces:** produz a skill que todas as tarefas seguintes atualizam.

- [ ] **Passo 1: conferir quais plugins usados estão instalados**

Via `ctx_execute` (shell): `ls ~/.claude/plugins/cache/*/` e procurar `superpowers`, `frontend-design`, `context-mode`, `browser-use`, `laravel-boost`.
Para cada um ausente: avisar o usuário e perguntar se instala agora ou se ele prefere instalar manualmente. Nunca instalar sozinho.

- [ ] **Passo 2: criar a skill do projeto**

`.claude/skills/recibos/SKILL.md`:

```markdown
---
name: recibos
description: Use ao trabalhar no sistema de cobranças e recibos (Laravel 13 + Inertia v3 + Vue 3 + dompdf) — regras de domínio, decisões, pegadinhas e acertos.
---

# Sistema de cobranças e recibos

Spec: `docs/superpowers/specs/2026-10-09-sistema-recibos-design.md`
Plano: `.claude/plans/2026-10-09-sistema-recibos.md`

## Regras de domínio
- Cobrança nasce com o recibo (mesma transação), antes do vencimento (`users.dias_antecedencia`).
- Recibo = snapshot congelado (prestador, tomador, serviço) + modelo com versão (`webiss-v1`).
  Situação, data de pagamento e NFS-e vinculada são lidas na hora de gerar o PDF.
- Modelo é do cliente; trocar vale só para recibos futuros.
- Numeração por usuário e ano; número cancelado não volta.
- Dinheiro em centavos (`integer`).

## Decisões (não reverter sem falar com o usuário)
- Inertia + Vue + dompdf (sem API, sem Chrome no servidor).
- Geração de cobranças "preguiçosa" ao abrir painel/cobranças; sem cron.
- Isolamento por relação do usuário + `findOrFail` (404), não por policy.
- Recibo não imita identidade oficial: sem brasão, sem "NFS-e", sem chave/QR, com aviso
  "Este recibo não substitui a Nota Fiscal de Serviços Eletrônica."

## Pegadinhas
- `pdftotext` instalado é xpdf 4.00: sem `-bbox-layout`. Coordenadas dos PDFs de referência
  foram extraídas com `pypdf` (`extract_text(visitor_text=...)`).
- A ferramenta Read não renderiza PDF nesta máquina (falta `pdftoppm`); Chrome DevTools não abriu
  PDF local (timeout). Para ver o visual, pedir print ao usuário.
- Inertia está na **v3**: root view com `<x-inertia::head>`/`<x-inertia::app>`, `Inertia::lazy()`
  removido (usar `optional()`), `config/inertia.php` reorganizado (`pages`, `testing`).
- Laravel 13: models usam atributos `#[Fillable]`/`#[Hidden]`; `config/app.php` tem `timezone`
  fixo (sem variável de ambiente).
- CNPJ alfanumérico em vigor desde julho/2026 (Receita Federal). DV: módulo 11 com valor
  ASCII − 48 por caractere. Exemplo oficial: `12ABC34501DE` → DV `35`.
- `withoutVite()` nos testes também neutraliza a diretiva `@fonts`.
- Bunny Fonts serve Archivo e Archivo Narrow estáticas (sem eixo de largura): rótulos usam
  Archivo Narrow em vez de "Archivo semicondensada".

## Acertos
- (preencher a cada tarefa aprovada: o que funcionou e por quê)

## Resultados da prova do dompdf (T0.4)
- (preencher com as respostas do usuário)
```

- [ ] **Passo 3: memória do projeto**

`.claude/memory/regras-banco-recibos.md`:

```markdown
---
name: regras-banco-recibos
description: Neste projeto, toda escrita no banco real passa pelo PORTÃO-BANCO do plano; testes só no SQLite em memória.
metadata:
  type: project
---

Toda escrita em `database/database.sqlite` (migrate, app:criar-admin, smoke test) segue o
PORTÃO-BANCO do plano `.claude/plans/2026-10-09-sistema-recibos.md`.
**Why:** regra global do usuário; autorização não é transitiva.
**How to apply:** db:show → db:show --counts → --pretend/comando literal → esperar "sim".
```

`.claude/MEMORY.md`:

```markdown
- [Regras de banco do projeto](memory/regras-banco-recibos.md) — portão antes de qualquer escrita no SQLite real
```

- [ ] **Passo 4: inventário no `CLAUDE.md` e no `README.md`**

Acrescentar ao fim do `CLAUDE.md`, **depois** de `</laravel-boost-guidelines>` (o Boost reescreve o que está dentro das tags):

```markdown
## Projeto: Gerenciador de Cobranças

- Skill do domínio: `.claude/skills/recibos/SKILL.md` (ler inteira antes de mexer).
- Plano: `.claude/plans/2026-10-09-sistema-recibos.md` · Spec: `docs/superpowers/specs/2026-10-09-sistema-recibos-design.md`

### Ferramentas do Claude Code usadas neste projeto
- **superpowers** (plugin): brainstorming, writing-plans, subagent-driven-development/executing-plans.
- **frontend-design** (plugin): direção visual das telas.
- **context-mode** (plugin/MCP): leitura e execução de comandos com economia de contexto.
- **laravel-boost** (pacote dev + MCP): guidelines e ferramentas do Laravel.
- **browser-use** (plugin/MCP, "browser-agent"): smoke tests no navegador.
```

Substituir o conteúdo de `README.md` por:

```markdown
# Gerenciador de Cobranças

Cobranças recorrentes com recibo em PDF A4 inspirado nos modelos de NFS-e (WebISS e DANFSe),
para envio antecipado ao cliente e baixa manual quando o pagamento é confirmado.

## Requisitos
PHP 8.4, Composer, Node 22, SQLite (PostgreSQL no futuro).

## Instalação
    composer install
    npm install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    php artisan app:criar-admin seu@email.com --nome="Seu Nome"
    npm run build

## Testes
    php artisan test

## Ferramentas do Claude Code usadas no desenvolvimento
superpowers, frontend-design, context-mode, laravel-boost, browser-use — ver `CLAUDE.md`.
```

- [ ] **Passo 5: conferir o `.gitignore` e commitar (se autorizado)**

Run: `git check-ignore -v .claude .claude/memory .claude/plans .claude/skills .claude/MEMORY.md` → nenhuma saída.

```powershell
git add .claude CLAUDE.md README.md
git commit -m "docs: skill do projeto, memória e inventário de ferramentas`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T0.6: PORTÃO-BANCO — migrações padrão no SQLite de desenvolvimento

**Estado:** concluída — `db:show`: SQLite local `database/database.sqlite` (não é produção); `migrate:status`: as 3 migrações padrão já constavam como Ran (lote 1); `users` com 0 linhas. Nenhuma escrita feita.

**Arquivos:** nenhum de código. Altera `database/database.sqlite`.

- [ ] **Passo 1:** executar o procedimento PORTÃO-BANCO (seção de restrições) para o comando `php artisan migrate`, mostrando a saída de `db:show`, `db:show --counts`, `migrate:status` e `migrate --pretend`.
- [ ] **Passo 2:** esperar o "sim" do usuário para `php artisan migrate`.
- [ ] **Passo 3:** rodar `php artisan migrate` e mostrar `php artisan migrate:status`.
- [ ] **Passo 4:** rodar a suíte inteira: `php artisan test` → esperado: todos passando.

---

## Fase 1 — Contas e acesso

### Tarefa T1.1: Usuário com status, admin e dados do prestador

**Estado:** testada — UsuarioModeloTest 4/4; suíte verde

**Arquivos:**
- Criar: `app/Enums/StatusUsuario.php`, `app/Enums/FamiliaModelo.php`, `app/Enums/ModeloRecibo.php`, `database/migrations/2026_10_09_100000_add_conta_e_prestador_to_users_table.php`
- Modificar: `app/Models/User.php`, `database/factories/UserFactory.php`
- Teste: `tests/Feature/Contas/UsuarioModeloTest.php`

**Interfaces:**
- Produz:
  - `enum StatusUsuario: string { Pendente='pendente'; Aprovado='aprovado'; Bloqueado='bloqueado' }`
  - `enum ModeloRecibo: string { WebissV1='webiss-v1'; DanfseV1='danfse-v1' }` com `view(): string` → `'recibos.pdf.webiss-v1'`
  - `enum FamiliaModelo: string { Webiss='webiss'; Danfse='danfse' }` com `versaoAtual(): ModeloRecibo`, `rotulo(): string`, `static opcoes(): list<array{valor:string, rotulo:string}>`
  - `User::CAMPOS_DO_PRESTADOR` (lista), `User::aprovado(): bool`, `User::perfilCompleto(): bool`
  - Relações `User::clientes()`, `contratos()`, `cobrancas()`, `recibos()` (as classes chegam nas fases 2 e 3; declarar agora as relações não quebra nada enquanto não forem chamadas)
  - Estados da factory: `pendente()`, `bloqueado()`, `admin()`, `semPerfil()`; o estado padrão é **aprovado com perfil completo**

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Contas/UsuarioModeloTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_novo_nasce_pendente_sem_admin_e_com_padroes(): void
    {
        $usuario = User::create([
            'name' => 'Ana',
            'email' => 'ana@exemplo.com',
            'password' => 'segredo123',
        ])->refresh();

        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
        $this->assertSame(FamiliaModelo::Webiss, $usuario->modelo_padrao);
        $this->assertSame(10, $usuario->dias_antecedencia);
    }

    public function test_status_e_admin_nao_sao_preenchiveis_em_massa(): void
    {
        $usuario = User::create([
            'name' => 'Ana',
            'email' => 'ana@exemplo.com',
            'password' => 'segredo123',
            'status' => 'aprovado',
            'is_admin' => true,
        ])->refresh();

        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
    }

    public function test_perfil_completo_exige_os_campos_do_prestador(): void
    {
        $this->assertTrue(User::factory()->create()->perfilCompleto());
        $this->assertFalse(User::factory()->semPerfil()->create()->perfilCompleto());
        $this->assertFalse(User::factory()->create(['cep' => null])->perfilCompleto());
    }

    public function test_familia_do_modelo_aponta_para_a_versao_atual(): void
    {
        $this->assertSame('recibos.pdf.webiss-v1', FamiliaModelo::Webiss->versaoAtual()->view());
        $this->assertSame('recibos.pdf.danfse-v1', FamiliaModelo::Danfse->versaoAtual()->view());
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=UsuarioModeloTest`
Esperado: FAIL (`Class "App\Enums\StatusUsuario" not found`).

- [ ] **Passo 3: enums**

`app/Enums/StatusUsuario.php`:

```php
<?php

namespace App\Enums;

enum StatusUsuario: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Bloqueado = 'bloqueado';
}
```

`app/Enums/ModeloRecibo.php`:

```php
<?php

namespace App\Enums;

/**
 * Layout congelado de um recibo. Ajuste visual depois de recibos emitidos = nova versão
 * (ex.: WebissV2 = 'webiss-v2') com novo template; a versão antiga continua renderizando.
 */
enum ModeloRecibo: string
{
    case WebissV1 = 'webiss-v1';
    case DanfseV1 = 'danfse-v1';

    public function view(): string
    {
        return 'recibos.pdf.'.$this->value;
    }
}
```

`app/Enums/FamiliaModelo.php`:

```php
<?php

namespace App\Enums;

enum FamiliaModelo: string
{
    case Webiss = 'webiss';
    case Danfse = 'danfse';

    /** Versão usada nos recibos emitidos a partir de agora. Ao criar uma v2, trocar só aqui. */
    public function versaoAtual(): ModeloRecibo
    {
        return match ($this) {
            self::Webiss => ModeloRecibo::WebissV1,
            self::Danfse => ModeloRecibo::DanfseV1,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Webiss => 'Modelo prefeitura (WebISS)',
            self::Danfse => 'Modelo nacional (DANFSe)',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(
            fn (self $familia) => ['valor' => $familia->value, 'rotulo' => $familia->rotulo()],
            self::cases(),
        );
    }
}
```

- [ ] **Passo 4: migração**

`database/migrations/2026_10_09_100000_add_conta_e_prestador_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUNAS = [
        'is_admin', 'status', 'documento', 'nome_fantasia', 'inscricao_municipal',
        'inscricao_estadual', 'telefone', 'logradouro', 'numero', 'complemento', 'bairro',
        'cidade', 'uf', 'cep', 'logo', 'modelo_padrao', 'dias_antecedencia',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->string('status', 20)->default('pendente')->index();
            $table->string('documento', 14)->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('logo')->nullable();
            $table->string('modelo_padrao', 20)->default('webiss');
            $table->unsignedSmallInteger('dias_antecedencia')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(self::COLUNAS);
        });
    }
};
```

- [ ] **Passo 5: model**

`app/Models/User.php` (conteúdo completo):

```php
<?php

namespace App\Models;

use App\Enums\FamiliaModelo;
use App\Enums\StatusUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'email', 'password', 'documento', 'nome_fantasia', 'inscricao_municipal',
    'inscricao_estadual', 'telefone', 'logradouro', 'numero', 'complemento', 'bairro',
    'cidade', 'uf', 'cep', 'modelo_padrao', 'dias_antecedencia',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Campos sem os quais o recibo sairia com o prestador em branco. */
    public const CAMPOS_DO_PRESTADOR = [
        'name', 'documento', 'telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'status' => StatusUsuario::class,
            'modelo_padrao' => FamiliaModelo::class,
            'dias_antecedencia' => 'integer',
        ];
    }

    public function aprovado(): bool
    {
        return $this->status === StatusUsuario::Aprovado;
    }

    public function perfilCompleto(): bool
    {
        foreach (self::CAMPOS_DO_PRESTADOR as $campo) {
            if (blank($this->{$campo})) {
                return false;
            }
        }

        return true;
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }

    public function recibos(): HasMany
    {
        return $this->hasMany(Recibo::class);
    }
}
```

- [ ] **Passo 6: factory**

`database/factories/UserFactory.php` (conteúdo completo):

```php
<?php

namespace Database\Factories;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * Padrão: aprovado e com perfil completo — é o que a maioria dos testes precisa.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => StatusUsuario::Aprovado,
            'documento' => '52998224725', // CPF válido de exemplo (não é de pessoa real conhecida)
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'Aracaju',
            'uf' => 'SE',
            'cep' => '49000000',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => ['email_verified_at' => null]);
    }

    public function pendente(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StatusUsuario::Pendente]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => ['status' => StatusUsuario::Bloqueado]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['is_admin' => true]);
    }

    public function semPerfil(): static
    {
        return $this->state(fn (array $attributes) => array_fill_keys(
            ['documento', 'telefone', 'logradouro', 'numero', 'bairro', 'cidade', 'uf', 'cep'],
            null,
        ));
    }
}
```

(A factory grava `status`/`is_admin` mesmo fora do `Fillable`: o Laravel desliga a proteção de atribuição em massa ao criar pela factory.)

- [ ] **Passo 7: rodar e ver passar**

Run: `php artisan test --filter=UsuarioModeloTest`
Esperado: PASS (4 testes).

- [ ] **Passo 8: commit (se autorizado)**

```powershell
git add app/Enums app/Models/User.php database tests/Feature/Contas/UsuarioModeloTest.php
git commit -m "feat: status de conta, admin e dados do prestador no usuário`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T1.2: Cadastro com aprovação pendente

**Estado:** testada — CadastroTest 4/4 (inclui texto das mensagens); suíte verde; build ok

**Arquivos:**
- Criar: `app/Http/Controllers/Auth/CadastroController.php`, `resources/js/Pages/Auth/Cadastro.vue`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/Contas/CadastroTest.php`

**Interfaces:**
- Consome: `User` (T1.1), `CamadaPublica`, `Campo` (T0.3)
- Produz: `GET /cadastro`, `POST /cadastro` (com `throttle:6,1`)

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Contas/CadastroTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Bruno Lima',
            'email' => 'bruno@exemplo.com',
            'password' => 'segredo123',
            'password_confirmation' => 'segredo123',
        ], $extra);
    }

    public function test_pagina_de_cadastro_renderiza(): void
    {
        $this->get('/cadastro')->assertInertia(fn (Assert $page) => $page->component('Auth/Cadastro'));
    }

    public function test_cadastro_cria_conta_pendente_sem_entrar(): void
    {
        $this->post('/cadastro', $this->dados())
            ->assertRedirect('/entrar')
            ->assertSessionHas('sucesso', 'Cadastro recebido. Você poderá entrar assim que o administrador liberar seu acesso.');

        $this->assertGuest();
        $usuario = User::where('email', 'bruno@exemplo.com')->firstOrFail();
        $this->assertSame(StatusUsuario::Pendente, $usuario->status);
        $this->assertFalse($usuario->is_admin);
    }

    public function test_email_ja_cadastrado_e_recusado(): void
    {
        User::factory()->create(['email' => 'bruno@exemplo.com']);

        $this->post('/cadastro', $this->dados())->assertSessionHasErrors('email');
    }

    public function test_senha_curta_ou_sem_confirmacao_e_recusada(): void
    {
        $this->post('/cadastro', $this->dados(['password' => '123', 'password_confirmation' => '123']))
            ->assertSessionHasErrors('password');
        $this->post('/cadastro', $this->dados(['password_confirmation' => 'outra-senha']))
            ->assertSessionHasErrors('password');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=CadastroTest`
Esperado: FAIL com 404.

- [ ] **Passo 3: controller**

`app/Http/Controllers/Auth/CadastroController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CadastroController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Cadastro');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Nasce pendente (default da coluna) e não entra: o admin libera depois.
        User::create($dados);

        return redirect('/entrar')
            ->with('sucesso', 'Cadastro recebido. Você poderá entrar assim que o administrador liberar seu acesso.');
    }
}
```

- [ ] **Passo 4: rotas**

`routes/web.php` — o grupo `guest` passa a ser:

```php
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::get('/cadastro', [CadastroController::class, 'create']);
    Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:6,1');
});
```

com `use App\Http\Controllers\Auth\CadastroController;` no topo.

- [ ] **Passo 5: página**

`resources/js/Pages/Auth/Cadastro.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaPublica from '../../Layouts/CamadaPublica.vue';
import Campo from '../../Components/Campo.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

const criar = () => form.post('/cadastro', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <CamadaPublica titulo="Criar conta">
        <p class="mb-6 text-sm text-grafite/80">
            Depois do cadastro, o administrador libera seu acesso. Você recebe a confirmação ao tentar entrar.
        </p>
        <form class="flex flex-col gap-4" @submit.prevent="criar">
            <Campo id="name" rotulo="Nome completo" :erro="form.errors.name">
                <input id="name" v-model="form.name" autocomplete="name" class="entrada" required>
            </Campo>
            <Campo id="email" rotulo="E-mail" :erro="form.errors.email">
                <input id="email" v-model="form.email" type="email" autocomplete="email" class="entrada" required>
            </Campo>
            <Campo id="password" rotulo="Senha (mínimo 8 caracteres)" :erro="form.errors.password">
                <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="entrada" required>
            </Campo>
            <Campo id="password_confirmation" rotulo="Repita a senha">
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="entrada" required>
            </Campo>
            <button class="botao" :disabled="form.processing">Criar conta</button>
            <p class="text-sm">
                Já tem conta? <Link href="/entrar" class="font-semibold text-carbono underline">Entrar</Link>
            </p>
        </form>
    </CamadaPublica>
</template>
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --filter=CadastroTest`
Esperado: PASS (4 testes).

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add app/Http/Controllers/Auth/CadastroController.php resources/js/Pages/Auth/Cadastro.vue routes/web.php tests/Feature/Contas/CadastroTest.php
git commit -m "feat: cadastro aberto com conta pendente de liberação`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T1.3: Entrar, sair, bloqueio por status e painel inicial

**Estado:** testada — LoginTest 9/9 (inclui limite de tentativas: RateLimiter `login`, 5/min por e-mail+IP); suíte verde; build ok

**Arquivos:**
- Criar: `app/Http/Middleware/GarantirContaAprovada.php`, `app/Http/Controllers/PainelController.php`, `resources/js/Layouts/CamadaApp.vue`, `resources/js/Pages/Painel.vue`
- Modificar: `app/Http/Controllers/Auth/LoginController.php`, `bootstrap/app.php`, `routes/web.php`
- Teste: `tests/Feature/Contas/LoginTest.php`

**Interfaces:**
- Consome: `User::aprovado()`, `StatusUsuario` (T1.1)
- Produz: `POST /entrar` (`throttle:6,1`), `POST /sair`, `GET /painel` (nome `painel`), alias de middleware `conta.aprovada`, layout `CamadaApp` (slot padrão; lê `auth.user`, `flash`, `pendentes`), props do painel `mes: string`, `perfilCompleto: bool` (a T5.1 acrescenta mais)

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Contas/LoginTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_aprovado_entra_e_vai_para_o_painel(): void
    {
        $usuario = User::factory()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertRedirect('/painel');

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_senha_errada_mostra_mensagem_clara(): void
    {
        $usuario = User::factory()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha incorretos.']);
        $this->assertGuest();
    }

    public function test_conta_pendente_nao_entra(): void
    {
        $usuario = User::factory()->pendente()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertSessionHasErrors(['email' => 'Seu cadastro aguarda liberação do administrador.']);
        $this->assertGuest();
    }

    public function test_conta_bloqueada_nao_entra(): void
    {
        $usuario = User::factory()->bloqueado()->create(['password' => 'segredo123']);

        $this->post('/entrar', ['email' => $usuario->email, 'password' => 'segredo123'])
            ->assertSessionHasErrors(['email' => 'Seu acesso foi bloqueado. Fale com o administrador.']);
        $this->assertGuest();
    }

    public function test_bloquear_derruba_quem_ja_esta_logado(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario)->get('/painel')->assertOk();

        $usuario->status = StatusUsuario::Bloqueado;
        $usuario->save();

        $this->get('/painel')
            ->assertRedirect('/entrar')
            ->assertSessionHas('erro', 'Seu acesso foi bloqueado. Fale com o administrador.');
        $this->assertGuest();
    }

    public function test_visitante_e_mandado_para_entrar(): void
    {
        $this->get('/painel')->assertRedirect('/entrar');
        $this->get('/')->assertRedirect('/painel');
    }

    public function test_logado_que_abre_entrar_vai_para_o_painel(): void
    {
        $this->actingAs(User::factory()->create())->get('/entrar')->assertRedirect('/painel');
    }

    public function test_sair_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->create())->post('/sair')->assertRedirect('/entrar');
        $this->assertGuest();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=LoginTest`
Esperado: FAIL (rota `POST /entrar` inexistente).

- [ ] **Passo 3: login e logout**

`app/Http/Controllers/Auth/LoginController.php` (conteúdo completo):

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Enums\StatusUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Entrar');
    }

    public function store(Request $request): RedirectResponse
    {
        $credenciais = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credenciais, $request->boolean('lembrar'))) {
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        $usuario = $request->user();

        if (! $usuario->aprovado()) {
            $this->encerrarSessao($request);

            throw ValidationException::withMessages([
                'email' => $usuario->status === StatusUsuario::Bloqueado
                    ? 'Seu acesso foi bloqueado. Fale com o administrador.'
                    : 'Seu cadastro aguarda liberação do administrador.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended('/painel');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->encerrarSessao($request);

        return redirect('/entrar');
    }

    private function encerrarSessao(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
```

- [ ] **Passo 4: middleware de conta aprovada**

`app/Http/Middleware/GarantirContaAprovada.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\StatusUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Derruba, no próximo clique, quem foi bloqueado (ou voltou a pendente) com a sessão aberta. */
class GarantirContaAprovada
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->aprovado()) {
            $mensagem = $usuario->status === StatusUsuario::Bloqueado
                ? 'Seu acesso foi bloqueado. Fale com o administrador.'
                : 'Seu cadastro aguarda liberação do administrador.';

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/entrar')->with('erro', $mensagem);
        }

        return $next($request);
    }
}
```

`bootstrap/app.php` — o bloco `withMiddleware` fica:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'conta.aprovada' => \App\Http\Middleware\GarantirContaAprovada::class,
        ]);
        $middleware->redirectUsersTo('/painel');
    })
```

- [ ] **Passo 5: painel inicial**

`app/Http/Controllers/PainelController.php`:

```php
<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoje = CarbonImmutable::today();

        return Inertia::render('Painel', [
            'mes' => Str::ucfirst($hoje->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'perfilCompleto' => $request->user()->perfilCompleto(),
        ]);
    }
}
```

(Verificado nesta máquina: `translatedFormat('F \d\e Y')` em `pt_BR` → `outubro de 2026`.)

- [ ] **Passo 6: rotas**

`routes/web.php` (conteúdo completo nesta tarefa):

```php
<?php

use App\Http\Controllers\Auth\CadastroController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PainelController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/painel');

Route::middleware('guest')->group(function () {
    Route::get('/entrar', [LoginController::class, 'create'])->name('login');
    Route::post('/entrar', [LoginController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/cadastro', [CadastroController::class, 'create']);
    Route::post('/cadastro', [CadastroController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'conta.aprovada'])->group(function () {
    Route::post('/sair', [LoginController::class, 'destroy']);
    Route::get('/painel', PainelController::class)->name('painel');

    // Fases seguintes acrescentam rotas aqui, acima do grupo admin.
});
```

- [ ] **Passo 7: layout autenticado e página do painel**

`resources/js/Layouts/CamadaApp.vue`:

```vue
<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();
const usuario = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);
const menuAberto = ref(false);

const itens = computed(() => [
    { href: '/painel', rotulo: 'Painel' },
    { href: '/cobrancas', rotulo: 'Cobranças' },
    { href: '/contratos', rotulo: 'Contratos' },
    { href: '/clientes', rotulo: 'Clientes' },
    { href: '/perfil', rotulo: 'Meu perfil' },
    ...(usuario.value?.is_admin
        ? [{ href: '/admin/usuarios', rotulo: 'Usuários', contador: page.props.pendentes }]
        : []),
]);

const ativo = (href) => page.url.startsWith(href);
const sair = () => router.post('/sair');
</script>

<template>
    <div class="min-h-screen md:grid md:grid-cols-[13rem_1fr]">
        <header class="flex items-center justify-between border-b border-linha bg-white px-4 py-3 md:hidden">
            <span class="font-semibold">Gerenciador de Cobranças</span>
            <button
                type="button"
                class="botao-secundario px-3 py-1"
                :aria-expanded="menuAberto"
                aria-controls="menu-principal"
                @click="menuAberto = !menuAberto"
            >
                Menu
            </button>
        </header>

        <nav
            id="menu-principal"
            aria-label="Principal"
            :class="[menuAberto ? 'block' : 'hidden', 'border-b border-linha bg-white md:block md:border-b-0 md:border-r']"
        >
            <p class="hidden px-5 pt-6 pb-4 font-semibold md:block">Gerenciador de Cobranças</p>
            <ul class="flex flex-col py-2">
                <li v-for="item in itens" :key="item.href">
                    <Link
                        :href="item.href"
                        class="flex items-center justify-between border-l-4 px-5 py-2 text-sm hover:bg-papel"
                        :class="ativo(item.href) ? 'border-carbono font-semibold text-carbono' : 'border-transparent'"
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
                <li>
                    <button type="button" class="w-full border-l-4 border-transparent px-5 py-2 text-left text-sm hover:bg-papel" @click="sair">
                        Sair
                    </button>
                </li>
            </ul>
        </nav>

        <main class="mx-auto w-full max-w-5xl px-4 py-6 md:px-8">
            <p v-if="flash.sucesso" role="status" class="mb-4 border-l-4 border-tinta-verde bg-white px-4 py-2 text-sm">
                {{ flash.sucesso }}
            </p>
            <p v-if="flash.erro" role="alert" class="mb-4 border-l-4 border-tinta-vermelha bg-white px-4 py-2 text-sm">
                {{ flash.erro }}
            </p>
            <slot />
        </main>
    </div>
</template>
```

(Os itens Cobranças, Contratos, Clientes e Meu perfil só abrem depois das fases 2, 3 e 5.)

`resources/js/Pages/Painel.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
});
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">{{ mes }}</h1>
        <p v-if="!perfilCompleto" class="mt-4 border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Complete seu perfil para emitir recibos.
            <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
        </p>
    </CamadaApp>
</template>
```

- [ ] **Passo 8: rodar e ver passar**

Run: `php artisan test --filter=LoginTest`
Esperado: PASS (8 testes).

- [ ] **Passo 9: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: entrar e sair com bloqueio por status da conta`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T1.4: Administração de usuários

**Estado:** pendente

**Arquivos:**
- Criar: `app/Http/Controllers/Admin/UsuarioController.php`, `resources/js/Pages/Admin/Usuarios.vue`
- Modificar: `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/web.php`
- Teste: `tests/Feature/Contas/AdminUsuariosTest.php`

**Interfaces:**
- Consome: `User`, `StatusUsuario` (T1.1)
- Produz: Gate `admin`; padrões numéricos dos parâmetros de rota `cliente`, `contrato`, `cobranca`, `recibo`, `usuario`, `nota`; prop compartilhada `pendentes: int`; rotas `GET /admin/usuarios`, `POST /admin/usuarios/{usuario}/aprovar`, `POST /admin/usuarios/{usuario}/bloquear`

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Contas/AdminUsuariosTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminUsuariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_quem_nao_e_admin_recebe_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/usuarios')->assertForbidden();
    }

    public function test_lista_mostra_pendentes_primeiro_e_conta_pendentes(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Aprovado']);
        User::factory()->pendente()->create(['name' => 'Zé Pendente']);

        $this->actingAs($admin)->get('/admin/usuarios')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Usuarios')
                ->has('usuarios', 3)
                ->where('usuarios.0.nome', 'Zé Pendente')
                ->where('usuarios.0.status', 'pendente')
                ->where('pendentes', 1));
    }

    public function test_admin_aprova_bloqueia_e_reativa(): void
    {
        $admin = User::factory()->admin()->create();
        $usuario = User::factory()->pendente()->create();

        $this->actingAs($admin)->post("/admin/usuarios/{$usuario->id}/aprovar")->assertRedirect();
        $this->assertSame(StatusUsuario::Aprovado, $usuario->fresh()->status);

        $this->post("/admin/usuarios/{$usuario->id}/bloquear")->assertRedirect();
        $this->assertSame(StatusUsuario::Bloqueado, $usuario->fresh()->status);

        $this->post("/admin/usuarios/{$usuario->id}/aprovar");
        $this->assertSame(StatusUsuario::Aprovado, $usuario->fresh()->status);
    }

    public function test_admin_nao_bloqueia_a_propria_conta(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post("/admin/usuarios/{$admin->id}/bloquear")
            ->assertSessionHas('erro', 'Você não pode bloquear a própria conta.');
        $this->assertSame(StatusUsuario::Aprovado, $admin->fresh()->status);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=AdminUsuariosTest`
Esperado: FAIL com 404.

- [ ] **Passo 3: Gate e padrões de rota**

`app/Providers/AppServiceProvider.php` — método `boot`:

```php
    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->is_admin);

        // IDs não numéricos viram 404 antes de chegar aos controllers (que recebem int).
        foreach (['cliente', 'contrato', 'cobranca', 'recibo', 'usuario', 'nota'] as $parametro) {
            Route::pattern($parametro, '[0-9]+');
        }
    }
```

com `use App\Models\User;`, `use Illuminate\Support\Facades\Gate;`, `use Illuminate\Support\Facades\Route;` no topo.

- [ ] **Passo 4: prop compartilhada de pendentes**

No `share` de `HandleInertiaRequests`, acrescentar ao array (com `use App\Enums\StatusUsuario;` e `use App\Models\User;` no topo):

```php
            'pendentes' => fn () => $request->user()?->is_admin
                ? User::where('status', StatusUsuario::Pendente)->count()
                : 0,
```

- [ ] **Passo 5: controller**

`app/Http/Controllers/Admin/UsuarioController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusUsuario;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        $usuarios = User::query()
            ->orderByRaw("case when status = 'pendente' then 0 else 1 end")
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'nome' => $usuario->name,
                'email' => $usuario->email,
                'status' => $usuario->status->value,
                'is_admin' => $usuario->is_admin,
                'cadastrado_em' => $usuario->created_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Usuarios', ['usuarios' => $usuarios]);
    }

    /** Também serve para reativar quem estava bloqueado. */
    public function aprovar(User $usuario): RedirectResponse
    {
        $usuario->status = StatusUsuario::Aprovado;
        $usuario->save();

        return back()->with('sucesso', "Acesso de {$usuario->name} liberado.");
    }

    public function bloquear(Request $request, User $usuario): RedirectResponse
    {
        if ($usuario->is($request->user())) {
            return back()->with('erro', 'Você não pode bloquear a própria conta.');
        }

        $usuario->status = StatusUsuario::Bloqueado;
        $usuario->save();

        return back()->with('sucesso', "Acesso de {$usuario->name} bloqueado.");
    }
}
```

- [ ] **Passo 6: rotas**

Em `routes/web.php`, ao fim do grupo `['auth', 'conta.aprovada']`:

```php
    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index']);
        Route::post('/usuarios/{usuario}/aprovar', [UsuarioController::class, 'aprovar']);
        Route::post('/usuarios/{usuario}/bloquear', [UsuarioController::class, 'bloquear']);
    });
```

com `use App\Http\Controllers\Admin\UsuarioController;` no topo.

- [ ] **Passo 7: página**

`resources/js/Pages/Admin/Usuarios.vue`:

```vue
<script setup>
import { router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ usuarios: { type: Array, required: true } });

const rotuloStatus = { pendente: 'Aguardando liberação', aprovado: 'Liberado', bloqueado: 'Bloqueado' };

const acao = (id, nome) => router.post(`/admin/usuarios/${id}/${nome}`, {}, { preserveScroll: true });
</script>

<template>
    <CamadaApp>
        <h1 class="mb-6 text-2xl font-semibold">Usuários</h1>
        <ul class="divide-y divide-linha border border-linha bg-white">
            <li v-for="usuario in usuarios" :key="usuario.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-semibold">{{ usuario.nome }} <span v-if="usuario.is_admin" class="font-estreita text-xs text-grafite/70">administrador</span></p>
                    <p class="text-sm text-grafite/80">{{ usuario.email }} · cadastro em {{ usuario.cadastrado_em }}</p>
                    <p class="font-estreita text-sm" :class="usuario.status === 'pendente' ? 'text-carbono' : usuario.status === 'bloqueado' ? 'text-tinta-vermelha' : 'text-tinta-verde'">
                        {{ rotuloStatus[usuario.status] }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button v-if="usuario.status === 'pendente'" type="button" class="botao" @click="acao(usuario.id, 'aprovar')">Liberar acesso</button>
                    <button v-if="usuario.status === 'bloqueado'" type="button" class="botao-secundario" @click="acao(usuario.id, 'aprovar')">Reativar</button>
                    <button v-if="usuario.status !== 'bloqueado'" type="button" class="botao-secundario" @click="acao(usuario.id, 'bloquear')">Bloquear</button>
                </div>
            </li>
        </ul>
    </CamadaApp>
</template>
```

- [ ] **Passo 8: rodar e ver passar**

Run: `php artisan test --filter=AdminUsuariosTest`
Esperado: PASS (4 testes).

- [ ] **Passo 9: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: tela de administração para liberar e bloquear contas`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T1.5: Comando `app:criar-admin`

**Estado:** pendente

**Arquivos:**
- Modificar: `routes/console.php`
- Teste: `tests/Feature/Contas/CriarAdminTest.php`

**Interfaces:** produz `php artisan app:criar-admin {email} {--nome=Administrador}`: cria (pedindo senha duas vezes) ou promove usuário existente a admin aprovado.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Contas/CriarAdminTest.php`:

```php
<?php

namespace Tests\Feature\Contas;

use App\Enums\StatusUsuario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CriarAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_admin_aprovado_com_senha(): void
    {
        $this->artisan('app:criar-admin', ['email' => 'admin@exemplo.com', '--nome' => 'Admin'])
            ->expectsQuestion('Senha', 'segredo123')
            ->expectsQuestion('Confirme a senha', 'segredo123')
            ->assertExitCode(0);

        $admin = User::where('email', 'admin@exemplo.com')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertSame(StatusUsuario::Aprovado, $admin->status);
        $this->assertTrue(Hash::check('segredo123', $admin->password));
    }

    public function test_promove_usuario_existente_sem_pedir_senha(): void
    {
        $usuario = User::factory()->pendente()->create(['email' => 'ana@exemplo.com']);

        $this->artisan('app:criar-admin', ['email' => 'ana@exemplo.com'])->assertExitCode(0);

        $usuario->refresh();
        $this->assertTrue($usuario->is_admin);
        $this->assertSame(StatusUsuario::Aprovado, $usuario->status);
    }

    public function test_senhas_diferentes_nao_criam_nada(): void
    {
        $this->artisan('app:criar-admin', ['email' => 'admin@exemplo.com'])
            ->expectsQuestion('Senha', 'segredo123')
            ->expectsQuestion('Confirme a senha', 'outra-senha')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'admin@exemplo.com']);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=CriarAdminTest`
Esperado: FAIL (`Command "app:criar-admin" is not defined`).

- [ ] **Passo 3: comando**

Acrescentar em `routes/console.php` (com `use App\Enums\StatusUsuario;` e `use App\Models\User;` no topo):

```php
Artisan::command('app:criar-admin {email} {--nome=Administrador}', function (string $email) {
    $usuario = User::where('email', $email)->first();

    if (! $usuario) {
        $senha = (string) $this->secret('Senha');

        if (mb_strlen($senha) < 8) {
            $this->error('A senha precisa ter pelo menos 8 caracteres.');

            return 1;
        }

        if ($senha !== (string) $this->secret('Confirme a senha')) {
            $this->error('As senhas não conferem. Nada foi criado.');

            return 1;
        }

        $usuario = User::create(['name' => $this->option('nome'), 'email' => $email, 'password' => $senha]);
    }

    $usuario->is_admin = true;
    $usuario->status = StatusUsuario::Aprovado;
    $usuario->save();

    $this->info("Administrador pronto: {$usuario->email}");

    return 0;
})->purpose('Cria ou promove um usuário a administrador com acesso liberado');
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=CriarAdminTest`
Esperado: PASS (3 testes). Se o `secret` não casar com `expectsQuestion` (prompts do Laravel), registrar o erro exato, consultar a doc de testes de console do Laravel 13 e ajustar o teste — não o comportamento.

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add routes/console.php tests/Feature/Contas/CriarAdminTest.php
git commit -m "feat: comando para criar ou promover administrador`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T1.6: PORTÃO-BANCO + criar admin + smoke test de contas

**Estado:** pendente

**Arquivos:** nenhum de código. Altera `database/database.sqlite`.

Três escritas, três autorizações separadas:

- [ ] **Passo 1:** PORTÃO-BANCO para `php artisan migrate` (aplica a migração da T1.1). Esperar "sim". Executar.
- [ ] **Passo 2:** PORTÃO-BANCO para `php artisan app:criar-admin <email do usuário> --nome="<nome>"` — perguntar ao usuário o e-mail e o nome; o comando pede a senha, então **o usuário roda no terminal dele** (o shell do agente não responde prompts). Mostrar antes `db:show --counts` da tabela `users`.
- [ ] **Passo 3:** PORTÃO-BANCO para o smoke test: avisar que entrar no sistema grava na tabela `sessions` (se `SESSION_DRIVER=database` — conferir no `.env`) e que o cadastro de teste cria uma linha em `users` com e-mail `smoke-AAAAMMDD@teste.local`. Esperar "sim".
- [ ] **Passo 4: subir a aplicação**

```powershell
npm run build
php artisan serve
```

(`php artisan serve` em segundo plano; URL `http://127.0.0.1:8000`.)

- [ ] **Passo 5: smoke test com o browser-agent** (regra global — não usar Playwright)

Roteiro, com print de cada tela:
1. `/` redireciona para `/entrar`.
2. Criar conta `smoke-AAAAMMDD@teste.local` → mensagem de cadastro recebido; tentar entrar → "Seu cadastro aguarda liberação do administrador."
3. Entrar como admin → `/painel` com aviso "Complete seu perfil…"; menu mostra "Usuários" com contador 1.
4. Em Usuários, liberar a conta de smoke; sair; entrar com ela → painel.
5. Viewport de 390 px de largura: menu vira botão "Menu" e abre/fecha.

- [ ] **Passo 6:** mostrar os prints ao usuário e pedir validação da fase 1. Atualizar a tabela de acompanhamento (T1.1–T1.6 → `validada` se confirmado).
- [ ] **Passo 7:** perguntar se a conta de smoke deve ser apagada; se sim, PORTÃO-BANCO para `DELETE` **pelo e-mail** `smoke-AAAAMMDD@teste.local`.

---

## Fase 2 — Perfil e clientes

### Tarefa T2.1: CPF/CNPJ (inclusive CNPJ alfanumérico)

**Estado:** pendente

**Arquivos:**
- Criar: `app/Support/Documento.php`, `app/Support/Ufs.php`, `app/Rules/DocumentoValido.php`
- Teste: `tests/Unit/DocumentoTest.php`

**Interfaces:**
- Produz:
  - `Documento::normalizar(?string): string` — só `[0-9A-Z]`, maiúsculas
  - `Documento::valido(?string): bool` — CPF (11 dígitos) ou CNPJ (12 alfanuméricos + 2 dígitos)
  - `Documento::formatar(?string): string` — `529.982.247-25`, `12.ABC.345/01DE-35`; vazio → `-`
  - `Documento::cnpjComDv(string $base12): string`
  - `Ufs::TODAS` (27 siglas)
  - Regra `new DocumentoValido` — mensagem "Informe um CPF ou CNPJ válido."

**Fonte da regra do CNPJ alfanumérico:** Receita Federal, *Perguntas e Respostas — CNPJ Alfanumérico*, pergunta 14 (lido nesta sessão): módulo 11, cada caractere vale `ASCII − 48`, pesos iguais aos do CNPJ numérico; exemplo oficial `12.ABC.345/01DE` → DV `35`. Migração da base concluída em 25/07/2026.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Unit/DocumentoTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Documento;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentoTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'CPF com máscara' => ['529.982.247-25'],
            'CPF só dígitos' => ['52998224725'],
            'CNPJ numérico' => ['11.222.333/0001-81'],
            'CNPJ alfanumérico (exemplo oficial da Receita)' => ['12.ABC.345/01DE-35'],
            'CNPJ alfanumérico em minúsculas' => ['12abc34501de35'],
        ];
    }

    public static function invalidos(): array
    {
        return [
            'CPF com DV errado' => ['529.982.247-24'],
            'CPF repetido' => ['111.111.111-11'],
            'CNPJ com DV errado' => ['11.222.333/0001-80'],
            'CNPJ zerado' => ['00.000.000/0000-00'],
            'CNPJ alfanumérico com DV errado' => ['12ABC34501DE36'],
            'CNPJ com letra no DV' => ['12ABC34501DE3A'],
            'tamanho errado' => ['1234567890'],
            'vazio' => [''],
        ];
    }

    #[DataProvider('validos')]
    public function test_aceita_documentos_validos(string $documento): void
    {
        $this->assertTrue(Documento::valido($documento));
    }

    #[DataProvider('invalidos')]
    public function test_recusa_documentos_invalidos(string $documento): void
    {
        $this->assertFalse(Documento::valido($documento));
    }

    public function test_calcula_dv_do_exemplo_oficial(): void
    {
        $this->assertSame('12ABC34501DE35', Documento::cnpjComDv('12ABC34501DE'));
        $this->assertSame('11222333000181', Documento::cnpjComDv('112223330001'));
    }

    public function test_formata_para_exibicao(): void
    {
        $this->assertSame('529.982.247-25', Documento::formatar('52998224725'));
        $this->assertSame('11.222.333/0001-81', Documento::formatar('11222333000181'));
        $this->assertSame('12.ABC.345/01DE-35', Documento::formatar('12abc34501de35'));
        $this->assertSame('-', Documento::formatar(null));
    }

    public function test_normaliza_removendo_mascara(): void
    {
        $this->assertSame('12ABC34501DE35', Documento::normalizar(' 12.abc.345/01de-35 '));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=DocumentoTest`
Esperado: FAIL (`Class "App\Support\Documento" not found`).

- [ ] **Passo 3: implementar**

`app/Support/Documento.php`:

```php
<?php

namespace App\Support;

final class Documento
{
    private const PESOS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const PESOS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** Só letras e dígitos, em maiúsculas (o CNPJ pode ter letras desde jul/2026). */
    public static function normalizar(?string $valor): string
    {
        return (string) preg_replace('/[^0-9A-Z]/', '', mb_strtoupper((string) $valor));
    }

    public static function valido(?string $valor): bool
    {
        $documento = self::normalizar($valor);

        if (preg_match('/^\d{11}$/', $documento)) {
            return self::cpfValido($documento);
        }

        if (preg_match('/^[0-9A-Z]{12}\d{2}$/', $documento)) {
            return ! preg_match('/^(\d)\1{13}$/', $documento)
                && self::cnpjComDv(substr($documento, 0, 12)) === $documento;
        }

        return false;
    }

    public static function formatar(?string $valor): string
    {
        $documento = self::normalizar($valor);

        return match (strlen($documento)) {
            0 => '-',
            11 => (string) preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $documento),
            14 => (string) preg_replace('/^(.{2})(.{3})(.{3})(.{4})(.{2})$/', '$1.$2.$3/$4-$5', $documento),
            default => $documento,
        };
    }

    /** Completa os 12 caracteres-base com os dois dígitos verificadores. */
    public static function cnpjComDv(string $base): string
    {
        $base = self::normalizar($base);
        $dv1 = self::dvCnpj($base, self::PESOS_DV1);

        return $base.$dv1.self::dvCnpj($base.$dv1, self::PESOS_DV2);
    }

    private static function cpfValido(string $cpf): bool
    {
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }
            if ((int) $cpf[$posicao] !== ($soma * 10) % 11 % 10) {
                return false;
            }
        }

        return true;
    }

    /** Módulo 11; cada caractere vale ASCII − 48 (regra da Receita para o CNPJ alfanumérico). */
    private static function dvCnpj(string $parcial, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (ord($parcial[$i]) - 48) * $peso;
        }
        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
```

`app/Support/Ufs.php`:

```php
<?php

namespace App\Support;

final class Ufs
{
    public const TODAS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA',
        'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];
}
```

`app/Rules/DocumentoValido.php`:

```php
<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class DocumentoValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Documento::valido(is_string($value) ? $value : null)) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=DocumentoTest`
Esperado: PASS (16 casos).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Support app/Rules tests/Unit/DocumentoTest.php
git commit -m "feat: validação de CPF e CNPJ, inclusive alfanumérico`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T2.2: Meu perfil

**Estado:** pendente

**Arquivos:**
- Criar: `app/Http/Requests/PerfilRequest.php`, `app/Http/Controllers/PerfilController.php`, `resources/js/Components/CamposEndereco.vue`, `resources/js/Pages/Perfil/Editar.vue`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/PerfilTest.php`

**Interfaces:**
- Consome: `Documento`, `DocumentoValido`, `Ufs` (T2.1), `FamiliaModelo` (T1.1)
- Produz: `GET /perfil`, `POST /perfil` (multipart), `GET /perfil/logo`; logo gravado no disco `local` (`storage/app/private`) em `logos/{user_id}/<hash>.<ext>` — arquivos antigos **não** são apagados (recibos emitidos apontam para eles pelo snapshot); componente `CamposEndereco` (props `form`, `ufs`, `obrigatorio`)

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/PerfilTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    /** PNG 1x1 válido — evita depender da extensão GD para gerar imagem falsa. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

    private function dados(array $extra = []): array
    {
        return array_merge([
            'name' => 'Marcos Prestador',
            'documento' => '529.982.247-25',
            'nome_fantasia' => '',
            'inscricao_municipal' => '1000736',
            'inscricao_estadual' => '',
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A-4',
            'numero' => 'M.06',
            'complemento' => '',
            'bairro' => 'Marcos Freire',
            'cidade' => 'Nossa Senhora do Socorro',
            'uf' => 'se',
            'cep' => '49160-000',
            'modelo_padrao' => 'danfse',
            'dias_antecedencia' => 7,
        ], $extra);
    }

    public function test_pagina_mostra_os_dados_do_prestador(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/perfil')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Perfil/Editar')
                ->where('perfil.name', $usuario->name)
                ->where('perfil.tem_logo', false)
                ->has('ufs', 27)
                ->has('modelos', 2));
    }

    public function test_salva_e_normaliza_os_dados(): void
    {
        $usuario = User::factory()->semPerfil()->create();

        $this->actingAs($usuario)->post('/perfil', $this->dados())
            ->assertRedirect()
            ->assertSessionHas('sucesso', 'Perfil salvo.');

        $usuario->refresh();
        $this->assertSame('52998224725', $usuario->documento);
        $this->assertSame('49160000', $usuario->cep);
        $this->assertSame('SE', $usuario->uf);
        $this->assertSame(FamiliaModelo::Danfse, $usuario->modelo_padrao);
        $this->assertSame(7, $usuario->dias_antecedencia);
        $this->assertTrue($usuario->perfilCompleto());
    }

    public function test_recusa_documento_uf_e_cep_invalidos(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/perfil', $this->dados(['documento' => '123', 'uf' => 'XX', 'cep' => '123']))
            ->assertSessionHasErrors(['documento', 'uf', 'cep']);
    }

    public function test_logo_e_gravado_sem_apagar_o_anterior_e_servido(): void
    {
        Storage::fake('local');
        $usuario = User::factory()->create();
        $png = fn () => UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG));

        $this->actingAs($usuario)->post('/perfil', $this->dados(['logo' => $png()]));
        $primeiro = $usuario->fresh()->logo;
        $this->post('/perfil', $this->dados(['logo' => $png()]));
        $segundo = $usuario->fresh()->logo;

        $this->assertNotSame($primeiro, $segundo);
        Storage::disk('local')->assertExists([$primeiro, $segundo]);
        $this->get('/perfil/logo')->assertOk();
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        Storage::fake('local');

        $this->actingAs(User::factory()->create())
            ->post('/perfil', $this->dados(['logo' => UploadedFile::fake()->createWithContent('logo.png', 'texto')]))
            ->assertSessionHasErrors('logo');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=PerfilTest`
Esperado: FAIL com 404.

- [ ] **Passo 3: request**

`app/Http/Requests/PerfilRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\FamiliaModelo;
use App\Rules\DocumentoValido;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PerfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'documento' => Documento::normalizar($this->input('documento')),
            'cep' => preg_replace('/\D/', '', (string) $this->input('cep')),
            'uf' => mb_strtoupper((string) $this->input('uf')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'documento' => ['required', new DocumentoValido],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'telefone' => ['required', 'string', 'max:20'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['required', 'string', 'max:255'],
            'cidade' => ['required', 'string', 'max:255'],
            'uf' => ['required', Rule::in(Ufs::TODAS)],
            'cep' => ['required', 'digits:8'],
            'modelo_padrao' => ['required', Rule::enum(FamiliaModelo::class)],
            'dias_antecedencia' => ['required', 'integer', 'between:0,60'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ];
    }
}
```

- [ ] **Passo 4: controller**

`app/Http/Controllers/PerfilController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\PerfilRequest;
use App\Support\Ufs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PerfilController extends Controller
{
    private const CAMPOS = [
        'name', 'email', 'documento', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual',
        'telefone', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
        'dias_antecedencia',
    ];

    public function edit(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Perfil/Editar', [
            'perfil' => array_map(fn ($valor) => $valor ?? '', $usuario->only(self::CAMPOS)) + [
                'modelo_padrao' => $usuario->modelo_padrao->value,
                'tem_logo' => filled($usuario->logo),
            ],
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ]);
    }

    public function update(PerfilRequest $request): RedirectResponse
    {
        $usuario = $request->user();
        $usuario->fill($request->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            // Nome com hash: o arquivo anterior fica, porque recibos já emitidos apontam para ele.
            $usuario->logo = $request->file('logo')->store("logos/{$usuario->id}", 'local');
        }

        $usuario->save();

        return back()->with('sucesso', 'Perfil salvo.');
    }

    public function logo(Request $request): StreamedResponse
    {
        $caminho = $request->user()->logo;
        abort_if(blank($caminho) || ! Storage::disk('local')->exists($caminho), 404);

        return Storage::disk('local')->response($caminho);
    }
}
```

- [ ] **Passo 5: rotas**

Em `routes/web.php`, dentro do grupo `['auth', 'conta.aprovada']`, antes do grupo admin (com `use App\Http\Controllers\PerfilController;`):

```php
    Route::get('/perfil', [PerfilController::class, 'edit']);
    Route::post('/perfil', [PerfilController::class, 'update']);
    Route::get('/perfil/logo', [PerfilController::class, 'logo']);
```

- [ ] **Passo 6: componentes e página**

`resources/js/Components/CamposEndereco.vue`:

```vue
<script setup>
import Campo from './Campo.vue';

defineProps({
    form: { type: Object, required: true },
    ufs: { type: Array, required: true },
    obrigatorio: { type: Boolean, default: false },
});
</script>

<template>
    <fieldset class="grid gap-4 sm:grid-cols-6">
        <legend class="mb-2 text-sm font-semibold">Endereço</legend>
        <Campo id="logradouro" rotulo="Logradouro" :erro="form.errors.logradouro" class="sm:col-span-4">
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
        <Campo id="cidade" rotulo="Cidade" :erro="form.errors.cidade" class="sm:col-span-3">
            <input id="cidade" v-model="form.cidade" autocomplete="address-level2" class="entrada" :required="obrigatorio">
        </Campo>
        <Campo id="uf" rotulo="UF" :erro="form.errors.uf" class="sm:col-span-1">
            <select id="uf" v-model="form.uf" class="entrada" :required="obrigatorio">
                <option value="">—</option>
                <option v-for="uf in ufs" :key="uf" :value="uf">{{ uf }}</option>
            </select>
        </Campo>
        <Campo id="cep" rotulo="CEP" :erro="form.errors.cep" class="sm:col-span-2">
            <input id="cep" v-model="form.cep" inputmode="numeric" autocomplete="postal-code" class="entrada" :required="obrigatorio">
        </Campo>
    </fieldset>
</template>
```

`resources/js/Pages/Perfil/Editar.vue`:

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CamposEndereco from '../../Components/CamposEndereco.vue';

const props = defineProps({
    perfil: { type: Object, required: true },
    modelos: { type: Array, required: true },
    ufs: { type: Array, required: true },
});

const form = useForm({ ...props.perfil, logo: null });

const salvar = () => form.post('/perfil', { preserveScroll: true });
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">Meu perfil</h1>
        <p class="mt-1 mb-6 text-sm text-grafite/80">Estes dados aparecem como prestador em todos os recibos novos.</p>

        <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="salvar">
            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Prestador</legend>
                <Campo id="name" rotulo="Nome ou razão social" :erro="form.errors.name" class="sm:col-span-4">
                    <input id="name" v-model="form.name" class="entrada" required>
                </Campo>
                <Campo id="documento" rotulo="CPF ou CNPJ" :erro="form.errors.documento" class="sm:col-span-2">
                    <input id="documento" v-model="form.documento" class="entrada" required>
                </Campo>
                <Campo id="nome_fantasia" rotulo="Nome fantasia" :erro="form.errors.nome_fantasia" class="sm:col-span-3">
                    <input id="nome_fantasia" v-model="form.nome_fantasia" class="entrada">
                </Campo>
                <Campo id="telefone" rotulo="Telefone" :erro="form.errors.telefone" class="sm:col-span-3">
                    <input id="telefone" v-model="form.telefone" type="tel" autocomplete="tel" class="entrada" required>
                </Campo>
                <Campo id="inscricao_municipal" rotulo="Inscrição municipal" :erro="form.errors.inscricao_municipal" class="sm:col-span-3">
                    <input id="inscricao_municipal" v-model="form.inscricao_municipal" class="entrada">
                </Campo>
                <Campo id="inscricao_estadual" rotulo="Inscrição estadual" :erro="form.errors.inscricao_estadual" class="sm:col-span-3">
                    <input id="inscricao_estadual" v-model="form.inscricao_estadual" class="entrada">
                </Campo>
                <p class="font-estreita text-xs text-grafite/70 sm:col-span-6">E-mail nos recibos: {{ perfil.email }}</p>
            </fieldset>

            <CamposEndereco :form="form" :ufs="ufs" obrigatorio />

            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Recibos</legend>
                <Campo id="modelo_padrao" rotulo="Modelo sugerido para clientes novos" :erro="form.errors.modelo_padrao" class="sm:col-span-3">
                    <select id="modelo_padrao" v-model="form.modelo_padrao" class="entrada">
                        <option v-for="modelo in modelos" :key="modelo.valor" :value="modelo.valor">{{ modelo.rotulo }}</option>
                    </select>
                </Campo>
                <Campo id="dias_antecedencia" rotulo="Gerar a cobrança quantos dias antes do vencimento" :erro="form.errors.dias_antecedencia" class="sm:col-span-3">
                    <input id="dias_antecedencia" v-model="form.dias_antecedencia" type="number" min="0" max="60" class="entrada">
                </Campo>
                <Campo id="logo" rotulo="Logo nos recibos (PNG ou JPG, até 1 MB)" :erro="form.errors.logo" class="sm:col-span-6">
                    <img v-if="perfil.tem_logo" src="/perfil/logo" alt="Logo atual" class="mb-2 h-16 w-auto">
                    <input id="logo" type="file" accept="image/png,image/jpeg" @input="form.logo = $event.target.files[0]">
                </Campo>
            </fieldset>

            <div>
                <button class="botao" :disabled="form.processing">Salvar perfil</button>
            </div>
        </form>
    </CamadaApp>
</template>
```

- [ ] **Passo 7: rodar e ver passar**

Run: `php artisan test --filter=PerfilTest`
Esperado: PASS (5 testes).

- [ ] **Passo 8: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: perfil do prestador com logo e preferências de recibo`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T2.3: Clientes

**Estado:** pendente

**Arquivos:**
- Criar: `database/migrations/2026_10_09_100100_create_clientes_table.php`, `app/Models/Cliente.php`, `database/factories/ClienteFactory.php`, `app/Http/Requests/ClienteRequest.php`, `app/Http/Controllers/ClienteController.php`, `resources/js/Pages/Clientes/Index.vue`, `resources/js/Pages/Clientes/Form.vue`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/ClientesTest.php`

**Interfaces:**
- Consome: `Documento`, `DocumentoValido`, `Ufs` (T2.1), `FamiliaModelo` (T1.1), `CamposEndereco` (T2.2)
- Produz: `Cliente` com `Cliente::CAMPOS` (lista usada no snapshot), cast `modelo` → `FamiliaModelo`, relações `user()`, `contratos()`, `cobrancas()`; `ClienteFactory` com documento CNPJ válido e único; rotas `resource('clientes')` sem `show`

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/ClientesTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientesTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertSessionHasNoErrors();
    }

    public function test_recusa_documento_invalido_e_repetido_no_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->create(['documento' => '11222333000181']);

        $this->actingAs($usuario)->post('/clientes', $this->dados(['documento' => '123']))
            ->assertSessionHasErrors('documento');
        $this->post('/clientes', $this->dados())->assertSessionHasErrors('documento');
    }

    public function test_mesmo_documento_em_usuarios_diferentes_e_permitido(): void
    {
        Cliente::factory()->create(['documento' => '11222333000181']);

        $this->actingAs(User::factory()->create())->post('/clientes', $this->dados())
            ->assertSessionHasNoErrors();
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
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=ClientesTest`
Esperado: FAIL (`Class "App\Models\Cliente" not found`).

- [ ] **Passo 3: migração, model e factory**

`database/migrations/2026_10_09_100100_create_clientes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('documento', 14);
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep', 8)->nullable();
            $table->string('modelo', 20);
            $table->timestamps();

            $table->unique(['user_id', 'documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
```

`app/Models/Cliente.php`:

```php
<?php

namespace App\Models;

use App\Enums\FamiliaModelo;
use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'modelo',
])]
class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory;

    /** Dados do tomador que vão para o snapshot do recibo e para o formulário. */
    public const CAMPOS = [
        'nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
    ];

    protected function casts(): array
    {
        return ['modelo' => FamiliaModelo::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
```

`database/factories/ClienteFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\FamiliaModelo;
use App\Models\Cliente;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        $raiz = str_pad((string) fake()->unique()->numberBetween(1, 99_999_999), 8, '0', STR_PAD_LEFT);

        return [
            'user_id' => User::factory(),
            'nome' => fake()->company(),
            'documento' => Documento::cnpjComDv($raiz.'0001'),
            'telefone' => '(75) 3333-0000',
            'email' => fake()->safeEmail(),
            'logradouro' => 'Rua Virgílio Oliveira Lima',
            'numero' => '185',
            'bairro' => 'Centro',
            'cidade' => 'Araci',
            'uf' => 'BA',
            'cep' => '48760000',
            'modelo' => FamiliaModelo::Webiss,
        ];
    }
}
```

- [ ] **Passo 4: request e controller**

`app/Http/Requests/ClienteRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\FamiliaModelo;
use App\Rules\DocumentoValido;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $cep = preg_replace('/\D/', '', (string) $this->input('cep'));
        $uf = mb_strtoupper((string) $this->input('uf'));

        $this->merge([
            'documento' => Documento::normalizar($this->input('documento')),
            'cep' => $cep === '' ? null : $cep,
            'uf' => $uf === '' ? null : $uf,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'documento' => [
                'required',
                new DocumentoValido,
                Rule::unique('clientes', 'documento')
                    ->where('user_id', $this->user()->id)
                    ->ignore($this->route('cliente')),
            ],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', Rule::in(Ufs::TODAS)],
            'cep' => ['nullable', 'digits:8'],
            'modelo' => ['required', Rule::enum(FamiliaModelo::class)],
        ];
    }
}
```

`app/Http/Controllers/ClienteController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\FamiliaModelo;
use App\Http\Requests\ClienteRequest;
use App\Models\Cliente;
use App\Support\Documento;
use App\Support\Ufs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function index(Request $request): Response
    {
        $clientes = $request->user()->clientes()->orderBy('nome')->get()
            ->map(fn (Cliente $cliente) => [
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => Documento::formatar($cliente->documento),
                'cidade' => collect([$cliente->cidade, $cliente->uf])->filter()->implode(' / '),
                'modelo' => $cliente->modelo->rotulo(),
            ]);

        return Inertia::render('Clientes/Index', ['clientes' => $clientes]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $request->user()->clientes()->create($request->validated());

        return redirect('/clientes')->with('sucesso', 'Cliente salvo.');
    }

    public function edit(Request $request, int $cliente): Response
    {
        return $this->formulario($request, $request->user()->clientes()->findOrFail($cliente));
    }

    public function update(ClienteRequest $request, int $cliente): RedirectResponse
    {
        $request->user()->clientes()->findOrFail($cliente)->update($request->validated());

        return redirect('/clientes')->with('sucesso', 'Cliente salvo.');
    }

    public function destroy(Request $request, int $cliente): RedirectResponse
    {
        $request->user()->clientes()->findOrFail($cliente)->delete();

        return redirect('/clientes')->with('sucesso', 'Cliente excluído.');
    }

    private function formulario(Request $request, ?Cliente $cliente): Response
    {
        return Inertia::render('Clientes/Form', [
            'cliente' => $cliente
                ? ['id' => $cliente->id, 'modelo' => $cliente->modelo->value]
                    + array_map(fn ($valor) => $valor ?? '', $cliente->only(Cliente::CAMPOS))
                : null,
            'modeloPadrao' => $request->user()->modelo_padrao->value,
            'modelos' => FamiliaModelo::opcoes(),
            'ufs' => Ufs::TODAS,
        ]);
    }
}
```

- [ ] **Passo 5: rotas**

Em `routes/web.php`, dentro do grupo autenticado, antes do grupo admin (com `use App\Http\Controllers\ClienteController;`):

```php
    Route::resource('clientes', ClienteController::class)->except('show');
```

- [ ] **Passo 6: páginas**

`resources/js/Pages/Clientes/Index.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ clientes: { type: Array, required: true } });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Clientes</h1>
            <Link href="/clientes/create" class="botao">Novo cliente</Link>
        </div>

        <p v-if="!clientes.length" class="border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Nenhum cliente ainda. Cadastre o primeiro para criar contratos e cobranças.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="cliente in clientes" :key="cliente.id">
                <Link :href="`/clientes/${cliente.id}/edit`" class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3 hover:bg-papel">
                    <span class="font-semibold">{{ cliente.nome }}</span>
                    <span class="font-estreita text-sm tabular-nums text-grafite/80">{{ cliente.documento }}</span>
                    <span class="w-full font-estreita text-xs text-grafite/70">{{ cliente.cidade || 'Sem cidade' }} · {{ cliente.modelo }}</span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
```

`resources/js/Pages/Clientes/Form.vue`:

```vue
<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import CamposEndereco from '../../Components/CamposEndereco.vue';

const props = defineProps({
    cliente: { type: Object, default: null },
    modeloPadrao: { type: String, required: true },
    modelos: { type: Array, required: true },
    ufs: { type: Array, required: true },
});

const campos = ['nome', 'documento', 'inscricao_municipal', 'inscricao_estadual', 'telefone', 'email',
    'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'];

const form = useForm({
    ...Object.fromEntries(campos.map((campo) => [campo, props.cliente?.[campo] ?? ''])),
    modelo: props.cliente?.modelo ?? props.modeloPadrao,
});

const modeloMudou = computed(() => props.cliente && form.modelo !== props.cliente.modelo);
const confirmandoExclusao = ref(false);

const salvar = () => (props.cliente ? form.put(`/clientes/${props.cliente.id}`) : form.post('/clientes'));
const excluir = () => router.delete(`/clientes/${props.cliente.id}`);
</script>

<template>
    <CamadaApp>
        <Link href="/clientes" class="font-estreita text-sm text-carbono underline">Voltar para clientes</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ cliente ? 'Editar cliente' : 'Novo cliente' }}</h1>

        <form class="flex max-w-3xl flex-col gap-8" @submit.prevent="salvar">
            <fieldset class="grid gap-4 sm:grid-cols-6">
                <legend class="mb-2 text-sm font-semibold">Tomador do serviço</legend>
                <Campo id="nome" rotulo="Nome ou razão social" :erro="form.errors.nome" class="sm:col-span-4">
                    <input id="nome" v-model="form.nome" class="entrada" required>
                </Campo>
                <Campo id="documento" rotulo="CPF ou CNPJ" :erro="form.errors.documento" class="sm:col-span-2">
                    <input id="documento" v-model="form.documento" class="entrada" required>
                </Campo>
                <Campo id="inscricao_municipal" rotulo="Inscrição municipal" :erro="form.errors.inscricao_municipal" class="sm:col-span-2">
                    <input id="inscricao_municipal" v-model="form.inscricao_municipal" class="entrada">
                </Campo>
                <Campo id="inscricao_estadual" rotulo="Inscrição estadual" :erro="form.errors.inscricao_estadual" class="sm:col-span-2">
                    <input id="inscricao_estadual" v-model="form.inscricao_estadual" class="entrada">
                </Campo>
                <Campo id="telefone" rotulo="Telefone" :erro="form.errors.telefone" class="sm:col-span-2">
                    <input id="telefone" v-model="form.telefone" type="tel" class="entrada">
                </Campo>
                <Campo id="email" rotulo="E-mail" :erro="form.errors.email" class="sm:col-span-6">
                    <input id="email" v-model="form.email" type="email" class="entrada">
                </Campo>
            </fieldset>

            <CamposEndereco :form="form" :ufs="ufs" />

            <fieldset class="flex flex-col gap-3">
                <legend class="mb-2 text-sm font-semibold">Modelo do recibo</legend>
                <label v-for="modelo in modelos" :key="modelo.valor" class="flex items-center gap-2 text-sm">
                    <input v-model="form.modelo" type="radio" name="modelo" :value="modelo.valor"> {{ modelo.rotulo }}
                </label>
                <p v-if="form.errors.modelo" class="text-xs text-tinta-vermelha">{{ form.errors.modelo }}</p>
                <p v-if="modeloMudou" role="status" class="border-l-4 border-carbono bg-white px-4 py-2 text-sm">
                    Recibos já emitidos para este cliente mantêm o modelo anterior. Só os próximos usam o novo.
                </p>
            </fieldset>

            <div class="flex flex-wrap gap-3">
                <button class="botao" :disabled="form.processing">Salvar cliente</button>
                <template v-if="cliente">
                    <button v-if="!confirmandoExclusao" type="button" class="botao-secundario" @click="confirmandoExclusao = true">Excluir cliente</button>
                    <template v-else>
                        <button type="button" class="botao-secundario border-tinta-vermelha text-tinta-vermelha" @click="excluir">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmandoExclusao = false">Manter cliente</button>
                    </template>
                </template>
            </div>
        </form>
    </CamadaApp>
</template>
```

- [ ] **Passo 7: rodar e ver passar**

Run: `php artisan test --filter=ClientesTest`
Esperado: PASS (8 testes).

- [ ] **Passo 8: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: cadastro de clientes com modelo de recibo por cliente`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Fase 3 — Contratos, cobranças e recibos

### Tarefa T3.1: Dinheiro e regra de valor em reais

**Estado:** pendente

**Arquivos:**
- Criar: `app/Support/Dinheiro.php`, `app/Rules/ValorEmReais.php`
- Teste: `tests/Unit/DinheiroTest.php`

**Interfaces:**
- Produz: `Dinheiro::paraCentavos(?string): ?int` (null = inválido), `Dinheiro::formatar(int $centavos): string` (`260,60`, `1.234.567,89`); regra `new ValorEmReais` (aceita de R$ 0,01 a R$ 99.999.999,99; mensagem "Informe um valor válido, como 260,60.")

- [ ] **Passo 1: escrever o teste que falha** (foco de revisão 1)

`tests/Unit/DinheiroTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'vírgula decimal' => ['260,60', 26060],
            'uma casa decimal' => ['260,6', 26060],
            'inteiro' => ['260', 26000],
            'com R$ e espaço' => ['R$ 260,60', 26060],
            'milhar com vírgula' => ['1.234,56', 123456],
            'milhar sem centavos' => ['1.234', 123400],
            'ponto decimal' => ['260.60', 26060],
            'um centavo' => ['0,01', 1],
            'espaços nas pontas' => [' 15 ', 1500],
        ];
    }

    public static function invalidos(): array
    {
        return [
            'vazio' => [''],
            'negativo' => ['-5'],
            'texto' => ['abc'],
            'duas vírgulas' => ['1,234,56'],
            'três casas decimais' => ['12,345'],
            'pontos fora do padrão' => ['1.23.4'],
        ];
    }

    #[DataProvider('validos')]
    public function test_converte_formatos_aceitos(string $entrada, int $centavos): void
    {
        $this->assertSame($centavos, Dinheiro::paraCentavos($entrada));
    }

    #[DataProvider('invalidos')]
    public function test_recusa_formatos_invalidos(string $entrada): void
    {
        $this->assertNull(Dinheiro::paraCentavos($entrada));
    }

    public function test_formata_em_reais(): void
    {
        $this->assertSame('260,60', Dinheiro::formatar(26060));
        $this->assertSame('1.234.567,89', Dinheiro::formatar(123456789));
        $this->assertSame('0,05', Dinheiro::formatar(5));
        $this->assertSame('0,00', Dinheiro::formatar(0));
    }
}
```

Acrescentar ao mesmo arquivo um teste da regra (precisa do container, então vai em `tests/Feature/ValorEmReaisTest.php`):

```php
<?php

namespace Tests\Feature;

use App\Rules\ValorEmReais;
use Tests\TestCase;

class ValorEmReaisTest extends TestCase
{
    public function test_limites_do_valor(): void
    {
        $valida = fn (string $valor) => validator(['valor' => $valor], ['valor' => [new ValorEmReais]])->passes();

        $this->assertTrue($valida('260,60'));
        $this->assertTrue($valida('99.999.999,99'));
        $this->assertFalse($valida('0,00'));
        $this->assertFalse($valida('100.000.000,00'));
        $this->assertFalse($valida('abc'));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter="DinheiroTest|ValorEmReaisTest"`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Support/Dinheiro.php`:

```php
<?php

namespace App\Support;

final class Dinheiro
{
    /**
     * Converte o que a pessoa digitou ("1.234,56", "260,6", "R$ 260") em centavos.
     * Retorna null quando não dá para interpretar sem chutar.
     */
    public static function paraCentavos(?string $valor): ?int
    {
        $texto = trim(str_replace(['R$', ' ', "\u{00A0}"], '', (string) $valor));

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',')) {
            // Formato brasileiro: ponto é milhar, vírgula é decimal.
            $texto = str_replace(',', '.', str_replace('.', '', $texto));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto)) {
            // "1.234" sem vírgula: ponto de milhar.
            $texto = str_replace('.', '', $texto);
        }

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $texto, $partes)) {
            return null;
        }

        return (int) $partes[1] * 100 + (int) str_pad($partes[2] ?? '0', 2, '0');
    }

    public static function formatar(int $centavos): string
    {
        return number_format(intdiv($centavos, 100), 0, ',', '.')
            .','.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
```

`app/Rules/ValorEmReais.php`:

```php
<?php

namespace App\Rules;

use App\Support\Dinheiro;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValorEmReais implements ValidationRule
{
    private const MAXIMO_CENTAVOS = 9_999_999_999; // R$ 99.999.999,99

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $centavos = is_string($value) ? Dinheiro::paraCentavos($value) : null;

        if ($centavos === null || $centavos < 1 || $centavos > self::MAXIMO_CENTAVOS) {
            $fail('Informe um valor válido, como 260,60.');
        }
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter="DinheiroTest|ValorEmReaisTest"`
Esperado: PASS.

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Support/Dinheiro.php app/Rules/ValorEmReais.php tests/Unit/DinheiroTest.php tests/Feature/ValorEmReaisTest.php
git commit -m "feat: conversão e formatação de valores em reais`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.2: Periodicidade e calculadora de períodos

**Estado:** pendente

**Arquivos:**
- Criar: `app/Enums/Periodicidade.php`, `app/Support/Periodo.php`, `app/Services/CalculadoraDePeriodos.php`
- Teste: `tests/Unit/CalculadoraDePeriodosTest.php`

**Interfaces:**
- Produz:
  - `enum Periodicidade: string { Mensal; Trimestral; Semestral; Anual }` com `meses(): int`, `rotulo(): string`, `static opcoes(): list<array{valor, rotulo}>`
  - `final readonly class Periodo { CarbonImmutable $inicio; CarbonImmutable $fim; CarbonImmutable $vencimento }`
  - `CalculadoraDePeriodos::periodo(CarbonImmutable $dataInicio, Periodicidade $p, int $diaVencimento, int $indice): Periodo`
  - `CalculadoraDePeriodos::devidos(CarbonImmutable $dataInicio, ?CarbonImmutable $dataFim, Periodicidade $p, int $diaVencimento, int $diasAntecedencia, CarbonImmutable $hoje): list<Periodo>`

**Regras (spec §7.1):** período *i* começa em `dataInicio + i×meses` (sem estouro de mês, sempre a partir da data original para não "escorregar") e termina na véspera do próximo; vencimento = `diaVencimento` no mês do início do período, limitado ao último dia do mês; devido quando `hoje >= vencimento − diasAntecedencia`; para no primeiro período que começa depois de `dataFim`. Verificado nesta máquina: `addMonthsNoOverflow(1)` de 31/01/2026 → 28/02/2026; `setDay(min(31, daysInMonth))` em fev/2026 → 28/02/2026.

- [ ] **Passo 1: escrever o teste que falha** (foco de revisão 4)

`tests/Unit/CalculadoraDePeriodosTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\Periodicidade;
use App\Services\CalculadoraDePeriodos;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CalculadoraDePeriodosTest extends TestCase
{
    private CalculadoraDePeriodos $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculadoraDePeriodos;
    }

    private function data(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data);
    }

    public function test_mensal_como_na_nota_de_referencia(): void
    {
        $p0 = $this->calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 0);
        $p1 = $this->calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 1);

        $this->assertSame(['2026-09-21', '2026-10-20', '2026-09-21'], [$p0->inicio->toDateString(), $p0->fim->toDateString(), $p0->vencimento->toDateString()]);
        $this->assertSame(['2026-10-21', '2026-11-20', '2026-10-21'], [$p1->inicio->toDateString(), $p1->fim->toDateString(), $p1->vencimento->toDateString()]);
    }

    public function test_inicio_no_dia_31_nao_escorrega_e_nao_deixa_buraco(): void
    {
        $inicio = $this->data('2026-01-31');
        $p0 = $this->calc->periodo($inicio, Periodicidade::Mensal, 31, 0);
        $p1 = $this->calc->periodo($inicio, Periodicidade::Mensal, 31, 1);
        $p2 = $this->calc->periodo($inicio, Periodicidade::Mensal, 31, 2);

        $this->assertSame('2026-02-27', $p0->fim->toDateString());
        $this->assertSame('2026-02-28', $p1->inicio->toDateString());
        $this->assertSame('2026-02-28', $p1->vencimento->toDateString(), 'dia 31 em fevereiro vira o último dia');
        $this->assertSame('2026-03-31', $p2->inicio->toDateString(), 'volta para o dia 31 original');
    }

    public function test_trimestral_e_anual(): void
    {
        $tri = $this->calc->periodo($this->data('2026-01-10'), Periodicidade::Trimestral, 10, 1);
        $anual = $this->calc->periodo($this->data('2026-02-15'), Periodicidade::Anual, 15, 0);

        $this->assertSame(['2026-04-10', '2026-07-09'], [$tri->inicio->toDateString(), $tri->fim->toDateString()]);
        $this->assertSame('2027-02-14', $anual->fim->toDateString());
    }

    public function test_devidos_respeita_a_antecedencia(): void
    {
        $devidos = fn (string $hoje) => $this->calc->devidos(
            $this->data('2026-09-21'), null, Periodicidade::Mensal, 21, 10, $this->data($hoje),
        );

        $this->assertCount(1, $devidos('2026-10-09'), 'próximo vencimento 21/10 − 10 dias = 11/10');
        $this->assertCount(1, $devidos('2026-10-10'));
        $this->assertCount(2, $devidos('2026-10-11'));
    }

    public function test_inicio_no_futuro_nao_gera_nada_antes_da_hora(): void
    {
        $this->assertSame([], $this->calc->devidos(
            $this->data('2026-12-01'), null, Periodicidade::Mensal, 1, 10, $this->data('2026-10-09'),
        ));
    }

    public function test_para_na_data_de_fim(): void
    {
        $devidos = $this->calc->devidos(
            $this->data('2026-01-01'), $this->data('2026-03-31'), Periodicidade::Mensal, 1, 0, $this->data('2026-12-01'),
        );

        $this->assertCount(3, $devidos);
        $this->assertSame('2026-03-01', $devidos[2]->inicio->toDateString());
    }

    public function test_vencimento_antes_do_inicio_do_periodo_e_permitido_pre_pago(): void
    {
        $p = $this->calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 10, 0);

        $this->assertSame('2026-09-10', $p->vencimento->toDateString());
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=CalculadoraDePeriodosTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Enums/Periodicidade.php`:

```php
<?php

namespace App\Enums;

enum Periodicidade: string
{
    case Mensal = 'mensal';
    case Trimestral = 'trimestral';
    case Semestral = 'semestral';
    case Anual = 'anual';

    public function meses(): int
    {
        return match ($this) {
            self::Mensal => 1,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
        };
    }

    public function rotulo(): string
    {
        return ucfirst($this->value);
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $p) => ['valor' => $p->value, 'rotulo' => $p->rotulo()], self::cases());
    }
}
```

`app/Support/Periodo.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final readonly class Periodo
{
    public function __construct(
        public CarbonImmutable $inicio,
        public CarbonImmutable $fim,
        public CarbonImmutable $vencimento,
    ) {}
}
```

`app/Services/CalculadoraDePeriodos.php`:

```php
<?php

namespace App\Services;

use App\Enums\Periodicidade;
use App\Support\Periodo;
use Carbon\CarbonImmutable;

final class CalculadoraDePeriodos
{
    /** Trava de segurança: 100 anos de períodos mensais. */
    private const LIMITE = 1200;

    public function periodo(CarbonImmutable $dataInicio, Periodicidade $periodicidade, int $diaVencimento, int $indice): Periodo
    {
        // Sempre a partir da data original: 31/01 → 28/02 → 31/03, sem escorregar para o dia 28.
        $inicio = $dataInicio->startOfDay()->addMonthsNoOverflow($indice * $periodicidade->meses());
        $fim = $dataInicio->startOfDay()->addMonthsNoOverflow(($indice + 1) * $periodicidade->meses())->subDay();
        $mes = $inicio->startOfMonth();

        return new Periodo($inicio, $fim, $mes->setDay(min($diaVencimento, $mes->daysInMonth)));
    }

    /** @return list<Periodo> */
    public function devidos(
        CarbonImmutable $dataInicio,
        ?CarbonImmutable $dataFim,
        Periodicidade $periodicidade,
        int $diaVencimento,
        int $diasAntecedencia,
        CarbonImmutable $hoje,
    ): array {
        $devidos = [];

        for ($indice = 0; $indice < self::LIMITE; $indice++) {
            $periodo = $this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice);

            if ($dataFim !== null && $periodo->inicio->gt($dataFim)) {
                break;
            }

            if ($periodo->vencimento->subDays($diasAntecedencia)->gt($hoje->startOfDay())) {
                break;
            }

            $devidos[] = $periodo;
        }

        return $devidos;
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=CalculadoraDePeriodosTest`
Esperado: PASS (7 testes).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Enums/Periodicidade.php app/Support/Periodo.php app/Services/CalculadoraDePeriodos.php tests/Unit/CalculadoraDePeriodosTest.php
git commit -m "feat: cálculo de períodos e vencimentos dos contratos`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.3: Contratos

**Estado:** pendente

**Arquivos:**
- Criar: `database/migrations/2026_10_09_100200_create_contratos_table.php`, `app/Models/Contrato.php`, `database/factories/ContratoFactory.php`, `app/Rules/LinhasMaximas.php`, `app/Http/Requests/ContratoRequest.php`, `app/Http/Controllers/ContratoController.php`, `resources/js/Pages/Contratos/Index.vue`, `resources/js/Pages/Contratos/Form.vue`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/ContratosTest.php`

**Interfaces:**
- Consome: `Dinheiro`, `ValorEmReais` (T3.1), `Periodicidade` (T3.2), `Cliente` (T2.3)
- Produz: `Contrato` (casts: `periodicidade` → enum, `data_inicio`/`data_fim` → `immutable_date`, `ativo` → bool); `ContratoRequest::dadosContrato(): array` (troca `valor` por `valor_centavos`); regra `new LinhasMaximas(int)`; rotas `resource('contratos')` sem `show` e `destroy` (contrato se encerra desmarcando "ativo")

- [ ] **Passo 1: escrever o teste que falha** (focos de revisão 3 e 4)

`tests/Feature/ContratosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\Periodicidade;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContratosTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'valor' => '260,60',
            'periodicidade' => 'mensal',
            'dia_vencimento' => 21,
            'data_inicio' => '2026-09-21',
            'data_fim' => null,
            'ativo' => true,
        ], $extra);
    }

    public function test_cria_contrato_convertendo_o_valor(): void
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dados())->assertRedirect('/contratos');

        $contrato = $this->usuario->contratos()->firstOrFail();
        $this->assertSame(26060, $contrato->valor_centavos);
        $this->assertSame(Periodicidade::Mensal, $contrato->periodicidade);
        $this->assertSame('2026-09-21', $contrato->data_inicio->toDateString());
        $this->assertTrue($contrato->ativo);
    }

    public function test_lista_com_cliente_e_valor_formatado(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['valor_centavos' => 26060]);

        $this->actingAs($this->usuario)->get('/contratos')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contratos/Index')
                ->has('contratos', 1)
                ->where('contratos.0.cliente', $this->cliente->nome)
                ->where('contratos.0.valor', '260,60'));
    }

    public function test_cliente_de_outro_usuario_e_recusado(): void
    {
        $alheio = Cliente::factory()->create();

        $this->actingAs($this->usuario)->post('/contratos', $this->dados(['cliente_id' => $alheio->id]))
            ->assertSessionHasErrors('cliente_id');
    }

    public function test_descricao_longa_demais_e_recusada(): void
    {
        $this->actingAs($this->usuario);

        $this->post('/contratos', $this->dados(['descricao' => str_repeat("linha\n", 26)]))
            ->assertSessionHasErrors(['descricao' => 'Use no máximo 25 linhas.']);
        $this->post('/contratos', $this->dados(['descricao' => str_repeat('a', 2001)]))
            ->assertSessionHasErrors('descricao');
    }

    public function test_datas_e_dia_de_vencimento_invalidos(): void
    {
        $this->actingAs($this->usuario);

        $this->post('/contratos', $this->dados(['data_fim' => '2026-09-01']))->assertSessionHasErrors('data_fim');
        $this->post('/contratos', $this->dados(['dia_vencimento' => 0]))->assertSessionHasErrors('dia_vencimento');
        $this->post('/contratos', $this->dados(['dia_vencimento' => 32]))->assertSessionHasErrors('dia_vencimento');
        $this->post('/contratos', $this->dados(['valor' => '0']))->assertSessionHasErrors('valor');
    }

    public function test_encerra_contrato_e_isola_contrato_alheio(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $alheio = Contrato::factory()->create();

        $this->actingAs($this->usuario)->put("/contratos/{$contrato->id}", $this->dados(['ativo' => false]))
            ->assertRedirect('/contratos');
        $this->assertFalse($contrato->fresh()->ativo);

        $this->get("/contratos/{$alheio->id}/edit")->assertNotFound();
        $this->put("/contratos/{$alheio->id}", $this->dados())->assertNotFound();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=ContratosTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: migração, model, factory**

`database/migrations/2026_10_09_100200_create_contratos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->text('descricao');
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->unsignedBigInteger('valor_centavos');
            $table->string('periodicidade', 20);
            $table->unsignedTinyInteger('dia_vencimento');
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
```

`app/Models/Contrato.php`:

```php
<?php

namespace App\Models;

use App\Enums\Periodicidade;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cliente_id', 'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
    'valor_centavos', 'periodicidade', 'dia_vencimento', 'data_inicio', 'data_fim', 'ativo',
])]
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'periodicidade' => Periodicidade::class,
            'dia_vencimento' => 'integer',
            'data_inicio' => 'immutable_date',
            'data_fim' => 'immutable_date',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
```

`database/factories/ContratoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\Periodicidade;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cliente_id' => fn (array $atributos) => Cliente::factory()->create(['user_id' => $atributos['user_id']])->id,
            'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos",
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '115061000',
            'codigo_tributacao_nacional' => '01.02.01',
            'valor_centavos' => 26060,
            'periodicidade' => Periodicidade::Mensal,
            'dia_vencimento' => 21,
            'data_inicio' => '2026-09-21',
            'data_fim' => null,
            'ativo' => true,
        ];
    }
}
```

- [ ] **Passo 4: regra, request, controller**

`app/Rules/LinhasMaximas.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Limita linhas para o texto caber na caixa de descrição do recibo A4. */
final class LinhasMaximas implements ValidationRule
{
    public function __construct(private int $maximo) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (count(preg_split('/\R/', rtrim((string) $value))) > $this->maximo) {
            $fail("Use no máximo {$this->maximo} linhas.");
        }
    }
}
```

`app/Http/Requests/ContratoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\Periodicidade;
use App\Rules\LinhasMaximas;
use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['required', 'string', 'max:2000', new LinhasMaximas(25)],
            'codigo_servico' => ['nullable', 'string', 'max:255'],
            'codigo_nbs' => ['nullable', 'string', 'max:20'],
            'codigo_tributacao_nacional' => ['nullable', 'string', 'max:20'],
            'valor' => ['required', new ValorEmReais],
            'periodicidade' => ['required', Rule::enum(Periodicidade::class)],
            'dia_vencimento' => ['required', 'integer', 'between:1,31'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'ativo' => ['boolean'],
        ];
    }

    public function dadosContrato(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['valor']) + [
            'valor_centavos' => Dinheiro::paraCentavos($dados['valor']),
            'ativo' => $this->boolean('ativo', true),
        ];
    }
}
```

`app/Http/Controllers/ContratoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\Periodicidade;
use App\Http\Requests\ContratoRequest;
use App\Models\Contrato;
use App\Support\Dinheiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContratoController extends Controller
{
    public function index(Request $request): Response
    {
        $contratos = $request->user()->contratos()->with('cliente:id,nome')
            ->orderByDesc('ativo')->orderBy('data_inicio')->get()
            ->map(fn (Contrato $contrato) => [
                'id' => $contrato->id,
                'cliente' => $contrato->cliente->nome,
                'resumo' => strtok($contrato->descricao, "\n"),
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->rotulo(),
                'dia_vencimento' => $contrato->dia_vencimento,
                'ativo' => $contrato->ativo,
            ]);

        return Inertia::render('Contratos/Index', ['contratos' => $contratos]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function store(ContratoRequest $request): RedirectResponse
    {
        $request->user()->contratos()->create($request->dadosContrato());

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As cobranças aparecem no painel quando chegar a hora.');
    }

    public function edit(Request $request, int $contrato): Response
    {
        return $this->formulario($request, $request->user()->contratos()->findOrFail($contrato));
    }

    public function update(ContratoRequest $request, int $contrato): RedirectResponse
    {
        $request->user()->contratos()->findOrFail($contrato)->update($request->dadosContrato());

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }

    private function formulario(Request $request, ?Contrato $contrato): Response
    {
        return Inertia::render('Contratos/Form', [
            'contrato' => $contrato ? [
                'id' => $contrato->id,
                'cliente_id' => $contrato->cliente_id,
                'descricao' => $contrato->descricao,
                'codigo_servico' => $contrato->codigo_servico ?? '',
                'codigo_nbs' => $contrato->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $contrato->codigo_tributacao_nacional ?? '',
                'valor' => Dinheiro::formatar($contrato->valor_centavos),
                'periodicidade' => $contrato->periodicidade->value,
                'dia_vencimento' => $contrato->dia_vencimento,
                'data_inicio' => $contrato->data_inicio->toDateString(),
                'data_fim' => $contrato->data_fim?->toDateString() ?? '',
                'ativo' => $contrato->ativo,
            ] : null,
            'clientes' => $request->user()->clientes()->orderBy('nome')->get(['id', 'nome']),
            'periodicidades' => Periodicidade::opcoes(),
        ]);
    }
}
```

- [ ] **Passo 5: rotas**

Em `routes/web.php`, no grupo autenticado (com `use App\Http\Controllers\ContratoController;`):

```php
    Route::resource('contratos', ContratoController::class)->except(['show', 'destroy']);
```

- [ ] **Passo 6: páginas**

`resources/js/Pages/Contratos/Index.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ contratos: { type: Array, required: true } });
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Contratos</h1>
            <Link href="/contratos/create" class="botao">Novo contrato</Link>
        </div>

        <p v-if="!contratos.length" class="border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Nenhum contrato ainda. Com um contrato, a cobrança e o recibo de cada período são gerados sozinhos.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="contrato in contratos" :key="contrato.id" :class="{ 'opacity-60': !contrato.ativo }">
                <Link :href="`/contratos/${contrato.id}/edit`" class="grid gap-1 px-4 py-3 hover:bg-papel sm:grid-cols-[1fr_auto]">
                    <span class="font-semibold">{{ contrato.cliente }}</span>
                    <span class="tabular-nums sm:text-right">R$ {{ contrato.valor }}</span>
                    <span class="text-sm text-grafite/80">{{ contrato.resumo }}</span>
                    <span class="font-estreita text-xs text-grafite/70 sm:text-right">
                        {{ contrato.periodicidade }}, vence dia {{ contrato.dia_vencimento }}{{ contrato.ativo ? '' : ' — encerrado' }}
                    </span>
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
```

`resources/js/Pages/Contratos/Form.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    contrato: { type: Object, default: null },
    clientes: { type: Array, required: true },
    periodicidades: { type: Array, required: true },
});

const form = useForm(props.contrato ?? {
    cliente_id: props.clientes[0]?.id ?? '',
    descricao: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    valor: '',
    periodicidade: 'mensal',
    dia_vencimento: 10,
    data_inicio: new Date().toLocaleDateString('sv-SE'),
    data_fim: '',
    ativo: true,
});

const salvar = () => (props.contrato ? form.put(`/contratos/${props.contrato.id}`) : form.post('/contratos'));
</script>

<template>
    <CamadaApp>
        <Link href="/contratos" class="font-estreita text-sm text-carbono underline">Voltar para contratos</Link>
        <h1 class="mt-2 mb-6 text-2xl font-semibold">{{ contrato ? 'Editar contrato' : 'Novo contrato' }}</h1>

        <p v-if="!clientes.length" class="border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>

        <form v-else class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <select id="cliente_id" v-model="form.cliente_id" class="entrada" required>
                    <option v-for="cliente in clientes" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
                </select>
            </Campo>
            <Campo id="descricao" rotulo="Descrição dos serviços (como sai no recibo, até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="6" class="entrada" required />
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
            <Campo id="valor" rotulo="Valor (R$)" :erro="form.errors.valor" class="sm:col-span-2">
                <input id="valor" v-model="form.valor" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="periodicidade" rotulo="Periodicidade" :erro="form.errors.periodicidade" class="sm:col-span-2">
                <select id="periodicidade" v-model="form.periodicidade" class="entrada">
                    <option v-for="p in periodicidades" :key="p.valor" :value="p.valor">{{ p.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="dia_vencimento" rotulo="Dia do vencimento" :erro="form.errors.dia_vencimento" class="sm:col-span-2">
                <input id="dia_vencimento" v-model="form.dia_vencimento" type="number" min="1" max="31" class="entrada" required>
            </Campo>
            <Campo id="data_inicio" rotulo="Início do primeiro período" :erro="form.errors.data_inicio" class="sm:col-span-3">
                <input id="data_inicio" v-model="form.data_inicio" type="date" class="entrada" required>
            </Campo>
            <Campo id="data_fim" rotulo="Fim do contrato (opcional)" :erro="form.errors.data_fim" class="sm:col-span-3">
                <input id="data_fim" v-model="form.data_fim" type="date" class="entrada">
            </Campo>
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

- [ ] **Passo 7: rodar e ver passar**

Run: `php artisan test --filter=ContratosTest`
Esperado: PASS (6 testes).

- [ ] **Passo 8: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: contratos recorrentes com validação de datas e descrição`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.4: Tabelas e models de cobranças e recibos + proteções

**Estado:** pendente

**Arquivos:**
- Criar: `database/migrations/2026_10_09_100300_create_cobrancas_table.php`, `database/migrations/2026_10_09_100400_create_recibos_table.php`, `app/Enums/StatusCobranca.php`, `app/Models/Cobranca.php`, `app/Models/Recibo.php`, `database/factories/CobrancaFactory.php`
- Modificar: `app/Http/Controllers/ClienteController.php` (`destroy`), `app/Http/Controllers/ContratoController.php` (`update`)
- Teste: `tests/Feature/Cobrancas/SituacaoCobrancaTest.php`, `tests/Feature/Cobrancas/ProtecoesTest.php`

**Interfaces:**
- Consome: `Dinheiro` (T3.1), `ModeloRecibo` (T1.1)
- Produz:
  - `enum StatusCobranca: string { Pendente='pendente'; Paga='paga'; Cancelada='cancelada' }`
  - `Cobranca::situacao(CarbonImmutable $hoje): string` → `'a_vencer' | 'vencida' | 'paga' | 'cancelada'`
  - `Cobranca::editavel(): bool` (pendente e não enviada)
  - `Cobranca::paraLista(CarbonImmutable $hoje): array` com chaves `id, cliente, vencimento, periodo, valor, situacao, pago_em, enviado, editavel, recibo{numero, pdf_url, pdf_nome}|null`
  - Relações `Cobranca::user()`, `cliente()`, `contrato()`, `recibo()` (hasOne)
  - `Recibo::numeroFormatado(): string` (`2026/000016`), `Recibo::nomeArquivo(): string` (`recibo-2026-000016-aplb-sindicato.pdf`), relação `cobranca()`
  - `CobrancaFactory` (pendente, sem contrato, vencimento 2026-10-10, R$ 260,60)

- [ ] **Passo 1: escrever os testes que falham** (foco de revisão 5)

`tests/Feature/Cobrancas/SituacaoCobrancaTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SituacaoCobrancaTest extends TestCase
{
    use RefreshDatabase;

    public function test_situacao_depende_do_status_e_do_vencimento(): void
    {
        $hoje = CarbonImmutable::parse('2026-10-10');
        $cobranca = Cobranca::factory()->create(['vencimento' => '2026-10-10']);

        $this->assertSame('a_vencer', $cobranca->situacao($hoje), 'vence hoje ainda não está vencida');
        $this->assertSame('vencida', $cobranca->situacao($hoje->addDay()));

        $cobranca->status = StatusCobranca::Paga;
        $this->assertSame('paga', $cobranca->situacao($hoje->addMonth()));

        $cobranca->status = StatusCobranca::Cancelada;
        $this->assertSame('cancelada', $cobranca->situacao($hoje));
    }

    public function test_editavel_so_pendente_e_nao_enviada(): void
    {
        $cobranca = Cobranca::factory()->create();
        $this->assertTrue($cobranca->editavel());

        $cobranca->enviado_em = now();
        $this->assertFalse($cobranca->editavel());
    }
}
```

`tests/Feature/Cobrancas/ProtecoesTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtecoesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cliente_com_contrato_ou_cobranca_nao_e_excluido(): void
    {
        $usuario = User::factory()->create();
        $comContrato = Cliente::factory()->for($usuario)->create();
        Contrato::factory()->for($usuario)->for($comContrato)->create();
        $comCobranca = Cliente::factory()->for($usuario)->create();
        Cobranca::factory()->for($usuario)->for($comCobranca)->create();

        $this->actingAs($usuario);
        foreach ([$comContrato, $comCobranca] as $cliente) {
            $this->delete("/clientes/{$cliente->id}")
                ->assertSessionHas('erro', 'Este cliente tem contratos ou cobranças e não pode ser excluído.');
            $this->assertModelExists($cliente);
        }
    }

    public function test_contrato_com_cobranca_nao_muda_inicio_nem_periodicidade(): void
    {
        $usuario = User::factory()->create();
        $contrato = Contrato::factory()->for($usuario)->create();
        Cobranca::factory()->for($usuario)->create([
            'cliente_id' => $contrato->cliente_id,
            'contrato_id' => $contrato->id,
            'periodo_inicio' => '2026-09-21',
        ]);
        $dados = [
            'cliente_id' => $contrato->cliente_id, 'descricao' => 'Hospedagem', 'valor' => '300,00',
            'periodicidade' => 'mensal', 'dia_vencimento' => 21, 'data_inicio' => '2026-09-21', 'ativo' => true,
        ];

        $this->actingAs($usuario);
        $this->put("/contratos/{$contrato->id}", ['data_inicio' => '2026-10-01'] + $dados)
            ->assertSessionHasErrors(['data_inicio' => 'Este contrato já tem cobranças. Para mudar o início ou a periodicidade, encerre-o e crie outro.']);
        $this->put("/contratos/{$contrato->id}", ['periodicidade' => 'anual'] + $dados)
            ->assertSessionHasErrors('data_inicio');

        $this->put("/contratos/{$contrato->id}", $dados)->assertSessionHasNoErrors();
        $this->assertSame(30000, $contrato->fresh()->valor_centavos, 'valor pode mudar: vale para as próximas');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter="SituacaoCobrancaTest|ProtecoesTest"`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: migrações**

`database/migrations/2026_10_09_100300_create_cobrancas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained()->restrictOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('periodo_inicio');
            $table->date('periodo_fim');
            $table->date('vencimento');
            $table->unsignedBigInteger('valor_centavos');
            $table->text('descricao');
            $table->string('codigo_servico')->nullable();
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('codigo_tributacao_nacional', 20)->nullable();
            $table->string('status', 20)->default('pendente')->index();
            $table->date('pago_em')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            // Idempotência da geração: um período por contrato. Avulsas (contrato nulo) não colidem.
            $table->unique(['contrato_id', 'periodo_inicio']);
            $table->index(['user_id', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas');
    }
};
```

`database/migrations/2026_10_09_100400_create_recibos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cobranca_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->unsignedInteger('numero');
            $table->string('modelo', 20);
            $table->timestamp('emitido_em');
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['user_id', 'ano', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recibos');
    }
};
```

- [ ] **Passo 4: enum, models, factory**

`app/Enums/StatusCobranca.php`:

```php
<?php

namespace App\Enums;

enum StatusCobranca: string
{
    case Pendente = 'pendente';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
}
```

`app/Models/Cobranca.php`:

```php
<?php

namespace App\Models;

use App\Enums\StatusCobranca;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Database\Factories\CobrancaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'cliente_id', 'contrato_id', 'periodo_inicio', 'periodo_fim', 'vencimento', 'valor_centavos',
    'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
])]
class Cobranca extends Model
{
    /** @use HasFactory<CobrancaFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'periodo_inicio' => 'immutable_date',
            'periodo_fim' => 'immutable_date',
            'vencimento' => 'immutable_date',
            'pago_em' => 'immutable_date',
            'enviado_em' => 'immutable_datetime',
            'status' => StatusCobranca::class,
            'valor_centavos' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function recibo(): HasOne
    {
        return $this->hasOne(Recibo::class);
    }

    public function situacao(CarbonImmutable $hoje): string
    {
        return match ($this->status) {
            StatusCobranca::Paga => 'paga',
            StatusCobranca::Cancelada => 'cancelada',
            StatusCobranca::Pendente => $this->vencimento->lt($hoje->startOfDay()) ? 'vencida' : 'a_vencer',
        };
    }

    public function editavel(): bool
    {
        return $this->status === StatusCobranca::Pendente && $this->enviado_em === null;
    }

    /** Linha usada no painel e na lista de cobranças. */
    public function paraLista(CarbonImmutable $hoje): array
    {
        return [
            'id' => $this->id,
            'cliente' => $this->cliente->nome,
            'vencimento' => $this->vencimento->format('d/m/Y'),
            'periodo' => $this->periodo_inicio->format('d/m/Y').' a '.$this->periodo_fim->format('d/m/Y'),
            'valor' => Dinheiro::formatar($this->valor_centavos),
            'situacao' => $this->situacao($hoje),
            'pago_em' => $this->pago_em?->format('d/m/Y'),
            'enviado' => $this->enviado_em !== null,
            'editavel' => $this->editavel(),
            'recibo' => $this->recibo ? [
                'numero' => $this->recibo->numeroFormatado(),
                'pdf_url' => "/recibos/{$this->recibo->id}/pdf",
                'pdf_nome' => $this->recibo->nomeArquivo(),
            ] : null,
        ];
    }
}
```

`app/Models/Recibo.php`:

```php
<?php

namespace App\Models;

use App\Enums\ModeloRecibo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'ano', 'numero', 'modelo', 'emitido_em', 'snapshot'])]
class Recibo extends Model
{
    protected function casts(): array
    {
        return [
            'ano' => 'integer',
            'numero' => 'integer',
            'modelo' => ModeloRecibo::class,
            'emitido_em' => 'immutable_datetime',
            'snapshot' => 'array',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }

    public function numeroFormatado(): string
    {
        return sprintf('%d/%06d', $this->ano, $this->numero);
    }

    public function nomeArquivo(): string
    {
        return sprintf('recibo-%d-%06d-%s.pdf', $this->ano, $this->numero, Str::slug($this->snapshot['tomador']['nome'] ?? 'cliente'));
    }
}
```

`database/factories/CobrancaFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cobranca>
 */
class CobrancaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cliente_id' => fn (array $atributos) => Cliente::factory()->create(['user_id' => $atributos['user_id']])->id,
            'contrato_id' => null,
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-10',
            'valor_centavos' => 26060,
            'descricao' => 'Serviço avulso',
        ];
    }
}
```

- [ ] **Passo 5: proteções nos controllers**

`ClienteController::destroy` passa a ser:

```php
    public function destroy(Request $request, int $cliente): RedirectResponse
    {
        $registro = $request->user()->clientes()->findOrFail($cliente);

        if ($registro->contratos()->exists() || $registro->cobrancas()->exists()) {
            return back()->with('erro', 'Este cliente tem contratos ou cobranças e não pode ser excluído.');
        }

        $registro->delete();

        return redirect('/clientes')->with('sucesso', 'Cliente excluído.');
    }
```

`ContratoController::update` passa a ser:

```php
    public function update(ContratoRequest $request, int $contrato): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        $dados = $request->dadosContrato();

        $mudaPeriodos = $registro->periodicidade->value !== $dados['periodicidade']
            || $registro->data_inicio->toDateString() !== CarbonImmutable::parse($dados['data_inicio'])->toDateString();

        // Mudar a âncora dos períodos geraria períodos novos sobrepostos aos já cobrados.
        if ($mudaPeriodos && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'data_inicio' => 'Este contrato já tem cobranças. Para mudar o início ou a periodicidade, encerre-o e crie outro.',
            ]);
        }

        $registro->update($dados);

        return redirect('/contratos')->with('sucesso', 'Contrato salvo. As mudanças valem para as próximas cobranças.');
    }
```

com `use Carbon\CarbonImmutable;` no topo do `ContratoController`.

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --filter="SituacaoCobrancaTest|ProtecoesTest|ClientesTest|ContratosTest"`
Esperado: PASS (inclusive os testes das tarefas anteriores).

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: tabelas de cobranças e recibos e proteções de exclusão e edição`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.5: Numeração e emissão de recibos (snapshot)

**Estado:** pendente

**Arquivos:**
- Criar: `app/Services/NumeradorDeRecibos.php`, `app/Services/EmissorDeRecibos.php`
- Teste: `tests/Feature/Recibos/EmissaoReciboTest.php`

**Interfaces:**
- Consome: `Cobranca`, `Recibo` (T3.4), `Cliente::CAMPOS` (T2.3), `FamiliaModelo::versaoAtual()` (T1.1)
- Produz:
  - `NumeradorDeRecibos::proximo(int $userId, int $ano): int`
  - `EmissorDeRecibos::emitir(Cobranca $cobranca): Recibo` — numera, grava modelo atual do cliente e snapshot
  - `EmissorDeRecibos::refazer(Recibo $recibo): void` — refaz só o snapshot (mesmo número e modelo)
  - `EmissorDeRecibos::snapshot(Cobranca $cobranca): array` com chaves `prestador` (`nome`, `email`, `logo` e os campos de prestador), `tomador` (`Cliente::CAMPOS`), `servico` (`descricao`, `codigo_servico`, `codigo_nbs`, `codigo_tributacao_nacional`, `valor_centavos`, `periodo_inicio`, `periodo_fim`, `vencimento` — datas `Y-m-d`)

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Recibos/EmissaoReciboTest.php`:

```php
<?php

namespace Tests\Feature\Recibos;

use App\Enums\FamiliaModelo;
use App\Enums\ModeloRecibo;
use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmissaoReciboTest extends TestCase
{
    use RefreshDatabase;

    private EmissorDeRecibos $emissor;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-09 10:00:00');
        $this->emissor = app(EmissorDeRecibos::class);
        $this->usuario = User::factory()->create(['logradouro' => 'Rua Antiga']);
        $this->cliente = Cliente::factory()->for($this->usuario)->create(['nome' => 'APLB Sindicato']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function cobranca(array $extra = []): Cobranca
    {
        return Cobranca::factory()->for($this->usuario)->for($this->cliente)->create($extra);
    }

    public function test_numera_em_sequencia_por_usuario(): void
    {
        $r1 = $this->emissor->emitir($this->cobranca());
        $r2 = $this->emissor->emitir($this->cobranca());
        $deOutro = $this->emissor->emitir(Cobranca::factory()->create());

        $this->assertSame([2026, 1], [$r1->ano, $r1->numero]);
        $this->assertSame(2, $r2->numero);
        $this->assertSame(1, $deOutro->numero);
        $this->assertSame('2026/000002', $r2->numeroFormatado());
        $this->assertSame('recibo-2026-000002-aplb-sindicato.pdf', $r2->nomeArquivo());
    }

    public function test_numeracao_reinicia_no_ano_novo(): void
    {
        $this->emissor->emitir($this->cobranca());
        CarbonImmutable::setTestNow('2027-01-01 08:00:00');

        $recibo = $this->emissor->emitir($this->cobranca());

        $this->assertSame([2027, 1], [$recibo->ano, $recibo->numero]);
    }

    public function test_numero_de_cobranca_cancelada_nao_volta(): void
    {
        $cancelada = $this->cobranca();
        $this->emissor->emitir($cancelada);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();

        $this->assertSame(2, $this->emissor->emitir($this->cobranca())->numero);
    }

    public function test_grava_a_versao_atual_do_modelo_do_cliente(): void
    {
        $webiss = $this->emissor->emitir($this->cobranca());
        $this->cliente->update(['modelo' => FamiliaModelo::Danfse]);
        $danfse = $this->emissor->emitir($this->cobranca());

        $this->assertSame(ModeloRecibo::WebissV1, $webiss->fresh()->modelo, 'troca de modelo não altera recibo antigo');
        $this->assertSame(ModeloRecibo::DanfseV1, $danfse->modelo);
    }

    public function test_snapshot_fica_congelado(): void
    {
        $recibo = $this->emissor->emitir($this->cobranca(['valor_centavos' => 26060]));

        $this->usuario->update(['logradouro' => 'Rua Nova']);
        $this->cliente->update(['nome' => 'Outro Nome']);

        $snapshot = $recibo->fresh()->snapshot;
        $this->assertSame('Rua Antiga', $snapshot['prestador']['logradouro']);
        $this->assertSame('APLB Sindicato', $snapshot['tomador']['nome']);
        $this->assertSame(26060, $snapshot['servico']['valor_centavos']);
        $this->assertSame('2026-10-10', $snapshot['servico']['vencimento']);
    }

    public function test_refazer_mantem_numero_e_modelo_e_atualiza_dados(): void
    {
        $cobranca = $this->cobranca();
        $recibo = $this->emissor->emitir($cobranca);
        $cobranca->update(['valor_centavos' => 30000]);
        $this->cliente->update(['modelo' => FamiliaModelo::Danfse]);

        $this->emissor->refazer($recibo->fresh());

        $recibo->refresh();
        $this->assertSame(1, $recibo->numero);
        $this->assertSame(ModeloRecibo::WebissV1, $recibo->modelo);
        $this->assertSame(30000, $recibo->snapshot['servico']['valor_centavos']);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=EmissaoReciboTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Services/NumeradorDeRecibos.php`:

```php
<?php

namespace App\Services;

use App\Models\Recibo;

final class NumeradorDeRecibos
{
    /** Chamar dentro da transação da emissão; o índice único (user_id, ano, numero) é a garantia final. */
    public function proximo(int $userId, int $ano): int
    {
        return (int) Recibo::where('user_id', $userId)->where('ano', $ano)->max('numero') + 1;
    }
}
```

`app/Services/EmissorDeRecibos.php`:

```php
<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Recibo;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class EmissorDeRecibos
{
    private const CAMPOS_DO_PRESTADOR = [
        'documento', 'nome_fantasia', 'inscricao_municipal', 'inscricao_estadual', 'telefone',
        'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep', 'logo',
    ];

    private const TENTATIVAS = 3;

    public function __construct(private NumeradorDeRecibos $numerador) {}

    public function emitir(Cobranca $cobranca): Recibo
    {
        $agora = CarbonImmutable::now();

        for ($tentativa = 1; ; $tentativa++) {
            try {
                return DB::transaction(fn () => $cobranca->recibo()->create([
                    'user_id' => $cobranca->user_id,
                    'ano' => $agora->year,
                    'numero' => $this->numerador->proximo($cobranca->user_id, $agora->year),
                    'modelo' => $cobranca->cliente->modelo->versaoAtual(),
                    'emitido_em' => $agora,
                    'snapshot' => $this->snapshot($cobranca),
                ]));
            } catch (UniqueConstraintViolationException $erro) {
                // Duas emissões simultâneas pegaram o mesmo número (possível no PostgreSQL): tenta o próximo.
                if ($tentativa >= self::TENTATIVAS) {
                    throw $erro;
                }
            }
        }
    }

    /** Cobrança ainda não enviada foi editada: mesmo número, mesmo layout, dados novos. */
    public function refazer(Recibo $recibo): void
    {
        $recibo->update(['snapshot' => $this->snapshot($recibo->cobranca()->firstOrFail())]);
    }

    public function snapshot(Cobranca $cobranca): array
    {
        $prestador = $cobranca->user()->firstOrFail();
        $tomador = $cobranca->cliente()->firstOrFail();

        return [
            'prestador' => ['nome' => $prestador->name, 'email' => $prestador->email]
                + $prestador->only(self::CAMPOS_DO_PRESTADOR),
            'tomador' => $tomador->only(Cliente::CAMPOS),
            'servico' => [
                'descricao' => $cobranca->descricao,
                'codigo_servico' => $cobranca->codigo_servico,
                'codigo_nbs' => $cobranca->codigo_nbs,
                'codigo_tributacao_nacional' => $cobranca->codigo_tributacao_nacional,
                'valor_centavos' => $cobranca->valor_centavos,
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
            ],
        ];
    }
}
```

(`user()->firstOrFail()`/`cliente()->firstOrFail()` buscam do banco, e não da relação em cache, para o snapshot refletir os dados atuais em `refazer`.)

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=EmissaoReciboTest`
Esperado: PASS (6 testes).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Services/NumeradorDeRecibos.php app/Services/EmissorDeRecibos.php tests/Feature/Recibos/EmissaoReciboTest.php
git commit -m "feat: emissão de recibo com numeração anual e snapshot congelado`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.6: Gerador de cobranças recorrentes

**Estado:** pendente

**Arquivos:**
- Criar: `app/Services/GeradorDeCobrancas.php`
- Teste: `tests/Feature/Cobrancas/GeradorDeCobrancasTest.php`

**Interfaces:**
- Consome: `CalculadoraDePeriodos::devidos` (T3.2), `EmissorDeRecibos::emitir` (T3.5), `User::perfilCompleto` (T1.1)
- Produz: `GeradorDeCobrancas::gerarPara(User $usuario, CarbonImmutable $hoje): int` (quantas criou). Ordem de criação: por vencimento, depois id do contrato — define a numeração dos recibos.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Cobrancas/GeradorDeCobrancasTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Contrato;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeradorDeCobrancasTest extends TestCase
{
    use RefreshDatabase;

    private GeradorDeCobrancas $gerador;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerador = app(GeradorDeCobrancas::class);
        $this->usuario = User::factory()->create(['dias_antecedencia' => 10]);
    }

    private function contrato(array $extra = []): Contrato
    {
        return Contrato::factory()->for($this->usuario)->create($extra);
    }

    private function gerar(string $hoje): int
    {
        return $this->gerador->gerarPara($this->usuario, CarbonImmutable::parse($hoje));
    }

    public function test_gera_cobranca_com_recibo_copiando_o_contrato(): void
    {
        $contrato = $this->contrato();

        $this->assertSame(1, $this->gerar('2026-10-09'));

        $cobranca = $contrato->cobrancas()->with('recibo')->firstOrFail();
        $this->assertSame('2026-09-21', $cobranca->periodo_inicio->toDateString());
        $this->assertSame('2026-10-20', $cobranca->periodo_fim->toDateString());
        $this->assertSame('2026-09-21', $cobranca->vencimento->toDateString());
        $this->assertSame(26060, $cobranca->valor_centavos);
        $this->assertSame($contrato->descricao, $cobranca->descricao);
        $this->assertSame('0102 - Programação', $cobranca->codigo_servico);
        $this->assertNotNull($cobranca->recibo);
    }

    public function test_rodar_de_novo_nao_duplica(): void
    {
        $this->contrato();

        $this->gerar('2026-10-09');
        $this->assertSame(0, $this->gerar('2026-10-09'));
        $this->assertSame(1, $this->usuario->cobrancas()->count());
    }

    public function test_respeita_a_antecedencia(): void
    {
        $this->contrato();

        $this->assertSame(2, $this->gerar('2026-10-11'));
    }

    public function test_recupera_periodos_atrasados_em_ordem(): void
    {
        $this->contrato();

        $this->assertSame(5, $this->gerar('2027-01-15'));

        $numerosPorVencimento = $this->usuario->cobrancas()->with('recibo')->orderBy('vencimento')->get()
            ->map(fn ($cobranca) => $cobranca->recibo->numero)->all();
        $this->assertSame([1, 2, 3, 4, 5], $numerosPorVencimento);
    }

    public function test_perfil_incompleto_suspende_a_geracao(): void
    {
        $this->usuario->update(['documento' => null]);
        $this->contrato();

        $this->assertSame(0, $this->gerar('2026-10-09'));
    }

    public function test_contrato_inativo_ou_encerrado(): void
    {
        $this->contrato(['ativo' => false]);
        $this->assertSame(0, $this->gerar('2026-10-09'));

        $this->contrato(['data_fim' => '2026-10-31']);
        $this->assertSame(2, $this->gerar('2027-03-01'));
    }

    public function test_varios_contratos_numerados_por_vencimento(): void
    {
        $tardio = $this->contrato(['data_inicio' => '2026-10-05', 'dia_vencimento' => 5]);
        $cedo = $this->contrato(['data_inicio' => '2026-09-21', 'dia_vencimento' => 21]);

        $this->gerar('2026-10-09');

        $this->assertSame(1, $cedo->cobrancas()->firstOrFail()->recibo->numero);
        $this->assertSame(2, $tardio->cobrancas()->firstOrFail()->recibo->numero);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=GeradorDeCobrancasTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Services/GeradorDeCobrancas.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GeradorDeCobrancas
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private EmissorDeRecibos $emissor,
    ) {}

    /** Cria cobrança + recibo de todo período devido que ainda não existe. Retorna quantas criou. */
    public function gerarPara(User $usuario, CarbonImmutable $hoje): int
    {
        if (! $usuario->perfilCompleto()) {
            return 0;
        }

        $aGerar = [];

        foreach ($usuario->contratos()->where('ativo', true)->get() as $contrato) {
            $existentes = $contrato->cobrancas()->pluck('periodo_inicio')
                ->map(fn ($data) => CarbonImmutable::parse($data)->toDateString())
                ->all();

            $devidos = $this->calculadora->devidos(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $usuario->dias_antecedencia,
                $hoje,
            );

            foreach ($devidos as $periodo) {
                if (! in_array($periodo->inicio->toDateString(), $existentes, true)) {
                    $aGerar[] = [$contrato, $periodo];
                }
            }
        }

        // Numeração dos recibos acompanha a ordem dos vencimentos.
        usort($aGerar, fn (array $a, array $b) => [$a[1]->vencimento, $a[0]->id] <=> [$b[1]->vencimento, $b[0]->id]);

        $criadas = 0;

        foreach ($aGerar as [$contrato, $periodo]) {
            try {
                DB::transaction(function () use ($usuario, $contrato, $periodo) {
                    $cobranca = $usuario->cobrancas()->create([
                        'cliente_id' => $contrato->cliente_id,
                        'contrato_id' => $contrato->id,
                        'periodo_inicio' => $periodo->inicio,
                        'periodo_fim' => $periodo->fim,
                        'vencimento' => $periodo->vencimento,
                        'valor_centavos' => $contrato->valor_centavos,
                        'descricao' => $contrato->descricao,
                        'codigo_servico' => $contrato->codigo_servico,
                        'codigo_nbs' => $contrato->codigo_nbs,
                        'codigo_tributacao_nacional' => $contrato->codigo_tributacao_nacional,
                    ]);

                    $this->emissor->emitir($cobranca);
                });
                $criadas++;
            } catch (UniqueConstraintViolationException) {
                // Outra aba gerou o mesmo período ao mesmo tempo: ele já existe; segue para o próximo.
            }
        }

        return $criadas;
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=GeradorDeCobrancasTest`
Esperado: PASS (7 testes).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Services/GeradorDeCobrancas.php tests/Feature/Cobrancas/GeradorDeCobrancasTest.php
git commit -m "feat: geração idempotente de cobranças recorrentes com recibo`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.7: Ações de cobrança (avulsa, editar, cancelar, baixa, enviada)

**Estado:** pendente

**Arquivos:**
- Criar: `app/Http/Requests/CobrancaRequest.php`, `app/Http/Controllers/CobrancaController.php`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/Cobrancas/AcoesCobrancaTest.php`

**Interfaces:**
- Consome: `EmissorDeRecibos` (T3.5), `ValorEmReais`, `Dinheiro` (T3.1), `LinhasMaximas` (T3.3), `Cobranca::editavel()` (T3.4)
- Produz: `POST /cobrancas`, `PUT /cobrancas/{cobranca}`, `POST /cobrancas/{cobranca}/cancelar`, `POST /cobrancas/{cobranca}/pagar` (campo `pago_em`), `POST /cobrancas/{cobranca}/enviada`; `CobrancaRequest::dadosCobranca(): array`. Os GETs (lista e formulários) chegam na T5.2.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Cobrancas/AcoesCobrancaTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcoesCobrancaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-09 10:00:00');
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id,
            'descricao' => 'Manutenção extra do site',
            'codigo_servico' => '0102 - Programação',
            'codigo_nbs' => '',
            'codigo_tributacao_nacional' => '',
            'valor' => '150,00',
            'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31',
            'vencimento' => '2026-10-20',
        ], $extra);
    }

    private function cobrancaComRecibo(array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create($extra);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca->fresh('recibo');
    }

    public function test_avulsa_nasce_com_recibo(): void
    {
        $this->actingAs($this->usuario)->post('/cobrancas', $this->dados())->assertRedirect('/cobrancas');

        $cobranca = $this->usuario->cobrancas()->with('recibo')->firstOrFail();
        $this->assertNull($cobranca->contrato_id);
        $this->assertSame(15000, $cobranca->valor_centavos);
        $this->assertSame(1, $cobranca->recibo->numero);
    }

    public function test_avulsa_exige_perfil_completo_e_cliente_proprio(): void
    {
        $this->actingAs($this->usuario);
        $this->post('/cobrancas', $this->dados(['cliente_id' => Cliente::factory()->create()->id]))
            ->assertSessionHasErrors('cliente_id');

        $this->usuario->update(['cep' => null]);
        $this->post('/cobrancas', $this->dados())->assertSessionHas('erro', 'Complete seu perfil para emitir recibos.');
        $this->assertSame(0, Cobranca::count());
    }

    public function test_editar_nao_enviada_mantem_o_numero_do_recibo(): void
    {
        $cobranca = $this->cobrancaComRecibo();

        $this->actingAs($this->usuario)->put("/cobrancas/{$cobranca->id}", $this->dados(['valor' => '300,00']))
            ->assertRedirect('/cobrancas');

        $cobranca->refresh()->load('recibo');
        $this->assertSame(30000, $cobranca->valor_centavos);
        $this->assertSame(1, $cobranca->recibo->numero);
        $this->assertSame(30000, $cobranca->recibo->snapshot['servico']['valor_centavos']);
    }

    public function test_cobranca_enviada_nao_e_editada(): void
    {
        $cobranca = $this->cobrancaComRecibo();
        $cobranca->enviado_em = now();
        $cobranca->save();

        $this->actingAs($this->usuario)->put("/cobrancas/{$cobranca->id}", $this->dados(['valor' => '300,00']))
            ->assertSessionHas('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        $this->assertSame(26060, $cobranca->fresh()->valor_centavos);
    }

    public function test_cobranca_de_contrato_nao_muda_periodo_nem_cliente(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $cobranca = $this->cobrancaComRecibo(['contrato_id' => $contrato->id, 'periodo_inicio' => '2026-09-21', 'periodo_fim' => '2026-10-20']);

        $this->actingAs($this->usuario)->put("/cobrancas/{$cobranca->id}", $this->dados(['valor' => '300,00']));

        $cobranca->refresh();
        $this->assertSame('2026-09-21', $cobranca->periodo_inicio->toDateString());
        $this->assertSame(30000, $cobranca->valor_centavos);
    }

    public function test_cancelar_so_pendente(): void
    {
        $cobranca = $this->cobrancaComRecibo();
        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/cancelar")->assertSessionHas('sucesso');
        $this->assertSame(StatusCobranca::Cancelada, $cobranca->fresh()->status);

        $this->post("/cobrancas/{$cobranca->id}/cancelar")
            ->assertSessionHas('erro', 'Só cobranças pendentes podem ser canceladas.');
    }

    public function test_baixa_com_data_valida(): void
    {
        $cobranca = $this->cobrancaComRecibo();
        $this->actingAs($this->usuario);

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-10'])->assertSessionHasErrors('pago_em');

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09'])->assertSessionHas('sucesso', 'Baixa registrada.');
        $cobranca->refresh();
        $this->assertSame(StatusCobranca::Paga, $cobranca->status);
        $this->assertSame('2026-10-09', $cobranca->pago_em->toDateString());

        $this->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09'])
            ->assertSessionHas('erro', 'Só cobranças pendentes recebem baixa.');
    }

    public function test_marca_como_enviada(): void
    {
        $cobranca = $this->cobrancaComRecibo();

        $this->actingAs($this->usuario)->post("/cobrancas/{$cobranca->id}/enviada")->assertRedirect();

        $this->assertNotNull($cobranca->fresh()->enviado_em);
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        $alheia = Cobranca::factory()->create();
        $this->actingAs($this->usuario);

        $this->put("/cobrancas/{$alheia->id}", $this->dados())->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/cancelar")->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/pagar", ['pago_em' => '2026-10-09'])->assertNotFound();
        $this->post("/cobrancas/{$alheia->id}/enviada")->assertNotFound();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=AcoesCobrancaTest`
Esperado: FAIL com 404/405.

- [ ] **Passo 3: request**

`app/Http/Requests/CobrancaRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Rules\LinhasMaximas;
use App\Rules\ValorEmReais;
use App\Support\Dinheiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class CobrancaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('user_id', $this->user()->id)],
            'descricao' => ['required', 'string', 'max:2000', new LinhasMaximas(25)],
            'codigo_servico' => ['nullable', 'string', 'max:255'],
            'codigo_nbs' => ['nullable', 'string', 'max:20'],
            'codigo_tributacao_nacional' => ['nullable', 'string', 'max:20'],
            'valor' => ['required', new ValorEmReais],
            'periodo_inicio' => ['required', 'date'],
            'periodo_fim' => ['required', 'date', 'after_or_equal:periodo_inicio'],
            'vencimento' => ['required', 'date'],
        ];
    }

    public function dadosCobranca(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['valor']) + ['valor_centavos' => Dinheiro::paraCentavos($dados['valor'])];
    }
}
```

- [ ] **Passo 4: controller**

`app/Http/Controllers/CobrancaController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Http\Requests\CobrancaRequest;
use App\Services\EmissorDeRecibos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CobrancaController extends Controller
{
    public function store(CobrancaRequest $request, EmissorDeRecibos $emissor): RedirectResponse
    {
        $usuario = $request->user();

        if (! $usuario->perfilCompleto()) {
            return back()->with('erro', 'Complete seu perfil para emitir recibos.');
        }

        DB::transaction(function () use ($usuario, $request, $emissor) {
            $emissor->emitir($usuario->cobrancas()->create($request->dadosCobranca()));
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança criada com o recibo.');
    }

    public function update(CobrancaRequest $request, int $cobranca, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->with('recibo')->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return back()->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        $dados = $request->dadosCobranca();
        if ($registro->contrato_id) {
            // Período e cliente de cobrança de contrato são definidos pelo contrato.
            $dados = Arr::except($dados, ['cliente_id', 'periodo_inicio', 'periodo_fim']);
        }

        DB::transaction(function () use ($registro, $dados, $emissor) {
            $registro->update($dados);
            $emissor->refazer($registro->recibo);
        });

        return redirect('/cobrancas')->with('sucesso', 'Cobrança atualizada. O recibo manteve o número.');
    }

    public function cancelar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes podem ser canceladas.');
        }

        $registro->status = StatusCobranca::Cancelada;
        $registro->save();

        return back()->with('sucesso', 'Cobrança cancelada. O recibo passa a sair como cancelado.');
    }

    public function pagar(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $dados = $request->validate(['pago_em' => ['required', 'date', 'before_or_equal:today']]);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes recebem baixa.');
        }

        $registro->status = StatusCobranca::Paga;
        $registro->pago_em = $dados['pago_em'];
        $registro->save();

        return back()->with('sucesso', 'Baixa registrada.');
    }

    /** Chamado pelo botão Enviar depois que a folha de compartilhamento (ou o download) concluiu. */
    public function marcarEnviada(Request $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);
        $registro->enviado_em = now();
        $registro->save();

        return back();
    }
}
```

(A ordem `findOrFail` antes de `validate` em `pagar` garante 404 para cobrança alheia mesmo com dados inválidos.)

- [ ] **Passo 5: rotas**

Em `routes/web.php`, grupo autenticado (com `use App\Http\Controllers\CobrancaController;`):

```php
    Route::post('/cobrancas', [CobrancaController::class, 'store']);
    Route::put('/cobrancas/{cobranca}', [CobrancaController::class, 'update']);
    Route::post('/cobrancas/{cobranca}/cancelar', [CobrancaController::class, 'cancelar']);
    Route::post('/cobrancas/{cobranca}/pagar', [CobrancaController::class, 'pagar']);
    Route::post('/cobrancas/{cobranca}/enviada', [CobrancaController::class, 'marcarEnviada']);
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --filter=AcoesCobrancaTest`
Esperado: PASS (9 testes). Depois a suíte inteira: `php artisan test` → tudo passando.

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: cobrança avulsa, edição, cancelamento, baixa e registro de envio`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T3.8: PORTÃO-BANCO — migrações da fase 3

**Estado:** pendente

- [ ] **Passo 1:** PORTÃO-BANCO para `php artisan migrate` (tabelas `clientes`, `contratos`, `cobrancas`, `recibos`). Mostrar o `--pretend`. Esperar "sim".
- [ ] **Passo 2:** executar e mostrar `php artisan migrate:status`.
- [ ] **Passo 3:** registrar na skill os acertos e pegadinhas da fase 3; atualizar a tabela de acompanhamento.

---

## Fase 4 — Recibo em PDF

### Tarefa T4.1: Valor por extenso

**Estado:** pendente

**Arquivos:**
- Criar: `app/Services/ValorPorExtenso.php`
- Teste: `tests/Unit/ValorPorExtensoTest.php`

**Interfaces:** produz `ValorPorExtenso::converter(int $centavos): string`.

- [ ] **Passo 1: escrever o teste que falha**

`tests/Unit/ValorPorExtensoTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\ValorPorExtenso;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValorPorExtensoTest extends TestCase
{
    public static function casos(): array
    {
        return [
            'nota de referência' => [26060, 'duzentos e sessenta reais e sessenta centavos'],
            'zero' => [0, 'zero reais'],
            'um real' => [100, 'um real'],
            'um centavo' => [1, 'um centavo'],
            'cem' => [10000, 'cem reais'],
            'cento e um' => [10100, 'cento e um reais'],
            'mil' => [100000, 'mil reais'],
            'mil e um' => [100100, 'mil e um reais'],
            'mil e dez' => [101000, 'mil e dez reais'],
            'mil e duzentos' => [120000, 'mil e duzentos reais'],
            'mil duzentos e trinta' => [123000, 'mil duzentos e trinta reais'],
            'dois mil' => [200000, 'dois mil reais'],
            'dezenove' => [1900, 'dezenove reais'],
            'um milhão exato' => [100000000, 'um milhão de reais'],
            'dois milhões exatos' => [200000000, 'dois milhões de reais'],
            'milhão e meio' => [150000000, 'um milhão e quinhentos mil reais'],
            'milhão e cem' => [100010000, 'um milhão e cem reais'],
            'composto' => [123456789, 'um milhão duzentos e trinta e quatro mil quinhentos e sessenta e sete reais e oitenta e nove centavos'],
        ];
    }

    #[DataProvider('casos')]
    public function test_escreve_por_extenso(int $centavos, string $esperado): void
    {
        $this->assertSame($esperado, ValorPorExtenso::converter($centavos));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=ValorPorExtensoTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Services/ValorPorExtenso.php`:

```php
<?php

namespace App\Services;

final class ValorPorExtenso
{
    private const UNIDADES = [
        '', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove', 'dez',
        'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove',
    ];

    private const DEZENAS = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];

    private const CENTENAS = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

    /** [singular, plural] por classe: unidades, milhar, milhão, bilhão. */
    private const CLASSES = [['', ''], ['mil', 'mil'], ['milhão', 'milhões'], ['bilhão', 'bilhões']];

    public static function converter(int $centavos): string
    {
        if ($centavos === 0) {
            return 'zero reais';
        }

        $reais = intdiv($centavos, 100);
        $resto = $centavos % 100;
        $partes = [];

        if ($reais > 0) {
            $partes[] = self::inteiro($reais).self::moeda($reais);
        }

        if ($resto > 0) {
            $partes[] = self::ate999($resto).($resto === 1 ? ' centavo' : ' centavos');
        }

        return implode(' e ', $partes);
    }

    private static function moeda(int $reais): string
    {
        if ($reais === 1) {
            return ' real';
        }

        // "um milhão de reais", mas "um milhão e quinhentos mil reais".
        return $reais % 1_000_000 === 0 ? ' de reais' : ' reais';
    }

    private static function inteiro(int $numero): string
    {
        $grupos = [];
        for ($classe = 0; $numero > 0; $classe++) {
            $grupos[$classe] = $numero % 1000;
            $numero = intdiv($numero, 1000);
        }

        $trechos = [];
        for ($classe = count($grupos) - 1; $classe >= 0; $classe--) {
            $grupo = $grupos[$classe];
            if ($grupo === 0) {
                continue;
            }

            $texto = ($classe === 1 && $grupo === 1)
                ? 'mil'
                : self::ate999($grupo).($classe > 0 ? ' '.self::CLASSES[$classe][$grupo === 1 ? 0 : 1] : '');

            $trechos[] = ['texto' => $texto, 'valor' => $grupo];
        }

        $saida = '';
        foreach ($trechos as $indice => $trecho) {
            if ($indice === 0) {
                $saida = $trecho['texto'];

                continue;
            }

            // "e" antes do último grupo quando ele é menor que cem ou centena exata: "mil e dez", "mil e duzentos".
            $ultimo = $indice === count($trechos) - 1;
            $comE = $ultimo && ($trecho['valor'] < 100 || $trecho['valor'] % 100 === 0);
            $saida .= ($comE ? ' e ' : ' ').$trecho['texto'];
        }

        return $saida;
    }

    private static function ate999(int $numero): string
    {
        if ($numero === 100) {
            return 'cem';
        }

        $partes = [];
        $centena = intdiv($numero, 100);
        $resto = $numero % 100;

        if ($centena > 0) {
            $partes[] = self::CENTENAS[$centena];
        }

        if ($resto > 0) {
            $partes[] = $resto < 20
                ? self::UNIDADES[$resto]
                : self::DEZENAS[intdiv($resto, 10)].($resto % 10 ? ' e '.self::UNIDADES[$resto % 10] : '');
        }

        return implode(' e ', $partes);
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=ValorPorExtensoTest`
Esperado: PASS (18 casos).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add app/Services/ValorPorExtenso.php tests/Unit/ValorPorExtensoTest.php
git commit -m "feat: valor por extenso em reais`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T4.2: Renderizador + rota do PDF + modelo `webiss-v1`

**Estado:** pendente

**Arquivos:**
- Criar: `app/Services/RenderizadorDeRecibo.php`, `app/Http/Controllers/ReciboPdfController.php`, `resources/views/recibos/pdf/_carimbo.blade.php`, `resources/views/recibos/pdf/webiss-v1.blade.php`
- Modificar: `routes/web.php`
- Teste: `tests/Feature/Recibos/RenderizadorDeReciboTest.php`, `tests/Feature/Recibos/PdfReciboTest.php`

**Interfaces:**
- Consome: `Recibo`, `Cobranca::situacao` (T3.4), `Documento` (T2.1), `Dinheiro` (T3.1), `ValorPorExtenso` (T4.1), resultado da prova do dompdf (T0.4)
- Produz:
  - `RenderizadorDeRecibo::dados(Recibo $recibo, CarbonImmutable $agora): array` — chaves `numero`, `ano_sequencia`, `emitido_em`, `competencia`, `vencimento`, `pago_em`, `situacao`, `carimbo{texto, detalhe, classe}`, `prestador{…}`, `tomador{…}`, `servico{codigo_servico, codigo_nbs, codigo_tributacao_nacional, linha, descricao, descricao_em_linha, periodo, obs_periodo}`, `valor`, `valor_extenso`, `informacoes` (lista de linhas), `gerado_em`, `logo` (data URI ou null), `aplicativo`
  - Partes (`prestador`/`tomador`): `nome`, `nome_fantasia`, `documento`, `inscricao_municipal`, `inscricao_estadual`, `telefone`, `email`, `endereco`, `endereco_completo`, `cidade_uf`, `municipio`, `cep`, `iniciais` — campo vazio vira `-`
  - `RenderizadorDeRecibo::pdf(Recibo $recibo, CarbonImmutable $agora)` — objeto do laravel-dompdf (A4 retrato)
  - `GET /recibos/{recibo}/pdf` → PDF inline com o nome de `Recibo::nomeArquivo()`

**Ajuste conforme a prova do dompdf (T0.4):** se a rotação não funcionou, remover `transform: rotate(...)` do estilo do carimbo; se o absoluto não respeitou a moldura, recalcular `top`/`left` a partir do topo da página.

- [ ] **Passo 1: escrever os testes que falham** (foco de revisão 2)

`tests/Feature/Recibos/RenderizadorDeReciboTest.php`:

```php
<?php

namespace Tests\Feature\Recibos;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Recibo;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderizadorDeReciboTest extends TestCase
{
    use RefreshDatabase;

    private RenderizadorDeRecibo $renderizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->renderizador = app(RenderizadorDeRecibo::class);
    }

    private function recibo(array $cliente = [], array $cobranca = []): Recibo
    {
        $usuario = User::factory()->create(['name' => 'Marcos Prestador', 'cidade' => 'Aracaju', 'uf' => 'SE']);
        $tomador = Cliente::factory()->for($usuario)->create($cliente);
        $registro = Cobranca::factory()->for($usuario)->for($tomador)->create($cobranca);

        return app(EmissorDeRecibos::class)->emitir($registro);
    }

    public function test_campos_vazios_do_tomador_viram_traco(): void
    {
        $recibo = $this->recibo(array_fill_keys(
            ['inscricao_municipal', 'inscricao_estadual', 'telefone', 'email', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep'],
            null,
        ));

        $tomador = $this->renderizador->dados($recibo, CarbonImmutable::now())['tomador'];

        foreach (['inscricao_municipal', 'inscricao_estadual', 'telefone', 'email', 'endereco', 'endereco_completo', 'municipio', 'cidade_uf', 'cep'] as $campo) {
            $this->assertSame('-', $tomador[$campo], "campo {$campo}");
        }
    }

    public function test_monta_endereco_e_documento_como_na_nota(): void
    {
        $recibo = $this->recibo(['documento' => '11222333000181', 'complemento' => null]);

        $tomador = $this->renderizador->dados($recibo, CarbonImmutable::now())['tomador'];

        $this->assertSame('11.222.333/0001-81', $tomador['documento']);
        $this->assertSame('Rua Virgílio Oliveira Lima, 185, Centro - CEP: 48760-000 - Araci - BA', $tomador['endereco_completo']);
        $this->assertSame('Araci / BA', $tomador['municipio']);
    }

    public function test_valores_periodo_e_extenso(): void
    {
        $dados = $this->renderizador->dados($this->recibo(), CarbonImmutable::now());

        $this->assertSame('2026/000001', $dados['numero']);
        $this->assertSame('260,60', $dados['valor']);
        $this->assertSame('10/2026', $dados['competencia']);
        $this->assertSame('Obs.: Período de 01/10/2026 a 31/10/2026', $dados['servico']['obs_periodo']);
        $this->assertContains('Valor por extenso: duzentos e sessenta reais e sessenta centavos.', $dados['informacoes']);
    }

    public function test_carimbo_acompanha_a_situacao_atual(): void
    {
        $recibo = $this->recibo();
        $cobranca = $recibo->cobranca;

        $this->assertSame(['texto' => 'A VENCER', 'detalhe' => '10/10/2026', 'classe' => 'a_vencer'], $this->renderizador->dados($recibo, CarbonImmutable::now())['carimbo']);
        $this->assertSame('VENCIDO', $this->renderizador->dados($recibo, CarbonImmutable::parse('2026-10-11'))['carimbo']['texto']);

        $cobranca->status = StatusCobranca::Paga;
        $cobranca->pago_em = '2026-10-09';
        $cobranca->save();
        $dados = $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now());
        $this->assertSame(['texto' => 'PAGO', 'detalhe' => 'em 09/10/2026', 'classe' => 'pago'], $dados['carimbo']);
        $this->assertSame('Pago em 09/10/2026', $dados['situacao']);
    }

    public function test_usa_o_snapshot_e_nao_o_perfil_atual(): void
    {
        $recibo = $this->recibo();
        $recibo->cobranca->user->update(['name' => 'Nome Novo']);

        $this->assertSame('Marcos Prestador', $this->renderizador->dados($recibo->fresh(), CarbonImmutable::now())['prestador']['nome']);
    }
}
```

`tests/Feature/Recibos/PdfReciboTest.php`:

```php
<?php

namespace Tests\Feature\Recibos;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfReciboTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_o_pdf_do_proprio_recibo(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['nome' => 'APLB Sindicato', 'modelo' => 'webiss']);
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create());

        $resposta = $this->actingAs($usuario)->get("/recibos/{$recibo->id}/pdf");

        $resposta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString($recibo->nomeArquivo(), (string) $resposta->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }

    public function test_recibo_de_outro_usuario_da_404(): void
    {
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->create());

        $this->actingAs(User::factory()->create())->get("/recibos/{$recibo->id}/pdf")->assertNotFound();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter="RenderizadorDeReciboTest|PdfReciboTest"`
Esperado: FAIL (classe/rota inexistente).

- [ ] **Passo 3: renderizador**

`app/Services/RenderizadorDeRecibo.php`:

```php
<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Recibo;
use App\Support\Dinheiro;
use App\Support\Documento;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

final class RenderizadorDeRecibo
{
    /** Dados congelados (snapshot) + dados vivos (situação, pagamento) no formato dos templates. */
    public function dados(Recibo $recibo, CarbonImmutable $agora): array
    {
        $snapshot = $recibo->snapshot;
        $servico = $snapshot['servico'];
        $cobranca = $recibo->cobranca;
        $carimbo = $this->carimbo($cobranca, $agora);
        $inicio = CarbonImmutable::parse($servico['periodo_inicio']);
        $periodo = $inicio->format('d/m/Y').' a '.CarbonImmutable::parse($servico['periodo_fim'])->format('d/m/Y');
        $extenso = ValorPorExtenso::converter($servico['valor_centavos']);

        return [
            'numero' => $recibo->numeroFormatado(),
            'ano_sequencia' => sprintf('%d / %06d', $recibo->ano, $recibo->numero),
            'emitido_em' => $recibo->emitido_em->format('d/m/Y H:i:s'),
            'competencia' => $inicio->format('m/Y'),
            'vencimento' => CarbonImmutable::parse($servico['vencimento'])->format('d/m/Y'),
            'pago_em' => $cobranca->pago_em?->format('d/m/Y') ?? '-',
            'situacao' => $carimbo['situacao'],
            'carimbo' => ['texto' => $carimbo['texto'], 'detalhe' => $carimbo['detalhe'], 'classe' => $carimbo['classe']],
            'prestador' => $this->parte($snapshot['prestador']),
            'tomador' => $this->parte($snapshot['tomador']),
            'servico' => [
                'codigo_servico' => $this->traco($servico['codigo_servico'] ?? null),
                'codigo_nbs' => $this->traco($servico['codigo_nbs'] ?? null),
                'codigo_tributacao_nacional' => $this->traco($servico['codigo_tributacao_nacional'] ?? null),
                'linha' => $this->traco($this->juntar([
                    $servico['codigo_servico'] ?? null,
                    filled($servico['codigo_nbs'] ?? null) ? 'NBS: '.$servico['codigo_nbs'] : null,
                ], '. ')),
                'descricao' => $servico['descricao'],
                'descricao_em_linha' => (string) preg_replace('/\s*\R\s*/', ' ', trim($servico['descricao'])),
                'periodo' => $periodo,
                'obs_periodo' => 'Obs.: Período de '.$periodo,
            ],
            'valor' => Dinheiro::formatar($servico['valor_centavos']),
            'valor_extenso' => $extenso,
            'informacoes' => ['Valor por extenso: '.$extenso.'.'],
            'gerado_em' => $agora->format('d/m/Y H:i'),
            'logo' => $this->logo($snapshot['prestador']['logo'] ?? null),
            'aplicativo' => config('app.name'),
        ];
    }

    public function pdf(Recibo $recibo, CarbonImmutable $agora)
    {
        return Pdf::loadView($recibo->modelo->view(), $this->dados($recibo, $agora))->setPaper('a4', 'portrait');
    }

    private function carimbo(Cobranca $cobranca, CarbonImmutable $agora): array
    {
        $vencimento = $cobranca->vencimento->format('d/m/Y');

        return match ($cobranca->situacao($agora)) {
            'paga' => ['texto' => 'PAGO', 'detalhe' => 'em '.$cobranca->pago_em->format('d/m/Y'), 'classe' => 'pago', 'situacao' => 'Pago em '.$cobranca->pago_em->format('d/m/Y')],
            'cancelada' => ['texto' => 'CANCELADO', 'detalhe' => null, 'classe' => 'cancelada', 'situacao' => 'Cancelado'],
            'vencida' => ['texto' => 'VENCIDO', 'detalhe' => $vencimento, 'classe' => 'vencida', 'situacao' => 'Vencido'],
            'a_vencer' => ['texto' => 'A VENCER', 'detalhe' => $vencimento, 'classe' => 'a_vencer', 'situacao' => 'A vencer'],
        };
    }

    private function parte(array $dados): array
    {
        $campo = fn (string $chave) => $dados[$chave] ?? null;
        $rua = $this->juntar([$campo('logradouro'), $campo('numero'), $campo('complemento'), $campo('bairro')], ', ');
        $cep = filled($campo('cep')) ? (string) preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $campo('cep')) : null;
        $cidadeUf = [$campo('cidade'), $campo('uf')];
        $nome = (string) $campo('nome');

        return [
            'nome' => $this->traco($nome),
            'nome_fantasia' => $this->traco($campo('nome_fantasia')),
            'documento' => Documento::formatar($campo('documento')),
            'inscricao_municipal' => $this->traco($campo('inscricao_municipal')),
            'inscricao_estadual' => $this->traco($campo('inscricao_estadual')),
            'telefone' => $this->traco($campo('telefone')),
            'email' => $this->traco($campo('email')),
            'endereco' => $this->traco($rua),
            'endereco_completo' => $this->traco($this->juntar([$rua, $cep ? "CEP: {$cep}" : null, $this->juntar($cidadeUf, ' - ')], ' - ')),
            'cidade_uf' => $this->traco($this->juntar($cidadeUf, ' - ')),
            'municipio' => $this->traco($this->juntar($cidadeUf, ' / ')),
            'cep' => $this->traco($cep),
            'iniciais' => collect(preg_split('/\s+/', trim($nome)))->filter()->take(2)
                ->map(fn (string $palavra) => mb_strtoupper(mb_substr($palavra, 0, 1)))->implode(''),
        ];
    }

    private function juntar(array $partes, string $separador): string
    {
        return collect($partes)->filter(fn ($parte) => filled($parte))->implode($separador);
    }

    private function traco(?string $valor): string
    {
        return filled($valor) ? $valor : '-';
    }

    /** Data URI: o dompdf não precisa acessar o disco nem a rede para desenhar o logo. */
    private function logo(?string $caminho): ?string
    {
        if (blank($caminho) || ! Storage::disk('local')->exists($caminho)) {
            return null;
        }

        return 'data:'.Storage::disk('local')->mimeType($caminho).';base64,'
            .base64_encode(Storage::disk('local')->get($caminho));
    }
}
```

- [ ] **Passo 4: controller e rota**

`app/Http/Controllers/ReciboPdfController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ReciboPdfController extends Controller
{
    public function __invoke(Request $request, int $recibo, RenderizadorDeRecibo $renderizador): Response
    {
        $registro = $request->user()->recibos()->with('cobranca')->findOrFail($recibo);

        try {
            return $renderizador->pdf($registro, CarbonImmutable::now())->stream($registro->nomeArquivo());
        } catch (Throwable $erro) {
            report($erro);

            return back()->with('erro', 'Não foi possível gerar o recibo. Tente de novo.');
        }
    }
}
```

Em `routes/web.php`, grupo autenticado (com `use App\Http\Controllers\ReciboPdfController;`):

```php
    Route::get('/recibos/{recibo}/pdf', ReciboPdfController::class);
```

- [ ] **Passo 5: carimbo e modelo WebISS**

`resources/views/recibos/pdf/_carimbo.blade.php`:

```blade
{{-- Situação lida da cobrança na hora de gerar o PDF (não faz parte do snapshot). --}}
<div class="carimbo carimbo-{{ $carimbo['classe'] }}" style="{{ $estilo }}">
    {{ $carimbo['texto'] }}
    @if ($carimbo['detalhe'])
        <div class="carimbo-detalhe">{{ $carimbo['detalhe'] }}</div>
    @endif
</div>
```

`resources/views/recibos/pdf/webiss-v1.blade.php` — disposição da NFS-e Avulsa WebISS (coordenadas medidas no PDF de referência; A4 595 × 842 pt):

```blade
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo {{ $numero }}</title>
<style>
    @page { margin: 16pt 18pt; }
    body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 7pt; color: #000; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { vertical-align: top; padding: 1.5pt 4pt; overflow: hidden; }
    .moldura { position: relative; border: 0.75pt solid #000; }
    .divisa { border-top: 0.75pt solid #000; }
    .secao { padding: 4pt 4pt 1pt; font-size: 7pt; font-weight: bold; }
    .rot { display: block; font-size: 5pt; }
    .val { display: block; font-size: 8pt; font-weight: bold; }
    .val-p { display: block; font-size: 7pt; font-weight: bold; }
    .num { text-align: right; }
    .centro { text-align: center; }
    .monograma { width: 56pt; height: 56pt; border: 1pt solid #000; font-size: 20pt; font-weight: bold; text-align: center; line-height: 56pt; }
    .carimbo { position: absolute; border: 3pt solid; padding: 4pt 12pt; font-weight: bold; text-align: center; }
    .carimbo-detalhe { font-size: 9pt; }
    .carimbo-pago { color: #2E7D4F; border-color: #2E7D4F; }
    .carimbo-a_vencer { color: #2F3C9E; border-color: #2F3C9E; }
    .carimbo-vencida { color: #B3261E; border-color: #B3261E; }
    .carimbo-cancelada { color: #6B7280; border-color: #6B7280; }
    .rodape { position: fixed; bottom: 0; left: 0; right: 0; font-size: 6pt; }
</style>
</head>
<body>
<div class="moldura">
    {{-- Cabeçalho: no original, brasão e prefeitura. Aqui, o prestador. --}}
    <table>
        <tr>
            <td style="width: 72pt; height: 84pt;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="max-width: 64pt; max-height: 76pt;">
                @else
                    <div class="monograma">{{ $prestador['iniciais'] }}</div>
                @endif
            </td>
            <td>
                <div style="font-size: 12pt; font-weight: bold;">{{ $prestador['nome'] }}</div>
                <div style="margin-top: 3pt; font-size: 10pt;">{{ $prestador['nome_fantasia'] }}</div>
                <div style="margin-top: 5pt; font-size: 7.5pt; line-height: 1.35;">
                    {{ $prestador['endereco_completo'] }}<br>
                    Tel.: {{ $prestador['telefone'] }} - E-mail: {{ $prestador['email'] }} - CPF/CNPJ: {{ $prestador['documento'] }}
                </div>
            </td>
            <td style="width: 112pt; padding: 4pt;">
                <div class="centro" style="border: 0.75pt solid #000; padding: 6pt;">
                    <span class="rot">Recibo nº</span>
                    <span style="display: block; font-size: 13pt; font-weight: bold;">{{ $numero }}</span>
                    <span class="rot" style="margin-top: 8pt;">Data de emissão</span>
                    <span class="val-p">{{ $emitido_em }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="divisa centro" style="padding: 5pt; font-size: 10pt; font-weight: bold;">RECIBO DE PRESTAÇÃO DE SERVIÇOS</div>

    <table class="divisa">
        <tr>
            <td style="width: 15%;"><span class="rot">Emissão (Horário de Brasília)</span><span class="val-p">{{ $emitido_em }}</span></td>
            <td style="width: 34%;"><span class="rot">Período de Competência</span><span class="val">{{ $competencia }}</span></td>
            <td><span class="rot">Município de Prestação do Serviço</span><span class="val">{{ $prestador['cidade_uf'] }}</span></td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width: 15%;"><span class="rot">Vencimento</span><span class="val">{{ $vencimento }}</span></td>
            <td><span class="rot">Situação</span><span class="val">{{ $situacao }}</span></td>
        </tr>
    </table>

    <div class="divisa secao">PRESTADOR DE SERVIÇOS</div>
    <table><tr><td><span class="rot">Razão Social</span><span class="val">{{ $prestador['nome'] }}</span></td></tr></table>
    <table>
        <tr>
            <td style="width: 61%;"><span class="rot">Nome Fantasia</span><span class="val">{{ $prestador['nome_fantasia'] }}</span></td>
            <td><span class="rot">Email</span><span class="val-p">{{ $prestador['email'] }}</span></td>
        </tr>
    </table>
    <table>
        <tr>
            <td style="width: 23%;"><span class="rot">CPF/CNPJ</span><span class="val">{{ $prestador['documento'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Municipal</span><span class="val">{{ $prestador['inscricao_municipal'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Estadual</span><span class="val">{{ $prestador['inscricao_estadual'] }}</span></td>
            <td style="width: 13%;"><span class="rot">Simples Nacional</span><span class="val">-</span></td>
            <td style="width: 15%;"><span class="rot">Incentivador Cultural</span><span class="val">-</span></td>
            <td><span class="rot">Fone/Fax</span><span class="val">{{ $prestador['telefone'] }}</span></td>
        </tr>
    </table>
    <table><tr><td><span class="rot">Endereço</span><span class="val-p">{{ $prestador['endereco_completo'] }}</span></td></tr></table>

    <div class="divisa secao">TOMADOR DE SERVIÇOS</div>
    <table><tr><td><span class="rot">Nome/Razão Social</span><span class="val">{{ $tomador['nome'] }}</span></td></tr></table>
    <table>
        <tr>
            <td style="width: 23%;"><span class="rot">CPF/CNPJ</span><span class="val">{{ $tomador['documento'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Municipal</span><span class="val">{{ $tomador['inscricao_municipal'] }}</span></td>
            <td style="width: 14%;"><span class="rot">Inscrição Estadual</span><span class="val">{{ $tomador['inscricao_estadual'] }}</span></td>
            <td style="width: 13%;"><span class="rot">Fone/Fax</span><span class="val">{{ $tomador['telefone'] }}</span></td>
            <td><span class="rot">E-mail</span><span class="val-p">{{ $tomador['email'] }}</span></td>
        </tr>
    </table>
    <table><tr><td><span class="rot">Endereço</span><span class="val-p">{{ $tomador['endereco_completo'] }}</span></td></tr></table>

    <div class="divisa secao">SERVIÇO PRESTADO</div>
    <table><tr><td><span style="font-size: 7pt;">{{ $servico['linha'] }}</span></td></tr></table>

    <div class="divisa secao">DESCRIÇÃO DOS SERVIÇOS</div>
    <table>
        <tr>
            <td style="height: 170pt;">
                <div style="font-size: 7.5pt; line-height: 1.4;">{!! nl2br(e($servico['descricao'])) !!}</div>
                <div style="margin-top: 8pt; font-size: 7.5pt;">{{ $servico['obs_periodo'] }}</div>
            </td>
        </tr>
    </table>

    <div class="divisa secao">TRIBUTOS FEDERAIS</div>
    <table>
        <tr>
            @foreach (['INSS (R$)', 'IR (R$)', 'PIS (R$)', 'COFINS (R$)', 'CSLL (R$)', 'Outras Retenções (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
        </tr>
    </table>

    <div class="divisa secao">VALORES</div>
    <table>
        <tr>
            <td class="num"><span class="rot">Valor dos Serviços (R$)</span><span class="val">{{ $valor }}</span></td>
            @foreach (['Deduções (R$)', 'Desc. Cond. (R$)', 'Desc. Incond. (R$)', 'B.C. do ISS (R$)', 'Alíquota ISS (%)', 'ISS (R$)', 'ISS Retido (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
        </tr>
    </table>
    <table>
        <tr>
            @foreach (['B.C. do IBS/CBS (R$)', 'Alíquota IBS/CBS (%)', 'Redução IBS/CBS (%)', 'IBS (R$)', 'CBS (R$)'] as $rotulo)
                <td class="num"><span class="rot">{{ $rotulo }}</span><span class="val">0,00</span></td>
            @endforeach
            <td class="num"><span class="rot">Valor Líquido (R$)</span><span class="val">{{ $valor }}</span></td>
            <td class="num"><span class="rot">Valor Total (R$)</span><span style="display: block; font-size: 12pt; font-weight: bold;">{{ $valor }}</span></td>
        </tr>
    </table>

    <div class="divisa secao">OUTRAS INFORMAÇÕES</div>
    <table>
        <tr>
            <td style="height: 40pt;">
                @foreach ($informacoes as $linha)
                    <div style="font-size: 6.5pt;">{{ $linha }}</div>
                @endforeach
            </td>
        </tr>
    </table>

    {{-- No original, a marca d'água "RASCUNHO" ocupa este lugar. --}}
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 330pt; left: 150pt; width: 230pt; font-size: 30pt; transform: rotate(-35deg);'])
</div>

<div class="rodape">
    Gerado em: {{ $gerado_em }} | {{ $aplicativo }}<br>
    Este recibo não substitui a Nota Fiscal de Serviços Eletrônica.
</div>
</body>
</html>
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --filter="RenderizadorDeReciboTest|PdfReciboTest"`
Esperado: PASS (7 testes). Se `content-type` vier com charset (`application/pdf; charset=...`), trocar o `assertHeader` por `assertStringStartsWith('application/pdf', ...)` e registrar na skill.

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: recibo em PDF no modelo WebISS com carimbo de situação`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T4.3: Modelo `danfse-v1`

**Estado:** pendente

**Arquivos:**
- Criar: `resources/views/recibos/pdf/danfse-v1.blade.php`
- Modificar: `tests/Feature/Recibos/PdfReciboTest.php` (novo teste)

**Interfaces:** consome as mesmas chaves de `RenderizadorDeRecibo::dados` (T4.2).

- [ ] **Passo 1: escrever o teste que falha**

Acrescentar a `PdfReciboTest`:

```php
    public function test_cliente_danfse_recebe_pdf_no_modelo_nacional(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['modelo' => 'danfse']);
        $recibo = app(EmissorDeRecibos::class)->emitir(Cobranca::factory()->for($usuario)->for($cliente)->create());

        $this->assertSame('recibos.pdf.danfse-v1', $recibo->modelo->view());
        $this->actingAs($usuario)->get("/recibos/{$recibo->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=test_cliente_danfse_recebe_pdf_no_modelo_nacional`
Esperado: FAIL — o controller captura o `View [recibos.pdf.danfse-v1] not found` e redireciona (302).

- [ ] **Passo 3: modelo DANFSe**

`resources/views/recibos/pdf/danfse-v1.blade.php` — grade de 4 colunas de 25% (x = 12/156/301/446 pt no original). Fonte Helvetica; se a T0.4 mostrou acentos quebrados na Helvetica, trocar por `'DejaVu Sans'`.

```blade
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Recibo {{ $numero }}</title>
<style>
    @page { margin: 12pt; }
    body { margin: 0; font-family: Helvetica, sans-serif; font-size: 6.5pt; color: #000; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    td { vertical-align: top; padding: 1pt 3pt; overflow: hidden; }
    .c1 { width: 25%; } .c2 { width: 50%; } .c3 { width: 75%; } .c4 { width: 100%; }
    .rot { display: block; font-size: 6pt; font-weight: bold; }
    .val { display: block; min-height: 7pt; font-size: 6.5pt; }
    .bloco { border-top: 0.75pt solid #000; }
    .titulo { background: #E6E6E6; font-size: 7pt; font-weight: bold; }
    .topo { position: relative; }
    .carimbo { position: absolute; border: 2.5pt solid; padding: 3pt 8pt; font-weight: bold; text-align: center; }
    .carimbo-detalhe { font-size: 7pt; }
    .carimbo-pago { color: #2E7D4F; border-color: #2E7D4F; }
    .carimbo-a_vencer { color: #2F3C9E; border-color: #2F3C9E; }
    .carimbo-vencida { color: #B3261E; border-color: #B3261E; }
    .carimbo-cancelada { color: #6B7280; border-color: #6B7280; }
    .rodape { position: fixed; bottom: 0; left: 0; right: 0; }
    .rodape td { height: 30pt; border: 0.75pt solid #000; }
</style>
</head>
<body>
<div class="topo">
    <table>
        <tr>
            <td style="width: 30%; height: 40pt;">
                @if ($logo)
                    <img src="{{ $logo }}" alt="" style="max-width: 150pt; max-height: 38pt;">
                @else
                    <span style="font-size: 12pt; font-weight: bold;">{{ $prestador['nome'] }}</span>
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <div style="font-size: 9pt; font-weight: bold;">RECIBO</div>
                <div style="font-size: 8pt; font-weight: bold;">Recibo de Prestação de Serviços</div>
            </td>
            <td style="width: 30%;"><span class="val">Município: {{ $prestador['municipio'] }}</span></td>
        </tr>
    </table>

    <table class="bloco"><tr><td class="c4"><span class="rot">NÚMERO DO RECIBO</span><span class="val" style="font-size: 9pt; font-weight: bold;">{{ $numero }}</span></td></tr></table>
    <table><tr>
        <td class="c1"><span class="rot">ANO / SEQUÊNCIA</span><span class="val">{{ $ano_sequencia }}</span></td>
        <td class="c1"><span class="rot">COMPETÊNCIA</span><span class="val">{{ $competencia }}</span></td>
        <td class="c1"><span class="rot">DATA E HORA DA EMISSÃO</span><span class="val">{{ $emitido_em }}</span></td>
        <td class="c1"></td>
    </tr></table>
    <table><tr>
        <td class="c1"><span class="rot">VENCIMENTO</span><span class="val">{{ $vencimento }}</span></td>
        <td class="c1"><span class="rot">PERÍODO</span><span class="val">{{ $servico['periodo'] }}</span></td>
        <td class="c1"><span class="rot">DATA DO PAGAMENTO</span><span class="val">{{ $pago_em }}</span></td>
        <td class="c1"></td>
    </tr></table>
    <table><tr>
        <td class="c1"><span class="rot">EMITENTE DO RECIBO</span><span class="val">Prestador</span></td>
        <td class="c1"><span class="rot">SITUAÇÃO DO RECIBO</span><span class="val">{{ $situacao }}</span></td>
        <td class="c1"><span class="rot">FINALIDADE</span><span class="val">-</span></td>
        <td class="c1"></td>
    </tr></table>

    {{-- No original, o QR code da NFS-e ocupa este canto. --}}
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 52pt; right: 14pt; width: 110pt; font-size: 14pt; transform: rotate(-8deg);'])
</div>

@foreach (['PRESTADOR / FORNECEDOR' => $prestador, 'TOMADOR / ADQUIRENTE' => $tomador] as $titulo => $parte)
    <table class="bloco"><tr>
        <td class="c1 titulo">{{ $titulo }}</td>
        <td class="c1"><span class="rot">CNPJ / CPF / NIF</span><span class="val">{{ $parte['documento'] }}</span></td>
        <td class="c1"><span class="rot">Indicador Municipal (Inscrição)</span><span class="val">{{ $parte['inscricao_municipal'] }}</span></td>
        <td class="c1"><span class="rot">Telefone</span><span class="val">{{ $parte['telefone'] }}</span></td>
    </tr></table>
    <table><tr>
        <td class="c2"><span class="rot">Nome / Nome Empresarial</span><span class="val">{{ $parte['nome'] }}</span></td>
        <td class="c1"><span class="rot">Município / Sigla UF</span><span class="val">{{ $parte['municipio'] }}</span></td>
        <td class="c1"><span class="rot">CEP</span><span class="val">{{ $parte['cep'] }}</span></td>
    </tr></table>
    <table><tr>
        <td class="c2"><span class="rot">Endereço</span><span class="val">{{ $parte['endereco'] }}</span></td>
        <td class="c2"><span class="rot">E-mail</span><span class="val">{{ $parte['email'] }}</span></td>
    </tr></table>
    @if ($loop->first)
        <table><tr>
            <td class="c1"><span class="rot">Simples Nacional na Data de Competência</span><span class="val">-</span></td>
            <td class="c3"><span class="rot">Regime de Apuração Tributária pelo SN</span><span class="val">-</span></td>
        </tr></table>
    @endif
@endforeach

<table class="bloco"><tr>
    <td class="c1 titulo">SERVIÇO PRESTADO</td>
    <td class="c1"><span class="rot">Código de Tributação Nacional/Municipal</span><span class="val">{{ $servico['codigo_tributacao_nacional'] }}</span></td>
    <td class="c1"><span class="rot">Código da NBS</span><span class="val">{{ $servico['codigo_nbs'] }}</span></td>
    <td class="c1"><span class="rot">Local da Prestação / Sigla UF / País</span><span class="val">{{ $prestador['municipio'] }} / -</span></td>
</tr></table>
<table><tr><td class="c4"><span class="val">{{ $servico['codigo_servico'] }}</span></td></tr></table>
<table><tr><td class="c4"><span class="rot">Descrição do Serviço</span><span class="val">{{ $servico['descricao_em_linha'] }} {{ $servico['obs_periodo'] }}</span></td></tr></table>

@foreach ([
    ['TRIBUTAÇÃO MUNICIPAL (ISSQN)', ['Tipo de Tributação do ISSQN', 'Município / Sigla UF / País de Incidência do ISSQN', ''], [
        ['BC ISSQN', 'Alíquota Aplicada', 'Retenção do ISSQN', 'ISSQN Apurado'],
    ]],
    ['TRIBUTAÇÃO FEDERAL (EXCETO CBS)', ['IRRF', 'Contribuição Previdenciária - Retida', 'Contribuições Sociais - Retidas'], [
        ['PIS - Débito Apuração Própria', 'COFINS - Débito Apuração Própria', 'Descrição Contrib. Sociais - Retidas', ''],
    ]],
    ['TRIBUTAÇÃO IBS/CBS', ['CST / cClassTrib', 'Indicador de Operação / Código IBGE Incidência / Município Incidência / Sigla UF', ''], [
        ['Exclusões e Reduções da Base de Cálculo', 'Base de Cálculo Após Exclusões e Reduções', 'Red. Alíquota IBS / Red. Alíquota CBS', 'Alíquota - IBS UF / IBS Mun'],
        ['Alíq. Efetiva Municipal - IBS', 'Valor Apurado Municipal - IBS', 'Alíq. Efetiva Estadual - IBS', 'Valor Apurado Estadual - IBS'],
        ['Valor Total Apurado - IBS', 'Alíquota - CBS', 'Alíquota Efetiva - CBS', 'Valor Total Apurado - CBS'],
    ]],
] as [$titulo, $primeira, $linhas])
    {{-- Quadros mantidos só no visual: o recibo não calcula tributos. --}}
    <table class="bloco"><tr>
        <td class="c1 titulo">{{ $titulo }}</td>
        @foreach ($primeira as $rotulo)
            <td class="c1">@if ($rotulo !== '')<span class="rot">{{ $rotulo }}</span><span class="val">-</span>@endif</td>
        @endforeach
    </tr></table>
    @foreach ($linhas as $linha)
        <table><tr>
            @foreach ($linha as $rotulo)
                <td class="c1">@if ($rotulo !== '')<span class="rot">{{ $rotulo }}</span><span class="val">-</span>@endif</td>
            @endforeach
        </tr></table>
    @endforeach
@endforeach

<table class="bloco"><tr>
    <td class="c1 titulo">VALOR TOTAL DO RECIBO</td>
    <td class="c1"><span class="rot">VALOR DA OPERAÇÃO / SERVIÇO</span><span class="val">R$ {{ $valor }}</span></td>
    <td class="c1"><span class="rot">Desconto Incondicionado</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">Desconto Condicionado</span><span class="val">-</span></td>
</tr></table>
<table><tr>
    <td class="c1"><span class="rot">Total das Retenções (ISSQN / Federais)</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">VALOR LÍQUIDO DO RECIBO</span><span class="val" style="font-weight: bold;">R$ {{ $valor }}</span></td>
    <td class="c1"><span class="rot">Total do IBS/CBS</span><span class="val">-</span></td>
    <td class="c1"><span class="rot">VALOR LÍQUIDO + IBS/CBS</span><span class="val">R$ {{ $valor }}</span></td>
</tr></table>

<table class="bloco"><tr><td class="c4 titulo">INFORMAÇÕES COMPLEMENTARES</td></tr></table>
<table><tr><td class="c4" style="height: 60pt;">
    @foreach ($informacoes as $linha)
        <div class="val">{{ $linha }}</div>
    @endforeach
    <div class="val" style="margin-top: 4pt;">Este recibo não substitui a Nota Fiscal de Serviços Eletrônica. Gerado em {{ $gerado_em }}.</div>
</td></tr></table>

<div class="rodape">
    <table><tr>
        <td style="width: 30%;"><span class="rot">DATA DO RECEBIMENTO:</span></td>
        <td style="width: 35%;"><span class="rot">IDENTIFICAÇÃO E ASSINATURA DO PRESTADOR</span></td>
        <td style="width: 35%;"><span class="rot">Nº DO RECIBO</span><span class="val">{{ $numero }}</span></td>
    </tr></table>
</div>
</body>
</html>
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=PdfReciboTest`
Esperado: PASS (3 testes).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add resources/views/recibos/pdf/danfse-v1.blade.php tests/Feature/Recibos/PdfReciboTest.php
git commit -m "feat: recibo em PDF no modelo nacional (DANFSe)`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T4.4: Comparação visual com as notas originais

**Estado:** pendente

**Arquivos:**
- Criar: `tests/Feature/Recibos/AmostrasVisuaisTest.php` (grupo `visual`)

**Interfaces:** gera `storage/app/private/verificacao/amostra-webiss.pdf` e `amostra-danfse.pdf` com dados fictícios de tamanho parecido com os das notas (nunca os dados pessoais reais do usuário dentro do repositório).

- [ ] **Passo 1: escrever o gerador de amostras**

`tests/Feature/Recibos/AmostrasVisuaisTest.php`:

```php
<?php

namespace Tests\Feature\Recibos;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('visual')]
class AmostrasVisuaisTest extends TestCase
{
    use RefreshDatabase;

    public function test_gera_amostras_dos_dois_modelos(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-13 14:30:53'));
        $usuario = User::factory()->create([
            'name' => 'Prestador Exemplo da Silva Santos',
            'documento' => '52998224725',
            'inscricao_municipal' => '1000736',
            'telefone' => '(79) 99999-0000',
            'logradouro' => 'Rua A-4 (Conjunto Exemplo I)',
            'numero' => 'M.06',
            'bairro' => 'Bairro Exemplo',
            'cidade' => 'Nossa Senhora do Socorro',
            'uf' => 'SE',
            'cep' => '49160000',
        ]);

        foreach (['webiss', 'danfse'] as $modelo) {
            $cliente = Cliente::factory()->for($usuario)->create([
                'nome' => 'Sindicato Exemplo dos Trabalhadores em Educação do Estado',
                'modelo' => $modelo,
                'logradouro' => 'Rua Exemplo Oliveira Lima',
                'complemento' => 'Delegacia Sindical do Sisal',
            ]);
            $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create([
                'descricao' => "- Hospedagem do domínio exemplo.com.br\n- Servidor de Arquivos\n- Pacote 5 subdomínios\n- Pacote 5 bancos de dados",
                'codigo_servico' => '0102 - Programação',
                'codigo_nbs' => '115061000',
                'codigo_tributacao_nacional' => '01.02.01',
                'periodo_inicio' => '2026-09-21',
                'periodo_fim' => '2026-10-20',
                'vencimento' => '2026-09-21',
            ]);
            $recibo = app(EmissorDeRecibos::class)->emitir($cobranca);

            $pdf = app(RenderizadorDeRecibo::class)->pdf($recibo, CarbonImmutable::now())->output();

            $this->assertStringStartsWith('%PDF', $pdf);
            Storage::disk('local')->put("verificacao/amostra-{$modelo}.pdf", $pdf);
        }
    }
}
```

- [ ] **Passo 2: gerar**

Run: `php artisan test --group=visual`
Esperado: PASS e os dois arquivos em `storage/app/private/verificacao/`.

- [ ] **Passo 3: conferência lado a lado com o usuário**

Pedir ao usuário que abra cada amostra ao lado da nota correspondente em `MATERIAIS/` e aponte diferenças de posição, tamanho, fonte e espaçamento (ou envie prints). Ajustar o template e repetir os passos 2–3 até o usuário aprovar. Verificar também: a descrição com 25 linhas cabe na caixa sem empurrar o rodapé para uma segunda página (foco de revisão 3) — gerar uma amostra extra com 25 linhas, só localmente.

- [ ] **Passo 4: registrar na skill**

Anotar em `.claude/skills/recibos/SKILL.md` cada ajuste de layout aprovado (o que mudou e por quê) e o aviso: **a partir daqui, mudança visual em `webiss-v1`/`danfse-v1` só vale antes de existir recibo emitido em produção; depois, criar `-v2`.**

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add tests/Feature/Recibos/AmostrasVisuaisTest.php resources/views/recibos .claude/skills
git commit -m "test: amostras visuais dos modelos de recibo aprovadas`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Fase 5 — Painel, envio e baixa

### Tarefa T5.1: Painel completo

**Estado:** pendente

**Arquivos:**
- Criar: `resources/js/Components/Carimbo.vue`, `resources/js/Components/BotaoEnviar.vue`, `resources/js/Components/BaixaInline.vue`, `resources/js/Components/CancelarInline.vue`, `resources/js/Components/LinhaCobranca.vue`
- Modificar: `app/Http/Controllers/PainelController.php`, `resources/js/Pages/Painel.vue`, `resources/css/app.css`
- Teste: `tests/Feature/PainelTest.php`

**Interfaces:**
- Consome: `GeradorDeCobrancas::gerarPara` (T3.6), `Cobranca::paraLista` (T3.4), `Dinheiro::formatar` (T3.1), rotas `/cobrancas/{id}/enviada|pagar|cancelar` (T3.7), `/recibos/{id}/pdf` (T4.2)
- Produz: props do painel `mes`, `perfilCompleto`, `resumo{previsto, recebido}` (strings formatadas), `paraEnviar`, `aguardando`, `recebidas` (listas de `paraLista`). Componentes:
  - `Carimbo` (prop `situacao`)
  - `BotaoEnviar` (props `cobrancaId`, `pdfUrl`, `nomeArquivo`, `rotulo='Enviar'`)
  - `BaixaInline` e `CancelarInline` (prop `cobrancaId`)
  - `LinhaCobranca` (prop `cobranca`; slot para as ações)

**Regras do resumo:** "previsto" = soma das cobranças não canceladas com vencimento no mês; "recebido" = parte paga dessas mesmas cobranças (as duas somas são comparáveis).

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/PainelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
    }

    public function test_abrir_o_painel_gera_as_cobrancas_devidas(): void
    {
        $usuario = User::factory()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Painel')
                ->where('mes', 'Outubro de 2026')
                ->has('paraEnviar', 1)
                ->where('paraEnviar.0.recibo.numero', '2026/000001')
                ->has('aguardando', 0)
                ->has('recebidas', 0));
    }

    public function test_blocos_seguem_envio_e_pagamento(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $nova = fn (array $dados) => Cobranca::factory()->for($usuario)->for($cliente)->create($dados);

        $paraEnviar = $nova(['vencimento' => '2026-10-20']);
        $enviada = $nova(['vencimento' => '2026-10-15']);
        $enviada->enviado_em = now();
        $enviada->save();
        $paga = $nova(['vencimento' => '2026-10-05', 'valor_centavos' => 10000]);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-06';
        $paga->save();
        $cancelada = $nova(['vencimento' => '2026-10-07']);
        $cancelada->status = StatusCobranca::Cancelada;
        $cancelada->save();
        foreach ([$paraEnviar, $enviada, $paga, $cancelada] as $cobranca) {
            app(EmissorDeRecibos::class)->emitir($cobranca);
        }

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->has('paraEnviar', 1)->where('paraEnviar.0.id', $paraEnviar->id)
                ->has('aguardando', 1)->where('aguardando.0.id', $enviada->id)
                ->has('recebidas', 1)->where('recebidas.0.id', $paga->id)
                ->where('resumo.previsto', '621,20')
                ->where('resumo.recebido', '100,00'));
    }

    public function test_perfil_incompleto_avisa_e_nao_gera(): void
    {
        $usuario = User::factory()->semPerfil()->create();
        Contrato::factory()->for($usuario)->create();

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page
                ->where('perfilCompleto', false)
                ->has('paraEnviar', 0));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=PainelTest`
Esperado: FAIL (props `paraEnviar` inexistentes).

- [ ] **Passo 3: controller**

`app/Http/Controllers/PainelController.php` (conteúdo completo):

```php
<?php

namespace App\Http\Controllers;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Services\GeradorDeCobrancas;
use App\Support\Dinheiro;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PainelController extends Controller
{
    public function __invoke(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();

        // Geração "preguiçosa": quem abre o painel vê o que já devia existir.
        $gerador->gerarPara($usuario, $hoje);

        $inicio = $hoje->startOfMonth();
        $fim = $hoje->endOfMonth();
        $comRelacoes = fn () => $usuario->cobrancas()->with(['cliente:id,nome', 'recibo']);
        $doMes = fn () => $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->whereBetween('vencimento', [$inicio, $fim]);
        $paraLista = fn (Cobranca $cobranca) => $cobranca->paraLista($hoje);

        $pendentes = $comRelacoes()->where('status', StatusCobranca::Pendente)->orderBy('vencimento')->get();

        return Inertia::render('Painel', [
            'mes' => Str::ucfirst($hoje->locale('pt_BR')->translatedFormat('F \d\e Y')),
            'perfilCompleto' => $usuario->perfilCompleto(),
            'diasAntecedencia' => $usuario->dias_antecedencia,
            'resumo' => [
                'previsto' => Dinheiro::formatar((int) $doMes()->sum('valor_centavos')),
                'recebido' => Dinheiro::formatar((int) $doMes()->where('status', StatusCobranca::Paga)->sum('valor_centavos')),
            ],
            'paraEnviar' => $pendentes->whereNull('enviado_em')->values()->map($paraLista),
            'aguardando' => $pendentes->whereNotNull('enviado_em')->values()->map($paraLista),
            'recebidas' => $comRelacoes()->where('status', StatusCobranca::Paga)
                ->whereBetween('pago_em', [$inicio, $fim])->orderByDesc('pago_em')->get()->map($paraLista),
        ]);
    }
}
```

- [ ] **Passo 4: componentes**

Acrescentar em `resources/css/app.css`, dentro de `@layer components`:

```css
    .carimbo {
        mix-blend-mode: multiply;
        letter-spacing: 0.08em;
    }
```

`resources/js/Components/Carimbo.vue`:

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({ situacao: { type: String, required: true } });

const estilos = {
    a_vencer: ['A vencer', 'border-carbono text-carbono'],
    vencida: ['Vencido', 'border-tinta-vermelha text-tinta-vermelha'],
    paga: ['Pago', 'border-tinta-verde text-tinta-verde'],
    cancelada: ['Cancelado', 'border-grafite/50 text-grafite/60'],
};

const atual = computed(() => estilos[props.situacao]);
</script>

<template>
    <span class="carimbo inline-block -rotate-6 rounded-sm border-2 px-2 py-0.5 text-xs font-bold uppercase" :class="atual[1]">
        {{ atual[0] }}
    </span>
</template>
```

`resources/js/Components/BotaoEnviar.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    cobrancaId: { type: Number, required: true },
    pdfUrl: { type: String, required: true },
    nomeArquivo: { type: String, required: true },
    rotulo: { type: String, default: 'Enviar' },
});

const preparando = ref(false);
const erro = ref('');

function baixar(arquivo) {
    const url = URL.createObjectURL(arquivo);
    Object.assign(document.createElement('a'), { href: url, download: arquivo.name }).click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function enviar() {
    preparando.value = true;
    erro.value = '';

    try {
        const resposta = await fetch(props.pdfUrl, { credentials: 'same-origin' });
        if (!resposta.ok || !(resposta.headers.get('content-type') ?? '').includes('pdf')) {
            throw new Error('PDF indisponível');
        }
        const arquivo = new File([await resposta.blob()], props.nomeArquivo, { type: 'application/pdf' });

        // Folha de compartilhamento nativa (celular, HTTPS); fora disso, download.
        if (navigator.canShare?.({ files: [arquivo] })) {
            try {
                await navigator.share({ files: [arquivo], title: props.nomeArquivo });
            } catch (falha) {
                if (falha.name === 'AbortError') {
                    return; // fechou a folha sem enviar: não marca como enviada
                }
                if (falha.name !== 'NotAllowedError') {
                    throw falha;
                }
                baixar(arquivo); // o navegador exigiu um toque novo depois do download do PDF
            }
        } else {
            baixar(arquivo);
        }

        router.post(`/cobrancas/${props.cobrancaId}/enviada`, {}, { preserveScroll: true });
    } catch {
        erro.value = 'Não foi possível preparar o PDF. Tente de novo.';
    } finally {
        preparando.value = false;
    }
}
</script>

<template>
    <span class="inline-flex flex-col">
        <button type="button" class="botao" :disabled="preparando" @click="enviar">
            {{ preparando ? 'Preparando PDF…' : rotulo }}
        </button>
        <span v-if="erro" role="alert" class="mt-1 text-xs text-tinta-vermelha">{{ erro }}</span>
    </span>
</template>
```

`resources/js/Components/BaixaInline.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({ cobrancaId: { type: Number, required: true } });

const aberto = ref(false);
const hoje = new Date().toLocaleDateString('sv-SE'); // AAAA-MM-DD no fuso do aparelho
const form = useForm({ pago_em: hoje });

const confirmar = () => form.post(`/cobrancas/${props.cobrancaId}/pagar`, {
    preserveScroll: true,
    onSuccess: () => { aberto.value = false; },
});
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Dar baixa</button>
    <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="confirmar">
        <label class="flex flex-col font-estreita text-xs">
            Data do pagamento
            <input v-model="form.pago_em" type="date" class="entrada" :max="hoje" required>
        </label>
        <button class="botao" :disabled="form.processing">Confirmar baixa</button>
        <button type="button" class="botao-secundario" @click="aberto = false">Voltar</button>
        <p v-if="form.errors.pago_em" class="w-full text-xs text-tinta-vermelha">{{ form.errors.pago_em }}</p>
    </form>
</template>
```

`resources/js/Components/CancelarInline.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ cobrancaId: { type: Number, required: true } });

const confirmando = ref(false);

const cancelar = () => router.post(`/cobrancas/${props.cobrancaId}/cancelar`, {}, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = false; },
});
</script>

<template>
    <button v-if="!confirmando" type="button" class="botao-secundario" @click="confirmando = true">Cancelar cobrança</button>
    <span v-else class="inline-flex flex-wrap gap-2">
        <button type="button" class="botao-secundario border-tinta-vermelha text-tinta-vermelha" @click="cancelar">Confirmar cancelamento</button>
        <button type="button" class="botao-secundario" @click="confirmando = false">Manter cobrança</button>
    </span>
</template>
```

`resources/js/Components/LinhaCobranca.vue`:

```vue
<script setup>
import Carimbo from './Carimbo.vue';

defineProps({ cobranca: { type: Object, required: true } });
</script>

<template>
    <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="font-semibold">{{ cobranca.cliente }}</p>
            <p class="font-estreita text-sm text-grafite/80">
                <template v-if="cobranca.situacao === 'paga'">Pago em {{ cobranca.pago_em }}</template>
                <template v-else>Vence {{ cobranca.vencimento }}</template>
                <template v-if="cobranca.recibo"> · recibo {{ cobranca.recibo.numero }}</template>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-lg font-semibold tabular-nums">R$ {{ cobranca.valor }}</span>
            <Carimbo :situacao="cobranca.situacao" />
            <slot />
        </div>
    </li>
</template>
```

- [ ] **Passo 5: página do painel**

`resources/js/Pages/Painel.vue` (conteúdo completo):

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import CamadaApp from '../Layouts/CamadaApp.vue';
import LinhaCobranca from '../Components/LinhaCobranca.vue';
import BotaoEnviar from '../Components/BotaoEnviar.vue';
import BaixaInline from '../Components/BaixaInline.vue';

defineProps({
    mes: { type: String, required: true },
    perfilCompleto: { type: Boolean, required: true },
    diasAntecedencia: { type: Number, required: true },
    resumo: { type: Object, required: true },
    paraEnviar: { type: Array, required: true },
    aguardando: { type: Array, required: true },
    recebidas: { type: Array, required: true },
});
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">{{ mes }}</h1>
        <p class="mt-1 text-lg tabular-nums">R$ {{ resumo.recebido }} recebidos de R$ {{ resumo.previsto }} previstos</p>

        <p v-if="!perfilCompleto" class="mt-4 border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Complete seu perfil para emitir recibos.
            <Link href="/perfil" class="font-semibold text-carbono underline">Completar perfil</Link>
        </p>

        <section class="mt-8" aria-labelledby="bloco-enviar">
            <h2 id="bloco-enviar" class="mb-2 text-lg font-semibold">Para enviar <span class="font-estreita text-grafite/70">({{ paraEnviar.length }})</span></h2>
            <p v-if="!paraEnviar.length" class="text-sm text-grafite/80">
                Nada para enviar agora. Cobranças novas aparecem aqui {{ diasAntecedencia }} dias antes do vencimento.
            </p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in paraEnviar" :key="cobranca.id" :cobranca="cobranca">
                    <BotaoEnviar v-if="cobranca.recibo" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="bloco-aguardando">
            <h2 id="bloco-aguardando" class="mb-2 text-lg font-semibold">Aguardando pagamento <span class="font-estreita text-grafite/70">({{ aguardando.length }})</span></h2>
            <p v-if="!aguardando.length" class="text-sm text-grafite/80">Nenhuma cobrança enviada esperando pagamento.</p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in aguardando" :key="cobranca.id" :cobranca="cobranca">
                    <BaixaInline :cobranca-id="cobranca.id" />
                    <BotaoEnviar v-if="cobranca.recibo" rotulo="Reenviar" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="bloco-recebidas">
            <h2 id="bloco-recebidas" class="mb-2 text-lg font-semibold">Recebidas no mês <span class="font-estreita text-grafite/70">({{ recebidas.length }})</span></h2>
            <p v-if="!recebidas.length" class="text-sm text-grafite/80">Nenhum pagamento registrado neste mês.</p>
            <ul v-else class="divide-y divide-linha border border-linha bg-white">
                <LinhaCobranca v-for="cobranca in recebidas" :key="cobranca.id" :cobranca="cobranca">
                    <BotaoEnviar v-if="cobranca.recibo" rotulo="Enviar recibo pago" :cobranca-id="cobranca.id" :pdf-url="cobranca.recibo.pdf_url" :nome-arquivo="cobranca.recibo.pdf_nome" />
                </LinhaCobranca>
            </ul>
        </section>
    </CamadaApp>
</template>
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --filter="PainelTest|LoginTest"`
Esperado: PASS. Depois `npm run build` sem erro.

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: painel do mês com envio pelo celular e baixa de pagamento`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T5.2: Telas de cobranças (lista com filtros, avulsa, edição)

**Estado:** pendente

**Arquivos:**
- Criar: `resources/js/Pages/Cobrancas/Index.vue`, `resources/js/Pages/Cobrancas/Form.vue`
- Modificar: `app/Http/Controllers/CobrancaController.php` (métodos `index`, `create`, `edit`, `formulario`), `routes/web.php`
- Teste: `tests/Feature/Cobrancas/TelasCobrancaTest.php`

**Interfaces:**
- Consome: `GeradorDeCobrancas` (T3.6), `Cobranca::paraLista`/`editavel` (T3.4), componentes da T5.1
- Produz: `GET /cobrancas?mes=AAAA-MM&status=&cliente_id=`, `GET /cobrancas/create`, `GET /cobrancas/{cobranca}/edit`

- [ ] **Passo 1: escrever o teste que falha**

`tests/Feature/Cobrancas/TelasCobrancaTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TelasCobrancaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
    }

    private function cobranca(array $dados = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->cliente)->create($dados);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca;
    }

    public function test_lista_do_mes_atual_so_do_usuario(): void
    {
        $doMes = $this->cobranca(['vencimento' => '2026-10-15']);
        $this->cobranca(['vencimento' => '2026-11-15']);
        Cobranca::factory()->create(['vencimento' => '2026-10-15']);

        $this->actingAs($this->usuario)->get('/cobrancas')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cobrancas/Index')
                ->where('filtros.mes', '2026-10')
                ->has('cobrancas', 1)
                ->where('cobrancas.0.id', $doMes->id));
    }

    public function test_filtros_de_mes_situacao_e_cliente(): void
    {
        $outroCliente = Cliente::factory()->for($this->usuario)->create();
        $paga = $this->cobranca(['vencimento' => '2026-11-05']);
        $paga->status = StatusCobranca::Paga;
        $paga->pago_em = '2026-10-01';
        $paga->save();
        $this->cobranca(['vencimento' => '2026-11-06']);
        $deOutro = Cobranca::factory()->for($this->usuario)->for($outroCliente)->create(['vencimento' => '2026-11-07']);

        $this->actingAs($this->usuario);
        $this->get('/cobrancas?mes=2026-11&status=paga')
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1)->where('cobrancas.0.id', $paga->id));
        $this->get("/cobrancas?mes=2026-11&cliente_id={$outroCliente->id}")
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1)->where('cobrancas.0.id', $deOutro->id));
        $this->get('/cobrancas?mes=novembro')->assertSessionHasErrors('mes');
    }

    public function test_abrir_a_lista_gera_as_cobrancas_devidas(): void
    {
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create(['data_inicio' => '2026-10-05', 'dia_vencimento' => 5]);

        $this->actingAs($this->usuario)->get('/cobrancas')
            ->assertInertia(fn (Assert $page) => $page->has('cobrancas', 1));
    }

    public function test_formularios_de_avulsa_e_edicao(): void
    {
        $editavel = $this->cobranca();
        $enviada = $this->cobranca();
        $enviada->enviado_em = now();
        $enviada->save();
        $alheia = Cobranca::factory()->create();

        $this->actingAs($this->usuario);
        $this->get('/cobrancas/create')
            ->assertInertia(fn (Assert $page) => $page->component('Cobrancas/Form')->where('cobranca', null)->has('clientes', 1));
        $this->get("/cobrancas/{$editavel->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('cobranca.id', $editavel->id)->where('cobranca.valor', '260,60'));
        $this->get("/cobrancas/{$enviada->id}/edit")
            ->assertRedirect('/cobrancas')
            ->assertSessionHas('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        $this->get("/cobrancas/{$alheia->id}/edit")->assertNotFound();
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=TelasCobrancaTest`
Esperado: FAIL (405 no `GET /cobrancas`).

- [ ] **Passo 3: métodos de leitura no controller**

Acrescentar ao `CobrancaController` (com `use App\Models\Cobranca;`, `use App\Services\GeradorDeCobrancas;`, `use App\Support\Dinheiro;`, `use Carbon\CarbonImmutable;`, `use Illuminate\Validation\Rule;`, `use Inertia\Inertia;`, `use Inertia\Response;`):

```php
    public function index(Request $request, GeradorDeCobrancas $gerador): Response
    {
        $usuario = $request->user();
        $hoje = CarbonImmutable::today();
        $gerador->gerarPara($usuario, $hoje);

        $filtros = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
            'status' => ['nullable', Rule::in(['pendente', 'paga', 'cancelada'])],
            'cliente_id' => ['nullable', 'integer'],
        ]);

        $mes = CarbonImmutable::createFromFormat('Y-m-d', ($filtros['mes'] ?? $hoje->format('Y-m')).'-01')->startOfDay();

        $consulta = $usuario->cobrancas()->with(['cliente:id,nome', 'recibo'])
            ->whereBetween('vencimento', [$mes, $mes->endOfMonth()])
            ->orderBy('vencimento');

        if (! empty($filtros['status'])) {
            $consulta->where('status', $filtros['status']);
        }
        if (! empty($filtros['cliente_id'])) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
        }

        return Inertia::render('Cobrancas/Index', [
            'cobrancas' => $consulta->get()->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje)),
            'filtros' => [
                'mes' => $mes->format('Y-m'),
                'status' => $filtros['status'] ?? '',
                'cliente_id' => isset($filtros['cliente_id']) ? (int) $filtros['cliente_id'] : '',
            ],
            'clientes' => $usuario->clientes()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->formulario($request, null);
    }

    public function edit(Request $request, int $cobranca): Response|RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if (! $registro->editavel()) {
            return redirect('/cobrancas')->with('erro', 'Cobrança já enviada não pode ser editada. Cancele e crie uma cobrança avulsa.');
        }

        return $this->formulario($request, $registro);
    }

    private function formulario(Request $request, ?Cobranca $cobranca): Response
    {
        return Inertia::render('Cobrancas/Form', [
            'cobranca' => $cobranca ? [
                'id' => $cobranca->id,
                'de_contrato' => $cobranca->contrato_id !== null,
                'cliente_id' => $cobranca->cliente_id,
                'descricao' => $cobranca->descricao,
                'codigo_servico' => $cobranca->codigo_servico ?? '',
                'codigo_nbs' => $cobranca->codigo_nbs ?? '',
                'codigo_tributacao_nacional' => $cobranca->codigo_tributacao_nacional ?? '',
                'valor' => Dinheiro::formatar($cobranca->valor_centavos),
                'periodo_inicio' => $cobranca->periodo_inicio->toDateString(),
                'periodo_fim' => $cobranca->periodo_fim->toDateString(),
                'vencimento' => $cobranca->vencimento->toDateString(),
            ] : null,
            'clientes' => $request->user()->clientes()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }
```

- [ ] **Passo 4: rotas**

Em `routes/web.php`, junto das rotas de cobrança da T3.7:

```php
    Route::get('/cobrancas', [CobrancaController::class, 'index']);
    Route::get('/cobrancas/create', [CobrancaController::class, 'create']);
    Route::get('/cobrancas/{cobranca}/edit', [CobrancaController::class, 'edit']);
```

- [ ] **Passo 5: páginas**

`resources/js/Pages/Cobrancas/Index.vue`:

```vue
<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';
import LinhaCobranca from '../../Components/LinhaCobranca.vue';
import BotaoEnviar from '../../Components/BotaoEnviar.vue';
import BaixaInline from '../../Components/BaixaInline.vue';
import CancelarInline from '../../Components/CancelarInline.vue';

const props = defineProps({
    cobrancas: { type: Array, required: true },
    filtros: { type: Object, required: true },
    clientes: { type: Array, required: true },
});

const filtro = reactive({ ...props.filtros });

const filtrar = () => router.get('/cobrancas', filtro, { preserveState: true, replace: true });
const aberta = (cobranca) => cobranca.situacao === 'a_vencer' || cobranca.situacao === 'vencida';
const rotuloEnvio = (cobranca) => (cobranca.situacao === 'paga' ? 'Enviar recibo pago' : cobranca.enviado ? 'Reenviar' : 'Enviar');
</script>

<template>
    <CamadaApp>
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold">Cobranças</h1>
            <Link href="/cobrancas/create" class="botao">Nova cobrança avulsa</Link>
        </div>

        <form class="mb-6 grid gap-4 sm:grid-cols-4" @submit.prevent="filtrar">
            <Campo id="mes" rotulo="Mês do vencimento">
                <input id="mes" v-model="filtro.mes" type="month" class="entrada">
            </Campo>
            <Campo id="status" rotulo="Situação">
                <select id="status" v-model="filtro.status" class="entrada">
                    <option value="">Todas</option>
                    <option value="pendente">Pendentes</option>
                    <option value="paga">Pagas</option>
                    <option value="cancelada">Canceladas</option>
                </select>
            </Campo>
            <Campo id="cliente_id" rotulo="Cliente">
                <select id="cliente_id" v-model="filtro.cliente_id" class="entrada">
                    <option value="">Todos</option>
                    <option v-for="cliente in clientes" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
                </select>
            </Campo>
            <div class="flex items-end">
                <button class="botao-secundario w-full">Filtrar</button>
            </div>
        </form>

        <p v-if="!cobrancas.length" class="border-l-4 border-carbono bg-white px-4 py-3 text-sm">
            Nenhuma cobrança com vencimento neste mês e filtros.
        </p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <LinhaCobranca v-for="cobranca in cobrancas" :key="cobranca.id" :cobranca="cobranca">
                <a v-if="cobranca.recibo" :href="cobranca.recibo.pdf_url" target="_blank" rel="noopener" class="botao-secundario">Ver PDF</a>
                <BotaoEnviar
                    v-if="cobranca.recibo && cobranca.situacao !== 'cancelada'"
                    :rotulo="rotuloEnvio(cobranca)"
                    :cobranca-id="cobranca.id"
                    :pdf-url="cobranca.recibo.pdf_url"
                    :nome-arquivo="cobranca.recibo.pdf_nome"
                />
                <BaixaInline v-if="aberta(cobranca)" :cobranca-id="cobranca.id" />
                <Link v-if="cobranca.editavel" :href="`/cobrancas/${cobranca.id}/edit`" class="botao-secundario">Editar</Link>
                <CancelarInline v-if="aberta(cobranca)" :cobranca-id="cobranca.id" />
            </LinhaCobranca>
        </ul>
    </CamadaApp>
</template>
```

`resources/js/Pages/Cobrancas/Form.vue`:

```vue
<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';
import Campo from '../../Components/Campo.vue';

const props = defineProps({
    cobranca: { type: Object, default: null },
    clientes: { type: Array, required: true },
});

const hoje = new Date().toLocaleDateString('sv-SE');

const form = useForm(props.cobranca ?? {
    cliente_id: props.clientes[0]?.id ?? '',
    descricao: '',
    codigo_servico: '',
    codigo_nbs: '',
    codigo_tributacao_nacional: '',
    valor: '',
    periodo_inicio: hoje,
    periodo_fim: hoje,
    vencimento: hoje,
});

const travado = Boolean(props.cobranca?.de_contrato);

const salvar = () => (props.cobranca ? form.put(`/cobrancas/${props.cobranca.id}`) : form.post('/cobrancas'));
</script>

<template>
    <CamadaApp>
        <Link href="/cobrancas" class="font-estreita text-sm text-carbono underline">Voltar para cobranças</Link>
        <h1 class="mt-2 mb-2 text-2xl font-semibold">{{ cobranca ? 'Editar cobrança' : 'Nova cobrança avulsa' }}</h1>
        <p class="mb-6 text-sm text-grafite/80">
            {{ cobranca ? 'O recibo mantém o número e é refeito com os dados novos.' : 'O recibo é emitido junto, com o modelo do cliente.' }}
        </p>

        <form class="grid max-w-3xl gap-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="cliente_id" rotulo="Cliente" :erro="form.errors.cliente_id" class="sm:col-span-6">
                <select id="cliente_id" v-model="form.cliente_id" class="entrada" :disabled="travado" required>
                    <option v-for="cliente in clientes" :key="cliente.id" :value="cliente.id">{{ cliente.nome }}</option>
                </select>
            </Campo>
            <p v-if="travado" class="font-estreita text-xs text-grafite/70 sm:col-span-6">Cliente e período vêm do contrato.</p>
            <Campo id="descricao" rotulo="Descrição dos serviços (até 25 linhas)" :erro="form.errors.descricao" class="sm:col-span-6">
                <textarea id="descricao" v-model="form.descricao" rows="5" class="entrada" required />
            </Campo>
            <Campo id="codigo_servico" rotulo="Serviço prestado" :erro="form.errors.codigo_servico" class="sm:col-span-6">
                <input id="codigo_servico" v-model="form.codigo_servico" class="entrada">
            </Campo>
            <Campo id="codigo_nbs" rotulo="Código NBS" :erro="form.errors.codigo_nbs" class="sm:col-span-3">
                <input id="codigo_nbs" v-model="form.codigo_nbs" class="entrada">
            </Campo>
            <Campo id="codigo_tributacao_nacional" rotulo="Código de tributação nacional" :erro="form.errors.codigo_tributacao_nacional" class="sm:col-span-3">
                <input id="codigo_tributacao_nacional" v-model="form.codigo_tributacao_nacional" class="entrada">
            </Campo>
            <Campo id="valor" rotulo="Valor (R$)" :erro="form.errors.valor" class="sm:col-span-2">
                <input id="valor" v-model="form.valor" inputmode="decimal" placeholder="260,60" class="entrada" required>
            </Campo>
            <Campo id="vencimento" rotulo="Vencimento" :erro="form.errors.vencimento" class="sm:col-span-4">
                <input id="vencimento" v-model="form.vencimento" type="date" class="entrada" required>
            </Campo>
            <Campo id="periodo_inicio" rotulo="Início do período" :erro="form.errors.periodo_inicio" class="sm:col-span-3">
                <input id="periodo_inicio" v-model="form.periodo_inicio" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <Campo id="periodo_fim" rotulo="Fim do período" :erro="form.errors.periodo_fim" class="sm:col-span-3">
                <input id="periodo_fim" v-model="form.periodo_fim" type="date" class="entrada" :disabled="travado" required>
            </Campo>
            <div class="sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ cobranca ? 'Salvar cobrança' : 'Criar cobrança e recibo' }}</button>
            </div>
        </form>
    </CamadaApp>
</template>
```

(Campos `disabled` continuam no objeto do `useForm` e são enviados; o controller ignora cliente e período de cobrança de contrato — T3.7.)

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test`
Esperado: suíte inteira PASS. Depois `npm run build` sem erro.

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: lista de cobranças com filtros e formulários de avulsa e edição`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T5.3: PORTÃO-BANCO + smoke test + revisão visual

**Estado:** pendente

**Arquivos:** ajustes de estilo que a revisão apontar (Vue/CSS); `.claude/skills/recibos/SKILL.md`.

- [ ] **Passo 1:** PORTÃO-BANCO para o smoke test: avisar que serão criados, com a marca `SMOKE`, um cliente "SMOKE Cliente", um contrato e as cobranças/recibos gerados, além de sessões. Mostrar `db:show --counts`. Esperar "sim".
- [ ] **Passo 2: subir a aplicação**

```powershell
npm run build
php artisan serve
```

- [ ] **Passo 3: smoke test com o browser-agent** (não Playwright), com print de cada tela, em 1280 px e em 390 px de largura:
  1. Perfil: preencher e enviar logo PNG; conferir a prévia.
  2. Clientes: criar "SMOKE Cliente" (modelo WebISS) e "SMOKE Cliente Nacional" (modelo DANFSe).
  3. Contratos: um contrato para cada, com início no mês passado.
  4. Painel: as cobranças aparecem em "Para enviar"; "Ver PDF" abre os dois modelos.
  5. "Enviar" no desktop: baixa o PDF e a cobrança passa para "Aguardando pagamento".
  6. "Dar baixa" com data de hoje: vai para "Recebidas"; "Enviar recibo pago" baixa o PDF com carimbo PAGO.
  7. Cobranças: filtros de mês/situação/cliente; cobrança avulsa; editar não enviada (número mantido); cancelar (carimbo CANCELADO no PDF).
  8. Teclado: Tab percorre menu, filtros e botões com foco visível.
- [ ] **Passo 4: Web Share** — o compartilhamento nativo só aparece em HTTPS ou `localhost` no celular. No Laragon (`http://*.test`) o esperado é o download. Registrar como **[INCONCLUSIVO]** até haver ambiente HTTPS; testar no celular quando houver.
- [ ] **Passo 5: revisão visual (skill frontend-design)** — com os prints: conferir se o carimbo é o único elemento ousado, se não há cards genéricos nem rótulos em caixa-alta fora do carimbo, contraste e alinhamento à esquerda. Remover um "acessório" que não sirva ao uso. Aplicar ajustes e repetir os prints.
- [ ] **Passo 6:** mostrar os prints ao usuário; pedir validação da direção visual (aprovada provisoriamente na spec) e das fases 2–5. Atualizar a tabela de acompanhamento.
- [ ] **Passo 7:** perguntar se os dados `SMOKE` devem ser apagados; se sim, PORTÃO-BANCO para `DELETE` **pelos nomes com prefixo `SMOKE`** (cobranças e recibos antes dos clientes, por causa das chaves estrangeiras).
- [ ] **Passo 8:** registrar na skill o resultado do smoke test, as pegadinhas e os acertos.

---

## Fase 6 — Importação de XML de NFS-e (opcional)

O usuário vai gerar os XMLs e colocá-los em `MATERIAIS/`. Só a T6.2 depende do formato real dos arquivos. A T6.1 e a T6.3 podem andar antes, **se o usuário autorizar** — por padrão, a fase espera a T6.0.

### Tarefa T6.0: Entrada — XMLs de exemplo (BLOQUEADA)

**Estado:** pendente (bloqueada)

- [ ] **Passo 1:** conferir se `MATERIAIS/` tem pelo menos um XML do portal nacional e um do WebISS. Se não, parar e avisar o usuário.
- [ ] **Passo 2:** inspecionar a estrutura de cada XML (via `ctx_execute` com `python`): raiz, namespaces e caminho de cada campo usado (número, chave/código de verificação, data de emissão, competência, documento/nome/endereço do tomador, valor dos serviços, valor líquido).
- [ ] **Passo 3:** escrever a tabela "campo → caminho XPath" de cada padrão na skill do projeto e conferir cada valor contra o PDF correspondente (ex.: número `2026000000016`, valor `260,60`, tomador `14.029.219/0001-28`, competência `09/2026`).
- [ ] **Passo 4:** perguntar ao usuário se os XMLs podem ir para o repositório como fixtures de teste (contêm CPF e endereço dele) ou se devem ser anonimizados antes.
- [ ] **Passo 5:** **atualizar este plano** com o código completo e os testes da T6.2, baseados na tabela do passo 3, e pedir aprovação antes de executar.

---

### Tarefa T6.1: Tabela de notas fiscais + leitura segura de XML

**Estado:** pendente

**Arquivos:**
- Criar: `database/migrations/2026_10_09_100500_create_notas_fiscais_table.php`, `app/Models/NotaFiscal.php`, `app/Services/Nfse/XmlRecusado.php`, `app/Services/Nfse/LeitorXmlSeguro.php`, `app/Services/Nfse/DadosNfse.php`, `app/Services/Nfse/LeitorNfse.php`
- Modificar: `app/Models/User.php` (relação `notasFiscais`), `app/Models/Cobranca.php` (relação `notaFiscal`)
- Teste: `tests/Unit/LeitorXmlSeguroTest.php`

**Interfaces:**
- Produz:
  - `LeitorXmlSeguro::carregar(string $conteudo): SimpleXMLElement` — lança `XmlRecusado` (mensagem para a pessoa) se passar de 1 MB, tiver `DOCTYPE`/`ENTITY` ou não for XML
  - `final readonly class DadosNfse(string $padrao, string $numero, ?string $chave, ?CarbonImmutable $emitidaEm, ?string $competencia /* AAAA-MM */, ?string $tomadorDocumento, array $tomador /* chaves de Cliente::CAMPOS */, int $valorServicosCentavos, ?int $valorLiquidoCentavos)`
  - `interface LeitorNfse { reconhece(SimpleXMLElement $xml): bool; ler(SimpleXMLElement $xml): DadosNfse; }`
  - `NotaFiscal` (tabela `notas_fiscais`), `User::notasFiscais()`, `Cobranca::notaFiscal()`

- [ ] **Passo 1: escrever o teste que falha**

`tests/Unit/LeitorXmlSeguroTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\Nfse\LeitorXmlSeguro;
use App\Services\Nfse\XmlRecusado;
use PHPUnit\Framework\TestCase;

class LeitorXmlSeguroTest extends TestCase
{
    public function test_carrega_xml_simples(): void
    {
        $xml = (new LeitorXmlSeguro)->carregar('<nota><numero>123</numero></nota>');

        $this->assertSame('123', (string) $xml->numero);
    }

    public function test_recusa_doctype_com_entidade_externa(): void
    {
        $this->expectException(XmlRecusado::class);
        $this->expectExceptionMessage('XML com DOCTYPE ou entidades não é aceito.');

        (new LeitorXmlSeguro)->carregar('<?xml version="1.0"?><!DOCTYPE n [<!ENTITY x SYSTEM "file:///c:/windows/win.ini">]><n>&x;</n>');
    }

    public function test_recusa_o_que_nao_e_xml(): void
    {
        $this->expectException(XmlRecusado::class);
        $this->expectExceptionMessage('O arquivo não é um XML válido.');

        (new LeitorXmlSeguro)->carregar('isto não é xml');
    }

    public function test_recusa_arquivo_grande_demais(): void
    {
        $this->expectException(XmlRecusado::class);

        (new LeitorXmlSeguro)->carregar('<n>'.str_repeat('a', LeitorXmlSeguro::TAMANHO_MAXIMO).'</n>');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter=LeitorXmlSeguroTest`
Esperado: FAIL (classe inexistente).

- [ ] **Passo 3: implementar**

`app/Services/Nfse/XmlRecusado.php`:

```php
<?php

namespace App\Services\Nfse;

use RuntimeException;

/** Mensagem já escrita para a pessoa que enviou o arquivo. */
final class XmlRecusado extends RuntimeException {}
```

`app/Services/Nfse/LeitorXmlSeguro.php`:

```php
<?php

namespace App\Services\Nfse;

use SimpleXMLElement;

final class LeitorXmlSeguro
{
    public const TAMANHO_MAXIMO = 1_048_576; // 1 MB

    public function carregar(string $conteudo): SimpleXMLElement
    {
        if (strlen($conteudo) > self::TAMANHO_MAXIMO) {
            throw new XmlRecusado('O arquivo passa de 1 MB.');
        }

        // Nota fiscal não usa DOCTYPE; recusar fecha a porta para XXE e "billion laughs".
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $conteudo)) {
            throw new XmlRecusado('XML com DOCTYPE ou entidades não é aceito.');
        }

        $anterior = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($conteudo, SimpleXMLElement::class, LIBXML_NONET);

            if ($xml === false) {
                throw new XmlRecusado('O arquivo não é um XML válido.');
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }
}
```

`app/Services/Nfse/DadosNfse.php`:

```php
<?php

namespace App\Services\Nfse;

use Carbon\CarbonImmutable;

final readonly class DadosNfse
{
    /**
     * @param  array<string, string|null>  $tomador  chaves de Cliente::CAMPOS
     */
    public function __construct(
        public string $padrao,
        public string $numero,
        public ?string $chave,
        public ?CarbonImmutable $emitidaEm,
        public ?string $competencia,
        public ?string $tomadorDocumento,
        public array $tomador,
        public int $valorServicosCentavos,
        public ?int $valorLiquidoCentavos,
    ) {}
}
```

`app/Services/Nfse/LeitorNfse.php`:

```php
<?php

namespace App\Services\Nfse;

use SimpleXMLElement;

interface LeitorNfse
{
    public function reconhece(SimpleXMLElement $xml): bool;

    public function ler(SimpleXMLElement $xml): DadosNfse;
}
```

`database/migrations/2026_10_09_100500_create_notas_fiscais_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cobranca_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('padrao', 20);
            $table->string('numero', 30);
            $table->string('chave', 60)->nullable();
            $table->date('emitida_em')->nullable();
            $table->string('competencia', 7)->nullable();
            $table->string('tomador_documento', 14)->nullable();
            $table->json('tomador')->nullable();
            $table->unsignedBigInteger('valor_servicos_centavos');
            $table->unsignedBigInteger('valor_liquido_centavos')->nullable();
            $table->string('situacao', 20);
            $table->longText('xml');
            $table->timestamps();

            $table->unique(['user_id', 'padrao', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_fiscais');
    }
};
```

`app/Models/NotaFiscal.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'padrao', 'numero', 'chave', 'emitida_em', 'competencia', 'tomador_documento', 'tomador',
    'valor_servicos_centavos', 'valor_liquido_centavos', 'situacao', 'xml',
])]
class NotaFiscal extends Model
{
    protected $table = 'notas_fiscais';

    protected function casts(): array
    {
        return [
            'emitida_em' => 'immutable_date',
            'tomador' => 'array',
            'valor_servicos_centavos' => 'integer',
            'valor_liquido_centavos' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
```

(`cobranca_id` fica fora do `Fillable`: o vínculo só muda pela ação explícita de vincular.)

Em `app/Models/User.php`, acrescentar:

```php
    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class);
    }
```

Em `app/Models/Cobranca.php`, acrescentar (com `use Illuminate\Database\Eloquent\Relations\HasOne;` já existente):

```php
    public function notaFiscal(): HasOne
    {
        return $this->hasOne(NotaFiscal::class);
    }
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --filter=LeitorXmlSeguroTest`
Esperado: PASS (4 testes). Rodar também `php artisan test` (a migração nova não pode quebrar nada).

- [ ] **Passo 5: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: tabela de notas fiscais e leitura segura de XML`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T6.2: Leitores nacional e municipal

**Estado:** pendente (bloqueada pela T6.0)

**Arquivos (previstos):**
- Criar: `app/Services/Nfse/LeitorNfseNacional.php`, `app/Services/Nfse/LeitorNfseMunicipal.php`, `tests/Fixtures/nfse/nacional.xml`, `tests/Fixtures/nfse/webiss.xml`
- Modificar: `app/Providers/AppServiceProvider.php` (registro dos leitores com a tag `leitores-nfse`)
- Teste: `tests/Unit/LeitoresNfseTest.php`

**Interfaces:** cada leitor implementa `LeitorNfse` (T6.1) e devolve `DadosNfse` com `padrao` = `'nacional'` ou `'municipal'`. Registro (método `register` do `AppServiceProvider`):

```php
        $this->app->tag([LeitorNfseNacional::class, LeitorNfseMunicipal::class], 'leitores-nfse');
```

**O código e os testes desta tarefa só serão escritos depois da T6.0**, a partir da tabela de caminhos XPath medida nos XMLs reais. Escrever o parser antes seria supor o formato, o que a regra global proíbe. O plano será atualizado e aprovado antes de executar esta tarefa. Pontos já sabidos:
- registrar o namespace padrão antes do XPath (`registerXPathNamespace`), senão a consulta volta vazia;
- valores monetários do XML viram centavos com `Dinheiro::paraCentavos` só se o formato for "1234.56" — confirmar o formato real na T6.0;
- os testes vão comparar cada campo com o valor visível no PDF correspondente.

---

### Tarefa T6.3: Conciliação, vínculo, citação no recibo e criar cliente

**Estado:** pendente

**Arquivos:**
- Criar: `app/Services/Nfse/Conciliador.php`, `app/Services/Nfse/ImportadorNfse.php`, `app/Http/Controllers/NotaFiscalController.php`, `resources/js/Pages/NotasFiscais/Index.vue`
- Modificar: `app/Providers/AppServiceProvider.php` (binding do importador), `app/Services/RenderizadorDeRecibo.php` (citação), `app/Http/Controllers/ClienteController.php` (`create` pré-preenchido), `resources/js/Pages/Clientes/Form.vue` (prop `sugestao`), `resources/js/Layouts/CamadaApp.vue` (item "Notas fiscais"), `routes/web.php`
- Teste: `tests/Feature/Nfse/ConciliacaoTest.php`, `tests/Feature/Nfse/ImportacaoNfseTest.php`

**Interfaces:**
- Consome: `LeitorXmlSeguro`, `DadosNfse`, `LeitorNfse`, `NotaFiscal` (T6.1)
- Produz:
  - `Conciliador::avaliar(User $usuario, DadosNfse $nota): array{situacao: 'conciliada'|'divergente'|'sem_cobranca', candidatas: list<Cobranca>, divergencias: list<string>}`
  - `ImportadorNfse::importar(User $usuario, string $conteudo): NotaFiscal` (lança `XmlRecusado`)
  - Rotas `GET /notas-fiscais`, `POST /notas-fiscais` (campo `arquivos[]`), `POST /notas-fiscais/{nota}/vincular` (campo `cobranca_id`)
  - `GET /clientes/create?nota={id}` → prop `sugestao` com os dados do tomador

**Regras:** casamento por documento do tomador + mês (competência igual ao mês do início do período ou do vencimento) + valor. Cobranças canceladas não entram. O sistema só **sugere**; o vínculo é sempre confirmado pela pessoa. A nota vinculada aparece no recibo como dado vivo ("Referente à NFS-e nº …"). Importar nota **não** dá baixa.

- [ ] **Passo 1: escrever os testes que falham**

`tests/Feature/Nfse/ConciliacaoTest.php`:

```php
<?php

namespace Tests\Feature\Nfse;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\Nfse\Conciliador;
use App\Services\Nfse\DadosNfse;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConciliacaoTest extends TestCase
{
    use RefreshDatabase;

    private function nota(string $documento, int $valor, string $competencia = '2026-10'): DadosNfse
    {
        return new DadosNfse('nacional', '123', null, CarbonImmutable::parse('2026-10-09'), $competencia, $documento, ['nome' => 'Tomador'], $valor, $valor);
    }

    public function test_situacoes_de_conciliacao(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create(['documento' => '11222333000181']);
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create(['periodo_inicio' => '2026-10-01', 'valor_centavos' => 26060]);
        $conciliador = new Conciliador;

        $conciliada = $conciliador->avaliar($usuario, $this->nota('11222333000181', 26060));
        $this->assertSame('conciliada', $conciliada['situacao']);
        $this->assertTrue($conciliada['candidatas'][0]->is($cobranca));

        $divergente = $conciliador->avaliar($usuario, $this->nota('11222333000181', 25000));
        $this->assertSame('divergente', $divergente['situacao']);
        $this->assertSame(['Valor da nota R$ 250,00 × cobrança R$ 260,60'], $divergente['divergencias']);

        $this->assertSame('sem_cobranca', $conciliador->avaliar($usuario, $this->nota('99999999000191', 26060))['situacao']);
        $this->assertSame('sem_cobranca', $conciliador->avaliar($usuario, $this->nota('11222333000181', 26060, '2026-12'))['situacao']);

        $cobranca->status = StatusCobranca::Cancelada;
        $cobranca->save();
        $this->assertSame('sem_cobranca', $conciliador->avaliar($usuario, $this->nota('11222333000181', 26060))['situacao']);
    }
}
```

`tests/Feature/Nfse/ImportacaoNfseTest.php`:

```php
<?php

namespace Tests\Feature\Nfse;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use App\Services\Nfse\Conciliador;
use App\Services\Nfse\DadosNfse;
use App\Services\Nfse\ImportadorNfse;
use App\Services\Nfse\LeitorNfse;
use App\Services\Nfse\LeitorXmlSeguro;
use App\Services\RenderizadorDeRecibo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use SimpleXMLElement;
use Tests\TestCase;

class ImportacaoNfseTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $this->usuario = User::factory()->create();

        // Leitor de teste: independe do formato real dos portais (T6.2).
        $leitor = new class implements LeitorNfse
        {
            public function reconhece(SimpleXMLElement $xml): bool
            {
                return $xml->getName() === 'notaTeste';
            }

            public function ler(SimpleXMLElement $xml): DadosNfse
            {
                return new DadosNfse(
                    'nacional', (string) $xml->numero, null, CarbonImmutable::parse('2026-10-09'),
                    (string) $xml->competencia, (string) $xml->documento,
                    ['nome' => (string) $xml->nome, 'documento' => (string) $xml->documento],
                    (int) $xml->valor, (int) $xml->valor,
                );
            }
        };

        $this->app->instance(ImportadorNfse::class, new ImportadorNfse(new LeitorXmlSeguro, new Conciliador, [$leitor]));
    }

    private function xml(string $documento = '11222333000181', int $valor = 26060): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('nota.xml',
            "<notaTeste><numero>123</numero><competencia>2026-10</competencia><documento>{$documento}</documento><nome>Tomador Novo</nome><valor>{$valor}</valor></notaTeste>");
    }

    public function test_importa_avalia_e_lista(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create(['documento' => '11222333000181']);
        Cobranca::factory()->for($this->usuario)->for($cliente)->create();

        $this->actingAs($this->usuario)->post('/notas-fiscais', ['arquivos' => [$this->xml()]])
            ->assertSessionHas('sucesso', '1 nota importada.');

        $this->get('/notas-fiscais')->assertInertia(fn (Assert $page) => $page
            ->component('NotasFiscais/Index')
            ->has('notas', 1)
            ->where('notas.0.situacao', 'conciliada')
            ->has('notas.0.sugestoes', 1));
    }

    public function test_arquivo_recusado_informa_o_motivo(): void
    {
        $perigoso = UploadedFile::fake()->createWithContent('perigoso.xml', '<!DOCTYPE x [<!ENTITY a "b">]><x>&a;</x>');
        $estranho = UploadedFile::fake()->createWithContent('estranho.xml', '<outraCoisa/>');

        $this->actingAs($this->usuario)->post('/notas-fiscais', ['arquivos' => [$perigoso, $estranho]])
            ->assertSessionHas('erro', 'perigoso.xml: XML com DOCTYPE ou entidades não é aceito. estranho.xml: Este XML não é de uma NFS-e reconhecida (padrão nacional ou WebISS).');
        $this->assertSame(0, $this->usuario->notasFiscais()->count());
    }

    public function test_vincular_cita_a_nota_no_recibo(): void
    {
        $cliente = Cliente::factory()->for($this->usuario)->create(['documento' => '11222333000181']);
        $cobranca = Cobranca::factory()->for($this->usuario)->for($cliente)->create();
        $recibo = app(EmissorDeRecibos::class)->emitir($cobranca);
        $this->actingAs($this->usuario)->post('/notas-fiscais', ['arquivos' => [$this->xml()]]);
        $nota = $this->usuario->notasFiscais()->firstOrFail();

        $this->post("/notas-fiscais/{$nota->id}/vincular", ['cobranca_id' => $cobranca->id])
            ->assertSessionHas('sucesso', 'Nota vinculada à cobrança.');

        $dados = app(RenderizadorDeRecibo::class)->dados($recibo->fresh(), CarbonImmutable::now());
        $this->assertContains('Referente à NFS-e nº 123.', $dados['informacoes']);
    }

    public function test_nao_vincula_cobranca_de_outro_usuario(): void
    {
        $this->actingAs($this->usuario)->post('/notas-fiscais', ['arquivos' => [$this->xml()]]);
        $nota = $this->usuario->notasFiscais()->firstOrFail();

        $this->post("/notas-fiscais/{$nota->id}/vincular", ['cobranca_id' => Cobranca::factory()->create()->id])
            ->assertSessionHasErrors('cobranca_id');
    }

    public function test_tomador_desconhecido_pre_preenche_o_cliente(): void
    {
        $this->actingAs($this->usuario)->post('/notas-fiscais', ['arquivos' => [$this->xml('99999999000191')]]);
        $nota = $this->usuario->notasFiscais()->firstOrFail();

        $this->get("/clientes/create?nota={$nota->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Clientes/Form')
            ->where('sugestao.nome', 'Tomador Novo')
            ->where('sugestao.documento', '99999999000191'));
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --filter="ConciliacaoTest|ImportacaoNfseTest"`
Esperado: FAIL (classes/rotas inexistentes).

- [ ] **Passo 3: conciliador e importador**

`app/Services/Nfse/Conciliador.php`:

```php
<?php

namespace App\Services\Nfse;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use App\Support\Dinheiro;

final class Conciliador
{
    /** @return array{situacao: string, candidatas: list<Cobranca>, divergencias: list<string>} */
    public function avaliar(User $usuario, DadosNfse $nota): array
    {
        $doMesmoMes = $usuario->cobrancas()
            ->where('status', '!=', StatusCobranca::Cancelada)
            ->whereHas('cliente', fn ($consulta) => $consulta->where('documento', $nota->tomadorDocumento))
            ->get()
            ->filter(fn (Cobranca $cobranca) => $nota->competencia === null
                || in_array($nota->competencia, [$cobranca->periodo_inicio->format('Y-m'), $cobranca->vencimento->format('Y-m')], true))
            ->values();

        $mesmoValor = $doMesmoMes->filter(fn (Cobranca $cobranca) => $cobranca->valor_centavos === $nota->valorServicosCentavos)->values();

        if ($mesmoValor->isNotEmpty()) {
            return ['situacao' => 'conciliada', 'candidatas' => $mesmoValor->all(), 'divergencias' => []];
        }

        if ($doMesmoMes->isNotEmpty()) {
            return [
                'situacao' => 'divergente',
                'candidatas' => $doMesmoMes->all(),
                'divergencias' => $doMesmoMes->map(fn (Cobranca $cobranca) => 'Valor da nota R$ '.Dinheiro::formatar($nota->valorServicosCentavos)
                    .' × cobrança R$ '.Dinheiro::formatar($cobranca->valor_centavos))->all(),
            ];
        }

        return ['situacao' => 'sem_cobranca', 'candidatas' => [], 'divergencias' => []];
    }
}
```

`app/Services/Nfse/ImportadorNfse.php`:

```php
<?php

namespace App\Services\Nfse;

use App\Models\NotaFiscal;
use App\Models\User;

final class ImportadorNfse
{
    /** @param  list<LeitorNfse>  $leitores */
    public function __construct(
        private LeitorXmlSeguro $leitorXml,
        private Conciliador $conciliador,
        private array $leitores,
    ) {}

    public function importar(User $usuario, string $conteudo): NotaFiscal
    {
        $xml = $this->leitorXml->carregar($conteudo);

        foreach ($this->leitores as $leitor) {
            if (! $leitor->reconhece($xml)) {
                continue;
            }

            $dados = $leitor->ler($xml);

            // Reimportar a mesma nota atualiza em vez de duplicar.
            return $usuario->notasFiscais()->updateOrCreate(
                ['padrao' => $dados->padrao, 'numero' => $dados->numero],
                [
                    'chave' => $dados->chave,
                    'emitida_em' => $dados->emitidaEm,
                    'competencia' => $dados->competencia,
                    'tomador_documento' => $dados->tomadorDocumento,
                    'tomador' => $dados->tomador,
                    'valor_servicos_centavos' => $dados->valorServicosCentavos,
                    'valor_liquido_centavos' => $dados->valorLiquidoCentavos,
                    'situacao' => $this->conciliador->avaliar($usuario, $dados)['situacao'],
                    'xml' => $conteudo,
                ],
            );
        }

        throw new XmlRecusado('Este XML não é de uma NFS-e reconhecida (padrão nacional ou WebISS).');
    }

    /** Reconstrói os dados a partir do que foi gravado (para reavaliar a conciliação na tela). */
    public static function dadosDe(NotaFiscal $nota): DadosNfse
    {
        return new DadosNfse(
            $nota->padrao, $nota->numero, $nota->chave, $nota->emitida_em, $nota->competencia,
            $nota->tomador_documento, $nota->tomador ?? [], $nota->valor_servicos_centavos, $nota->valor_liquido_centavos,
        );
    }
}
```

No método `register` do `AppServiceProvider` (com os `use` de `ImportadorNfse`, `LeitorXmlSeguro`, `Conciliador`):

```php
        $this->app->bind(ImportadorNfse::class, fn ($app) => new ImportadorNfse(
            $app->make(LeitorXmlSeguro::class),
            $app->make(Conciliador::class),
            iterator_to_array($app->tagged('leitores-nfse')),
        ));
```

(Enquanto a T6.2 não registrar leitores, toda importação real responde "Este XML não é de uma NFS-e reconhecida…" — comportamento correto, não erro.)

- [ ] **Passo 4: controller, rotas e citação**

`app/Http/Controllers/NotaFiscalController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\NotaFiscal;
use App\Services\Nfse\Conciliador;
use App\Services\Nfse\ImportadorNfse;
use App\Services\Nfse\XmlRecusado;
use App\Support\Dinheiro;
use App\Support\Documento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotaFiscalController extends Controller
{
    public function index(Request $request, Conciliador $conciliador): Response
    {
        $usuario = $request->user();

        $notas = $usuario->notasFiscais()->with('cobranca.cliente')->latest()->get()
            ->map(function (NotaFiscal $nota) use ($usuario, $conciliador) {
                $avaliacao = $nota->cobranca_id ? null : $conciliador->avaliar($usuario, ImportadorNfse::dadosDe($nota));

                return [
                    'id' => $nota->id,
                    'numero' => $nota->numero,
                    'tomador' => $nota->tomador['nome'] ?? '-',
                    'documento' => Documento::formatar($nota->tomador_documento),
                    'valor' => Dinheiro::formatar($nota->valor_servicos_centavos),
                    'situacao' => $avaliacao['situacao'] ?? $nota->situacao,
                    'vinculada' => $nota->cobranca ? $nota->cobranca->cliente->nome.' — venc. '.$nota->cobranca->vencimento->format('d/m/Y') : null,
                    'divergencias' => $avaliacao['divergencias'] ?? [],
                    'sugestoes' => collect($avaliacao['candidatas'] ?? [])->map(fn (Cobranca $cobranca) => [
                        'id' => $cobranca->id,
                        'rotulo' => 'Venc. '.$cobranca->vencimento->format('d/m/Y').' — R$ '.Dinheiro::formatar($cobranca->valor_centavos),
                    ])->all(),
                    'cliente_existe' => $usuario->clientes()->where('documento', $nota->tomador_documento)->exists(),
                ];
            });

        return Inertia::render('NotasFiscais/Index', ['notas' => $notas]);
    }

    public function store(Request $request, ImportadorNfse $importador): RedirectResponse
    {
        $request->validate([
            'arquivos' => ['required', 'array', 'max:20'],
            'arquivos.*' => ['file', 'max:1024'],
        ]);

        $importadas = 0;
        $recusas = [];

        foreach ($request->file('arquivos') as $arquivo) {
            try {
                $importador->importar($request->user(), (string) file_get_contents($arquivo->getRealPath()));
                $importadas++;
            } catch (XmlRecusado $motivo) {
                $recusas[] = $arquivo->getClientOriginalName().': '.$motivo->getMessage();
            }
        }

        $resposta = back();
        if ($importadas > 0) {
            $resposta = $resposta->with('sucesso', $importadas === 1 ? '1 nota importada.' : "{$importadas} notas importadas.");
        }
        if ($recusas) {
            $resposta = $resposta->with('erro', implode(' ', $recusas));
        }

        return $resposta;
    }

    public function vincular(Request $request, int $nota): RedirectResponse
    {
        $registro = $request->user()->notasFiscais()->findOrFail($nota);
        $dados = $request->validate([
            'cobranca_id' => [
                'required',
                Rule::exists('cobrancas', 'id')->where('user_id', $request->user()->id),
                Rule::unique('notas_fiscais', 'cobranca_id')->ignore($registro->id),
            ],
        ]);

        $cobranca = $request->user()->cobrancas()->findOrFail($dados['cobranca_id']);
        $registro->cobranca_id = $cobranca->id;
        $registro->situacao = $cobranca->valor_centavos === $registro->valor_servicos_centavos ? 'conciliada' : 'divergente';
        $registro->save();

        return back()->with('sucesso', 'Nota vinculada à cobrança.');
    }
}
```

Rotas em `routes/web.php`, grupo autenticado (com `use App\Http\Controllers\NotaFiscalController;`):

```php
    Route::get('/notas-fiscais', [NotaFiscalController::class, 'index']);
    Route::post('/notas-fiscais', [NotaFiscalController::class, 'store']);
    Route::post('/notas-fiscais/{nota}/vincular', [NotaFiscalController::class, 'vincular']);
```

Citação no `RenderizadorDeRecibo::dados` — trocar a linha de `informacoes` por:

```php
            'informacoes' => array_values(array_filter([
                'Valor por extenso: '.$extenso.'.',
                $cobranca->notaFiscal ? 'Referente à NFS-e nº '.$cobranca->notaFiscal->numero.'.' : null,
            ])),
```

`ClienteController::create` passa a aceitar a nota de origem:

```php
    public function create(Request $request): Response
    {
        $nota = $request->filled('nota') ? $request->user()->notasFiscais()->find($request->integer('nota')) : null;

        return $this->formulario($request, null, $nota?->tomador);
    }
```

e `formulario` ganha o terceiro parâmetro `?array $sugestao = null`, repassado como prop `'sugestao' => $sugestao`. Em `Clientes/Form.vue`, acrescentar a prop `sugestao: { type: Object, default: null }` e trocar a inicialização dos campos por:

```js
    ...Object.fromEntries(campos.map((campo) => [campo, props.cliente?.[campo] ?? props.sugestao?.[campo] ?? ''])),
```

Em `CamadaApp.vue`, acrescentar à lista `itens`, depois de "Clientes": `{ href: '/notas-fiscais', rotulo: 'Notas fiscais' },`.

- [ ] **Passo 5: página**

`resources/js/Pages/NotasFiscais/Index.vue`:

```vue
<script setup>
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import CamadaApp from '../../Layouts/CamadaApp.vue';

defineProps({ notas: { type: Array, required: true } });

const envio = useForm({ arquivos: [] });
const escolhidas = reactive({});

const importar = () => envio.post('/notas-fiscais', { onSuccess: () => envio.reset() });
const vincular = (nota) => router.post(`/notas-fiscais/${nota.id}/vincular`, { cobranca_id: escolhidas[nota.id] }, { preserveScroll: true });

const textoSituacao = { conciliada: 'Confere com a cobrança', divergente: 'Diferente da cobrança', sem_cobranca: 'Sem cobrança correspondente' };
</script>

<template>
    <CamadaApp>
        <h1 class="text-2xl font-semibold">Notas fiscais</h1>
        <p class="mt-1 mb-6 text-sm text-grafite/80">Importe o XML baixado do portal para conferir com as cobranças. Importar não dá baixa.</p>

        <form class="mb-8 flex flex-wrap items-end gap-3" @submit.prevent="importar">
            <label class="flex flex-col font-estreita text-xs">
                Arquivos XML (até 20, 1 MB cada)
                <input type="file" accept=".xml,text/xml,application/xml" multiple @input="envio.arquivos = [...$event.target.files]">
            </label>
            <button class="botao" :disabled="envio.processing || !envio.arquivos.length">Importar</button>
            <p v-if="envio.errors.arquivos" class="w-full text-xs text-tinta-vermelha">{{ envio.errors.arquivos }}</p>
        </form>

        <p v-if="!notas.length" class="border-l-4 border-carbono bg-white px-4 py-3 text-sm">Nenhuma nota importada ainda.</p>

        <ul v-else class="divide-y divide-linha border border-linha bg-white">
            <li v-for="nota in notas" :key="nota.id" class="flex flex-col gap-2 px-4 py-3">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <span class="font-semibold">NFS-e nº {{ nota.numero }} — {{ nota.tomador }}</span>
                    <span class="tabular-nums">R$ {{ nota.valor }}</span>
                </div>
                <p class="font-estreita text-sm" :class="nota.situacao === 'conciliada' ? 'text-tinta-verde' : nota.situacao === 'divergente' ? 'text-tinta-vermelha' : 'text-grafite/80'">
                    {{ nota.vinculada ? `Vinculada: ${nota.vinculada}` : textoSituacao[nota.situacao] }}
                </p>
                <p v-for="divergencia in nota.divergencias" :key="divergencia" class="text-sm text-tinta-vermelha">{{ divergencia }}</p>
                <div v-if="!nota.vinculada && nota.sugestoes.length" class="flex flex-wrap items-end gap-2">
                    <select v-model="escolhidas[nota.id]" class="entrada max-w-xs" :aria-label="`Cobrança para a nota ${nota.numero}`">
                        <option v-for="sugestao in nota.sugestoes" :key="sugestao.id" :value="sugestao.id">{{ sugestao.rotulo }}</option>
                    </select>
                    <button type="button" class="botao-secundario" :disabled="!escolhidas[nota.id]" @click="vincular(nota)">Vincular à cobrança</button>
                </div>
                <Link v-if="!nota.cliente_existe" :href="`/clientes/create?nota=${nota.id}`" class="font-semibold text-carbono underline">
                    Cadastrar {{ nota.tomador }} como cliente
                </Link>
            </li>
        </ul>
    </CamadaApp>
</template>
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test`
Esperado: suíte inteira PASS. `npm run build` sem erro.

- [ ] **Passo 7: commit (se autorizado)**

```powershell
git add -A
git commit -m "feat: conciliação de NFS-e importada com cobranças e citação no recibo`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T6.4: PORTÃO-BANCO — migração da fase 6

**Estado:** pendente

- [ ] **Passo 1:** PORTÃO-BANCO para `php artisan migrate` (tabela `notas_fiscais`). Esperar "sim". Executar.
- [ ] **Passo 2:** com a T6.2 concluída, smoke test com o browser-agent importando os XMLs reais de `MATERIAIS/` (PORTÃO-BANCO próprio: cria linhas em `notas_fiscais`).
- [ ] **Passo 3:** validação do usuário; registrar na skill; atualizar a tabela de acompanhamento.

---

## Fase 7 — Portabilidade para PostgreSQL (futura)

### Tarefa T7.1: Suíte contra PostgreSQL de teste

**Estado:** pendente

- [ ] **Passo 1:** pedir ao usuário uma base PostgreSQL **de teste** (host, porta, banco, usuário). Nunca usar a de produção; confirmar com ele que a base pode ser apagada e recriada pelos testes (`RefreshDatabase` roda todas as migrações e limpa as tabelas).
- [ ] **Passo 2:** PORTÃO-BANCO para essa base (mostrar a conexão e o que será escrito).
- [ ] **Passo 3:** rodar a suíte com variáveis de ambiente da sessão (o `phpunit.xml` só define `DB_CONNECTION`/`DB_DATABASE` quando não há variável de ambiente — confirmar que as tags `<env>` não têm `force="true"`):

```powershell
$env:DB_CONNECTION = 'pgsql'; $env:DB_HOST = '<host>'; $env:DB_PORT = '<porta>'; $env:DB_DATABASE = '<banco_de_teste>'; $env:DB_USERNAME = '<usuario>'; $env:DB_PASSWORD = '<senha>'
php artisan test
```

- [ ] **Passo 4:** corrigir o que for específico de SQLite (esperado: nada). Registrar na skill.

---

## Execução

- Rodar `php artisan test` ao fim de cada tarefa (não só o filtro da tarefa) antes de marcá-la como `testada`.
- Uma tarefa só vira `validada` com confirmação explícita do usuário.
- Ao fim de **cada tarefa** (pedido do usuário em 2026-10-09): atualizar o estado na tabela de acompanhamento e no campo **Estado** da tarefa, e registrar na skill do projeto as decisões, pegadinhas e acertos da tarefa — tudo **no mesmo commit da tarefa**.
- Ao fim de cada **fase**: sugerir `/compact` ao usuário (regra de compactar contexto).

