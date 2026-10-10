# Pagamento no recibo e formulários melhores — Plano de implementação

> **Para agentes executores:** SUB-SKILL OBRIGATÓRIA: use superpowers:executing-plans (ou
> superpowers:subagent-driven-development) para executar este plano tarefa a tarefa. Os passos usam
> checkbox (`- [ ]`) para acompanhamento.

**Objetivo:** recibo que diz como pagar (QR PIX com valor, chave e "copia e cola", ou linha digitável do
boleto), contratos pré-pagos e pós-pagos, carimbo "VENCENDO HOJE", máscaras nos campos e busca de cliente
por autosugestão.

**Arquitetura:** segue o projeto existente (Laravel 13 + Inertia v3 + Vue 3 + dompdf). Regras puras em
`app/Support` e enums (testáveis sem banco); forma de pagamento é dado **vivo** da cobrança, lido pelo
`RenderizadorDeRecibo` na hora do PDF, como a situação. Máscaras e autosugestão são código do projeto
(diretiva Vue + funções puras testadas com o runner embutido do Node), sem biblioteca JS nova.

**Stack:** PHP 8.4.24 · Laravel 13.35 · Inertia v3 · Vue 3.5 · Tailwind 4 · barryvdh/laravel-dompdf 3.1.2 ·
PHPUnit 12 · Node 22.16 (`node --test`) · **novo:** `chillerlan/php-qrcode` (aprovado pelo usuário).

**Spec:** [docs/superpowers/specs/2026-10-10-pagamento-e-formularios-design.md](../../docs/superpowers/specs/2026-10-10-pagamento-e-formularios-design.md)
— base: [2026-10-09-sistema-recibos-design.md](../../docs/superpowers/specs/2026-10-09-sistema-recibos-design.md).
Skill do domínio: `.claude/skills/recibos/SKILL.md` (**ler inteira antes de começar**).

**Branch:** `feat/pagamento-e-formularios` (saído do `main` em `df9fd8d`).

---

## Acompanhamento

Estados: `pendente` → `concluída` (código pronto) → `testada` (verificação rodada e passando) →
`validada` (usuário confirmou). Atualizar esta tabela **e** o campo **Estado** da tarefa no commit da tarefa.

| Fase | Tarefa | Estado |
|---|---|---|
| 8 | T8.1 Situação "vence hoje" (carimbo laranja) | testada |
| 8 | T8.2 Máscaras nos campos | testada (navegador na T11.4) |
| 8 | T8.3 Busca de cliente por autosugestão | testada (teclado/leitor de tela na T11.4) |
| 8 | T8.4 Fechamento da fase 8 (merge + push) | concluída |
| 9 | T9.1 Calculadora: pós-pago | testada |
| 9 | T9.2 Modalidade no contrato | testada |
| 9 | T9.3 PORTÃO-BANCO + fechamento da fase 9 | concluída (migrate autorizado: lote 2; merge e push) |
| 10 | T10.1 Chaves PIX no perfil | testada (tela no smoke da T11.4) |
| 10 | T10.2 Forma de pagamento no contrato e na cobrança | testada (tela no smoke da T11.4) |
| 10 | T10.3 Trocar a forma de pagamento depois de enviada | pendente |
| 10 | T10.4 PORTÃO-BANCO + fechamento da fase 10 | pendente |
| 11 | T11.1 PIX "copia e cola" (BR Code) conferido com o Banco Central | pendente |
| 11 | T11.2 QR Code em PNG (`chillerlan/php-qrcode`) | pendente |
| 11 | T11.3 Bloco de pagamento nos PDFs | pendente |
| 11 | T11.4 Smoke test, revisão final e fechamento | pendente |

### Pendências para o final
- [ ] **V4** — conferência visual: carimbo VENCENDO HOJE (tela e PDFs), bloco de pagamento nos dois
  modelos, QR **escaneado num celular** abrindo o valor e o nome certos. — pendente
- (continuam valendo as pendências V1–V3, D1, L1, M1, M2 do plano `2026-10-09-sistema-recibos.md`)

---

## Restrições globais

Valem para todas as tarefas (além das do plano anterior, que continuam valendo: pt-BR, dinheiro em
centavos, SQL portátil, isolamento por relação + `findOrFail`, models com `#[Fillable]`, Inertia v3,
context-mode, PowerShell com `;`, documentação por tarefa, nunca supor):

- **Testes** rodam no SQLite em memória (`phpunit.xml`); usar `LazilyRefreshDatabase` e `$this->travelTo()`.
- **Commit por tarefa** só com `php artisan test` verde **e** `npm run build` sem erro; antes, `vendor/bin/pint --dirty --format agent`.
- **Escrita no banco real** (PostgreSQL de dev `207.180.205.211:1`, banco `recibos`, usuário `recibos_app`):
  só pelo PORTÃO-BANCO do plano anterior — `db:show --counts`, listar migrações pendentes, mostrar o comando
  literal, esperar o "sim" **daquele** comando, recontar em comando separado logo antes, executar e mostrar.
- **Fechamento de fase:** com todas as tarefas da fase `testada`, `git checkout main; git merge --no-ff
  feat/pagamento-e-formularios`, `git push origin main`, voltar ao branch (autorização permanente do
  usuário, memória `merge-e-push-por-fase`). Nunca force push.
- **Default de coluna** novo precisa estar também em `$attributes` do model (pegadinha da skill).
- **Layout dos PDFs:** alterar `webiss-v1`/`danfse-v1` direto (decisão do usuário: nenhum recibo foi
  enviado a cliente ainda). O teste "25 linhas cabem em 1 página" tem de continuar passando.
- **Hook de design:** sem `border-l-4`; avisos com borda inteira.
- **Isolamento:** toda FK escolhida pelo usuário (`cliente_id`, `chave_pix_id`) validada com `Rule::exists(...)->where('user_id', ...)`.

## Foco de revisão

1. **Chave PIX digitada de jeitos diferentes** (máscara, espaços, `+55`, e-mail com maiúsculas) → guardada
   como o DICT conhece, e o QR paga na chave certa — testes em **T10.1**.
2. **Nome e cidade do prestador com acento e longos** → BR Code em ASCII, cortado no limite, com CRC válido
   (senão o app do banco recusa o QR) — testes em **T11.1**.
3. **PIX sem chave, chave de outro usuário, chave excluída** → recusa clara; chave em uso não é excluída —
   testes em **T10.2**.
4. **Pós-pago com dia de vencimento igual ao último dia do período, ou 29–31** → vence estritamente depois do
   período, sem pular mês nem cair no mês errado — testes em **T9.1**.
5. **Recibo no limite (25 linhas) com PIX ou boleto** → continua em 1 página nos dois modelos — teste em **T11.3**.

---

## Fase 8 — Formulários e "vencendo hoje" (sem mudança no banco)

### Tarefa T8.1: Situação "vence hoje" (carimbo laranja)

**Estado:** testada (suíte 164/164; build ok)

**Arquivos:**
- Modificar: `app/Models/Cobranca.php` (`situacao`), `app/Services/RenderizadorDeRecibo.php` (`carimbo`),
  `resources/views/recibos/pdf/webiss-v1.blade.php`, `resources/views/recibos/pdf/danfse-v1.blade.php`,
  `resources/css/app.css`, `resources/js/Components/Carimbo.vue`, `resources/js/Pages/Cobrancas/Index.vue`
- Teste: `tests/Feature/Cobrancas/SituacaoCobrancaTest.php`, `tests/Feature/Recibos/RenderizadorDeReciboTest.php`

**Interfaces:** produz a situação `'vence_hoje'` em `Cobranca::situacao()` e `paraLista()['situacao']`;
carimbo do PDF `['texto' => 'VENCENDO HOJE', 'detalhe' => 'dd/mm/aaaa', 'classe' => 'vence_hoje']`.

- [ ] **Passo 1: testes que falham**

Em `SituacaoCobrancaTest::test_situacao_depende_do_status_e_do_vencimento`, trocar as duas primeiras
asserções por:

```php
        $this->assertSame('a_vencer', $cobranca->situacao($hoje->subDay()));
        $this->assertSame('vence_hoje', $cobranca->situacao($hoje), 'no dia do vencimento');
        $this->assertSame('vencida', $cobranca->situacao($hoje->addDay()));
```

Acrescentar a `RenderizadorDeReciboTest` (o `setUp` já fixa o relógio em 2026-10-09 10:00):

```php
    public function test_carimbo_vencendo_hoje(): void
    {
        $recibo = $this->recibo([], ['vencimento' => '2026-10-09']);

        $dados = $this->renderizador->dados($recibo, CarbonImmutable::now());

        $this->assertSame(['texto' => 'VENCENDO HOJE', 'detalhe' => '09/10/2026', 'classe' => 'vence_hoje'], $dados['carimbo']);
        $this->assertSame('Vence hoje', $dados['situacao']);
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter="SituacaoCobrancaTest|test_carimbo_vencendo_hoje"`
Esperado: FAIL (`'a_vencer'` no lugar de `'vence_hoje'`; `UnhandledMatchError` não acontece porque a
situação ainda não existe — o carimbo sai A VENCER).

- [ ] **Passo 3: implementar**

`Cobranca::situacao`:

```php
    public function situacao(CarbonImmutable $hoje): string
    {
        return match ($this->status) {
            StatusCobranca::Paga => 'paga',
            StatusCobranca::Cancelada => 'cancelada',
            StatusCobranca::Pendente => match (true) {
                $this->vencimento->lt($hoje->startOfDay()) => 'vencida',
                $this->vencimento->isSameDay($hoje) => 'vence_hoje',
                default => 'a_vencer',
            },
        };
    }
```

`RenderizadorDeRecibo::carimbo` — acrescentar o caso (antes de `'a_vencer'`):

```php
            'vence_hoje' => ['texto' => 'VENCENDO HOJE', 'detalhe' => $vencimento, 'classe' => 'vence_hoje', 'situacao' => 'Vence hoje'],
```

Nos dois templates, no `<style>`, depois de `.carimbo-vencida`:

```css
    .carimbo-vence_hoje { color: #C2410C; border-color: #C2410C; }
```

"VENCENDO HOJE" tem 13 letras e não cabe na largura do carimbo com a fonte atual. Trocar o `@include` do
carimbo:

- `webiss-v1.blade.php`:
```blade
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 330pt; left: 150pt; width: 230pt; font-size: '.($carimbo['classe'] === 'vence_hoje' ? '22' : '30').'pt; transform: rotate(-35deg);'])
```
- `danfse-v1.blade.php`:
```blade
    @include('recibos.pdf._carimbo', ['estilo' => 'top: 52pt; right: 14pt; width: 110pt; font-size: '.($carimbo['classe'] === 'vence_hoje' ? '10' : '14').'pt; transform: rotate(-8deg);'])
```

`resources/css/app.css`, no `@theme`, depois de `--color-tinta-vermelha` (contraste 5,18:1 sobre branco,
calculado pela fórmula de luminância da WCAG):

```css
    --color-tinta-laranja: #C2410C;
```

`Carimbo.vue`, no objeto `estilos`, depois de `a_vencer`:

```js
    vence_hoje: ['Vencendo hoje', 'border-tinta-laranja text-tinta-laranja'],
```

`Cobrancas/Index.vue` — a baixa e o cancelamento precisam continuar disponíveis no dia do vencimento:

```js
const aberta = (cobranca) => ['a_vencer', 'vence_hoje', 'vencida'].includes(cobranca.situacao);
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --compact --filter="SituacaoCobrancaTest|RenderizadorDeReciboTest|AmostrasVisuaisTest"`
Esperado: PASS. Depois `php artisan test --compact` e `npm run build` sem erro.

- [ ] **Passo 5: skill + commit**

Na skill, em Acertos: "T8.1: situação `vence_hoje` (vencimento = hoje) com carimbo laranja `#C2410C`
(contraste 5,18:1); fonte do carimbo menor para caber 'VENCENDO HOJE' (22pt WebISS, 10pt DANFSe); a lista de
cobranças trata `vence_hoje` como aberta (baixa/cancelar)."

```powershell
git add app resources tests .claude
git commit -m "feat: carimbo VENCENDO HOJE em laranja no dia do vencimento`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T8.2: Máscaras nos campos

**Estado:** testada (node --test 5/5; suíte PHP verde; build ok). Comportamento no navegador: smoke da T11.4.

**Arquivos:**
- Criar: `resources/js/mascaras.js`, `resources/js/mascaras.test.js`, `resources/js/diretivas/mascara.js`
- Modificar: `resources/js/app.js`, `resources/js/Pages/Clientes/Form.vue`, `resources/js/Components/CamposEndereco.vue`,
  `resources/js/Pages/Perfil/Editar.vue`, `resources/js/Pages/Contratos/Form.vue`, `resources/js/Pages/Cobrancas/Form.vue`

**Interfaces:** produz `formatadores` (`valor`, `documento`, `cpf`, `cnpj`, `cep`, `telefone`,
`linhaDigitavel`, `minusculas`) e a diretiva global `v-mascara="'nome'"`. T10.1 e T10.2 usam
`'cpf'`, `'cnpj'`, `'telefone'`, `'minusculas'` e `'linhaDigitavel'`.

O projeto não tem runner de testes JS; o Node 22 já instalado tem o `node:test` embutido (sem dependência
nova; o `package.json` já tem `"type": "module"`). O Vite não empacota o arquivo de teste (só entra o que
`app.js` importa).

