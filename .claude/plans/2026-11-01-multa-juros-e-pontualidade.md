# Fase 27 — multa, juros, outros acréscimos e desconto por pontualidade — plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: superpowers:subagent-driven-development (recomendado) ou
> superpowers:executing-plans, tarefa por tarefa. Passos com caixa (`- [ ]`) para acompanhar.
> **Não iniciar sem o comando do Marcos** (pedido dele em 2026-11-01).

**Objetivo:** o contrato define desconto por pontualidade, multa e juros (% ou R$); a baixa traz esses valores
preenchidos e editáveis, aceita "outros acréscimos", e o valor do dia aparece na lista, no recibo, na mensagem e no PIX.

**Arquitetura:** regra única e pura em `App\Support\CondicoesDePagamento`, espelhada em `resources/js/condicoes.js`. A
cobrança grava `multa_centavos`, `juros_centavos` e `outros_centavos` na baixa (o desconto continua em
`desconto_centavos`). `App\Services\ValoresDaBaixa` decide, para baixa, lote e correção, o que vale entre o digitado e o
contrato. Valor pago continua derivado.

**Stack:** Laravel 13 / PHP 8.4, Inertia v3 + Vue 3, PHPUnit (SQLite em memória), `node --test`, dompdf.

**Spec:** `docs/superpowers/specs/2026-11-01-multa-juros-e-pontualidade-design.md` (ler inteira antes de começar).

**Depende da Fase 26:** começar só depois do merge dela no `main`.

**Estados das tarefas:** pendente / concluída / testada / validada.

## Restrições globais

- Tudo em pt-BR: código novo, testes, mensagens, commits. Nomes impostos pelo framework ficam como são.
- Ler a skill `.claude/skills/recibos/SKILL.md` inteira antes de mexer; aplicar os "Padrões de usabilidade".
- Dinheiro em centavos (`int`), sem float. Regra de contrato em inteiros: percentual em pontos-base (2% = 200), R$ em
  centavos. Percentual: até 3 dígitos inteiros e 2 decimais, vírgula ou ponto, máximo 100.
- Multa: `intdiv(valor × pontosBase + 5000, 10000)`. Juros em %: `intdiv(valor × pontosBase × dias + 150000, 300000)`.
  Juros em R$: `intdiv(centavos × dias + 15, 30)`. Base: sempre o valor original. No JS, `BigInt` (o produto passa de
  2^53).
- `valor pago = valor − desconto + multa + juros + outros`, sempre derivado. Sem atraso, o servidor zera multa, juros e
  outros. `0 ≤ desconto ≤ valor`.
- Auditoria (texto exato da spec): `Baixa registrada (R$ 210,00, multa R$ 4,20, juros R$ 21,00, outros R$ 5,00, desconto
  R$ 0,00, pago R$ 240,20, em 01/11/2026)`. Só os acréscimos maiores que zero entram no texto. Sem acréscimos, os
  textos da Fase 26 ficam como estão.
- Sem regra no contrato e sem valores gravados, tudo sai igual ao de hoje: lista, recibo, PIX, mensagem e relatórios.
- Wizard da Fase 25 intocado: rotas de baixa fora de `progresso:*`; suíte da Fase 25 verde sem mudar asserção.
- Depois de mexer em PHP: `vendor/bin/pint --dirty --format agent`. Depois de mexer em `.vue`/`.js`: `npm run build`
  (parar se falhar). Testes JS: `node --test 'resources/js/**/*.test.js'` (o Node 24 falha com o diretório).
- PORTÃO-BANCO: testes só em SQLite em memória. `migrate` no Postgres local (cópia de produção) só com conexão, contagem,
  comando literal e "sim" do Marcos, comando a comando. VPS: acesso só com autorização expressa do Marcos.
- Branch `feat/multa-juros-pontualidade` saído do `main`; um commit por tarefa, com o estado da tarefa neste plano e a
  skill atualizados no mesmo commit. Fase concluída e testada: merge `--no-ff` no `main` + push (autorização permanente da
  memória `merge-e-push-por-fase`).

## Pontos de revisão (o que os testes de cada tarefa precisam pegar)

1. Vencimento e pagamento no mesmo dia, e diferença de datas em fuso de São Paulo e na virada do horário: 0 dia de atraso,
   sem acréscimo, com desconto → Tarefa 1.
2. Contrato renovado vencido (cria contrato novo) leva as condições junto → Tarefa 2.
3. Cobrança sem contrato (avulsa, ou contrato excluído, com `contrato_id` nulo) abre a baixa, a lista e o recibo sem erro
   → Tarefas 3, 6 e 8.
