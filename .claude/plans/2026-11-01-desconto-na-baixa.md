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

**Spec:** `docs/superpowers/specs/2026-11-01-desconto-na-baixa-design.md` (ler inteira antes de começar).

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
4. Lote com valores diferentes: arredondamento por cobrança e soma da prévia igual à soma gravada → Tarefa 3.
5. Cobrança paga antes desta fase (desconto 0) sai igual na lista, painel, relatórios e recibo → Tarefas 5, 6 e 7.

---

### Tarefa 1 — Dado e regra do desconto — **Estado: testada**

**Arquivos:**
- Criar: migração `add_desconto_centavos_to_cobrancas_table` (`php artisan make:migration`), `app/Support/Desconto.php`,
  `resources/js/desconto.js`, `resources/js/desconto.test.js`, `tests/Unit/DescontoTest.php`.
- Modificar: `app/Models/Cobranca.php` (cast `desconto_centavos` → `integer`; `$attributes` com `desconto_centavos =>
  0`; **não** entra no `#[Fillable]`, como `status`).

**Interfaces (produz):**
- PHP `Desconto::centavos(int $valorCentavos, string $tipo, ?string $entrada): ?int` — `$tipo` em
  `Desconto::PERCENTUAL = 'percentual'` | `Desconto::VALOR = 'valor'`; vazio/null → `0`; formato inválido → `null`;
  **não** limita ao valor (quem chama valida o limite).
- PHP `Desconto::rotulo(int $valorCentavos, int $descontoCentavos, string $tipo, ?string $entrada): string` — devolve
  `10% = R$ 24,76` ou `R$ 24,76` (usado na auditoria).
- PHP `Cobranca::valorPagoCentavos(): int` — `valor_centavos − desconto_centavos`.
- JS `descontoEmCentavos(valorCentavos, tipo, entrada) → number|null` (mesma regra do PHP).
- JS `travarDesconto(valorCentavos, tipo, entrada) → { entrada: string, travou: boolean }` — % acima de 100 vira `'100'`;
  R$ acima do valor vira o valor formatado por `formatadores.valor`; senão devolve a entrada e `travou: false`.

- [x] **Passo 1: testes que falham** — `DescontoTest` (PHP) e `desconto.test.js` com os mesmos casos, valor 24757:
  vazio → 0; `'0'` → 0; `'10'` % → 2476; `'12,5'` e `'12.5'` % → 3095; `'100'` % → 24757; `'150'` % → 37136 (sem
  limitar); `'24,76'` R$ → 2476; `'-5'`, `'1e3'`, `'+5'`, `'abc'` → null; `rotulo` → `10% = R$ 24,76` e `R$ 24,76`.
  JS também: `travarDesconto(24757, 'percentual', '150')` → `{entrada: '100', travou: true}`;
  `travarDesconto(24757, 'valor', '300,00')` → `{entrada: '247,57', travou: true}`; dentro do limite → `travou: false`.
  Mais um teste de model: `Cobranca::factory()->create()->valorPagoCentavos()` igual a `valor_centavos` (desconto 0).
- [x] **Passo 2:** `php artisan test --compact tests/Unit/DescontoTest.php` e `node --test resources/js/desconto.test.js`
  → FALHAM (classe/módulo inexistente).
- [x] **Passo 3:** migração (`unsignedBigInteger('desconto_centavos')->default(0)->after('valor_centavos')`), model,
  `Desconto` e `desconto.js`. Percentual: regex `^\d{1,3}([.,]\d{1,2})?$`, converter para pontos-base em inteiros.
  Valor: `Dinheiro::paraCentavos`. JS em R$: a máscara garante o formato; centavos = dígitos.