- [ ] **Passo 1: teste que falha** — `resources/js/mascaras.test.js`:

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatadores as f } from './mascaras.js';

test('valor entra pelos centavos', () => {
    assert.equal(f.valor(''), '');
    assert.equal(f.valor('2'), '0,02');
    assert.equal(f.valor('26060'), '260,60');
    assert.equal(f.valor('260,60'), '260,60');
    assert.equal(f.valor('1.234,56'), '1.234,56');
    assert.equal(f.valor('R$ 1234567,89'), '1.234.567,89');
    assert.equal(f.valor('00012'), '0,12');
});

test('documento vira CPF até 11 dígitos e CNPJ (inclusive com letras) depois', () => {
    assert.equal(f.documento('529'), '529');
    assert.equal(f.documento('52998224725'), '529.982.247-25');
    assert.equal(f.documento('11222333000181'), '11.222.333/0001-81');
    assert.equal(f.documento('12abc34501de35'), '12.ABC.345/01DE-35');
    assert.equal(f.documento('11.222.333/0001-81999'), '11.222.333/0001-81');
});

test('cpf, cnpj, cep e telefone', () => {
    assert.equal(f.cpf('52998224725999'), '529.982.247-25');
    assert.equal(f.cnpj('11222333000181'), '11.222.333/0001-81');
    assert.equal(f.cep('49160000'), '49160-000');
    assert.equal(f.telefone('7932220000'), '(79) 3222-0000');
    assert.equal(f.telefone('79999990000'), '(79) 99999-0000');
    assert.equal(f.telefone('(79'), '(79');
});

test('linha digitável de boleto (47) e de arrecadação (48, começa com 8)', () => {
    assert.equal(
        f.linhaDigitavel('23793381286000000000300000000400184340000026060'),
        '23793.38128 60000.000003 00000.000400 1 84340000026060',
    );
    assert.equal(
        f.linhaDigitavel('846700000017435900240209024050002435842210108119'),
        '84670000001-7 43590024020-9 02405000243-5 84221010811-9',
    );
});

test('minúsculas sem espaços nas pontas', () => {
    assert.equal(f.minusculas(' Fulano@Exemplo.COM '), 'fulano@exemplo.com');
});
```

> Os números de boleto acima só testam a **máscara** (agrupamento), não o dígito verificador.

- [ ] **Passo 2: rodar e ver falhar**

Run: `node --test resources/js/mascaras.test.js`
Esperado: FAIL — `Cannot find module ... mascaras.js`.

- [ ] **Passo 3: funções puras** — `resources/js/mascaras.js`:

```js
const digitos = (texto) => String(texto ?? '').replace(/\D/g, '');

/** Preenche o molde: '0' e 'A' recebem um caractere; o resto é separador fixo. */
function aplicar(limpo, molde) {
    let saida = '';
    let indice = 0;

    for (const caractere of molde) {
        if (indice >= limpo.length) {
            break;
        }
        saida += caractere === '0' || caractere === 'A' ? limpo[indice++] : caractere;
    }

    return saida;
}

function cnpj(texto) {
    const limpo = String(texto ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);

    return aplicar(limpo, 'AA.AAA.AAA/AAAA-00');
}

/** Formatação enquanto digita. É conforto: o servidor normaliza e valida de novo. */
export const formatadores = {
    /** Dígitos entram pelos centavos: 2 → 0,02; 26060 → 260,60. */
    valor(texto) {
        const numeros = digitos(texto).replace(/^0+(?=\d)/, '');
        if (numeros === '') {
            return '';
        }
        const completo = numeros.padStart(3, '0');
        const inteiro = completo.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return `${inteiro},${completo.slice(-2)}`;
    },

    /** CPF enquanto tiver até 11 dígitos; depois CNPJ (alfanumérico desde jul/2026). */
    documento(texto) {
        const limpo = String(texto ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);

        return limpo.length <= 11 && /^\d*$/.test(limpo) ? aplicar(limpo, '000.000.000-00') : cnpj(limpo);
    },

    cpf: (texto) => aplicar(digitos(texto).slice(0, 11), '000.000.000-00'),

    cnpj,

    cep: (texto) => aplicar(digitos(texto).slice(0, 8), '00000-000'),

    telefone(texto) {
        const numeros = digitos(texto).slice(0, 11);

        return aplicar(numeros, numeros.length <= 10 ? '(00) 0000-0000' : '(00) 00000-0000');
    },

    /** Boleto bancário (47) ou arrecadação/convênio (48, começa com 8). */
    linhaDigitavel(texto) {
        const numeros = digitos(texto).slice(0, 48);

        return numeros.startsWith('8')
            ? aplicar(numeros, '00000000000-0 00000000000-0 00000000000-0 00000000000-0')
            : aplicar(numeros.slice(0, 47), '00000.00000 00000.000000 00000.000000 0 00000000000000');
    },

    minusculas: (texto) => String(texto ?? '').trim().toLowerCase(),
};
```

- [ ] **Passo 4: rodar e ver passar**

Run: `node --test resources/js/mascaras.test.js`
Esperado: PASS (5 testes).

- [ ] **Passo 5: diretiva** — `resources/js/diretivas/mascara.js`:

```js
import { formatadores } from '../mascaras.js';

function formatar(el) {
    const formatador = formatadores[el.dataset.mascara];
    if (!formatador) {
        return;
    }
    const formatado = formatador(el.value);
    if (formatado !== el.value) {
        el.value = formatado;
        // O v-model escuta 'input': assim ele guarda o valor já formatado. Na segunda passada nada muda.
        el.dispatchEvent(new Event('input'));
    }
}

/** Uso: v-mascara="'valor'". O cursor vai para o fim ao formatar — aceitável nesses campos. */
export default {
    mounted(el, { value }) {
        el.dataset.mascara = value;
        el.addEventListener('input', () => formatar(el));
        formatar(el);
    },
    updated(el, { value }) {
        el.dataset.mascara = value;
        formatar(el);
    },
};
```

`resources/js/app.js` — importar e registrar:

```js
import mascara from './diretivas/mascara.js';
```

```js
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('mascara', mascara)
            .mount(el);
```

- [ ] **Passo 6: aplicar nos campos** (acrescentar o atributo indicado ao `<input>` com o `id` citado):

| Arquivo | Campo (`id`) | Atributo |
|---|---|---|
| `Pages/Clientes/Form.vue` | `documento` | `v-mascara="'documento'"` |
| `Pages/Clientes/Form.vue` | `telefone` | `v-mascara="'telefone'" inputmode="tel"` |
| `Components/CamposEndereco.vue` | `cep` | `v-mascara="'cep'"` |
| `Pages/Perfil/Editar.vue` | `documento` | `v-mascara="'documento'"` |
| `Pages/Perfil/Editar.vue` | `telefone` | `v-mascara="'telefone'"` |
| `Pages/Contratos/Form.vue` | `valor` | `v-mascara="'valor'"` (o `placeholder="260,60"` fica) |
| `Pages/Cobrancas/Form.vue` | `valor` | `v-mascara="'valor'"` |

As datas continuam `type="date"`.

- [ ] **Passo 7: verificar**

Run: `node --test resources/js/mascaras.test.js; php artisan test --compact; npm run build`
Esperado: tudo verde (nenhum teste PHP muda: o servidor já aceita "1.234,56", "529.982.247-25",
"49160-000"). O comportamento no navegador (digitar, colar, editar valor existente) é conferido no smoke
test da T11.4.

- [ ] **Passo 8: skill + commit**

Na skill, em Acertos: "T8.2: máscaras = funções puras em `resources/js/mascaras.js` (testes:
`node --test resources/js/mascaras.test.js`, runner embutido do Node 22, sem dependência) + diretiva global
`v-mascara`. Valor entra pelos centavos (digitar 260 dá 2,60 — comportamento de máscara monetária). A diretiva
dispara `input` para o `v-model` guardar o valor formatado."

```powershell
git add resources .claude
git commit -m "feat: máscaras de valor, CPF/CNPJ, CEP e telefone nos formulários`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T8.3: Busca de cliente por autosugestão

**Estado:** testada (suíte 167/167; build ok). Combobox no navegador: smoke da T11.4.

**Arquivos:**
- Criar: `resources/js/Components/BuscaCliente.vue`
- Modificar: `app/Http/Controllers/ClienteController.php` (método `busca`), `routes/web.php`,
  `app/Http/Controllers/ContratoController.php` (`formulario`), `app/Http/Controllers/CobrancaController.php`
  (`index`, `formulario`), `resources/js/Pages/Contratos/Form.vue`, `resources/js/Pages/Cobrancas/Form.vue`,
  `resources/js/Pages/Cobrancas/Index.vue`
- Teste: `tests/Feature/ClientesTest.php`, `tests/Feature/Cobrancas/TelasCobrancaTest.php`

**Interfaces:**
- `GET /clientes/busca?q=` → JSON `[{id: int, nome: string, documento: string formatado}]`, no máximo 10.
- Props novas: `Contratos/Form` e `Cobrancas/Form` recebem `temClientes: bool` e `clienteAtual: {id, nome}|null`
  (a prop `clientes` sai); `Cobrancas/Index` recebe `clienteFiltro: {id, nome}|null` (a prop `clientes` sai).
- `<BuscaCliente id v-model inicial obrigatorio desativado vazio />`.

- [ ] **Passo 1: testes que falham** — acrescentar a `tests/Feature/ClientesTest.php`:

```php
    public function test_busca_por_nome_ou_documento_so_entre_os_clientes_do_usuario(): void
    {
        $usuario = User::factory()->create();
        $ana = Cliente::factory()->for($usuario)->create(['nome' => 'Ana Martins Comércio', 'documento' => '11222333000181']);
        Cliente::factory()->for($usuario)->create(['nome' => 'Bruno Lima']);
        Cliente::factory()->create(['nome' => 'Ana de Outro Usuário']);
        $this->actingAs($usuario);

        $this->getJson('/clientes/busca?q=ANA')->assertOk()->assertExactJson([
            ['id' => $ana->id, 'nome' => 'Ana Martins Comércio', 'documento' => '11.222.333/0001-81'],
        ]);
        $this->getJson('/clientes/busca?q=11.222')->assertJsonCount(1)->assertJsonPath('0.id', $ana->id);
        $this->getJson('/clientes/busca?q=a')->assertExactJson([]);
    }

    public function test_busca_devolve_no_maximo_dez_e_trata_curinga_como_texto(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->count(12)->sequence(fn ($sequencia) => ['nome' => "Cliente {$sequencia->index}"])->create();
        $this->actingAs($usuario);

        $this->getJson('/clientes/busca?q=cliente')->assertJsonCount(10);
        $this->getJson('/clientes/busca?q='.urlencode('%%'))->assertExactJson([]);
    }

    public function test_busca_exige_login(): void
    {
        $this->getJson('/clientes/busca?q=ana')->assertUnauthorized();
    }
```

(`ClientesTest` já importa `Cliente` e `User`; conferir os `use` ao colar.)

Em `TelasCobrancaTest::test_formularios_de_avulsa_e_edicao`, trocar `->has('clientes', 1)` por
`->where('temClientes', true)->where('clienteAtual', null)` e, na edição, acrescentar
`->where('clienteAtual.id', $this->cliente->id)`.

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter="test_busca|test_formularios_de_avulsa_e_edicao"`
Esperado: FAIL (404 em `/clientes/busca`; prop `temClientes` inexistente).

- [ ] **Passo 3: endpoint**

`ClienteController` (acrescentar `use Illuminate\Http\JsonResponse;`):

```php
    /** Autosugestão: nome (sem diferença de maiúsculas) ou começo do documento; só clientes do usuário. */
    public function busca(Request $request): JsonResponse
    {
        $termo = trim((string) $request->query('q', ''));

        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        // % e _ digitados são texto, não curinga.
        $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($termo));
        $documento = Documento::normalizar($termo);

        $clientes = $request->user()->clientes()
            ->where(function ($consulta) use ($literal, $documento) {
                $consulta->whereRaw("lower(nome) like ? escape '\\'", ["%{$literal}%"]);
                if ($documento !== '') {
                    $consulta->orWhere('documento', 'like', "{$documento}%");
                }
            })
            ->orderBy('nome')->limit(10)->get(['id', 'nome', 'documento']);

        return response()->json($clientes->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'documento' => Documento::formatar($cliente->documento),
        ]));
    }
```

> `lower()` do SQLite só converte ASCII; no PostgreSQL converte tudo. Acentos contam na busca (limitação
> aceita na spec, 6.2).

`routes/web.php`, **antes** de `Route::resource('clientes', ...)`:

```php
    Route::get('/clientes/busca', [ClienteController::class, 'busca']);
```

- [ ] **Passo 4: props dos formulários e da lista**

`ContratoController::formulario` — trocar a linha `'clientes' => ...` por:

```php
            'temClientes' => $request->user()->clientes()->exists(),
            'clienteAtual' => $contrato?->cliente()->first(['id', 'nome']),
```

`CobrancaController::formulario` — idem:

```php
            'temClientes' => $request->user()->clientes()->exists(),
            'clienteAtual' => $cobranca?->cliente()->first(['id', 'nome']),