4. Regra do contrato alterada depois da baixa não muda valor, recibo nem relatório da cobrança paga → Tarefas 3 e 8.
5. No lote com "usar a data de vencimento", cobrança a vencer é baixada com a data de hoje e recebe o desconto do contrato;
   vencida não recebe acréscimo → Tarefa 4.

---

### Tarefa 1 — Dados e regra de cálculo — **Estado: testada**

**Arquivos:**
- Criar: migrações `add_condicoes_de_pagamento_to_contratos_table` e `add_acrescimos_to_cobrancas_table`,
  `app/Support/CondicoesDePagamento.php`, `resources/js/condicoes.js`, `resources/js/condicoes.test.js`,
  `tests/Unit/CondicoesDePagamentoTest.php`, `tests/Feature/Cobrancas/CondicoesDaCobrancaTest.php`.
- Modificar: `app/Models/Contrato.php` (fillable, casts), `app/Models/Cobranca.php` (`VALOR_PAGO_SQL`,
  `valorPagoCentavos`, casts, `$attributes` com os três em 0, fora do `#[Fillable]`), `database/factories/ContratoFactory.php`
  (estado `comCondicoes()`: desconto 15%, multa 2%, juros 1%).

**Interfaces (produz):**
- `CondicoesDePagamento::diasDeAtraso(CarbonInterface $vencimento, CarbonInterface $data): int` (≥ 0, dias corridos).
- `::multaCentavos(int $valor, ?string $tipo, ?int $regra): int`
- `::jurosCentavos(int $valor, ?string $tipo, ?int $regra, int $dias): int`
- `::descontoCentavos(int $valor, ?string $tipo, ?int $regra): int` (limitado a `$valor`).
- `::regraDaEntrada(string $tipo, ?string $entrada): ?int` — vazio ou `0` → `0`; inválido ou acima de 100% → `null`.
- `::entradaDaRegra(?string $tipo, ?int $regra): string` — `1500`/`percentual` → `15`; `250`/`percentual` → `2,5`;
  `1500`/`valor` → `15,00`; sem regra → `''`.
- `Contrato::regrasDePagamento(): ?array` — `{desconto: {tipo, valor}|null, multa: …, juros: …}`, `null` sem nenhuma regra.
- `Cobranca::condicoesNaData(CarbonImmutable $data): array` — `{dias, desconto_centavos, multa_centavos, juros_centavos,
  total_centavos}`, pela tabela "valor do dia" da spec; sem contrato ou sem regra, `total_centavos` = valor.
- `Cobranca::acrescimosCentavos(): int` — soma dos três gravados.
- JS `diasDeAtraso(vencimentoIso, dataIso)`, `multaEmCentavos(valor, tipo, regra)`, `jurosEmCentavos(valor, tipo, regra,
  dias)`, `descontoDoContrato(valor, tipo, regra)`, `regraDaEntrada(tipo, entrada)`, `entradaDaRegra(tipo, regra)`,
  `condicoesNaData(valor, vencimentoIso, dataIso, regras)` → mesmos campos em camelCase. Reusa `descontoEmCentavos`.

- [x] **Passo 1: testes que falham**, os mesmos casos em PHP e em JS, valor 21000:
  multa 200 → 420; multa de 500 centavos → 500; `10050` a 100 → 101 (meio centavo para cima).
  Juros 100: 0 dia → 0; 1 → 7; 15 → 105; 30 → 210; 300 → 2100; `45000` a 10, 1 dia → 2 (meio centavo).
  Juros de 3000 centavos ao mês: 1 dia → 100; 15 → 1500; 45 centavos ao mês, 1 dia → 2.
  Desconto: `10000` a 1500 → 1500; 1500 centavos → 1500; 20000 centavos sobre `10000` → 10000. Sem regra: tudo 0.
  Sem estouro: `1000000000` a 10000, 3650 dias → 121666666667 (no JS, exige `BigInt`).
  `2026-01-05` a `2026-11-01` → 300; mesmo dia → 0; data anterior → 0; `2026-02-15` a `2026-02-16` → 1.
  `regraDaEntrada`: `'2'` → 200; `'2,5'` e `'2.5'` → 250; `'100'` → 10000; `'150'` → null; `'abc'` → null; `'15,00'` em R$ → 1500.
  JS: rodar o arquivo também com `TZ=America/Sao_Paulo`.
  `CondicoesDaCobrancaTest`: valor 10000, com `comCondicoes()`, vencimento 05/01: em 05/01 → desconto 1500, multa 0, total
  8500; em 04/02 (30 dias) → desconto 0, multa 200, juros 100, total 10300. Sem contrato → total 10000.
  `valorPagoCentavos` com valor 21000, desconto 1000, multa 420, juros 2100, outros 500 → 23020; soma por
  `VALOR_PAGO_SQL` igual.
