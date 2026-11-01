# Fase 26 — valor e desconto na baixa — plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: superpowers:subagent-driven-development (recomendado) ou
> superpowers:executing-plans, tarefa por tarefa. Passos com caixa (`- [ ]`) para acompanhar.
> **Não iniciar sem o comando do Marcos** (pedido dele em 2026-11-01).

**Objetivo:** a baixa da cobrança mostra o valor e aceita desconto (percentual ou R$, travado em 100%), e o desconto
aparece na lista, no painel, nos relatórios e no recibo (v2, que substitui o v1).

**Arquitetura:** `cobrancas.desconto_centavos` (padrão 0); valor pago = `valor_centavos − desconto_centavos`, sempre
derivado. Uma regra só de cálculo em `App\Support\Desconto` (servidor) espelhada em `resources/js/desconto.js` (tela).
Recibo `webiss-v2`/`danfse-v2` preenche campos que o layout já tem; migração de dados troca os recibos v1 por v2.

**Stack:** Laravel 13 / PHP 8.4, Inertia v3 + Vue 3, PHPUnit (SQLite em memória), `node --test`, dompdf.

**Spec:** `docs/superpowers/specs/2026-11-01-desconto-na-baixa-design.md` (ler inteira antes de começar). As pegadinhas e
os acertos de cada tarefa estão na skill, seção "Fase 26" (`.claude/skills/recibos/SKILL.md`); aqui ficam só as decisões.

**Estados das tarefas:** pendente / concluída / testada / validada.

## Restrições globais

- Tudo em pt-BR: código novo, testes, mensagens, commits. Nomes impostos pelo framework ficam como são.
- Ler a skill `.claude/skills/recibos/SKILL.md` inteira antes de mexer; aplicar os "Padrões de usabilidade".
- Dinheiro em centavos (`int`), sem float na conta final.
- Desconto: `0 ≤ desconto ≤ valor` (100% aceito). Percentual: até 3 dígitos inteiros e 2 decimais, vírgula ou ponto.
- Arredondamento do percentual: `intdiv(valor × pontosBase + 5000, 10000)` (pontos-base = % × 100; meio centavo para
  cima).
- Auditoria (texto exato da spec): `Baixa registrada (R$ 247,57, desconto 10% = R$ 24,76, pago R$ 222,81, em
  01/11/2026)`; integral: `Baixa com desconto integral (100% = R$ 247,57, pago R$ 0,00, em 01/11/2026)`; desconto em
  R$: `desconto R$ 24,76` no lugar de `desconto 10% = R$ 24,76`; sem desconto: o texto de hoje.
- Wizard da Fase 25 intocado: `FamiliaModelo::opcoes()` continua com 2 famílias; rotas de baixa fora de
  `progresso:*`; suíte da Fase 25 verde sem mudar asserção.
- Depois de mexer em PHP: `vendor/bin/pint --dirty --format agent`. Depois de mexer em `.vue`/`.js`: `npm run build`
  (parar se falhar).
- PORTÃO-BANCO: testes só em SQLite em memória. Qualquer `migrate` no Postgres local (cópia de produção) só com
  conexão, contagem, comando literal e "sim" do Marcos, comando a comando.
- Branch `feat/desconto-na-baixa` saído do `main`; um commit por tarefa, com o estado da tarefa neste plano e a skill
  atualizados no mesmo commit. Fase concluída e testada: merge `--no-ff` no `main` + push (autorização permanente da
  memória `merge-e-push-por-fase`).

## Pontos de revisão (o que os testes de cada tarefa precisam pegar)

1. Percentual com vírgula e com ponto (`12,5` e `12.5`) dão o mesmo desconto → Tarefa 1.
2. Trocar % ↔ R$ depois de digitar limpa o número (10% não vira R$ 0,10) → Tarefa 2.
3. Corrigir pagamento sem mexer no desconto mantém o desconto atual → Tarefa 4.
4. Lote com valores diferentes: arredondamento por cobrança e soma da prévia igual à soma gravada → Tarefas 3 e 3b.
5. Cobrança paga antes desta fase (desconto 0) sai igual na lista, painel, relatórios e recibo → Tarefas 5, 6 e 7.

---

### Tarefa 1 — Dado e regra do desconto — **Estado: testada**