```

`CobrancaController::index` — trocar `'clientes' => ...` por:

```php
            'clienteFiltro' => empty($filtros['cliente_id']) ? null : $usuario->clientes()->find($filtros['cliente_id'], ['id', 'nome']),
```

- [ ] **Passo 5: componente** — `resources/js/Components/BuscaCliente.vue`:

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
});

const emit = defineEmits(['update:modelValue']);

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
        const resposta = await fetch(`/clientes/busca?q=${encodeURIComponent(termo)}`, {
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

function escolher(cliente) {
    texto.value = cliente.nome;
    emit('update:modelValue', cliente.id);
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

watch(() => props.inicial, (cliente) => {
    texto.value = cliente?.nome ?? '';
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
                v-for="(cliente, indice) in sugestoes"
                :id="`${id}-opcao-${indice}`"
                :key="cliente.id"
                role="option"
                :aria-selected="indice === ativo"
                class="cursor-pointer px-3 py-2 text-sm"
                :class="indice === ativo ? 'bg-papel text-carbono' : ''"
                @mousedown.prevent="escolher(cliente)"
                @mouseenter="ativo = indice"
            >
                <span class="font-semibold">{{ cliente.nome }}</span>
                <span class="ml-2 font-estreita text-xs text-grafite/70">{{ cliente.documento }}</span>
            </li>
            <li v-if="!sugestoes.length" class="px-3 py-2 text-sm text-grafite/70">Nenhum cliente encontrado.</li>
        </ul>
    </div>
</template>
```

- [ ] **Passo 6: usar nas páginas**

`Contratos/Form.vue`:
- props: trocar `clientes: { type: Array, required: true }` por
  `temClientes: { type: Boolean, required: true }, clienteAtual: { type: Object, default: null }`;
- `useForm`: `cliente_id: ''` (no lugar de `props.clientes[0]?.id ?? ''`);
- `<p v-if="!clientes.length" ...>` → `<p v-if="!temClientes" ...>`;
- trocar o `<select id="cliente_id" ...>...</select>` por
  `<BuscaCliente id="cliente_id" v-model="form.cliente_id" :inicial="clienteAtual" obrigatorio />`;
- `import BuscaCliente from '../../Components/BuscaCliente.vue';`

`Cobrancas/Form.vue`: as mesmas trocas, com `:desativado="travado"` no `BuscaCliente`. No formulário
novo, sem clientes, mostrar o mesmo aviso do contrato:

```vue
        <p v-if="!temClientes" class="border border-linha bg-white px-4 py-3 text-sm">
            Cadastre um cliente antes. <Link href="/clientes/create" class="font-semibold text-carbono underline">Novo cliente</Link>
        </p>
```

(e `<form v-else ...>`).

`Cobrancas/Index.vue`:
- props: trocar `clientes` por `clienteFiltro: { type: Object, default: null }`;
- no filtro, trocar o `<select id="cliente_id">` por
  `<BuscaCliente id="cliente_id" v-model="filtro.cliente_id" :inicial="clienteFiltro" vazio="Todos os clientes" />`;
- importar `BuscaCliente`.

- [ ] **Passo 7: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde. (Teclado e leitor de tela do combobox: smoke test da T11.4.)

- [ ] **Passo 8: skill + commit**

Na skill: "T8.3: `GET /clientes/busca?q=` (2+ caracteres, até 10, `lower(nome) like ? escape '\'` portátil,
curingas escapados, prefixo do documento); `BuscaCliente.vue` é um combobox ARIA (setas, Enter, Esc;
`mousedown.prevent` na opção para o `blur` não fechar antes do clique). Formulários recebem `temClientes`
e `clienteAtual` no lugar da lista de clientes."

```powershell
git add app routes resources tests .claude
git commit -m "feat: busca de cliente por autosugestão nos contratos e cobranças`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T8.4: Fechamento da fase 8

**Estado:** concluída (merge no main e push)

- [ ] Conferir: T8.1–T8.3 `testada`; `php artisan test --compact`, `node --test resources/js/mascaras.test.js` e `npm run build` verdes.
- [ ] Merge e push (autorização permanente):

```powershell
git checkout main; git merge --no-ff feat/pagamento-e-formularios -m "merge: fase 8 — máscaras, autosugestão de cliente e vencendo hoje`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"; if ($?) { git push origin main }; git checkout feat/pagamento-e-formularios
```

- [ ] Atualizar a tabela (T8.4 `concluída`) e o ledger.

---

## Fase 9 — Pré-pago e pós-pago

### Tarefa T9.1: Calculadora — pós-pago

**Estado:** testada (CalculadoraDePeriodosTest + GeradorDeCobrancasTest 16/16; suíte verde)

**Arquivos:**
- Criar: `app/Enums/Modalidade.php`
- Modificar: `app/Services/CalculadoraDePeriodos.php`
- Teste: `tests/Unit/CalculadoraDePeriodosTest.php`

**Interfaces:**
- `enum Modalidade: string { Prepago = 'prepago'; Pospago = 'pospago' }` com `rotulo()` e `opcoes()`.
- `CalculadoraDePeriodos::periodo(..., int $indice, Modalidade $modalidade = Modalidade::Prepago): Periodo`
- `CalculadoraDePeriodos::devidos(..., CarbonImmutable $hoje, Modalidade $modalidade = Modalidade::Prepago): array`
  (o padrão mantém todos os chamadores e testes atuais).

- [ ] **Passo 1: testes que falham** — acrescentar a `CalculadoraDePeriodosTest` (com `use App\Enums\Modalidade;`):

```php
    public function test_pos_pago_vence_no_primeiro_dia_escolhido_depois_do_periodo(): void
    {
        $calc = new CalculadoraDePeriodos;
        $vence = fn (string $inicio, int $dia, Periodicidade $periodicidade = Periodicidade::Mensal) => $calc
            ->periodo($this->data($inicio), $periodicidade, $dia, 0, Modalidade::Pospago)->vencimento->toDateString();

        $this->assertSame('2026-11-10', $vence('2026-10-01', 10), 'serviço de outubro vence em novembro');
        $this->assertSame('2026-10-20', $vence('2026-09-15', 20), 'primeiro dia 20 depois de 14/10');
        $this->assertSame('2026-11-10', $vence('2026-09-15', 10), 'dia 10/10 é antes do fim do período');
        $this->assertSame('2026-11-15', $vence('2026-09-16', 15), 'o próprio dia do fim não vale: estritamente depois');
        $this->assertSame('2026-02-28', $vence('2026-01-01', 31), 'dia 31 em fevereiro vira o último dia');
        $this->assertSame('2027-01-10', $vence('2026-10-01', 10, Periodicidade::Trimestral), 'trimestre out–dez vence em janeiro');
    }

    public function test_pos_pago_so_e_devido_perto_do_vencimento(): void
    {
        $devidos = (new CalculadoraDePeriodos)->devidos(
            $this->data('2026-09-01'), null, Periodicidade::Mensal, 10, 10, $this->data('2026-10-09'), Modalidade::Pospago,
        );

        // Setembro vence 10/10 (devido desde 30/09); outubro vence 10/11 (ainda não).
        $this->assertSame(['2026-10-10'], array_map(fn ($periodo) => $periodo->vencimento->toDateString(), $devidos));
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter=CalculadoraDePeriodosTest`
Esperado: FAIL — `Class "App\Enums\Modalidade" not found`.

- [ ] **Passo 3: implementar**

`app/Enums/Modalidade.php`:

```php
<?php

namespace App\Enums;

enum Modalidade: string
{
    case Prepago = 'prepago';
    case Pospago = 'pospago';

    public function rotulo(): string
    {
        return match ($this) {
            self::Prepago => 'Pré-pago',
            self::Pospago => 'Pós-pago',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $modalidade) => ['valor' => $modalidade->value, 'rotulo' => $modalidade->rotulo()], self::cases());
    }
}
```

`CalculadoraDePeriodos` (acrescentar `use App\Enums\Modalidade;`):

```php
    public function periodo(
        CarbonImmutable $dataInicio,
        Periodicidade $periodicidade,
        int $diaVencimento,
        int $indice,
        Modalidade $modalidade = Modalidade::Prepago,
    ): Periodo {
        // Sempre a partir da data original: 31/01 → 28/02 → 31/03, sem escorregar para o dia 28.
        $inicio = $dataInicio->startOfDay()->addMonthsNoOverflow($indice * $periodicidade->meses());
        $fim = $dataInicio->startOfDay()->addMonthsNoOverflow(($indice + 1) * $periodicidade->meses())->subDay();

        $vencimento = $modalidade === Modalidade::Pospago
            ? $this->primeiroDiaDepois($fim, $diaVencimento)
            : $this->diaNoMes($inicio, $diaVencimento);

        return new Periodo($inicio, $fim, $vencimento);
    }

    /** O dia escolhido no mês da data, limitado ao último dia do mês. */
    private function diaNoMes(CarbonImmutable $data, int $dia): CarbonImmutable
    {
        $mes = $data->startOfMonth();

        return $mes->setDay(min($dia, $mes->daysInMonth));
    }

    /** Pós-pago: primeira data com o dia escolhido estritamente depois do fim do período. */
    private function primeiroDiaDepois(CarbonImmutable $fim, int $dia): CarbonImmutable
    {
        $candidato = $this->diaNoMes($fim, $dia);

        return $candidato->gt($fim) ? $candidato : $this->diaNoMes($fim->startOfMonth()->addMonthNoOverflow(), $dia);
    }
```

Em `devidos`, acrescentar o parâmetro `Modalidade $modalidade = Modalidade::Prepago` ao final e passá-lo:
`$this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice, $modalidade)`.

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --compact --filter=CalculadoraDePeriodosTest`
Esperado: PASS (os testes antigos continuam passando com o padrão pré-pago).

- [ ] **Passo 5: skill + commit**

Na skill: "T9.1: pós-pago = primeira data com o dia escolhido **estritamente** depois do fim do período
(limitada ao último dia do mês); pré-pago inalterado (padrão do parâmetro)."

```powershell
git add app tests .claude
git commit -m "feat: vencimento pós-pago na calculadora de períodos`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T9.2: Modalidade no contrato

**Estado:** testada (suíte 171/171; build ok)

**Arquivos:**
- Criar: `database/migrations/2026_10_10_100000_add_modalidade_to_contratos_table.php`
- Modificar: `app/Models/Contrato.php`, `app/Http/Requests/ContratoRequest.php`, `app/Http/Controllers/ContratoController.php`,
  `app/Services/GeradorDeCobrancas.php`, `database/factories/ContratoFactory.php`, `lang/pt_BR/validation.php`,
  `resources/js/Pages/Contratos/Form.vue`
- Teste: `tests/Feature/ContratosTest.php`

**Interfaces:** `contratos.modalidade` (string, default `prepago`), cast `Modalidade`; prop `modalidades` no
formulário; erro `modalidade` quando o contrato com cobranças tenta mudar.

- [ ] **Passo 1: testes que falham** — em `ContratosTest` acrescentar `'modalidade' => 'prepago'` ao array do
helper `dados()` e os testes (com `use App\Enums\Modalidade; use App\Models\Cobranca; use App\Services\GeradorDeCobrancas; use Carbon\CarbonImmutable;` se faltarem):

```php
    public function test_contrato_pos_pago_gera_cobranca_que_vence_depois_do_periodo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
        [$usuario, $cliente] = $this->usuarioComCliente();
        $usuario->update(['dias_antecedencia' => 10]);

        $this->actingAs($usuario)->post('/contratos', $this->dados($cliente, [
            'modalidade' => 'pospago', 'data_inicio' => '2026-10-01', 'dia_vencimento' => 10,
        ]))->assertRedirect('/contratos');

        app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::today());

        $cobranca = $usuario->cobrancas()->sole();
        $this->assertSame(
            ['2026-10-01', '2026-10-31', '2026-11-10'],
            [$cobranca->periodo_inicio->toDateString(), $cobranca->periodo_fim->toDateString(), $cobranca->vencimento->toDateString()],
        );
    }

    public function test_modalidade_invalida_e_recusada_e_nao_muda_com_cobrancas(): void
    {
        [$usuario, $cliente] = $this->usuarioComCliente();
        $this->actingAs($usuario);
        $this->post('/contratos', $this->dados($cliente, ['modalidade' => 'quinzenal']))->assertSessionHasErrors('modalidade');

        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        Cobranca::factory()->for($usuario)->for($cliente)->create(['contrato_id' => $contrato->id]);

        $this->put("/contratos/{$contrato->id}", $this->dados($cliente, ['modalidade' => 'pospago']))
            ->assertSessionHasErrors(['modalidade' => 'Este contrato já tem cobranças. Para mudar a modalidade, encerre-o e crie outro.']);
        $this->assertSame(Modalidade::Prepago, $contrato->fresh()->modalidade);
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter=ContratosTest`
Esperado: FAIL (coluna `modalidade` inexistente / sem erro de validação).

- [ ] **Passo 3: migração** — `database/migrations/2026_10_10_100000_add_modalidade_to_contratos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            // Contratos existentes ficam pré-pagos: o vencimento deles não muda.
            $table->string('modalidade', 10)->default('prepago');
        });
    }

    public function down(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('modalidade');
        });
    }
};
```

- [ ] **Passo 4: model, request, controller, gerador, factory**