- [x] **Passo 2:** `php artisan test --compact tests/Unit/CondicoesDePagamentoTest.php tests/Feature/Cobrancas/CondicoesDaCobrancaTest.php`
  e `node --test resources/js/condicoes.test.js` → FALHAM (classe e módulo inexistentes).
- [x] **Passo 3:** migrações conforme spec §1 (colunas de contrato nulas; de cobrança com padrão 0), models, classe e módulo.
- [x] **Passo 4:** os mesmos comandos → PASSAM. Suíte inteira verde (a fórmula nova não muda quem tem zero). Pint.
- [x] **Passo 5:** commit `feat: regra única de multa, juros e desconto por pontualidade (PHP e JS)`.

### Tarefa 2 — Condições no contrato — **Estado: testada**

**Arquivos:** `app/Http/Requests/ContratoRequest.php`, `app/Http/Controllers/ContratoController.php` (`store`, `update`,
recomeço ~363, renovação de vencido ~445), `resources/js/Pages/Contratos/Form.vue`, criar
`resources/js/Components/CampoValorOuPercentual.vue`, `tests/Feature/ContratosTest.php`,
`tests/Feature/RenovarContratoVencidoTest.php`.

**Interfaces:**
- Consome: Tarefa 1.
- Produz: `CampoValorOuPercentual.vue` — props `rotulo`, `tipo` (v-model), `modelValue` (v-model), `erro`, `ajuda`,
  `etiqueta` (opcional), `valorMaximoCentavos` (opcional), `somenteReais` (sem seletor), `id`. Máscara `valor` em R$ e `percentual`
  em %; `aria-describedby` com ajuda e erro. Usa `formatadores` de `mascaras.js` e a trava de `desconto.js`.
- Pedido do contrato: `desconto_pontualidade_tipo`, `desconto_pontualidade`, `multa_tipo`, `multa`, `juros_tipo`, `juros`
  (texto); o request grava `*_tipo` e `*_valor` com `regraDaEntrada`. Vazio ou `0` → `null` nos dois.

- [x] **Passo 1: testes que falham:** `test_grava_condicoes_em_percentual_e_em_reais` (`'2,5'` % → 250; `'15,00'` R$ →
  1500); `test_contrato_sem_condicoes_continua_valido`; `test_zero_vira_sem_regra`; `test_percentual_acima_de_100_e_recusado`
  (erro no campo); `test_desconto_em_reais_acima_do_valor_do_contrato_e_recusado`;
  `test_mudar_condicoes_audita_antes_e_depois` (`contrato.condicoes`) e `test_salvar_sem_mudar_condicoes_nao_audita`;
  `test_outro_usuario_nao_ve_condicoes`; em `RenovarContratoVencidoTest`, `test_renovar_vencido_leva_as_condicoes`.
- [x] **Passo 2:** rodar os dois arquivos → FALHAM.
- [x] **Passo 3:** request, controller e cópia das condições na renovação de vencido (o `create` de ~445 lista os campos um a
  um; o de ~363 já recebe `$dados`). `edit` manda os campos com `entradaDaRegra`.
- [x] **Passo 4:** `CampoValorOuPercentual.vue` e seção "Condições de pagamento" no `Form.vue`, com o exemplo da spec §2
  recalculado com `condicoes.js`: `Exemplo com o valor deste contrato (R$ 100,00): em dia R$ 85,00 · 30 dias de atraso R$ 103,00`.
  Lógica do exemplo em função pura com teste `node --test`.
- [x] **Passo 5:** testes + JS + build → PASSAM. Suíte da Fase 25 verde. Pint.
- [x] **Passo 6:** commit `feat: contrato define desconto por pontualidade, multa e juros`.

**Decisão do controlador (provada):** mudar só as condições não aciona o fluxo de correção do cronograma nem refaz cobranças
(`test_mudar_so_as_condicoes_nao_pede_correcao_nem_mexe_nas_cobrancas`). Além do previsto, `test_recomecar_leva_as_condicoes_do_formulario_ao_contrato_novo`
(`EncerramentoContratoTest`), `test_formato_ou_tipo_invalido_das_condicoes_e_recusado` e, no JS, `regraDoCampo`/`exemploDasCondicoes`
(`condicoes.test.js`) e `totalDosItens` (`itens.test.js`). Falta conferir no navegador (390 px e 1280 px, digitação com a máscara, trava
e exemplo ao vivo): fica para a Tarefa 10.

### Tarefa 3 — Baixa individual — **Estado: testada**