**Arquivos:** migração `add_desconto_centavos_to_cobrancas_table`, `app/Support/Desconto.php`, `resources/js/desconto.js` e
`desconto.test.js`, `tests/Unit/DescontoTest.php`, `app/Models/Cobranca.php` (cast, `$attributes` com `desconto_centavos => 0`,
**fora** do `#[Fillable]` como `status`).

**Interfaces:** PHP `Desconto::centavos(int $valorCentavos, string $tipo, ?string $entrada): ?int` (`$tipo` =
`Desconto::PERCENTUAL` | `Desconto::VALOR`; vazio → 0; formato inválido → `null`; **não** limita ao valor),
`Desconto::rotulo(...)` (`10% = R$ 24,76` ou `R$ 24,76`), `Cobranca::valorPagoCentavos()`. JS `descontoEmCentavos(valor, tipo,
entrada)` e `travarDesconto(valor, tipo, entrada) → { entrada, travou }` (% acima de 100 vira `'100'`; R$ acima do valor vira o valor).

- [x] **Passos 1–5:** testes com os mesmos casos nas duas linguagens (valor 24757: vazio e `'0'` → 0; `'10'` % → 2476; `'12,5'` e
  `'12.5'` % → 3095; `'100'` % → 24757; `'24,76'` R$ → 2476; `'-5'`, `'1e3'`, `'+5'`, `'abc'` → `null`; travas de 150% e de R$ 300,00) →
  falham → migração, model, `Desconto`, `desconto.js` → passam, Pint → commit `feat: desconto_centavos na cobrança e regra única do
  desconto (PHP e JS)`.

  Registro: o JS em R$ espelha `Dinheiro::paraCentavos` (não "centavos = dígitos"), por decisão do controlador; PHP e JS
  conferidos com 8.000 entradas sorteadas; divergem só em R$ com dígitos demais (20 dígitos: PHP `null`, JS `1e22`; a trava da tela limita) e em tab no meio de `"R$\t5"` (PHP 500 via `Dinheiro::paraCentavos`, JS `null`); os dois cortam só espaço ASCII nas pontas.

### Tarefa 2 — Baixa individual com valor e desconto — **Estado: testada**

**Arquivos:** `CobrancaController::pagar`, `BaixaInline.vue`, `Painel.vue`, `Cobrancas/Index.vue`,
`tests/Feature/Cobrancas/DescontoNaBaixaTest.php`.

**Interfaces:** `CobrancaController::descontoValidado(Request, int $valorCentavos): int` (privado, reusado nas Tarefas 3b e 4;
erro no campo `desconto`); `BaixaInline` com prop `valorCentavos` (obrigatória), envia `{ pago_em, desconto_tipo, desconto }`.

- [x] **Passos 1–6:** `DescontoNaBaixaTest` (percentual, R$, integral, acima do valor recusado, sem desconto = texto de hoje, alheia
  = 404 antes da validação) → falham → `pagar` valida depois do `findOrFail` e da checagem de status, grava por atribuição direta na
  transação, audita com `Desconto::rotulo`; tela conforme spec §2 → passam, Pint, build → commit `feat: baixa mostra o valor e aceita
  desconto em % ou R$`.

  Registro: (1) `formatadores.percentual` (`mascaras.js`, com teste) é a máscara do % que a spec pede e o plano não previa. (2) A
  trava roda num `watch` do número, não num `@input` (a ordem entre `v-model`, `@input` e `v-mascara` muda o que cada um enxerga).
  (3) Sem teste de componente (sem jsdom nem Vue Test Utils): "trocar limpa o número", trava e aviso foram para módulo `.js` na Tarefa 4.

### Tarefa 3 — Baixa em lote com percentual — **Estado: testada** (o campo mudou na Tarefa 3b)

**Arquivos:** `CobrancaController::pagarEmLote`, `BaixaEmLote.vue`, `tests/Feature/Cobrancas/AcoesEmLoteTest.php`.

- [x] **Passos 1–6:** percentual por cobrança (10001 e 24757 a 10% → 1000 e 2476, soma 3476), lote sem desconto, acima de 100 recusado,
  auditoria "Baixa em lote" com o rótulo; prévia por cobrança com totais → commit `feat: baixa em lote com percentual de desconto e
  prévia por cobrança` (mais `refactor: aviso temporário da baixa vira módulo único (avisoTemporario.js)`).

  Registro: `resumoDaBaixa` deixou de ler o `Request` (recebe tipo, entrada e `emLote`); a validação do percentual ficou em
  `Desconto::percentualValido`; a conta da prévia ficou pura em `previaDoLote` (`desconto.js`), com o mesmo caso do teste PHP.