`Contrato`: acrescentar `'modalidade'` ao `#[Fillable]`; no `casts()`, `'modalidade' => Modalidade::class`;
e `protected $attributes = ['modalidade' => 'prepago'];` (com `use App\Enums\Modalidade;`).

`ContratoRequest::rules()`: `'modalidade' => ['required', Rule::enum(Modalidade::class)],`.

`ContratoController::update` — depois do bloco de `$mudaPeriodos`:

```php
        if ($registro->modalidade->value !== $dados['modalidade'] && $registro->cobrancas()->exists()) {
            return back()->withErrors([
                'modalidade' => 'Este contrato já tem cobranças. Para mudar a modalidade, encerre-o e crie outro.',
            ]);
        }
```

`ContratoController::formulario`: no array do contrato, `'modalidade' => $contrato->modalidade->value,`; nas
props, `'modalidades' => Modalidade::opcoes(),`.

`GeradorDeCobrancas::gerarPara`: passar `$contrato->modalidade` como último argumento de `devidos(...)`.

`ContratoFactory::definition()`: `'modalidade' => Modalidade::Prepago,`.

`lang/pt_BR/validation.php`, em `attributes`: `'modalidade' => 'modalidade',`.

- [ ] **Passo 5: formulário** — `Contratos/Form.vue`: prop `modalidades: { type: Array, required: true }`;
`useForm` com `modalidade: 'prepago'`; e, depois do campo de periodicidade:

```vue
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
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde.

- [ ] **Passo 7: skill + commit**

```powershell
git add app database lang resources tests .claude
git commit -m "feat: contrato pré-pago ou pós-pago`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T9.3: PORTÃO-BANCO + fechamento da fase 9

**Estado:** concluída — `migrate` autorizado pelo usuário em 2026-10-10 (contratos = 3 antes e depois; lote 2); merge no main e push.

- [ ] `php artisan db:show --counts` e `php artisan migrate:status` (leitura); mostrar ao usuário: conexão
  (PostgreSQL de **desenvolvimento**), contagem de `contratos`, a migração pendente
  `2026_10_10_100000_add_modalidade_to_contratos_table` e o comando literal `php artisan migrate`.
  Efeito: coluna nova com default `prepago` em todas as linhas; nenhuma linha apagada.
- [ ] Esperar o "sim" **deste** comando. Recontar `contratos` em comando separado; se mudou, parar e avisar.
- [ ] Executar `php artisan migrate` e mostrar o resultado e o `migrate:status`.
- [ ] Merge + push da fase 9 (mesmo comando da T8.4, mensagem `merge: fase 9 — contrato pré-pago e pós-pago`).
- [ ] Tabela e ledger atualizados.

---

## Fase 10 — Chaves PIX e forma de pagamento

### Tarefa T10.1: Chaves PIX no perfil

**Estado:** testada (suíte 178/178; build ok). Ajustes: regras do DICT 8.5; `authorize()` do request devolve 404 para chave alheia.

**Arquivos:**
- Criar: `app/Enums/TipoChavePix.php`, `app/Models/ChavePix.php`, `database/factories/ChavePixFactory.php`,
  `database/migrations/2026_10_10_100100_create_chaves_pix_table.php`, `app/Http/Requests/ChavePixRequest.php`,
  `app/Http/Controllers/ChavePixController.php`, `resources/js/Components/ChavesPix.vue`
- Modificar: `app/Models/User.php`, `app/Http/Controllers/PerfilController.php`, `app/Providers/AppServiceProvider.php`,
  `routes/web.php`, `lang/pt_BR/validation.php`, `resources/js/Pages/Perfil/Editar.vue`
- Teste: `tests/Unit/TipoChavePixTest.php`, `tests/Feature/ChavesPixTest.php`

**Interfaces:**
- `TipoChavePix::normalizar(?string): ?string` (null = inválida para o tipo), `formatar(string): string`,
  `rotulo(): string`, `opcoes()`.
- `ChavePix` (tabela `chaves_pix`): `tipo` (cast enum), `chave`, `apelido`; `rotulo(): string`
  ("apelido ou tipo — chave formatada"). `User::chavesPix(): HasMany`, `User::opcoesDeChavePix(): list<array{id, rotulo}>`.
- Rotas `POST /perfil/chaves-pix`, `PUT|DELETE /perfil/chaves-pix/{chave}`.

- [x] **Passo 1: verificar o CNPJ alfanumérico no DICT** — **feito em 2026-10-10:** Manual Operacional do DICT v8.5
  (29/07/2026), seção 1: CNPJ alfanumérico (14 caracteres, sem pontos/traços) e telefone **só celular** (E.164). Código
  e testes abaixo já ajustados. — procurar na documentação do Banco Central (ctx_fetch_and_index
  na página do Pix em bcb.gov.br / regulamento do DICT) se chave do tipo CNPJ aceita o formato alfanumérico.
  - Confirmado que aceita → em `TipoChavePix::normalizar`, o caso CNPJ usa `Documento::normalizar` (aceita letras)
    e o teste que espera `null` para `12.ABC.345/01DE-35` passa a esperar `'12ABC34501DE35'`.
  - Não confirmado → manter **só numérico** (como no código abaixo) e registrar **[INCONCLUSIVO]** na skill.

- [ ] **Passo 2: testes que falham**

`tests/Unit/TipoChavePixTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Enums\TipoChavePix;
use PHPUnit\Framework\TestCase;

class TipoChavePixTest extends TestCase
{
    public function test_normaliza_cada_tipo_como_o_dict_conhece(): void
    {
        $this->assertSame('52998224725', TipoChavePix::Cpf->normalizar('529.982.247-25'));
        $this->assertSame('11222333000181', TipoChavePix::Cnpj->normalizar('11.222.333/0001-81'));
        $this->assertSame('fulano@exemplo.com', TipoChavePix::Email->normalizar(' Fulano@Exemplo.com '));
        $this->assertSame('+5579999990000', TipoChavePix::Telefone->normalizar('(79) 99999-0000'));
        $this->assertSame('+5579999990000', TipoChavePix::Telefone->normalizar('+55 79 99999-0000'));
        $this->assertSame('123e4567-e12b-12d1-a456-426655440000', TipoChavePix::Aleatoria->normalizar('123E4567-E12B-12D1-A456-426655440000'));
    }

    public function test_recusa_chave_que_nao_serve_para_o_tipo(): void
    {
        $this->assertNull(TipoChavePix::Cpf->normalizar('111.111.111-11'));
        $this->assertNull(TipoChavePix::Cpf->normalizar('11.222.333/0001-81'));
        $this->assertSame('12ABC34501DE35', TipoChavePix::Cnpj->normalizar('12.abc.345/01de-35'), 'CNPJ alfanumérico (Manual do DICT 8.5)');
        $this->assertNull(TipoChavePix::Cnpj->normalizar('12.ABC.345/01DE-36'), 'dígito verificador errado');
        $this->assertNull(TipoChavePix::Telefone->normalizar('(79) 3222-0000'), 'só celular (E.164 no DICT)');
        $this->assertNull(TipoChavePix::Email->normalizar('sem-arroba'));
        $this->assertNull(TipoChavePix::Telefone->normalizar('9999-0000'));
        $this->assertNull(TipoChavePix::Aleatoria->normalizar('nao-e-uuid'));
    }

    public function test_formata_para_exibir(): void
    {
        $this->assertSame('529.982.247-25', TipoChavePix::Cpf->formatar('52998224725'));
        $this->assertSame('+55 (79) 99999-0000', TipoChavePix::Telefone->formatar('+5579999990000'));
        $this->assertSame('fulano@exemplo.com', TipoChavePix::Email->formatar('fulano@exemplo.com'));
    }
}
```

`tests/Feature/ChavesPixTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\ChavePix;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChavesPixTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cadastra_chave_normalizada_e_lista_no_perfil(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->post('/perfil/chaves-pix', ['tipo' => 'telefone', 'chave' => '(79) 99999-0000', 'apelido' => 'Celular'])
            ->assertSessionHas('sucesso', 'Chave PIX cadastrada.');

        $this->assertSame('+5579999990000', $usuario->chavesPix()->sole()->chave);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('chavesPix', 1)
            ->where('chavesPix.0.chave', '+55 (79) 99999-0000')
            ->where('chavesPix.0.apelido', 'Celular'));
    }

    public function test_chave_invalida_para_o_tipo_e_recusada(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/perfil/chaves-pix', ['tipo' => 'cpf', 'chave' => '111.111.111-11'])
            ->assertSessionHasErrors(['chave' => 'Informe uma chave válida para o tipo escolhido.']);

        $this->assertSame(0, ChavePix::count());
    }

    public function test_chave_repetida_so_e_recusada_para_o_mesmo_usuario(): void
    {
        $usuario = User::factory()->create();
        ChavePix::factory()->for($usuario)->create(['tipo' => 'email', 'chave' => 'a@exemplo.com']);

        $this->actingAs($usuario)->post('/perfil/chaves-pix', ['tipo' => 'email', 'chave' => 'A@Exemplo.com'])
            ->assertSessionHasErrors(['chave' => 'Esta chave já está cadastrada.']);
        $this->actingAs(User::factory()->create())->post('/perfil/chaves-pix', ['tipo' => 'email', 'chave' => 'a@exemplo.com'])
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso');
    }

    public function test_edita_e_exclui_so_a_propria_chave(): void
    {
        $usuario = User::factory()->create();
        $minha = ChavePix::factory()->for($usuario)->create();
        $alheia = ChavePix::factory()->create();
        $this->actingAs($usuario);

        $this->put("/perfil/chaves-pix/{$minha->id}", ['tipo' => 'cpf', 'chave' => '52998224725', 'apelido' => 'Pessoal'])
            ->assertSessionHas('sucesso', 'Chave PIX atualizada.');
        $this->assertSame('52998224725', $minha->fresh()->chave);

        $this->put("/perfil/chaves-pix/{$alheia->id}", ['tipo' => 'cpf', 'chave' => '52998224725'])->assertNotFound();
        $this->delete("/perfil/chaves-pix/{$alheia->id}")->assertNotFound();

        $this->delete("/perfil/chaves-pix/{$minha->id}")->assertSessionHas('sucesso', 'Chave PIX excluída.');
        $this->assertNull($minha->fresh());
    }
}
```

- [ ] **Passo 3: rodar e ver falhar**

Run: `php artisan test --compact --filter="TipoChavePixTest|ChavesPixTest"`
Esperado: FAIL (classes e rotas inexistentes).

- [ ] **Passo 4: enum** — `app/Enums/TipoChavePix.php`:

```php
<?php

namespace App\Enums;

use App\Support\Documento;

enum TipoChavePix: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Email = 'email';
    case Telefone = 'telefone';
    case Aleatoria = 'aleatoria';

    public function rotulo(): string
    {
        return match ($this) {
            self::Cpf => 'CPF',
            self::Cnpj => 'CNPJ',
            self::Email => 'E-mail',
            self::Telefone => 'Telefone',
            self::Aleatoria => 'Chave aleatória',
        };
    }

    /** A chave como o DICT a conhece; null quando não serve para o tipo. */
    public function normalizar(?string $valor): ?string
    {
        $valor = trim((string) $valor);

        return match ($this) {
            self::Cpf => self::documento($valor, 11),
            // Manual Operacional do DICT 8.5, seção 1: CNPJ alfanumérico, 14 caracteres, sem pontos ou traços.
            self::Cnpj => self::documento($valor, 14),
            self::Email => filter_var($valor, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($valor) <= 77 ? mb_strtolower($valor) : null,
            self::Telefone => self::telefone($valor),
            self::Aleatoria => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $valor) === 1 ? mb_strtolower($valor) : null,
        };
    }

    public function formatar(string $chave): string
    {
        return match ($this) {
            self::Cpf, self::Cnpj => Documento::formatar($chave),
            self::Telefone => (string) preg_replace('/^\+55(\d{2})(\d{5})(\d{4})$/', '+55 ($1) $2-$3', $chave),
            default => $chave,
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $tipo) => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()], self::cases());
    }

    private static function documento(string $valor, int $tamanho): ?string
    {
        $documento = Documento::normalizar($valor);

        return strlen($documento) === $tamanho && Documento::valido($documento) ? $documento : null;
    }

    private static function telefone(string $valor): ?string
    {
        $digitos = (string) preg_replace('/\D/', '', $valor);

        if (strlen($digitos) >= 12 && str_starts_with($digitos, '55')) {
            $digitos = substr($digitos, 2);
        }

        // DICT: só telefone celular, padrão E.164 (+55 + DDD + 9 dígitos começando com 9).
        return strlen($digitos) === 11 && $digitos[2] === '9' ? '+55'.$digitos : null;
    }
}
```

- [ ] **Passo 5: migração, model, factory**

`database/migrations/2026_10_10_100100_create_chaves_pix_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chaves_pix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 20);
            $table->string('chave', 77);
            $table->string('apelido', 60)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'chave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chaves_pix');
    }
};
```

`app/Models/ChavePix.php`:

```php
<?php

namespace App\Models;

use App\Enums\TipoChavePix;
use Database\Factories\ChavePixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tipo', 'chave', 'apelido'])]
class ChavePix extends Model
{
    /** @use HasFactory<ChavePixFactory> */
    use HasFactory;

    protected $table = 'chaves_pix';

    protected function casts(): array
    {
        return ['tipo' => TipoChavePix::class];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Como aparece nas listas de escolha: "Nubank PJ — 11.222.333/0001-81". */
    public function rotulo(): string
    {
        return ($this->apelido ?: $this->tipo->rotulo()).' — '.$this->tipo->formatar($this->chave);
    }
}
```