**Arquivos:** criar `app/Services/ValoresDaBaixa.php`, `tests/Feature/Cobrancas/AcrescimosNaBaixaTest.php`; modificar
`CobrancaController` (`pagar`, `resumoDaBaixa`), `Cobranca::paraLista`, `ListaDeCobrancas` (~89) e `PainelController` (~89)
(`with` do contrato), `BaixaInline.vue`, `usarValoresDaBaixa.js` e teste.

**Interfaces:**
- Consome: Tarefas 1 e 2.
- Produz: `ValoresDaBaixa::resolver(Cobranca $cobranca, CarbonImmutable $pagoEm, array $dados, string $modo): array` —
  `$modo` em `individual` | `lote` | `correcao`; devolve `{desconto_centavos, multa_centavos, juros_centavos,
  outros_centavos, rotulo_desconto}` (`rotulo_desconto` é o texto da auditoria, como `10% = R$ 24,76`, também para a regra do
  contrato, via `entradaDaRegra`), pela tabela "Entrada na baixa" da spec; sem atraso, zera os três acréscimos; lança
  `ValidationException` no campo que errou, com as mensagens do desconto da Fase 26.
- Produz: `ValoresDaBaixa::regras(): array` (regras do `validate` dos sete campos).
- `paraLista` ganha `vencimento_iso` e `regras` (`Contrato::regrasDePagamento()`, com `contrato` carregado junto).

- [x] **Passo 1: testes que falham** (`AcrescimosNaBaixaTest`), valor 21000, vencimento 05/01/2026, `comCondicoes()` com multa
  2% e juros 1%: `test_pedido_sem_campos_usa_o_contrato` (em 01/11/2026 → multa 420, juros 2100);
  `test_valor_digitado_vence_o_contrato`; `test_campo_vazio_fica_sem_valor`; `test_zero_fica_sem_valor`;
  `test_outros_soma_e_audita` (500 → texto exato da restrição global, pago R$ 240,20 com multa e juros);
  `test_sem_atraso_zera_acrescimos_mesmo_preenchidos`; `test_pago_no_vencimento_aplica_desconto_do_contrato`;
  `test_pago_atrasado_nao_aplica_desconto_do_contrato`; `test_cobranca_sem_contrato_aceita_lancamento_manual`;
  `test_multa_invalida_da_erro_no_campo_e_nao_grava`; `test_percentual_acima_de_100_e_recusado`;
  `test_cobranca_alheia_da_404_antes_da_validacao`; `test_mudar_contrato_depois_nao_muda_o_valor_gravado`;
  `test_lista_nao_faz_consulta_por_linha_ao_carregar_contrato` (contar consultas com 1 e com 5 cobranças: iguais).
  Em `DescontoNaBaixaTest`, nada muda de asserção.
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** `ValoresDaBaixa`, `pagar` e `resumoDaBaixa` (troca `descontoValidado` por `resolver`). Desconto integral sem
  acréscimos mantém o texto da Fase 26.
- [x] **Passo 4:** `BaixaInline.vue` conforme spec §2, usando `CampoValorOuPercentual` e `condicoes.js`; lógica de "trocar a data
  recalcula o não editado" e "Recalcular" em função pura testada em `usarValoresDaBaixa.test.js`.
  Painel e lista passam `:vencimento-iso="cobranca.vencimento_iso"` e `:regras="cobranca.regras"`.
- [x] **Passo 5:** testes + JS + build → PASSAM. Suíte da Fase 25 verde. Pint.
- [x] **Passo 6:** commit `feat: baixa traz multa e juros do contrato e aceita outros acréscimos`.

**Registro (2026-11-01):** os 14 testes do passo 1 mais `test_juros_em_reais_digitado_e_ao_mes`, `test_tipo_ausente_ou_invalido_da_erro_no_tipo`,
`test_com_acrescimo_a_auditoria_mostra_o_desconto_mesmo_integral`, `test_acima_do_teto_em_reais_e_recusado` (multa, juros e outros, no teto passa),
`test_lista_e_painel_levam_o_vencimento_e_as_regras_do_contrato` e três do `resolver` nos modos lote e correção (a tabela da spec). A lista e o painel
carregam o contrato junto (`ListaDeCobrancas` e `PainelController` são os únicos chamadores de `paraLista`). Decisões do controlador cumpridas:
`CampoValorOuPercentual` ganhou `complementoDoMaximo`, o evento `digitar` (limpa o erro do servidor) e o slot `calculado`; a etiqueta "contrato" usa
`Etiqueta.vue` com a variante `compacta`; o teto de R$ vale para `outros` pela mesma `regraDaEntrada`, no servidor e na trava da tela. **Desvio:** a trava e o
aviso de `usarDescontoDaBaixa` não foram apagados, porque `BaixaEmLote` e `CorrecaoPagamentoInline` ainda a usam (as Tarefas 4 e 5 trocam por
`CampoValorOuPercentual` e apagam a função); a `BaixaInline` já não a usa, então a trava não roda duas vezes. Falta conferir no navegador (390 px e
1280 px, digitação com a máscara, "Recalcular", o valor calculado embaixo de cada campo): fica para a Tarefa 10.