### Tarefa 4 — Corrigir pagamento e desfazer baixa — **Estado: testada**

**Arquivos:** `CobrancaController::corrigirPagamento` e `desfazerBaixa`, `CorrecaoPagamentoInline.vue`, `Cobrancas/Index.vue`
(passa `valor-centavos` e `desconto-centavos`), `usarDescontoDaBaixa.js`, `DescontoNaBaixaTest`.

- [x] **Passos 1–6:** corrigir muda data e desconto com auditoria antes/depois; corrigir sem mexer no desconto mantém o desconto;
  senha e motivo; desfazer zera o desconto → commit `feat: corrigir pagamento muda o desconto; desfazer baixa zera`.

  Registro: (1) Pedido **sem** `desconto`/`desconto_tipo` corrige só a data e **mantém** o desconto (a tela sempre manda os dois; campo
  vazio com o tipo = tirar o desconto). (2) `antes`/`depois` de `pagamento.corrigido` e de `baixa.desfeita` levam o desconto (o
  `AuditoriaTest` antigo foi atualizado). (3) A lógica de tela saiu do `BaixaInline` para `usarDescontoDaBaixa.js`, com teste `node --test`
  (cobre o ponto de revisão 2 sem navegador). (4) `Cobranca::paraLista` ganhou `desconto_centavos`.

### Tarefa 5 — Lista de cobranças e painel — **Estado: testada**

**Arquivos:** `Cobranca::paraLista`, `ListaDeCobrancas`, `PainelController`, `Cobrancas/Index.vue`, `LinhaCobranca.vue`, testes
`DadosDaListaTest`, `FiltroSituacaoTest`, `PainelTest`.

- [x] **Passos 1–6:** aba e rodapé "Pagas" somam o valor pago e as demais o valor cheio; recebido do mês no painel = valor − desconto;
  paga sem desconto mantém as somas → commit `feat: lista e painel somam o valor pago das cobranças pagas`.

  Registro: só a situação "paga" soma o valor pago, em quatro pontos da lista e dois do painel (o "Previsto" segue cheio);
  `Cobranca::VALOR_PAGO_SQL` é a conta em SQL (a Tarefa 6 reusa); `paraLista` ganhou `valor_pago`; a tela mostra "pago R$ …" só com
  `situacao === 'paga'` e desconto > 0. **Não conferido no PostgreSQL** (a coluna ainda não existia no banco local).

### Tarefa 6 — Relatórios — **Estado: testada**

**Arquivos:** `app/Services/Relatorios.php` (`faturamento`, `porCliente`, `porProduto`), `itens.js`, `Dinheiro`, `tests/Feature/Relatorios/*`
(inclui `FaturamentoTest::test_csv_para_o_excel_em_portugues`).

- [x] **Passos 1–5:** recebido = valor pago; coluna `descontos` logo depois de `recebido`; por cliente com o valor pago; por produto com a
  linha `Descontos concedidos`; CSV byte a byte → commit `feat: relatórios usam o valor pago e mostram os descontos`.

  Registro: (1) Faturamento: `previsto` e `inadimplencia` seguem cheios; a coluna `descontos` existe sempre (0,00 sem desconto).
  (2) Por produto: itens e "Sem item" cheios; `Descontos concedidos` (negativa, última, só se houver) faz o total bater com o `resumo.recebido`
  do painel (provado em teste). (3) **Achado fora da lista:** `Dinheiro::formatar` e `formatarCentavos` (JS) não formatavam negativo e o CSV
  poria apóstrofo em `-22,00`; corrigidos nos três lugares, com teste. (4) Contas a receber não mudou. **Não conferido no PostgreSQL.**

### Tarefa 7 — Recibo v2 substitui o v1 — **Estado: testada**

**Arquivos:** `ModeloRecibo`, `FamiliaModelo::versaoAtual`, `RenderizadorDeRecibo::dados`, templates `webiss-v2` e `danfse-v2` (v1 apagados),
migração `troca_modelo_dos_recibos_para_v2`, `MigracaoReciboV2Test`, `AmostrasVisuaisTest` e os 5 testes que citavam v1.