`database/factories/ChavePixFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\TipoChavePix;
use App\Models\ChavePix;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChavePix> */
class ChavePixFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tipo' => TipoChavePix::Email,
            'chave' => fake()->unique()->safeEmail(),
            'apelido' => null,
        ];
    }
}
```

`User` (com `use App\Models\ChavePix;` se necessário — mesmo namespace):

```php
    public function chavesPix(): HasMany
    {
        return $this->hasMany(ChavePix::class);
    }

    /** @return list<array{id: int, rotulo: string}> */
    public function opcoesDeChavePix(): array
    {
        return $this->chavesPix()->orderBy('id')->get()
            ->map(fn (ChavePix $chave) => ['id' => $chave->id, 'rotulo' => $chave->rotulo()])
            ->all();
    }
```

- [ ] **Passo 6: request, controller, rotas**

`app/Http/Requests/ChavePixRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Enums\TipoChavePix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChavePixRequest extends FormRequest
{
    /** Chave de outro usuário vira 404 antes da validação (senão o erro de unicidade revelaria que ela existe). */
    public function authorize(): bool
    {
        $chave = $this->route('chave');

        abort_if($chave !== null && ! $this->user()->chavesPix()->whereKey($chave)->exists(), 404);

        return true;
    }

    protected function prepareForValidation(): void
    {
        $tipo = TipoChavePix::tryFrom((string) $this->input('tipo'));

        $this->merge(['chave' => $tipo?->normalizar($this->input('chave'))]);
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoChavePix::class)],
            'chave' => [
                'required',
                Rule::unique('chaves_pix', 'chave')->where('user_id', $this->user()->id)->ignore($this->route('chave')),
            ],
            'apelido' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'chave.required' => 'Informe uma chave válida para o tipo escolhido.',
            'chave.unique' => 'Esta chave já está cadastrada.',
        ];
    }
}
```

`app/Http/Controllers/ChavePixController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChavePixRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChavePixController extends Controller
{
    public function store(ChavePixRequest $request): RedirectResponse
    {
        $request->user()->chavesPix()->create($request->validated());

        return back()->with('sucesso', 'Chave PIX cadastrada.');
    }

    public function update(ChavePixRequest $request, int $chave): RedirectResponse
    {
        $request->user()->chavesPix()->findOrFail($chave)->update($request->validated());

        return back()->with('sucesso', 'Chave PIX atualizada.');
    }

    public function destroy(Request $request, int $chave): RedirectResponse
    {
        $request->user()->chavesPix()->findOrFail($chave)->delete();

        return back()->with('sucesso', 'Chave PIX excluída.');
    }
}
```

`routes/web.php` (com `use App\Http\Controllers\ChavePixController;`), junto das rotas de perfil:

```php
    Route::post('/perfil/chaves-pix', [ChavePixController::class, 'store']);
    Route::put('/perfil/chaves-pix/{chave}', [ChavePixController::class, 'update']);
    Route::delete('/perfil/chaves-pix/{chave}', [ChavePixController::class, 'destroy']);
```

`AppServiceProvider::boot`: acrescentar `'chave'` à lista do `Route::pattern` numérico.

`lang/pt_BR/validation.php`, em `attributes`: `'tipo' => 'tipo', 'chave' => 'chave', 'apelido' => 'apelido',`.

`PerfilController::edit` — acrescentar às props:

```php
            'chavesPix' => $usuario->chavesPix()->orderBy('id')->get()->map(fn (ChavePix $chave) => [
                'id' => $chave->id,
                'tipo' => $chave->tipo->value,
                'tipo_rotulo' => $chave->tipo->rotulo(),
                'chave' => $chave->tipo->formatar($chave->chave),
                'apelido' => $chave->apelido ?? '',
            ]),
            'tiposChavePix' => TipoChavePix::opcoes(),
```

(com `use App\Enums\TipoChavePix; use App\Models\ChavePix;`).

- [ ] **Passo 7: tela** — `resources/js/Components/ChavesPix.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Campo from './Campo.vue';

defineProps({
    chaves: { type: Array, required: true },
    tipos: { type: Array, required: true },
});

const mascaras = { cpf: 'cpf', cnpj: 'cnpj', telefone: 'telefone', email: 'minusculas', aleatoria: 'minusculas' };
const editando = ref(null); // id da chave em edição, 'nova' para inclusão
const confirmando = ref(null);
const form = useForm({ tipo: 'cpf', chave: '', apelido: '' });

function abrir(chave = null) {
    form.clearErrors();
    form.tipo = chave?.tipo ?? 'cpf';
    form.chave = chave?.chave ?? '';
    form.apelido = chave?.apelido ?? '';
    editando.value = chave ? chave.id : 'nova';
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'nova') {
        form.post('/perfil/chaves-pix', opcoes);
    } else {
        form.put(`/perfil/chaves-pix/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/chaves-pix/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});
</script>