### Tarefa 4 — Baixa em lote — **Estado: testada**

**Arquivos:** `CobrancaController::pagarEmLote` e `conferirDescontoDoLote`, `BaixaEmLote.vue`, `resources/js/desconto.js`
(`previaDoLote`), `tests/Feature/Cobrancas/AcoesEmLoteTest.php`.

**Interfaces:** consome `ValoresDaBaixa::resolver(…, 'lote')`. `previaDoLote` passa a receber as linhas
(`{valorCentavos, vencimentoIso, regras}`), a data de cada uma e os campos do topo, e devolve por linha
`{valorCentavos, acrescimosCentavos, descontoCentavos, pagoCentavos}` e os totais.

- [x] **Passo 1: testes que falham:** `test_em_branco_usa_o_contrato_de_cada_cobranca` (dois contratos com multas diferentes,
  clientes diferentes); `test_preenchido_vale_para_todas`; `test_percentual_calculado_por_cobranca_e_reais_igual_em_cada`;
  `test_zero_zera_em_todas`; `test_so_cobranca_em_atraso_recebe_acrescimo`; `test_modo_vencimento_nao_gera_acrescimo`
  (ponto 5); `test_modo_vencimento_a_vencer_recebe_desconto_do_contrato` (ponto 5);
  `test_multa_invalida_recusa_o_lote_sem_gravar_nada`; `test_lote_sem_regra_fica_igual` (mesmos valores de antes);
  auditoria com o texto da restrição global em cada cobrança. JS: `previaDoLote` com o mesmo exemplo do PHP, soma igual.
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** servidor. Resolver todas as cobranças **antes** da transação, como o desconto já faz: recusa não grava nada.
- [x] **Passo 4:** `BaixaEmLote.vue` conforme spec §2, com `CampoValorOuPercentual`, ajuda e aviso exatos da spec, e a prévia com
  Vencimento, Valor, Acréscimos, Desconto e Pago. Em branco é diferente de zero, inclusive no envio.
- [x] **Passo 5:** testes + JS + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: lote aplica o contrato de cada cobrança e aceita valores para todas`.

**Registro (2026-11-01):** os testes do passo 1 estão em `AcoesEmLoteTest` (43 no arquivo; `em_branco` roda com o campo ausente e com o campo vazio, e `multa_invalida` com 9 entradas, nas duas datas),
mais `test_desconto_do_contrato_em_reais_acima_do_valor_fica_no_valor` e `test_soma_gravada_do_lote_bate_com_a_previa_da_tela`; o JS tem 12 testes novos de `previaDoLote` e `dataDaBaixaEmLote`
com os mesmos números (R$ 210,00 e R$ 100,00 vencidos em 05/01/2026, relógio em 01/11/2026, soma paga R$ 548,70). `pagarEmLote` conferiu o limite do desconto em R$ pela menor
(`conferirDescontoDoLote`, como antes) e resolveu todas as cobranças (`ValoresDaBaixa::resolver`, modo `lote`) **antes** da transação; a gravação e a auditoria saíram para `registrarBaixa`,
que a baixa individual também usa. **Desvios:** (1) `previaDoLote` mora em `usarValoresDaBaixa.js` e não em `desconto.js`, porque precisa de `camposDaRegra` e `calcularBaixa` e `condicoes.js` já
importa `desconto.js` (importação circular); `desconto.js` perdeu a versão antiga e `menorValor` ficou. (2) R8: do `usarDescontoDaBaixa()` saiu só o `complementoDoMaximo` (único pedaço que só o lote
usava); a trava e o aviso ficam porque `CorrecaoPagamentoInline` ainda os usa, e a Tarefa 5 apaga a função. (3) A auditoria do lote com acréscimo começa por "Baixa registrada" (texto da spec, igual ao
individual); sem acréscimo segue "Baixa em lote". Falta conferir no navegador: digitação com a máscara, a trava e o aviso nos quatro campos, 390 px e 1280 px, a prévia com os totais e o aviso da data de vencimento
(a renderização inicial foi conferida sem navegador): fica para a Tarefa 10.

### Tarefa 5 — Corrigir pagamento e desfazer baixa — **Estado: testada**

**Arquivos:** `CobrancaController::corrigirPagamento` e `desfazerBaixa`, `CorrecaoPagamentoInline.vue`,
`Cobrancas/Index.vue`, `Cobranca::paraLista`, `tests/Feature/Cobrancas/AcrescimosNaBaixaTest.php`, `AuditoriaTest`.

**Interfaces:** consome `ValoresDaBaixa::resolver(…, 'correcao')`. `paraLista` ganha `multa_centavos`, `juros_centavos` e
`outros_centavos`. Remover `descontoValidado` se não sobrar chamador.

- [x] **Passo 1: testes que falham:** `test_corrigir_muda_acrescimos_com_auditoria_antes_e_depois`;
  `test_corrigir_sem_os_campos_mantem_o_gravado`; `test_corrigir_data_nao_recalcula`;
  `test_corrigir_para_data_sem_atraso_zera_acrescimos`; `test_corrigir_exige_senha_e_motivo`;
  `test_desfazer_zera_os_quatro_valores`.
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** servidor; `antes`/`depois` de `pagamento.corrigido` e `baixa.desfeita` com os quatro valores.
- [x] **Passo 4:** tela com os campos, abrindo com os valores gravados em R$, e o valor pago novo.
- [x] **Passo 5:** testes + JS + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: corrigir pagamento muda multa, juros e outros; desfazer zera`.