**Interfaces:** `dados()['desconto']` e `['valor_pago']` (formatados; `null` quando a cobrança não está paga **ou** o desconto é 0).
WebISS: `Desc. Cond. (R$)` = `$desconto ?? '0,00'` e `Valor Líquido (R$)` = `$valor_pago ?? $valor`. DANFSe: `Desconto Condicionado` =
`$desconto ?? '-'` e as duas linhas de valor líquido = `$valor_pago ?? $valor`. "Valor Total" segue cheio.

- [x] **Passos 1–7:** copiar v1 → v2 → testes que falham (13 vermelhos) → campos do v2 e `dados()` → prova de igualdade v1 × v2 (ver
  "Registro de execução") → apagar teste temporário, templates e casos v1 → suíte inteira verde, Pint → commit `feat: recibos v2 mostram desconto
  e valor pago e substituem o v1`.

  Registro: (1) A prova corrigiu o brief: o v1 do WebISS imprime `0,00` em `Desc. Cond. (R$)`, não `-`. (2) A migração usa `DB::table` (troca só
  `modelo`, nem `updated_at`), porque sem os casos v1 o cast de `Recibo.modelo` quebra ao ler a linha antiga; `down` inverso. **Nunca rodada fora do
  SQLite em memória**; no Postgres local é do portão. (3) `FamiliaModelo::opcoes()` segue com 2 famílias (teste em `UsuarioModeloTest`).
  (4) Asserções antigas que mudaram: só `WebissV1`/`DanfseV1`/`*-v1` → v2 em `TrocaDeModeloTest`, `AcoesCobrancaTest`, `EmissaoReciboTest`,
  `PdfReciboTest` e `UsuarioModeloTest`. (5) `AmostrasVisuaisTest::test_paga_com_desconto_cabe_em_uma_pagina_nos_dois_modelos` é guarda contra o v2
  estourar a página (não podia ficar vermelho antes: o v1 ignora o desconto).

### Tarefa 3b — Desconto em R$ no lote e texto de ajuda — **Estado: testada**

Pedido do Marcos ao ver a tela (2026-11-01): o lote ganha o mesmo seletor `% | R$` da baixa individual (R$ = o mesmo valor em cada
cobrança, limite na menor pendente selecionada), e as três telas ganham um texto de ajuda sobre o seletor. Substitui o
`desconto_percentual` da Tarefa 3 (nunca publicado). Brief: `.superpowers/sdd/2026-11-01-desconto-na-baixa/tarefa-3b-brief.md`.

**Arquivos:** `CobrancaController::pagarEmLote` (`regrasDoDesconto`, `MENSAGENS_DO_DESCONTO`, `conferirDescontoDoLote`), `BaixaEmLote.vue`,
`BaixaInline.vue`, `CorrecaoPagamentoInline.vue`, `desconto.js` (`previaDoLote`, `menorValor`, `semSeparadorFinal`), `usarDescontoDaBaixa.js`
(`complementoDoMaximo`), `AcoesEmLoteTest`.

- [x] **Passos:** testes do lote em R$ e em % (servidor e `desconto.test.js`) → servidor → telas → commit `feat: baixa em lote aceita desconto em R$ e
  telas explicam o seletor`.

  Registro: (1) `pagarEmLote` troca `desconto_percentual` por `desconto_tipo` + `desconto`, com as mesmas regras da baixa individual; a conferência
  roda **antes** da transação, só sobre as pendentes selecionadas, e lote recusado não grava nada (teste confere status, `pago_em`, desconto e
  auditorias). (2) "10," é enviado como "10" nas três telas (`semSeparadorFinal`). (3) O erro do desconto e o da data da `BaixaInline` foram para dentro
  da coluna do campo; a ajuda também (`w-0 min-w-full`), e a linha passou de `items-end` para `items-start`, com os botões em `mt-4`. (4) Asserções
  antigas alteradas: só os campos de entrada dos testes de lote da Tarefa 3 e a mensagem de "% acima de 100 ou inválido", agora separada em duas;
  nenhuma foi enfraquecida. (5) **Sem navegador:** a ajuda embaixo do campo, o alinhamento depois do `items-start`, a quebra do texto e o modal em
  390 px só se conferem no passo 4 da Tarefa 8.

### Tarefa 8 — Wizard, conferência e documentação — **Estado: concluída** (passos 3 e 4 e o merge/push são do controlador; vira `testada` depois deles)