- [x] **Passo 4:** os mesmos comandos → PASSAM. Pint.
- [x] **Passo 5:** commit `feat: desconto_centavos na cobrança e regra única do desconto (PHP e JS)`.

  Registro: JS em R$ espelha `Dinheiro::paraCentavos` (não "centavos = dígitos"), por decisão do controlador; os
  mesmos casos valem nos dois tipos e nas duas linguagens (PHP e JS conferidos com 8.000 entradas sorteadas, 0
  diferenças). Os dois cortam só espaço ASCII nas pontas (o `trim()` do JS tira o NBSP, o do PHP não).

### Tarefa 2 — Baixa individual com valor e desconto — **Estado: testada**

**Arquivos:** `app/Http/Controllers/CobrancaController.php` (`pagar`), `resources/js/Components/BaixaInline.vue`,
`resources/js/Pages/Painel.vue:65`, `resources/js/Pages/Cobrancas/Index.vue:399`, teste
`tests/Feature/Cobrancas/DescontoNaBaixaTest.php`.

**Interfaces:**
- Consome: Tarefa 1.
- Produz: método privado `CobrancaController::descontoValidado(Request $request, int $valorCentavos): int` — valida
  `desconto_tipo` (`nullable|in:percentual,valor`, obrigatório quando `desconto` vem preenchido) e `desconto`
  (`nullable|string|max:20`), calcula com `Desconto::centavos`, lança `ValidationException::withMessages(['desconto'
  => …])` para formato inválido ("Desconto inválido.") ou acima do valor ("O desconto não pode passar do valor da
  cobrança."). Reusado nas Tarefas 4.
- Produz: `BaixaInline` com prop nova `valorCentavos: Number` (obrigatória); envia `{ pago_em, desconto_tipo,
  desconto }`.

- [x] **Passo 1: testes que falham** (`DescontoNaBaixaTest`):
  `test_baixa_com_desconto_percentual_grava_e_audita` (valor 24757, 10% → `desconto_centavos` 2476, auditoria com o
  texto exato da restrição global); `test_baixa_com_desconto_em_reais`; `test_baixa_com_desconto_integral_audita_como_integral`
  (100% → pago 0, texto "Baixa com desconto integral (…)"); `test_desconto_acima_do_valor_e_recusado` (erro em
  `desconto`, cobrança continua pendente); `test_baixa_sem_desconto_mantem_texto_de_hoje`;
  `test_cobranca_alheia_da_404_antes_da_validacao` (primeiro a própria com sucesso, depois a alheia com desconto
  inválido → 404).
- [x] **Passo 2:** `php artisan test --compact tests/Feature/Cobrancas/DescontoNaBaixaTest.php` → FALHAM.
- [x] **Passo 3:** `pagar` chama `descontoValidado` depois do `findOrFail` e da checagem de status; grava
  `desconto_centavos` por atribuição direta (fora do fillable) na mesma transação; auditoria com `Desconto::rotulo`.
- [x] **Passo 4:** `BaixaInline.vue` conforme spec §2: valor só leitura, campo + seletor `%`/`R$` (máscara `valor`
  quando R$), `watch` do tipo **limpa** o número, `travarDesconto` a cada `input` com aviso `aria-live` "Máximo: 100%" /
  "Máximo: R$ …" por ~2 s, valor pago com `.destacar`, aviso laranja no integral, erro embaixo do campo. Painel e lista
  passam `:valor-centavos="cobranca.valor_centavos"`.
- [x] **Passo 5:** testes da tarefa + `node --test resources/js/` → PASSAM. Pint. `npm run build`.
- [x] **Passo 6:** commit `feat: baixa mostra o valor e aceita desconto em % ou R$`.

  Registro: (1) o plano não previa a máscara do percentual — `formatadores.percentual` (`mascaras.js`, com teste) é a
  máscara que a spec §2 pede ("percentual com até 2 casas"). (2) A trava roda num `watch` do número, não num `@input`:
  a ordem entre `v-model`, `@input` e a diretiva `v-mascara` muda o que cada um enxerga, e o `watch` só vê o valor já
  formatado. (3) "Trocar limpa o número", a trava e o aviso são comportamento de tela: não há teste de componente
  (sem jsdom/Vue Test Utils e sem dependência nova); ficam para a conferência no navegador da Tarefa 8.

### Tarefa 3 — Baixa em lote com percentual — **Estado: testada**

**Arquivos:** `CobrancaController::pagarEmLote` (linha ~244), `resources/js/Components/BaixaEmLote.vue`,
`tests/Feature/Cobrancas/AcoesEmLoteTest.php`.

**Interfaces:** consome `Desconto::centavos(…, Desconto::PERCENTUAL, …)` e `descontoEmCentavos` (JS). Campo novo
`desconto_percentual` (`nullable`, mesma regex de percentual, máximo 100).

- [x] **Passo 1: testes que falham** (em `AcoesEmLoteTest`): `test_lote_aplica_percentual_em_cada_cobranca` (valores
  10001 e 24757 a 10% → 1000 e 2476: arredondamento por cobrança; soma 3476); `test_lote_sem_percentual_continua_sem_desconto`;
  `test_lote_percentual_acima_de_100_e_recusado`; auditoria "Baixa em lote" com o rótulo do desconto.
- [x] **Passo 2:** rodar o arquivo → FALHAM.
- [x] **Passo 3:** servidor aplica o percentual a cada cobrança pendente selecionada, na transação de hoje.
- [x] **Passo 4:** `BaixaEmLote.vue`: campo "Desconto (%) para todas" com trava em 100; a prévia lista valor, desconto
  e valor pago por cobrança (com `descontoEmCentavos`) e os totais.
- [x] **Passo 5:** testes + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: baixa em lote com percentual de desconto e prévia por cobrança`.

  Registro: (1) `resumoDaBaixa` deixou de ler `desconto_tipo`/`desconto` do `Request`: recebe tipo e entrada por
  parâmetro (e `emLote`), então serve à baixa individual e ao lote. (2) A validação do percentual ficou em
  `Desconto::percentualValido` (formato + 0 a 100), com teste unitário; o erro sai em `desconto_percentual`. (3) A conta
  da prévia ficou pura em `previaDoLote` (`desconto.js`), com o mesmo caso do teste PHP (10001 e 24757 a 10% → soma
  3476), porque não há teste de componente; "Máximo: 100%", a rolagem da prévia e o layout em 390 px ficam para a
  conferência no navegador da Tarefa 8.

### Tarefa 4 — Corrigir pagamento e desfazer baixa — **Estado: testada**

**Arquivos:** `CobrancaController::corrigirPagamento` (~332) e `desfazerBaixa` (~354),
`resources/js/Components/CorrecaoPagamentoInline.vue`, `resources/js/Pages/Cobrancas/Index.vue:417` (passa
`valor-centavos` e `desconto-centavos`), `tests/Feature/Cobrancas/DescontoNaBaixaTest.php`.

**Interfaces:** consome `descontoValidado` (Tarefa 2). `CorrecaoPagamentoInline` ganha props `valorCentavos` e
`descontoCentavos`; o formulário inicia com `desconto_tipo: 'valor'` e `desconto` = desconto atual formatado.

- [x] **Passo 1: testes que falham:** `test_corrigir_muda_data_e_desconto_com_auditoria_antes_e_depois`;
  `test_corrigir_sem_mexer_no_desconto_mantem_o_desconto` (envia o desconto atual em R$ → igual);
  `test_corrigir_desconto_exige_senha_e_motivo`; `test_desfazer_baixa_zera_o_desconto`.
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** implementar (auditoria `pagamento.corrigido` com antes/depois de `pago_em` e `desconto_centavos`).
- [x] **Passo 4:** tela de correção com os campos de desconto e o valor pago novo.
- [x] **Passo 5:** testes + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: corrigir pagamento muda o desconto; desfazer baixa zera`.

  Registro: (1) Pedido **sem** `desconto`/`desconto_tipo` corrige só a data e **mantém** o desconto (a tela sempre manda
  os dois; campo vazio com o tipo = tirar o desconto). (2) `antes`/`depois` de `pagamento.corrigido` ganharam
  `desconto_centavos` (o `AuditoriaTest` antigo, que esperava só `pago_em`, foi atualizado) e o `resumo` só cita o
  desconto quando ele muda; `baixa.desfeita` também grava o desconto desfeito em `antes`/`depois`. (3) A lógica da tela
  (valor pago, trava, trocar tipo limpa, aviso) saiu do `BaixaInline` para `usarDescontoDaBaixa.js` e é usada pelos dois
  componentes; por ser função sobre um `useForm`, ganhou teste `node --test` (trava, troca limpa, vírgula final,
  integral), o que cobre o ponto de revisão 2 sem navegador. (4) `Cobranca::paraLista` ganhou `desconto_centavos`
  (decisão do controlador). (5) Texto do desfazer cita o desconto que será desfeito. (6) Só a renderização inicial dos
  dois componentes foi conferida (SSR do Vite, sem navegador); interação e layout em 390 px ficam para a Tarefa 8.

### Tarefa 5 — Lista de cobranças e painel — **Estado: testada**

**Arquivos:** `app/Models/Cobranca.php` (`paraLista`), `app/Services/ListaDeCobrancas.php` (linhas 55, 77, 116),
`app/Http/Controllers/PainelController.php` (linhas 55 e 78), `resources/js/Pages/Cobrancas/Index.vue`,
`resources/js/Pages/Painel.vue`, testes `tests/Feature/Cobrancas/DadosDaListaTest.php`, `FiltroSituacaoTest.php`,
`tests/Feature/PainelTest.php`.

**Interfaces:** `paraLista` ganha `desconto_centavos` e `valor_pago` (formatado). Soma de pagas em SQL:
`sum(valor_centavos - desconto_centavos)`.

- [x] **Passo 1: testes que falham:** aba/rodapé "Pagas" soma o valor pago e as demais o valor cheio
  (`FiltroSituacaoTest`); recebido do mês no painel = valor − desconto (`PainelTest`); cobrança paga sem desconto
  mantém as somas de antes (ponto de revisão 5).
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** servidor (`leves` passa a buscar `desconto_centavos`; `paga` soma o valor pago).
- [x] **Passo 4:** tela: paga com desconto mostra o valor cheio e, embaixo e menor, "pago R$ …"; mesma coisa em
  "Recebidas no mês" do painel.
- [x] **Passo 5:** testes + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: lista e painel somam o valor pago das cobranças pagas`.

  Registro: (1) Só a situação "paga" soma o valor pago; as demais seguem com o valor cheio. Vale nos quatro lugares da
  lista (`leves` → totais por situação e rodapé; `$agregar` das abas com `Cobranca::VALOR_PAGO_SQL`) e nos dois do painel
  (resumo `recebido` e `total_geral` de "Recebidas no mês"). O `previsto` do painel continua com o valor cheio.
  (2) `Cobranca::VALOR_PAGO_SQL` (`valor_centavos - desconto_centavos`) é a conta em SQL, para a Tarefa 6 reusar; no
  painel entra por `DB::raw` dentro de `sum()`, antes do `ORDER BY` (PostgreSQL). **Não conferido no PostgreSQL**
  (`desconto_centavos` ainda não existe no banco local; migrar é do portão). (3) `paraLista` ganhou `valor_pago`.
  (4) Tela: "pago R$ …" só quando `situacao === 'paga'` e `desconto_centavos > 0`, em `Cobrancas/Index.vue` (coluna Valor)
  e em `LinhaCobranca.vue` (as três listas do painel, na prática só "Recebidas no mês"); `Painel.vue` não mudou, o
  resumo vem pronto do servidor. (5) Só a renderização de `LinhaCobranca` foi conferida (SSR do Vite, sem navegador:
  com desconto, 100%, sem desconto e pendente); a coluna da lista (xl e 390 px) fica para a Tarefa 8.

### Tarefa 6 — Relatórios — **Estado: testada**

**Arquivos:** `app/Services/Relatorios.php` (`faturamento`, `porCliente`, `porProduto`), `resources/js/relatorios.js`
(se lista colunas), `tests/Feature/Relatorios/*` (inclui `FaturamentoTest::test_csv_para_o_excel_em_portugues`).

**Interfaces:** faturamento ganha coluna `self::coluna('descontos', 'Descontos')` logo depois de `recebido`; por
produto ganha a linha `item => 'Descontos concedidos'` com `recebido` = −(soma dos descontos das pagas no período) e
`previsto` 0; os itens e "Sem item" continuam com o valor cheio dos itens, então o total = painel.

- [x] **Passo 1: testes que falham:** faturamento (recebido = valor − desconto; coluna "Descontos"; previsto e
  inadimplência cheios); por cliente (recebido e média com valor pago); por produto (linha negativa e total igual ao
  recebido do painel no mesmo período); CSV byte a byte com a coluna nova; sem desconto, relatório igual ao de antes.
- [x] **Passo 2:** rodar `php artisan test --compact tests/Feature/Relatorios` → FALHAM.
- [x] **Passo 3:** implementar. Contas a receber não muda.
- [x] **Passo 4:** testes → PASSAM. Pint. Build se `relatorios.js` mudou (`relatorios.js` não mudou; `itens.js` mudou, então build).
- [x] **Passo 5:** commit `feat: relatórios usam o valor pago e mostram os descontos`.

  Registro: (1) **Faturamento:** `recebido` soma `valorPagoCentavos()` e a coluna nova `descontos` (logo depois de
  `recebido`, `soma: true`) soma `desconto_centavos` das pagas com `pago_em` no mês; `previsto` e `inadimplencia` seguem com
  o valor cheio (a paga com atraso entra cheia no atraso do mês em que venceu). A coluna existe sempre (com 0,00 sem
  desconto). (2) **Por cliente:** `sum(Cobranca::VALOR_PAGO_SQL)` no `selectRaw` (recebido e, por consequência, a média).
  (3) **Por produto:** itens e "Sem item" com o valor cheio; a linha `Descontos concedidos` (`recebido` = −soma, `previsto`
  0, `contratos` 0) é a **última** e só existe se a soma for maior que 0; `totais.recebido` = `resumo.recebido` do painel
  (provado em teste). (4) **Achado fora da lista de arquivos:** `Dinheiro::formatar` e `formatarCentavos` (JS) não
  formatavam negativo (`-24,-76` e `-,50`) e o CSV poria apóstrofo em `-22,00` (a defesa contra fórmula olhava todo texto
  que começa com `-`). Corrigidos nos três lugares, com teste. (5) Contas a receber não mudou. (6) Sem `ORDER BY` junto de
  agregado nas consultas novas (PostgreSQL); **não conferido no PostgreSQL** (a coluna `desconto_centavos` ainda não está
  no banco local; migrar é do portão). (7) Asserções antigas que mudaram: ver o relatório da tarefa.

### Tarefa 7 — Recibo v2 substitui o v1 — **Estado: testada**

**Arquivos:** `app/Enums/ModeloRecibo.php`, `app/Enums/FamiliaModelo.php` (`versaoAtual`),
`app/Services/RenderizadorDeRecibo.php` (`dados`), criar `resources/views/recibos/pdf/webiss-v2.blade.php` e
`danfse-v2.blade.php`, apagar os v1, migração de dados `troca_modelo_dos_recibos_para_v2`, testes
`tests/Feature/Recibos/*`, `TrocaDeModeloTest`, `AcoesCobrancaTest`, `Contas/UsuarioModeloTest`, teste novo
`tests/Feature/Recibos/MigracaoReciboV2Test.php`.

**Interfaces:** `dados()` ganha `desconto` (formatado, ou `null` quando não está paga ou desconto = 0) e
`valor_pago` (formatado, ou `null` no mesmo caso). v2 = v1 com: WebISS — no `@foreach` de rótulos, `Desc. Cond. (R$)`
mostra `$desconto ?? '0,00'` (o texto original dizia `'-'`; a prova de igualdade mostrou que o v1 imprime `0,00`);
`Valor Líquido (R$)` mostra `$valor_pago ?? $valor`. DANFSe — linha 137 `Desconto
Condicionado` mostra `$desconto ?? '-'`; linhas 141 e 143 (valor líquido) mostram `$valor_pago ?? $valor`.
"Valor Total" continua `$valor`.

- [x] **Passo 1:** copiar os v1 para v2 e acrescentar os casos `WebissV2`/`DanfseV2` (sem apagar nada ainda).
- [x] **Passo 2: prova de igualdade (temporária):** teste que renderiza a view v1 e a v2 com os mesmos `dados()` de
  uma cobrança **sem** desconto (WebISS e DANFSe, paga e pendente) e exige HTML idêntico → PASSA. Registrar o
  resultado no "Registro de execução" deste plano.
- [x] **Passo 3: testes que falham:** paga com desconto preenche os dois campos (texto do PDF via `RenderizadorDeReciboTest`);
  `AmostrasVisuaisTest` ganha as combinações com desconto e continua exigindo 1 página;
  `MigracaoReciboV2Test`: `up` troca só `modelo` (v1 → v2, as duas famílias, número/snapshot iguais) e `down` volta
  (`(require database_path('migrations/…'))->up()`); `FamiliaModelo::opcoes()` continua com 2 itens e
  `versaoAtual()` devolve v2.
- [x] **Passo 4:** rodar `php artisan test --compact tests/Feature/Recibos tests/Feature/TrocaDeModeloTest.php` → FALHAM.
- [x] **Passo 5:** implementar os campos nos v2, `dados()`, `versaoAtual()`, migração de dados; apagar o teste
  temporário do passo 2, os templates v1 e os casos v1; atualizar os 5 arquivos de teste que esperavam v1.
- [x] **Passo 6:** suíte inteira `php artisan test --compact` → verde. Pint.
- [x] **Passo 7:** commit `feat: recibos v2 mostram desconto e valor pago e substituem o v1`.

  Registro: (1) **Ordem real** (decisão do controlador): copiar → testes que falham (13 vermelhos) → campos do v2 e
  `dados()` → prova de igualdade (ver "Registro de execução", abaixo) → apagar teste temporário, v1 e casos v1. (2) **Brief
  corrigido pela prova:** no WebISS o v1 imprime `0,00` em `Desc. Cond. (R$)` (não `-`); o v2 usa `$desconto ?? '0,00'`.
  (3) **DANFSe:** `Desconto Condicionado` mostra `R$ 24,76` (com o "R$ " das células vizinhas) ou `-`; as duas linhas de valor
  líquido mostram `R$ {{ $valor_pago ?? $valor }}`. "Valor Total" / "VALOR DA OPERAÇÃO / SERVIÇO" seguem com o valor cheio.
  (4) `dados()['desconto']` e `['valor_pago']` são `null` quando a cobrança não está paga **ou** o desconto é 0 (a regra
  mora em `dados()`, os templates só usam `??`). (5) Migração `2026_11_01_105832_troca_modelo_dos_recibos_para_v2` com
  `DB::table` (troca só `modelo`, não toca `updated_at`); `MigracaoReciboV2Test` prova `up`, `down`, as duas famílias e que
  tudo o mais da linha fica igual. **Nunca rodada fora do SQLite em memória**; no Postgres local é do portão (Tarefa 8).
  (6) `FamiliaModelo::opcoes()` segue com 2 famílias (teste em `UsuarioModeloTest`). (7) Asserções antigas que mudaram: só
  `WebissV1`/`DanfseV1`/`*-v1` → v2 em `TrocaDeModeloTest`, `AcoesCobrancaTest`, `EmissaoReciboTest`, `PdfReciboTest` e
  `UsuarioModeloTest`. (8) `AmostrasVisuaisTest::test_paga_com_desconto_cabe_em_uma_pagina_nos_dois_modelos` (desconto,
  integral e sem desconto, 25 linhas) não podia ficar vermelho antes (o v1 ignora o desconto): é guarda contra o v2 estourar a
  página; passa em 1 página.

### Tarefa 8 — Wizard, conferência e documentação — **Estado: pendente**

**Arquivos:** `.claude/skills/recibos/SKILL.md` (acertos e pegadinhas da Fase 26; atualizar a regra do `-v2` com a
exceção consciente), `.claude/plans/2026-10-10-roteiro-modulos.md` (estado), README (só se algo de uso mudou).

- [ ] **Passo 1:** suíte da Fase 25 sem mudar asserção: `php artisan test --compact
  tests/Feature/ProgressoDeConfiguracaoTest.php tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php` e
  `node --test resources/js/progressoHolofote.test.js resources/js/progresso.test.js` → PASSAM. `git diff main --
  tests/Feature/ProgressoDeConfiguracao*` vazio.
- [ ] **Passo 2:** `php artisan test --compact` (tudo) e `node --test resources/js/` → verdes; `npm run build` ok.
- [ ] **Passo 3: PORTÃO-BANCO (Postgres local, cópia de produção):** mostrar conexão, contagem (`cobrancas`,
  `recibos` por `modelo`), o comando literal `php artisan migrate`, e só rodar com o "sim" do Marcos. Recontar depois.
- [ ] **Passo 4: conferência no navegador** (pedir autorização antes; avisar que o browser-agent não está instalado,
  só o Playwright; conta de teste, nunca dados reais): baixa individual (%, R$, trava, troca de tipo, integral),
  lote, correção, lista e painel, 390 px sem rolagem e 1280 px; faixa de baixa com o coach aberto e recolhido.
- [ ] **Passo 5:** PDF de amostra com desconto em `storage/app/private/verificacao/` para a conferência visual do
  Marcos.
- [ ] **Passo 6:** documentação, estados deste plano, commit `docs: registra a Fase 26 (desconto na baixa)`; com tudo
  `testada`, merge `--no-ff` no `main` + push. Avisar o Marcos: o deploy roda as duas migrações em produção
  (a segunda **altera todos os recibos**); backup antes.

## Registro de execução

### Tarefa 7 — prova de igualdade v1 × v2 (2026-11-01, feita com o v2 já editado e antes de apagar o v1)

Teste temporário `tests/Feature/Recibos/ProvaIgualdadeV1V2Test.php` (apagado depois): renderiza `view('recibos.pdf.{familia}-v1', $dados)`
e `-v2` com os **mesmos** `dados()` de cobranças **sem** desconto — WebISS e DANFSe × pendente, pendente com PIX (QR no
HTML) e paga — e exige `assertSame` no HTML inteiro (6 comparações).

- **1ª rodada, com o v2 escrito como o brief diz** (WebISS: `Desc. Cond. (R$)` mostra `$desconto ?? '-'`): **FALHOU** em
  `webiss pendente`. Diferença única: `<span class="val">0,00</span>` no v1 × `<span class="val">-</span>` no v2. O v1 imprime
  `0,00` em todas as colunas desse `@foreach`; o brief supôs `-` (que é o que o DANFSe imprime). A spec §3 manda "sem
  desconto, o v2 é igual ao v1", então o padrão do WebISS é `0,00`: `$desconto ?? '0,00'`.
- **2ª rodada, com `?? '0,00'`:** `resultado: passed | testes: 1 | passaram: 1 | asserções: 13` — HTML idêntico nos 6 casos.
- DANFSe passou nas duas rodadas (o `-` do v1 é o mesmo `?? '-'` do v2).