**Registro (2026-11-01):** os testes do passo 1 estão em `AcrescimosNaBaixaTest` (mais `test_corrigir_acrescimo_invalido_da_erro_no_campo_e_nao_grava` e `test_lista_traz_multa_juros_e_outros_gravados`;
suíte PHP de 805 para 816: 813 na entrega e +3 na rodada de correção). `corrigirPagamento` valida `pago_em` + senha + motivo, confere a situação (só paga) e então `ValoresDaBaixa::resolver(..., 'correcao')`; `antes`/`depois` de
`pagamento.corrigido` e de `baixa.desfeita` levam os quatro valores (`valoresGravados`), e o resumo cita só o que mudou (`desconto de R$ 0,00 para R$ 5,00, multa de ...`). `descontoValidado` saiu (sem chamador).
JS: de 281 para 278 (saíram os 8 testes de `usarDescontoDaBaixa()`, entraram 1 do clamp e 4 da correção). **Desvios:** (1) R8 cumprido: `usarDescontoDaBaixa()` foi apagada, com seus testes e os imports sem uso
(`travarDesconto`, `criarAvisoTemporario`, `formatarCentavos`); o arquivo `usarValoresDaBaixa.js` segue com `calcularBaixa`, `previaDoLote` e `usarValoresDaBaixa`, então não foi apagado. (2) O clamp do desconto
(`desconto ≤ valor`) foi para `calcularBaixa`; o de `previaDoLote` saiu. (3) **Juros na correção = total em R$ (R10, rodada de correção):** o juros digitado na baixa é "ao mês" (o servidor multiplica pelos dias), mas o gravado é o total; reenviar "21,00" viraria R$ 210,00 em 300 dias.
A primeira solução (juros em branco que não ia no pedido) foi trocada: na correção o campo de juros é só em R$ ("Juros (total)"), abre com o gravado, e o `ValoresDaBaixa::resolver`, modo `correcao`, usa o digitado como centavos totais
(sem `× dias/30`); baixa individual e lote seguem ao mês. Saíram `jurosGravadoCentavos` e `pedidoDaCorrecao`; entrou `jurosTotais` em `calcularBaixa` e `usarValoresDaBaixa`. (4) `usarDescontoDaBaixa.js` renomeado para `usarValoresDaBaixa.js` (e o teste).
Falta conferir no
navegador: digitação com a máscara, a trava, 390 px e 1280 px, o alinhamento dos campos e o aviso de "sem atraso" (a renderização inicial foi conferida sem navegador): fica para a Tarefa 10.

### Tarefa 6 — Lista e painel — **Estado: testada**

**Arquivos:** `Cobranca::paraLista`, `app/Services/ListaDeCobrancas.php`, `resources/js/Pages/Cobrancas/Index.vue`, `resources/js/Components/LinhaCobranca.vue`,
`tests/Feature/Cobrancas/DadosDaListaTest.php`, `tests/Feature/Cobrancas/FiltroSituacaoTest.php`, `tests/Feature/PainelTest.php`.

**Interfaces:** `paraLista` ganha `linha_do_dia` (`string|null`): `até 05/01: R$ 85,00` ou `hoje R$ 235,20`; `null` sem regra, em
paga e em cancelada, e quando o valor do dia é igual ao valor. `valor_pago` só muda de fórmula.

- [x] **Passo 1: testes que falham:** `linha_do_dia` nos dois textos; `null` sem regra (ponto 3) e em cobrança sem contrato;
  soma das pendentes continua com o valor cheio; soma das pagas e `resumo.recebido` usam o valor pago com acréscimos; sem
  regra e sem valores, as somas ficam iguais.