**Arquivos:** `.claude/skills/recibos/SKILL.md` (acertos e pegadinhas da Fase 26; exceção consciente à regra do `-v2`),
`.claude/plans/2026-10-10-roteiro-modulos.md` (estado), README (nada mudou no uso: sem alteração).

- [x] **Passo 1:** suíte da Fase 25 sem mudar asserção: `php artisan test --compact tests/Feature/ProgressoDeConfiguracaoTest.php
  tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php` (11 testes, 65 asserções) e `node --test resources/js/progressoHolofote.test.js
  resources/js/progresso.test.js` (8) → passam. `git diff $(git merge-base main HEAD) -- tests/Feature/ProgressoDeConfiguracao*` e os testes JS
  da Fase 25 → vazios. (Contra o `merge-base` e não contra o `main`, que avançou com `8cca23a`, de outra sessão.)
- [x] **Passo 2:** `php artisan test --compact` (657 testes, 3132 asserções) e `node --test 'resources/js/**/*.test.js'` (170) → verdes; `npm run build` ok.
- [ ] **Passo 3: PORTÃO-BANCO (Postgres local, cópia de produção) — do controlador:** mostrar conexão, contagem (`cobrancas`, `recibos` por `modelo`), o
  comando literal `php artisan migrate`, e só rodar com o "sim" do Marcos. Recontar depois. (A migração `desconto_centavos` já rodou, com autorização;
  falta a dos recibos.)
- [ ] **Passo 4: conferência no navegador — do controlador, com o Marcos** (pedir autorização antes; avisar que o browser-agent não está instalado, só o
  Playwright; conta de teste, nunca dados reais): baixa individual (%, R$, trava, troca de tipo, integral), lote (% e R$, trava pela menor cobrança,
  prévia), correção, o texto de ajuda do seletor nas três telas (e se ele quebra bem na faixa, sem desalinhar os campos), lista e painel, 390 px sem
  rolagem e 1280 px; faixa de baixa com o coach aberto e recolhido.
- [x] **Passo 5:** PDFs de amostra com desconto em `storage/app/private/verificacao/` (fora do git), 1 página cada: `amostra-webiss-paga-desconto.pdf`,
  `-paga-integral.pdf`, `-paga-sem-desconto.pdf` e os três de `amostra-danfse-…`. Texto conferido com `pdftotext -layout` (WebISS: 24,76 em
  `Desc. Cond.`, 222,81 em `Valor Líquido`, 247,57 em `Valor Total`; DANFSe: `R$ 24,76` e `R$ 222,81`). Conferência visual: do Marcos.
- [x] **Passo 6 (documentação):** skill, este plano e o roteiro atualizados; commit `docs: registra a Fase 26 (desconto na baixa)`.
- [ ] **Passo 6 (merge) — do controlador:** com tudo `testada`, merge `--no-ff` no `main` + push. Avisar o Marcos: o deploy roda as duas migrações em
  produção (a segunda **altera todos os recibos**, só o campo `modelo`); backup antes.

## Registro de execução

### Tarefa 7 — prova de igualdade v1 × v2 (2026-11-01, feita com o v2 já editado e antes de apagar o v1)

Teste temporário (apagado depois) que renderizava `view('recibos.pdf.{familia}-v1', $dados)` e `-v2` com os **mesmos** `dados()` de cobranças **sem**
desconto — WebISS e DANFSe × pendente, pendente com PIX e paga — e exigia `assertSame` no HTML inteiro (6 comparações).

- **1ª rodada** (WebISS com `$desconto ?? '-'`, como o brief dizia): **falhou** em `webiss pendente`, diferença única: `0,00` no v1 × `-` no v2. A spec §3 manda
  "sem desconto, o v2 é igual ao v1", então o padrão do WebISS é `0,00`.
- **2ª rodada** (`?? '0,00'`): 1 teste, 13 asserções, HTML idêntico nos 6 casos. O DANFSe passou nas duas rodadas.

### Correção final da revisão (2026-11-01)

NBSP trocado pelo escape de U+00A0 em `desconto.js` e no teste; frase "0 diferenças" corrigida (plano e skill); novo teste do lote com cobrança de outro usuário; estado "Tarefas 1 a 7 e 3b testadas; a 8 concluída" na skill e no roteiro.