<template>
    <section id="chaves-pix" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-chaves-pix">
        <h2 id="titulo-chaves-pix" class="text-sm font-semibold">Chaves PIX</h2>
        <p class="text-sm text-grafite/80">As chaves aparecem para escolha nos contratos e cobranças; o recibo mostra o QR Code da chave escolhida.</p>

        <ul v-if="chaves.length" class="divide-y divide-linha border border-linha bg-white">
            <li v-for="chave in chaves" :key="chave.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div>
                    <p class="font-semibold">{{ chave.apelido || chave.tipo_rotulo }}</p>
                    <p class="font-estreita text-sm text-grafite/80">{{ chave.tipo_rotulo }} · {{ chave.chave }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="botao-secundario" @click="abrir(chave)">Editar</button>
                    <button v-if="confirmando !== chave.id" type="button" class="botao-secundario" @click="confirmando = chave.id">Excluir</button>
                    <template v-else>
                        <button type="button" class="botao-secundario border-tinta-vermelha text-tinta-vermelha" @click="excluir(chave.id)">Confirmar exclusão</button>
                        <button type="button" class="botao-secundario" @click="confirmando = null">Manter chave</button>
                    </template>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-grafite/80">Nenhuma chave cadastrada.</p>

        <form v-if="editando" class="grid gap-4 border border-linha bg-white p-4 sm:grid-cols-6" @submit.prevent="salvar">
            <Campo id="tipo_chave" rotulo="Tipo" :erro="form.errors.tipo" class="sm:col-span-2">
                <select id="tipo_chave" v-model="form.tipo" class="entrada">
                    <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                </select>
            </Campo>
            <Campo id="chave" rotulo="Chave" :erro="form.errors.chave" class="sm:col-span-4">
                <input id="chave" v-model="form.chave" v-mascara="mascaras[form.tipo]" class="entrada" required>
            </Campo>
            <Campo id="apelido" rotulo="Apelido (opcional, ex.: Nubank PJ)" :erro="form.errors.apelido" class="sm:col-span-6">
                <input id="apelido" v-model="form.apelido" maxlength="60" class="entrada">
            </Campo>
            <div class="flex flex-wrap gap-2 sm:col-span-6">
                <button class="botao" :disabled="form.processing">{{ editando === 'nova' ? 'Cadastrar chave' : 'Salvar chave' }}</button>
                <button type="button" class="botao-secundario" @click="editando = null">Voltar</button>
            </div>
        </form>
        <div v-else>
            <button type="button" class="botao-secundario" @click="abrir()">Nova chave PIX</button>
        </div>
    </section>
</template>
```

`Pages/Perfil/Editar.vue`: props `chavesPix: { type: Array, required: true }, tiposChavePix: { type: Array, required: true }`;
importar `ChavesPix`; depois do `</form>` do perfil:

```vue
        <ChavesPix class="mt-10" :chaves="chavesPix" :tipos="tiposChavePix" />
```

- [ ] **Passo 8: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde.

- [ ] **Passo 9: skill + commit**

Na skill: "T10.1: chaves PIX por usuário (`chaves_pix`, único por usuário+chave); normalização no enum
`TipoChavePix` (CPF/CNPJ só dígitos com DV, e-mail minúsculo, telefone `+55DDDNÚMERO`, aleatória UUID
minúsculo) antes da validação de unicidade. CNPJ alfanumérico: [resultado do passo 1]."

```powershell
git add app database lang resources routes tests .claude
git commit -m "feat: chaves PIX no perfil do prestador`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T10.2: Forma de pagamento no contrato e na cobrança

**Estado:** testada (suíte 183/183; build ok)

**Arquivos:**
- Criar: `app/Enums/FormaPagamento.php`, `app/Http/Requests/Concerns/RegrasDePagamento.php`,
  `database/migrations/2026_10_10_100200_add_pagamento_to_contratos_e_cobrancas.php`,
  `resources/js/Components/CamposPagamento.vue`
- Modificar: `app/Models/Contrato.php`, `app/Models/Cobranca.php`, `app/Models/ChavePix.php`,
  `app/Http/Requests/ContratoRequest.php`, `app/Http/Requests/CobrancaRequest.php`,
  `app/Http/Controllers/ContratoController.php`, `app/Http/Controllers/CobrancaController.php`,
  `app/Http/Controllers/ChavePixController.php`, `app/Services/GeradorDeCobrancas.php`, `lang/pt_BR/validation.php`,
  `resources/js/Pages/Contratos/Form.vue`, `resources/js/Pages/Cobrancas/Form.vue`
- Teste: `tests/Feature/FormaPagamentoTest.php`

**Interfaces:**
- `enum FormaPagamento: string { Pix = 'pix'; Boleto = 'boleto'; Nenhuma = 'nenhuma' }` com `rotulo()`, `opcoes()`.
- Colunas: `contratos.forma_pagamento`, `contratos.chave_pix_id`; `cobrancas.forma_pagamento`,
  `cobrancas.chave_pix_id`, `cobrancas.linha_digitavel` (só dígitos).
- `Contrato::chavePix()`, `Cobranca::chavePix()`: `BelongsTo`; `ChavePix::contratos()`, `ChavePix::cobrancas()`: `HasMany`.
- Trait `RegrasDePagamento`: `regrasDePagamento(bool $comLinhaDigitavel): array`, `prepararPagamento(): void`,
  `dadosDePagamento(array $validados): array`.
- Props de formulário: `chavesPix` (`User::opcoesDeChavePix()`), `formasPagamento` (`FormaPagamento::opcoes()`).

- [ ] **Passo 1: testes que falham** — `tests/Feature/FormaPagamentoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Models\ChavePix;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FormaPagamentoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const LINHA = '23793381286000000000300000000400184340000026060';

    /** @return array{0: User, 1: Cliente, 2: ChavePix} */
    private function cenario(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create(), ChavePix::factory()->for($usuario)->create()];
    }

    private function contrato(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id, 'descricao' => 'Hospedagem', 'valor' => '260,60', 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 15, 'data_inicio' => '2026-10-01', 'ativo' => true,
        ], $extra);
    }

    private function avulsa(Cliente $cliente, array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $cliente->id, 'descricao' => 'Manutenção', 'valor' => '150,00',
            'periodo_inicio' => '2026-10-01', 'periodo_fim' => '2026-10-31', 'vencimento' => '2026-10-20',
        ], $extra);
    }

    public function test_pix_exige_chave_do_proprio_usuario(): void
    {
        [$usuario, $cliente] = $this->cenario();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix']))
            ->assertSessionHasErrors(['chave_pix_id' => 'Escolha a chave PIX.']);
        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix', 'chave_pix_id' => ChavePix::factory()->create()->id]))
            ->assertSessionHasErrors(['chave_pix_id' => 'O valor escolhido para chave PIX é inválido.']);
        $this->assertSame(0, Contrato::count());
    }

    public function test_contrato_com_pix_gera_cobranca_com_a_mesma_chave(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();

        $this->actingAs($usuario)->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'pix', 'chave_pix_id' => $chave->id]))
            ->assertRedirect('/contratos');
        app(GeradorDeCobrancas::class)->gerarPara($usuario->fresh(), CarbonImmutable::today());

        $cobranca = $usuario->cobrancas()->sole();
        $this->assertSame(FormaPagamento::Pix, $cobranca->forma_pagamento);
        $this->assertSame($chave->id, $cobranca->chave_pix_id);
    }

    public function test_boleto_descarta_chave_e_guarda_linha_so_com_digitos(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();
        $this->actingAs($usuario);

        $this->post('/contratos', $this->contrato($cliente, ['forma_pagamento' => 'boleto', 'chave_pix_id' => $chave->id]));
        $this->assertNull(Contrato::sole()->chave_pix_id);

        $this->post('/cobrancas', $this->avulsa($cliente, [
            'forma_pagamento' => 'boleto', 'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060',
        ]))->assertRedirect('/cobrancas');
        $this->assertSame(self::LINHA, $usuario->cobrancas()->sole()->linha_digitavel);

        $this->post('/cobrancas', $this->avulsa($cliente, ['forma_pagamento' => 'boleto', 'linha_digitavel' => '123']))
            ->assertSessionHasErrors(['linha_digitavel' => 'A linha digitável tem 47 ou 48 números.']);
    }

    public function test_sem_forma_informada_vale_nao_informar(): void
    {
        [$usuario, $cliente] = $this->cenario();

        $this->actingAs($usuario)->post('/cobrancas', $this->avulsa($cliente))->assertRedirect('/cobrancas');

        $this->assertSame(FormaPagamento::Nenhuma, $usuario->cobrancas()->sole()->forma_pagamento);
    }

    public function test_chave_em_uso_nao_e_excluida(): void
    {
        [$usuario, $cliente, $chave] = $this->cenario();
        Contrato::factory()->for($usuario)->for($cliente)->create(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);

        $this->actingAs($usuario)->delete("/perfil/chaves-pix/{$chave->id}")
            ->assertSessionHas('erro', 'Esta chave está em uso em contratos ou cobranças. Troque a forma de pagamento deles antes de excluir.');
        $this->assertNotNull($chave->fresh());
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter=FormaPagamentoTest`
Esperado: FAIL (enum e colunas inexistentes).

- [ ] **Passo 3: enum e migração**

`app/Enums/FormaPagamento.php`:

```php
<?php

namespace App\Enums;

enum FormaPagamento: string
{
    case Pix = 'pix';
    case Boleto = 'boleto';
    case Nenhuma = 'nenhuma';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Boleto => 'Boleto bancário',
            self::Nenhuma => 'Não informar',
        };
    }

    /** @return list<array{valor: string, rotulo: string}> */
    public static function opcoes(): array
    {
        return array_map(fn (self $forma) => ['valor' => $forma->value, 'rotulo' => $forma->rotulo()], self::cases());
    }
}
```

`database/migrations/2026_10_10_100200_add_pagamento_to_contratos_e_cobrancas.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['contratos', 'cobrancas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                // Existentes ficam "não informar": nenhum recibo ganha bloco de pagamento sem o usuário escolher.
                $table->string('forma_pagamento', 10)->default('nenhuma');
                $table->foreignId('chave_pix_id')->nullable()->constrained('chaves_pix')->restrictOnDelete();

                if ($tabela === 'cobrancas') {
                    $table->string('linha_digitavel', 48)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['contratos', 'cobrancas'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->dropConstrainedForeignId('chave_pix_id');
                $table->dropColumn($tabela === 'cobrancas' ? ['forma_pagamento', 'linha_digitavel'] : ['forma_pagamento']);
            });
        }
    }
};
```

- [ ] **Passo 4: models**

`Contrato`: `#[Fillable]` + `'forma_pagamento', 'chave_pix_id'`; `casts()` + `'forma_pagamento' => FormaPagamento::class`;
`$attributes` = `['modalidade' => 'prepago', 'forma_pagamento' => 'nenhuma']`; relação:

```php
    public function chavePix(): BelongsTo
    {
        return $this->belongsTo(ChavePix::class);
    }
```

`Cobranca`: `#[Fillable]` + `'forma_pagamento', 'chave_pix_id', 'linha_digitavel'`; `casts()` +
`'forma_pagamento' => FormaPagamento::class`; `$attributes` = `['status' => 'pendente', 'forma_pagamento' => 'nenhuma']`;
a mesma relação `chavePix()`.

`ChavePix`:

```php
    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
```

- [ ] **Passo 5: regras compartilhadas** — `app/Http/Requests/Concerns/RegrasDePagamento.php`:

```php
<?php

namespace App\Http\Requests\Concerns;

use App\Enums\FormaPagamento;
use Illuminate\Validation\Rule;

/** Forma de pagamento: igual no contrato, na cobrança e na troca depois de enviada. */
trait RegrasDePagamento
{
    protected function prepararPagamento(): void
    {
        $this->mergeIfMissing(['forma_pagamento' => FormaPagamento::Nenhuma->value]);

        if ($this->has('linha_digitavel')) {
            $linha = (string) preg_replace('/\D/', '', (string) $this->input('linha_digitavel'));
            $this->merge(['linha_digitavel' => $linha === '' ? null : $linha]);
        }
    }

    protected function regrasDePagamento(bool $comLinhaDigitavel): array
    {
        $regras = [
            'forma_pagamento' => ['required', Rule::enum(FormaPagamento::class)],
            'chave_pix_id' => [
                'nullable',
                'required_if:forma_pagamento,pix',
                Rule::exists('chaves_pix', 'id')->where('user_id', $this->user()->id),
            ],
        ];

        if ($comLinhaDigitavel) {
            $regras['linha_digitavel'] = ['nullable', 'regex:/^(\d{47}|\d{48})$/'];
        }

        return $regras;
    }

    /** Chave só com PIX; linha digitável só com boleto. */
    protected function dadosDePagamento(array $validados): array
    {
        $forma = FormaPagamento::from($validados['forma_pagamento']);
        $dados = [
            'forma_pagamento' => $forma,
            'chave_pix_id' => $forma === FormaPagamento::Pix ? $validados['chave_pix_id'] : null,
        ];

        if (array_key_exists('linha_digitavel', $validados)) {
            $dados['linha_digitavel'] = $forma === FormaPagamento::Boleto ? $validados['linha_digitavel'] : null;
        }

        return $dados;
    }
}
```

`ContratoRequest`: `use RegrasDePagamento;`; `prepareForValidation()` chamando `$this->prepararPagamento();`;
`rules()` = `[...regras atuais..., ...$this->regrasDePagamento(false)]`; `dadosContrato()`:

```php
    public function dadosContrato(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['valor', 'forma_pagamento', 'chave_pix_id']) + [
            'valor_centavos' => Dinheiro::paraCentavos($dados['valor']),
            'ativo' => $this->boolean('ativo', true),
        ] + $this->dadosDePagamento($dados);
    }
```

`CobrancaRequest`: igual, com `regrasDePagamento(true)` e:

```php
    public function dadosCobranca(): array
    {
        $dados = $this->validated();

        return Arr::except($dados, ['valor', 'forma_pagamento', 'chave_pix_id', 'linha_digitavel'])
            + ['valor_centavos' => Dinheiro::paraCentavos($dados['valor'])]
            + $this->dadosDePagamento($dados);
    }
```

`lang/pt_BR/validation.php`: em `attributes`, `'forma_pagamento' => 'forma de pagamento', 'chave_pix_id' => 'chave PIX',
'linha_digitavel' => 'linha digitável',`; e, no nível de cima do array (antes de `'attributes'`):

```php
    'custom' => [
        'chave_pix_id' => ['required_if' => 'Escolha a chave PIX.'],
        'linha_digitavel' => ['regex' => 'A linha digitável tem 47 ou 48 números.'],
    ],
```

- [ ] **Passo 6: controllers e gerador**

`GeradorDeCobrancas` — no `create([...])` da cobrança:

```php
                        'forma_pagamento' => $contrato->forma_pagamento,
                        'chave_pix_id' => $contrato->chave_pix_id,
```

`ContratoController::formulario` — no array do contrato: `'forma_pagamento' => $contrato->forma_pagamento->value,
'chave_pix_id' => $contrato->chave_pix_id ?? '',`; nas props: `'chavesPix' => $request->user()->opcoesDeChavePix(),
'formasPagamento' => FormaPagamento::opcoes(),`.

`CobrancaController::formulario` — idem, mais `'linha_digitavel' => $cobranca->linha_digitavel ?? '',`.

`ChavePixController::destroy`:

```php
    public function destroy(Request $request, int $chave): RedirectResponse
    {
        $registro = $request->user()->chavesPix()->findOrFail($chave);

        if ($registro->contratos()->exists() || $registro->cobrancas()->exists()) {
            return back()->with('erro', 'Esta chave está em uso em contratos ou cobranças. Troque a forma de pagamento deles antes de excluir.');
        }

        $registro->delete();

        return back()->with('sucesso', 'Chave PIX excluída.');
    }
```

- [ ] **Passo 7: componente e formulários** — `resources/js/Components/CamposPagamento.vue`:

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
import Campo from './Campo.vue';

defineProps({
    form: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
    comLinhaDigitavel: { type: Boolean, default: false },
});
</script>

<template>
    <fieldset class="grid gap-4 sm:col-span-6 sm:grid-cols-6">
        <legend class="mb-2 text-sm font-semibold">Forma de pagamento</legend>
        <div class="flex flex-wrap gap-4 sm:col-span-6">
            <label v-for="forma in formasPagamento" :key="forma.valor" class="flex items-center gap-2 text-sm">
                <input v-model="form.forma_pagamento" type="radio" name="forma_pagamento" :value="forma.valor"> {{ forma.rotulo }}
            </label>
        </div>
        <p v-if="form.errors.forma_pagamento" class="text-xs text-tinta-vermelha sm:col-span-6">{{ form.errors.forma_pagamento }}</p>

        <template v-if="form.forma_pagamento === 'pix'">
            <Campo v-if="chavesPix.length" id="chave_pix_id" rotulo="Chave PIX para receber" :erro="form.errors.chave_pix_id" class="sm:col-span-6">
                <select id="chave_pix_id" v-model="form.chave_pix_id" class="entrada" required>
                    <option value="" disabled>Escolha a chave</option>
                    <option v-for="chave in chavesPix" :key="chave.id" :value="chave.id">{{ chave.rotulo }}</option>
                </select>
            </Campo>
            <p v-else class="border border-linha bg-white px-4 py-3 text-sm sm:col-span-6">
                Você ainda não tem chave PIX. <Link href="/perfil#chaves-pix" class="font-semibold text-carbono underline">Cadastrar chave PIX</Link>
            </p>
        </template>

        <Campo
            v-if="comLinhaDigitavel && form.forma_pagamento === 'boleto'"
            id="linha_digitavel"
            rotulo="Linha digitável do boleto (opcional)"
            :erro="form.errors.linha_digitavel"
            class="sm:col-span-6"
        >
            <input id="linha_digitavel" v-model="form.linha_digitavel" v-mascara="'linhaDigitavel'" inputmode="numeric" class="entrada">
        </Campo>
    </fieldset>
</template>
```

`Contratos/Form.vue`: props `chavesPix` e `formasPagamento` (Array, required); `useForm` com
`forma_pagamento: 'nenhuma', chave_pix_id: ''`; antes do checkbox "Contrato ativo":
`<CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" />`.

`Cobrancas/Form.vue`: idem, com `linha_digitavel: ''` no `useForm` e `com-linha-digitavel` no componente,
antes do botão de salvar.

- [ ] **Passo 8: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde (os testes antigos continuam passando: sem `forma_pagamento` no POST vale `nenhuma`).

- [ ] **Passo 9: skill + commit**

Na skill: "T10.2: forma de pagamento no contrato (copiada para a cobrança na geração) e na cobrança;
`RegrasDePagamento` compartilhada (chave só com PIX, linha só com boleto, `mergeIfMissing` deixa
`nenhuma` como padrão); chave em uso não é excluída (controller + `restrictOnDelete`)."

```powershell
git add app database lang resources tests .claude
git commit -m "feat: forma de pagamento (PIX ou boleto) no contrato e na cobrança`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T10.3: Trocar a forma de pagamento depois de enviada

**Estado:** pendente

**Arquivos:**
- Criar: `app/Http/Requests/PagamentoRequest.php`, `resources/js/Components/PagamentoInline.vue`
- Modificar: `app/Http/Controllers/CobrancaController.php` (`pagamento`, `index`), `app/Http/Controllers/PainelController.php`,
  `app/Models/Cobranca.php` (`paraLista`), `routes/web.php`, `resources/js/Pages/Cobrancas/Index.vue`, `resources/js/Pages/Painel.vue`
- Teste: `tests/Feature/Cobrancas/AcoesCobrancaTest.php`

**Interfaces:** `PUT /cobrancas/{cobranca}/pagamento` (só pendente); `paraLista()['pagamento']` =
`{forma_pagamento, chave_pix_id, linha_digitavel}`; props `chavesPix` e `formasPagamento` em `Cobrancas/Index` e `Painel`.

- [ ] **Passo 1: testes que falham** — acrescentar a `AcoesCobrancaTest` (com `use App\Enums\FormaPagamento;`):

```php
    public function test_troca_a_forma_de_pagamento_de_cobranca_ja_enviada_sem_mexer_no_resto(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $cobranca->enviado_em = now();
        $cobranca->save();

        $this->actingAs($usuario)->put("/cobrancas/{$cobranca->id}/pagamento", [
            'forma_pagamento' => 'boleto',
            'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060',
            'valor' => '999,99',
        ])->assertSessionHas('sucesso', 'Forma de pagamento atualizada.');

        $cobranca->refresh();
        $this->assertSame(FormaPagamento::Boleto, $cobranca->forma_pagamento);
        $this->assertSame('23793381286000000000300000000400184340000026060', $cobranca->linha_digitavel);
        $this->assertSame(26060, $cobranca->valor_centavos);
    }

    public function test_paga_ou_cancelada_nao_troca_forma_e_alheia_da_404(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente);
        $cobranca->status = StatusCobranca::Paga;
        $cobranca->pago_em = '2026-10-09';
        $cobranca->save();
        $this->actingAs($usuario);

        $this->put("/cobrancas/{$cobranca->id}/pagamento", ['forma_pagamento' => 'boleto'])
            ->assertSessionHas('erro', 'Só cobranças pendentes mudam a forma de pagamento.');
        $this->assertSame(FormaPagamento::Nenhuma, $cobranca->fresh()->forma_pagamento);

        $alheia = Cobranca::factory()->create();
        $this->put("/cobrancas/{$alheia->id}/pagamento", ['forma_pagamento' => 'boleto'])->assertNotFound();
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter="test_troca_a_forma|test_paga_ou_cancelada_nao_troca"`
Esperado: FAIL (rota inexistente — 405/404).

- [ ] **Passo 3: implementar**

`app/Http/Requests/PagamentoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RegrasDePagamento;
use Illuminate\Foundation\Http\FormRequest;

class PagamentoRequest extends FormRequest
{
    use RegrasDePagamento;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepararPagamento();
    }

    public function rules(): array
    {
        return $this->regrasDePagamento(true);
    }

    public function dados(): array
    {
        return $this->dadosDePagamento($this->validated() + ['linha_digitavel' => null]);
    }
}
```

`CobrancaController` (com `use App\Http\Requests\PagamentoRequest;`):

```php
    /** Forma de pagamento é dado vivo: muda até a baixa, mesmo com a cobrança enviada. */
    public function pagamento(PagamentoRequest $request, int $cobranca): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes mudam a forma de pagamento.');
        }

        $registro->update($request->dados());

        return back()->with('sucesso', 'Forma de pagamento atualizada.');
    }
```

`routes/web.php`, junto das ações de cobrança:

```php
    Route::put('/cobrancas/{cobranca}/pagamento', [CobrancaController::class, 'pagamento']);
```

`Cobranca::paraLista()` — acrescentar:

```php
            'pagamento' => [
                'forma_pagamento' => $this->forma_pagamento->value,
                'chave_pix_id' => $this->chave_pix_id ?? '',
                'linha_digitavel' => $this->linha_digitavel ?? '',
            ],
```

`CobrancaController::index` e `PainelController::__invoke` — acrescentar às props:

```php
            'chavesPix' => $usuario->opcoesDeChavePix(),
            'formasPagamento' => FormaPagamento::opcoes(),
```

(com `use App\Enums\FormaPagamento;`).

`resources/js/Components/PagamentoInline.vue`:

```vue
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import CamposPagamento from './CamposPagamento.vue';

const props = defineProps({
    cobrancaId: { type: Number, required: true },
    pagamento: { type: Object, required: true },
    chavesPix: { type: Array, required: true },
    formasPagamento: { type: Array, required: true },
});

const aberto = ref(false);
const form = useForm({ ...props.pagamento });

const salvar = () => form.put(`/cobrancas/${props.cobrancaId}/pagamento`, {
    preserveScroll: true,
    onSuccess: () => { aberto.value = false; },
});
</script>

<template>
    <button v-if="!aberto" type="button" class="botao-secundario" @click="aberto = true">Forma de pagamento</button>
    <form v-else class="grid w-full gap-3 sm:grid-cols-6" @submit.prevent="salvar">
        <CamposPagamento :form="form" :chaves-pix="chavesPix" :formas-pagamento="formasPagamento" com-linha-digitavel />
        <div class="flex flex-wrap gap-2 sm:col-span-6">
            <button class="botao" :disabled="form.processing">Salvar forma de pagamento</button>
            <button type="button" class="botao-secundario" @click="aberto = false">Voltar</button>
        </div>
    </form>
</template>
```

> `CamposPagamento` usa `id` fixos (`chave_pix_id`, `linha_digitavel`); só um `PagamentoInline` fica aberto por vez
> na prática. Se o smoke test mostrar dois abertos, trocar os `id` por `` `${campo}-${cobrancaId}` ``.

`Cobrancas/Index.vue`: props `chavesPix` e `formasPagamento` (Array, required); dentro da `LinhaCobranca`,
depois do `BaixaInline`:

```vue
                <PagamentoInline
                    v-if="aberta(cobranca)"
                    :cobranca-id="cobranca.id"
                    :pagamento="cobranca.pagamento"
                    :chaves-pix="chavesPix"
                    :formas-pagamento="formasPagamento"
                />
```

`Painel.vue`: as mesmas props; no bloco "Aguardando pagamento", depois do `BaixaInline`, o mesmo `PagamentoInline`
(sem o `v-if`).

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde.

- [ ] **Passo 5: skill + commit**

```powershell
git add app resources routes tests .claude
git commit -m "feat: trocar a forma de pagamento de cobrança pendente, mesmo já enviada`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T10.4: PORTÃO-BANCO + fechamento da fase 10

**Estado:** pendente

- [ ] `db:show --counts` e `migrate:status` (leitura); mostrar a conexão (PostgreSQL de desenvolvimento), as
  contagens de `contratos` e `cobrancas` e as migrações pendentes `2026_10_10_100100_create_chaves_pix_table` e
  `2026_10_10_100200_add_pagamento_to_contratos_e_cobrancas`; comando literal `php artisan migrate`.
  Efeito: tabela nova vazia; colunas novas com `nenhuma`/nulo nas linhas existentes; nada apagado.
- [ ] Esperar o "sim"; recontar em comando separado; executar; mostrar resultado e `migrate:status`.
- [ ] Merge + push (`merge: fase 10 — chaves PIX e forma de pagamento`); tabela e ledger.

---

## Fase 11 — Bloco de pagamento no PDF

### Tarefa T11.1: PIX "copia e cola" (BR Code) conferido com o Banco Central

**Estado:** pendente

**Arquivos:**
- Criar: `app/Support/PixCopiaECola.php`
- Teste: `tests/Unit/PixCopiaEColaTest.php`

**Interfaces:** `PixCopiaECola::gerar(string $chave, string $nome, string $cidade, int $valorCentavos, string $txid): string`;
`PixCopiaECola::crc16(string $dados): string` (4 hexadecimais maiúsculos).

- [ ] **Passo 1: conferir o padrão** — abrir o "Manual de Padrões para Iniciação do Pix" do Banco Central
  (ctx_fetch_and_index / busca em bcb.gov.br) e confirmar, registrando a fonte na skill:
  1. IDs e ordem dos campos (`00`, `26` com `00`=`br.gov.bcb.pix` e `01`=chave, `52`, `53`=`986`, `54`, `58`=`BR`, `59`, `60`, `62`/`05`, `63`);
  2. limites: nome 25, cidade 15, txid até 25 alfanuméricos (`***` quando não há);
  3. CRC16-CCITT (polinômio `0x1021`, valor inicial `0xFFFF`), calculado sobre o payload **com** `6304`;
  4. se o campo `01` (método de iniciação) é obrigatório no QR estático com valor;
  5. o **exemplo oficial** do manual (payload completo) para o teste do CRC.
  O exemplo abaixo é o que se espera encontrar; **substituir pelo do manual** se for diferente. Sem a conferência,
  a tarefa não passa de `concluída` e vira pergunta ao usuário ([INCONCLUSIVO]).

- [ ] **Passo 2: teste que falha** — `tests/Unit/PixCopiaEColaTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\PixCopiaECola;
use PHPUnit\Framework\TestCase;

class PixCopiaEColaTest extends TestCase
{
    public function test_crc_do_exemplo_oficial_do_banco_central(): void
    {
        // Exemplo do Manual de Padrões para Iniciação do Pix (conferido no passo 1).
        $exemplo = '00020126580014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-4266554400005204000053039865802BR5913Fulano de Tal6008BRASILIA62070503***63041D3D';

        $this->assertSame(substr($exemplo, -4), PixCopiaECola::crc16(substr($exemplo, 0, -4)));
    }

    public function test_monta_payload_com_valor_identificacao_e_textos_no_limite(): void
    {
        $payload = PixCopiaECola::gerar('+5579999990000', 'José da Silva Prestação de Serviços Ltda', 'Nossa Senhora do Socorro', 26060, 'REC2026000016');

        $this->assertStringStartsWith('000201', $payload);
        $this->assertStringContainsString('0014br.gov.bcb.pix0114+5579999990000', $payload);
        $this->assertStringContainsString('52040000530398654062', $payload);
        $this->assertStringContainsString('5406260.60', $payload);
        $this->assertStringContainsString('5802BR5925JOSE DA SILVA PRESTACAO D6015NOSSA SENHORA D', $payload);
        $this->assertStringContainsString('62170513REC2026000016', $payload);
        $this->assertSame(PixCopiaECola::crc16(substr($payload, 0, -4)), substr($payload, -4));
        $this->assertSame('6304', substr($payload, -8, 4));
    }

    public function test_txid_vazio_vira_tres_asteriscos(): void
    {
        $this->assertStringContainsString('62070503***', PixCopiaECola::gerar('fulano@exemplo.com', 'Fulano', 'Aracaju', 100, ''));
    }
}
```

> Conta conferida à mão: `5925` + `JOSE DA SILVA PRESTACAO D` (25 caracteres) e `6015` + `NOSSA SENHORA D` (15);
> `62` = `0513REC2026000016` (17 caracteres) → `6217…`.

- [ ] **Passo 3: rodar e ver falhar**

Run: `php artisan test --compact --filter=PixCopiaEColaTest`
Esperado: FAIL — classe inexistente.

- [ ] **Passo 4: implementar** — `app/Support/PixCopiaECola.php`:

```php
<?php

namespace App\Support;

use Illuminate\Support\Str;

/** BR Code estático do PIX com valor (padrão EMV do Banco Central). */
final class PixCopiaECola
{
    public static function gerar(string $chave, string $nome, string $cidade, int $valorCentavos, string $txid): string
    {
        $identificacao = substr((string) preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);

        $payload = self::campo('00', '01')
            .self::campo('26', self::campo('00', 'br.gov.bcb.pix').self::campo('01', $chave))
            .self::campo('52', '0000')
            .self::campo('53', '986')
            .self::campo('54', sprintf('%d.%02d', intdiv($valorCentavos, 100), $valorCentavos % 100))
            .self::campo('58', 'BR')
            .self::campo('59', self::texto($nome, 25))
            .self::campo('60', self::texto($cidade, 15))
            .self::campo('62', self::campo('05', $identificacao === '' ? '***' : $identificacao))
            .'6304';

        return $payload.self::crc16($payload);
    }

    /** CRC16-CCITT: polinômio 0x1021, valor inicial 0xFFFF. */
    public static function crc16(string $dados): string
    {
        $crc = 0xFFFF;

        foreach (str_split($dados) as $caractere) {
            $crc ^= ord($caractere) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }

    private static function campo(string $id, string $valor): string
    {
        return $id.sprintf('%02d', strlen($valor)).$valor;
    }

    /** Maiúsculas sem acento (o app do banco exibe esses campos), cortado no limite. */
    private static function texto(string $valor, int $limite): string
    {
        $limpo = (string) preg_replace('/[^A-Z0-9 ]/', '', strtoupper(Str::ascii($valor)));

        return rtrim(substr(trim($limpo), 0, $limite));
    }
}
```

- [ ] **Passo 5: rodar e ver passar**

Run: `php artisan test --compact --filter=PixCopiaEColaTest`
Esperado: PASS (3 testes).

- [ ] **Passo 6: skill + commit**

Na skill: o que o manual confirmou (fonte e data), os limites e o exemplo oficial usado no teste.

```powershell
git add app tests .claude
git commit -m "feat: PIX copia e cola (BR Code) com valor e identificação do recibo`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T11.2: QR Code em PNG (`chillerlan/php-qrcode`)

**Estado:** pendente

**Arquivos:**
- Modificar: `composer.json`, `composer.lock`
- Criar: `app/Support/QrCodePix.php`
- Teste: `tests/Unit/QrCodePixTest.php`

**Interfaces:** `QrCodePix::dataUri(string $payload): string` — `data:image/png;base64,...`.

- [ ] **Passo 1: instalar (dependência aprovada pelo usuário)**

Run: `composer require chillerlan/php-qrcode`
Depois: `composer show chillerlan/php-qrcode` (versão instalada) e ler o README em
`vendor/chillerlan/php-qrcode/README.md` para confirmar, **na versão instalada**, os nomes de `QROptions`,
da constante de saída PNG (na v5: `QROutputInterface::GDIMAGE_PNG`) e da opção `outputBase64`. Ajustar o
código do passo 3 ao que o README disser.

- [ ] **Passo 2: teste que falha** — `tests/Unit/QrCodePixTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\QrCodePix;
use PHPUnit\Framework\TestCase;

class QrCodePixTest extends TestCase
{
    public function test_gera_png_quadrado_em_data_uri(): void
    {
        $uri = QrCodePix::dataUri('00020126360014br.gov.bcb.pix0114+55799999900005204000053039865406260.605802BR5906FULANO6007ARACAJU62070503***6304ABCD');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $png = base64_decode(substr($uri, strlen('data:image/png;base64,')), true);
        $this->assertStringStartsWith("\x89PNG", $png);
        [$largura, $altura] = getimagesizefromstring($png);
        $this->assertSame($largura, $altura);
        $this->assertGreaterThanOrEqual(150, $largura);
    }
}
```

- [ ] **Passo 3: implementar** — `app/Support/QrCodePix.php`:

```php
<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** QR do PIX como PNG embutido (o dompdf desenha imagem sem ler disco nem rede). */
final class QrCodePix
{
    public static function dataUri(string $payload): string
    {
        $opcoes = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 5,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($opcoes))->render($payload);
    }
}
```

- [ ] **Passo 4: rodar e ver passar**

Run: `php artisan test --compact --filter=QrCodePixTest`
Esperado: PASS.

- [ ] **Passo 5: skill + commit**

Na skill: versão instalada e API confirmada no README.

```powershell
git add composer.json composer.lock app tests .claude
git commit -m "feat: QR Code do PIX em PNG com chillerlan/php-qrcode`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T11.3: Bloco de pagamento nos PDFs

**Estado:** pendente

**Arquivos:**
- Modificar: `app/Services/RenderizadorDeRecibo.php`, `resources/views/recibos/pdf/webiss-v1.blade.php`,
  `resources/views/recibos/pdf/danfse-v1.blade.php`
- Teste: `tests/Feature/Recibos/RenderizadorDeReciboTest.php`, `tests/Feature/Recibos/AmostrasVisuaisTest.php`

**Interfaces:** `RenderizadorDeRecibo::dados()['pagamento']`:
- PIX: `['forma' => 'pix', 'chave' => formatada, 'tipo' => rótulo, 'favorecido' => nome do prestador,
  'copia_e_cola_linhas' => list<string> (pedaços de 90), 'qr' => data URI]`;
- boleto: `['forma' => 'boleto', 'linha_digitavel' => formatada|null]`;
- não informar: `['forma' => null]`.
- txid = `REC` + ano + número com 6 dígitos; valor e nome/cidade vêm do **snapshot** (o mesmo valor impresso).

- [ ] **Passo 1: testes que falham** — acrescentar a `RenderizadorDeReciboTest`
(com `use App\Enums\FormaPagamento; use App\Enums\TipoChavePix; use App\Models\ChavePix;`):

```php
    public function test_pagamento_pix_traz_qr_chave_e_copia_e_cola(): void
    {
        $usuario = User::factory()->create(['name' => 'Marcos Prestador', 'cidade' => 'Aracaju', 'uf' => 'SE']);
        $chave = ChavePix::factory()->for($usuario)->create(['tipo' => TipoChavePix::Telefone, 'chave' => '+5579999990000']);
        $cobranca = Cobranca::factory()->for($usuario)->for(Cliente::factory()->for($usuario))
            ->create(['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $chave->id]);
        $recibo = app(EmissorDeRecibos::class)->emitir($cobranca);

        $pagamento = $this->renderizador->dados($recibo, CarbonImmutable::now())['pagamento'];

        $this->assertSame('pix', $pagamento['forma']);
        $this->assertSame('+55 (79) 99999-0000', $pagamento['chave']);
        $this->assertSame('Telefone', $pagamento['tipo']);
        $this->assertStringStartsWith('data:image/png;base64,', $pagamento['qr']);
        $copiaECola = implode('', $pagamento['copia_e_cola_linhas']);
        $this->assertStringContainsString('5406260.60', $copiaECola);
        $this->assertStringContainsString('0513REC2026000001', $copiaECola);
        $this->assertStringContainsString('5916MARCOS PRESTADOR6007ARACAJU', $copiaECola);
    }

    public function test_pagamento_boleto_e_sem_forma(): void
    {
        $boleto = $this->recibo([], ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => '23793381286000000000300000000400184340000026060']);
        $this->assertSame(
            ['forma' => 'boleto', 'linha_digitavel' => '23793.38128 60000.000003 00000.000400 1 84340000026060'],
            $this->renderizador->dados($boleto, CarbonImmutable::now())['pagamento'],
        );

        $this->assertSame(['forma' => null], $this->renderizador->dados($this->recibo(), CarbonImmutable::now())['pagamento']);
    }
```

> O helper `recibo()` deste teste cria um usuário novo a cada chamada; por isso o teste de PIX monta o cenário à mão.

Em `AmostrasVisuaisTest`: no `setUp`, criar `$this->chave = ChavePix::factory()->for($this->usuario)->create(['tipo' => TipoChavePix::Email, 'chave' => 'financeiro.exemplo@exemplo.com.br']);`
(propriedade `private ChavePix $chave;`); o helper `pdf()` ganha `array $pagamento = []` mesclado ao `create([...])`
da cobrança; e `test_descricao_no_limite_cabe_em_uma_pagina` passa a percorrer também as formas:

```php
        $formas = [
            'nenhuma' => [],
            'pix' => ['forma_pagamento' => FormaPagamento::Pix, 'chave_pix_id' => $this->chave->id],
            'boleto' => ['forma_pagamento' => FormaPagamento::Boleto, 'linha_digitavel' => '23793381286000000000300000000400184340000026060'],
        ];

        foreach (['webiss' => $curtas, 'danfse' => $curtas, 'webiss-paragrafo' => $mista] as $amostra => $descricao) {
            foreach ($formas as $forma => $pagamento) {
                $modelo = strtok($amostra, '-');
                $pdf = $this->pdf($modelo, $descricao, $pagamento);
                Storage::disk('local')->put("verificacao/amostra-{$amostra}-{$forma}-25-linhas.pdf", $pdf->output());

                $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count(), "amostra {$amostra} com {$forma}");
            }
        }
```

E `test_gera_amostras_dos_dois_modelos` grava também `amostra-{$modelo}-pix.pdf` com PIX (para a conferência V4).

- [ ] **Passo 2: rodar e ver falhar**

Run: `php artisan test --compact --filter="RenderizadorDeReciboTest|AmostrasVisuaisTest"`
Esperado: FAIL (chave `pagamento` inexistente).

- [ ] **Passo 3: renderizador** (com `use App\Enums\FormaPagamento; use App\Support\PixCopiaECola; use App\Support\QrCodePix;`)

No array de `dados()`: `'pagamento' => $this->pagamento($recibo, $snapshot),` e os métodos:

```php
    /** Forma de pagamento é viva (lida da cobrança); valor e prestador vêm do snapshot, como o resto do recibo. */
    private function pagamento(Recibo $recibo, array $snapshot): array
    {
        $cobranca = $recibo->cobranca;

        if ($cobranca->forma_pagamento === FormaPagamento::Boleto) {
            return [
                'forma' => 'boleto',
                'linha_digitavel' => filled($cobranca->linha_digitavel) ? $this->linhaDigitavel($cobranca->linha_digitavel) : null,
            ];
        }

        $chave = $cobranca->forma_pagamento === FormaPagamento::Pix ? $cobranca->chavePix : null;

        if ($chave === null) {
            return ['forma' => null];
        }

        $payload = PixCopiaECola::gerar(
            $chave->chave,
            (string) $snapshot['prestador']['nome'],
            (string) ($snapshot['prestador']['cidade'] ?? ''),
            $snapshot['servico']['valor_centavos'],
            sprintf('REC%d%06d', $recibo->ano, $recibo->numero),
        );

        return [
            'forma' => 'pix',
            'chave' => $chave->tipo->formatar($chave->chave),
            'tipo' => $chave->tipo->rotulo(),
            'favorecido' => $this->traco($snapshot['prestador']['nome'] ?? null),
            'copia_e_cola_linhas' => str_split($payload, 90),
            'qr' => QrCodePix::dataUri($payload),
        ];
    }

    private function linhaDigitavel(string $linha): string
    {
        return strlen($linha) === 48
            ? (string) preg_replace('/^(\d{11})(\d)(\d{11})(\d)(\d{11})(\d)(\d{11})(\d)$/', '$1-$2 $3-$4 $5-$6 $7-$8', $linha)
            : (string) preg_replace('/^(\d{5})(\d{5})(\d{5})(\d{6})(\d{5})(\d{6})(\d)(\d{14})$/', '$1.$2 $3.$4 $5.$6 $7 $8', $linha);
    }
```

- [ ] **Passo 4: `webiss-v1`** — QR ao lado de TRIBUTOS/VALORES (sem altura extra) e textos em OUTRAS INFORMAÇÕES.

Envolver o trecho que vai de `<div class="divisa secao">TRIBUTOS FEDERAIS</div>` até o fim da última
`<table>` de VALORES:

```blade
    @php($comQr = $pagamento['forma'] === 'pix')
    @if ($comQr)
        <table><tr><td style="width: 78%; padding: 0;">
    @endif

    {{-- (TRIBUTOS FEDERAIS e VALORES, sem mudança) --}}

    @if ($comQr)
        </td><td style="width: 22%; padding: 4pt; text-align: center; border-top: 0.75pt solid #000; border-left: 0.75pt solid #000;">
            <img src="{{ $pagamento['qr'] }}" alt="" style="width: 62pt; height: 62pt;">
            <span class="rot" style="margin-top: 2pt;">PIX — {{ $pagamento['tipo'] }}</span>
            <span style="display: block; font-size: 5.5pt; font-weight: bold;">{{ $pagamento['chave'] }}</span>
        </td></tr></table>
    @endif
```

Em OUTRAS INFORMAÇÕES, depois do `@foreach ($informacoes ...)`:

```blade
                @if ($pagamento['forma'] === 'pix')
                    @foreach ($pagamento['copia_e_cola_linhas'] as $linhaPix)
                        <div style="font-size: 5pt;">{{ $loop->first ? 'PIX copia e cola: ' : '' }}{{ $linhaPix }}</div>
                    @endforeach
                @elseif ($pagamento['forma'] === 'boleto')
                    <div style="font-size: 6.5pt;">Pagamento por boleto bancário{{ $pagamento['linha_digitavel'] ? ' — linha digitável: '.$pagamento['linha_digitavel'] : '' }}</div>
                @endif
```

- [ ] **Passo 5: `danfse-v1`** — bloco PAGAMENTO antes de INFORMAÇÕES COMPLEMENTARES:

```blade
@if ($pagamento['forma'] === 'pix')
    <table class="bloco"><tr><td class="c4 titulo">PAGAMENTO</td></tr></table>
    <table><tr>
        <td class="c1" style="text-align: center;"><img src="{{ $pagamento['qr'] }}" alt="" style="width: 70pt; height: 70pt;"></td>
        <td class="c3">
            <span class="rot">Forma de Pagamento</span><span class="val">PIX — {{ $pagamento['tipo'] }}</span>
            <span class="rot">Chave</span><span class="val">{{ $pagamento['chave'] }}</span>
            <span class="rot">Favorecido</span><span class="val">{{ $pagamento['favorecido'] }}</span>
            <span class="rot">PIX Copia e Cola</span>
            @foreach ($pagamento['copia_e_cola_linhas'] as $linhaPix)
                <span class="val" style="font-size: 5.5pt;">{{ $linhaPix }}</span>
            @endforeach
        </td>
    </tr></table>
@elseif ($pagamento['forma'] === 'boleto')
    <table class="bloco"><tr><td class="c4 titulo">PAGAMENTO</td></tr></table>
    <table><tr><td class="c4">
        <span class="rot">Forma de Pagamento</span><span class="val">Boleto bancário</span>
        @if ($pagamento['linha_digitavel'])
            <span class="rot">Linha Digitável</span><span class="val">{{ $pagamento['linha_digitavel'] }}</span>
        @endif
    </td></tr></table>
@endif
```

- [ ] **Passo 6: rodar e ver passar**

Run: `php artisan test --compact; npm run build`
Esperado: verde. **Se** o WebISS com PIX e 25 linhas passar de 1 página, ajustar nesta ordem e rodar de novo:
(1) fonte do copia e cola 4,5pt; (2) QR 56pt; (3) chave em 5pt. Não reduzir o limite de 25 linhas (spec).
Medir posições com `pypdf` (fórmula `tm[4]*cm[1] + tm[5]*cm[3] + cm[5]`, pegadinha da skill).

- [ ] **Passo 7: skill + commit**

```powershell
git add app resources tests .claude
git commit -m "feat: bloco de pagamento (QR PIX ou boleto) nos recibos WebISS e DANFSe`n`nCo-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Tarefa T11.4: Smoke test, revisão final e fechamento

**Estado:** pendente

- [ ] **PORTÃO-BANCO para o smoke test** com o usuário SMOKE (id 2, já existente): mostrar contagens de
  `chaves_pix`, `contratos`, `cobrancas`, `recibos`; dizer o que será criado (1–2 chaves PIX, 1 contrato pós-pago
  com PIX, 1 avulsa com boleto, trocas de forma de pagamento) — tudo preso ao `user_id` 2; esperar o "sim".
- [ ] Smoke test com o **browser-agent** (Chrome dedicado na porta 9333, ver skill), em 1280 e 390 px:
  1. máscaras: digitar e colar valor, CPF, CNPJ (inclusive alfanumérico), CEP, telefone, linha digitável; editar um valor já salvo;
  2. autosugestão: achar cliente por parte do nome e do CNPJ, só com teclado (setas, Enter, Esc);
  3. carimbo VENCENDO HOJE na lista e no PDF;
  4. contrato pós-pago: vencimento no mês seguinte;
  5. chaves PIX: cadastrar, editar, tentar excluir chave em uso;
  6. PDF com PIX (QR, chave, copia e cola) e com boleto, nos dois modelos;
  7. trocar a forma de pagamento de cobrança já enviada e conferir o PDF.
- [ ] Prints e PDFs em `storage/app/private/verificacao/smoke-pagamento/`; marcar V4 (conferência visual e QR
  escaneado no celular) nas pendências.
- [ ] Revisão final do branch inteiro com revisor de contexto novo (skill executing-plans), uma rodada de
  correções com teste que falha antes.
- [ ] Merge + push da fase 11; tabela e ledger; pedir a limpeza dos dados SMOKE (PORTÃO-BANCO) quando o usuário quiser.