- [x] **Passo 2:** rodar → FALHAM.
- [x] **Passo 3:** servidor.
- [x] **Passo 4:** tela: `linha_do_dia` embaixo do valor, menor; "pago R$ …" quando valor pago ≠ valor. Tirar a cópia do bloco entre
  `Index.vue` e `LinhaCobranca.vue` (achado da Fase 26): `paraLista` ganha `pago_diferente` (`bool`: paga e valor pago ≠ valor), e o `v-if` dos
  dois lê só esse campo.
- [x] **Passo 5:** testes + build → PASSAM. Pint.
- [x] **Passo 6:** commit `feat: lista e painel mostram o valor do dia`.

**Registro (Tarefa 6):** o teste da soma das pagas com acréscimos pegou um defeito da Tarefa 1: `ListaDeCobrancas::montar` lia as cobranças com `get([...colunas])` sem `multa_centavos`, `juros_centavos` e `outros_centavos`, e o Eloquent completa a coluna que falta com o `$attributes` (0), então `totais` e `total_geral` da lista ignoravam os acréscimos (a aba "Pagas" e o painel usavam `VALOR_PAGO_SQL` e estavam certos). Corrigido incluindo as três colunas. Falta conferir no navegador (Tarefa 10): a linha embaixo do valor em 390 px e 1280 px.

### Tarefa 7 — Relatórios — **Estado: pendente**

**Arquivos:** `app/Services/Relatorios.php` (`faturamento`, `porProduto`), `tests/Feature/Relatorios/*`.

**Interfaces:** faturamento ganha `self::coluna('acrescimos', 'Acréscimos')` depois de `descontos`. Por produto ganha as linhas
`Multas recebidas`, `Juros recebidos` e `Outros acréscimos`, positivas, antes de `Descontos concedidos`, com `previsto` 0 e
`contratos` 0, só quando houver valor no período.

- [ ] **Passo 1: testes que falham:** faturamento (recebido com acréscimos; coluna; previsto e inadimplência cheios); por cliente
  (recebido e média); por produto (linhas e total igual ao `resumo.recebido` do painel no mesmo período); CSV byte a byte
  (`FaturamentoTest::test_csv_para_o_excel_em_portugues`); sem acréscimos, valores iguais aos de antes e sem linhas novas;
  contas a receber sem mudança.
- [ ] **Passo 2:** `php artisan test --compact tests/Feature/Relatorios` → FALHAM.
- [ ] **Passo 3:** implementar, sem `ORDER BY` junto de agregado (PostgreSQL).
- [ ] **Passo 4:** testes → PASSAM. Pint.
- [ ] **Passo 5:** commit `feat: relatórios mostram multas, juros e outros acréscimos`.

### Tarefa 8 — Recibo e PIX — **Estado: pendente**

**Arquivos:** `app/Services/RenderizadorDeRecibo.php` (`dados`, `pagamento`), `app/Models/Cobranca.php`,
`tests/Feature/Recibos/RenderizadorDeReciboTest.php`, `PdfReciboTest.php`, `AmostrasVisuaisTest.php`. **Os templates não mudam:** usam
`$desconto`, `$valor_pago ?? $valor` e `$informacoes`.

**Interfaces:**
- Produz: `Cobranca::fraseDaCondicao(CarbonImmutable $data): ?string` — pendente com regra, nos textos exatos da spec §3
  (antes e depois do vencimento), sem a parte de `+ multa e juros` quando o contrato não tem; `null` sem regra e quando o valor do
  dia é igual ao valor.
- Produz: `Cobranca::detalheDosAcrescimos(): ?string` — paga: `Acréscimos por atraso: multa R$ 4,20; juros R$ 21,00; outros R$ 5,00.`,
  só os maiores que zero; `null` sem acréscimo.
- `dados()`: `desconto` e `valor_pago` também em pendente com regra, pela tabela da spec §3; `informacoes` acrescenta a frase, o
  detalhe e o aviso do boleto, e o valor por extenso segue o Valor Líquido.

- [ ] **Passo 1: prova de igualdade (permanente):** teste que renderiza WebISS e DANFSe, sem regra e sem valores, paga e pendente,
  com e sem PIX, e compara com o HTML de uma fixture gravada a partir do código atual, com a data congelada (`Carbon::setTestNow`), **antes** de mudar
  `dados()` → PASSA.
- [ ] **Passo 2: testes que falham:** paga com acréscimos (líquido, detalhe, extenso pelo líquido; contrato alterado depois não muda:
  ponto 4); pendente antes do vencimento (desconto, líquido, frase); pendente vencida (líquido, frase com a data);
  sem contrato (ponto 3); cancelada sem nada novo; PIX: `payload` com o valor do dia; valor do dia 0 → `forma` nula e sem
  QR; boleto com o aviso exato; `AmostrasVisuaisTest` com as combinações novas, 1 página.
- [ ] **Passo 3:** rodar `php artisan test --compact tests/Feature/Recibos` → FALHAM.
- [ ] **Passo 4:** implementar; `pagamento()` usa `condicoesNaData($agora)['total_centavos']` só com regra (senão, o snapshot, como hoje).
- [ ] **Passo 5:** suíte inteira → verde. Pint.
- [ ] **Passo 6:** commit `feat: recibo e PIX mostram o valor do dia e os acréscimos`.

### Tarefa 9 — Mensagem de WhatsApp — **Estado: pendente**

**Arquivos:** `app/Http/Controllers/AvisoWhatsappController.php`, `resources/js/mensagemWhatsapp.js` e teste,
`resources/js/Components/AvisarWhatsapp.vue`, `MensagensWhatsapp.vue`, `tests/Feature/AvisoWhatsappTest.php`.

**Interfaces:** consome `condicoesNaData` e `fraseDaCondicao`. A resposta ganha `condicao` (`string`, frases únicas juntadas por espaço).
`montarMensagem` recebe `condicao`; `{condicao}` entra nas variáveis. `MENSAGEM_SUGERIDA` usa `Total: {total} {condicao}`.

- [ ] **Passo 1: testes que falham:** `total_centavos` soma o valor do dia (vencida com regra; a vencer com desconto; sem regra igual);
  `condicao` com a frase; modelo antigo sem `{condicao}` continua igual; `{condicao}` vazia não deixa texto literal; legenda nova.
- [ ] **Passo 2:** rodar `AvisoWhatsappTest` e `mensagemWhatsapp.test.js` → FALHAM.
- [ ] **Passo 3:** implementar; `{itens}` continua com os valores originais.
- [ ] **Passo 4:** testes + build → PASSAM. Pint.
- [ ] **Passo 5:** commit `feat: mensagem de WhatsApp com o valor do dia e a variável {condicao}`.

### Tarefa 10 — Wizard, conferência e documentação — **Estado: pendente**

**Arquivos:** `.claude/skills/recibos/SKILL.md`, `.claude/plans/2026-10-10-roteiro-modulos.md`, README (só se algo de uso mudou).

- [ ] **Passo 1:** suíte da Fase 25 sem mudar asserção: `php artisan test --compact tests/Feature/ProgressoDeConfiguracaoTest.php
  tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php` e `node --test resources/js/progressoHolofote.test.js
  resources/js/progresso.test.js` → PASSAM. `git diff main -- tests/Feature/ProgressoDeConfiguracao*` vazio.
- [ ] **Passo 2:** `php artisan test --compact` e `node --test 'resources/js/**/*.test.js'` → verdes; `npm run build` ok.
- [ ] **Passo 3: PORTÃO-BANCO (Postgres local):** mostrar conexão, contagem (`contratos`, `cobrancas`), o comando literal
  `php artisan migrate`, e só rodar com o "sim" do Marcos. Recontar depois.
- [ ] **Passo 4: conferência no navegador** (pedir autorização antes; avisar que o browser-agent não está instalado, só o Playwright;
  conta de teste, nunca dados reais): contrato com condições; baixa em dia e em atraso, troca de data, "Recalcular"; lote com clientes
  diferentes; correção; lista e painel; reenvio e PIX; 390 px sem rolagem e 1280 px; faixa de baixa com o coach aberto e recolhido.
- [ ] **Passo 5:** PDFs de amostra em `storage/app/private/verificacao/` para a conferência visual do Marcos, inclusive PIX com o valor do dia.
- [ ] **Passo 6:** documentação, estados deste plano, commit `docs: registra a Fase 27 (multa, juros e pontualidade)`; com tudo
  `testada`, merge `--no-ff` no `main` + push. Avisar o Marcos de que o deploy roda as duas migrações aditivas e de que, ao definir
  regra num contrato, os recibos pendentes dele passam a sair com o valor do dia.

## Registro de execução

- 2026-11-01: spec e plano aprovados pelo Marcos. Execução por subagentes, com as regras globais de subagentes (context-mode,
  ponytail lite, modelo informado e nunca Opus nem Fable, sem acesso à VPS). Implementação ainda não iniciada: depende do merge da
  Fase 26.
- 2026-11-01: Tarefa 2 testada (PHPUnit 752, JS 258, `npm run build` ok). Sem conferência no navegador (a subagente não abre navegador).
- 2026-11-01: Tarefa 3 testada (PHPUnit 784, JS 277, `npm run build` ok). Sem conferência no navegador (a subagente não abre navegador).
