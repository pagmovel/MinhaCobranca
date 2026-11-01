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
- `valor pago = valor − desconto + multa + juros + outros`, sempre derivado. Sem atraso, o servidor zera multa e juros;
  `outros` vale em qualquer data (Tarefa 11, pedido do Marcos de 2026-11-01). `0 ≤ desconto ≤ valor`.
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
  contrato, via `entradaDaRegra`), pela tabela "Entrada na baixa" da spec; sem atraso, zera multa e juros (`outros` fica em qualquer data); lança
  `ValidationException` no campo que errou, com as mensagens do desconto da Fase 26.
- Produz: `ValoresDaBaixa::regras(): array` (regras do `validate` dos sete campos).
- `paraLista` ganha `vencimento_iso` e `regras` (`Contrato::regrasDePagamento()`, com `contrato` carregado junto).

- [x] **Passo 1: testes que falham** (`AcrescimosNaBaixaTest`), valor 21000, vencimento 05/01/2026, `comCondicoes()` com multa
  2% e juros 1%: `test_pedido_sem_campos_usa_o_contrato` (em 01/11/2026 → multa 420, juros 2100);
  `test_valor_digitado_vence_o_contrato`; `test_campo_vazio_fica_sem_valor`; `test_zero_fica_sem_valor`;
  `test_outros_soma_e_audita` (500 → texto exato da restrição global, pago R$ 240,20 com multa e juros);
  `test_sem_atraso_zera_multa_e_juros_mesmo_preenchidos_e_mantem_outros`; `test_pago_no_vencimento_aplica_desconto_do_contrato`;
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
  `test_zero_zera_em_todas`; `test_so_cobranca_em_atraso_recebe_multa_e_juros_e_outros_vale_em_todas`; `test_modo_vencimento_nao_gera_multa_nem_juros_mas_soma_outros`
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
  `test_corrigir_para_data_sem_atraso_zera_multa_e_juros_e_mantem_outros` e `test_corrigir_para_data_sem_atraso_sem_os_campos_mantem_o_desconto_e_o_outros_gravados`; `test_corrigir_exige_senha_e_motivo`;
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

**Registro (Tarefa 6):** o teste da soma das pagas com acréscimos pegou um defeito da Tarefa 1: `ListaDeCobrancas::montar` lia as cobranças com `get([...colunas])` sem `multa_centavos`, `juros_centavos` e `outros_centavos`, e a coluna que falta fica `null` (o `newFromBuilder` chama `setRawAttributes`, que substitui o `$attributes`; o `null` vira 0 na soma, sem erro), então `totais` e `total_geral` da lista ignoravam os acréscimos (a aba "Pagas" e o painel usavam `VALOR_PAGO_SQL` e estavam certos). Corrigido incluindo as três colunas. Falta conferir no navegador (Tarefa 10): a linha embaixo do valor em 390 px e 1280 px.

### Tarefa 7 — Relatórios — **Estado: testada**

**Arquivos:** `app/Services/Relatorios.php` (`faturamento`, `porProduto`), `tests/Feature/Relatorios/*`.

**Interfaces:** faturamento ganha `self::coluna('acrescimos', 'Acréscimos')` depois de `descontos`. Por produto ganha as linhas
`Multas recebidas`, `Juros recebidos` e `Outros acréscimos`, positivas, antes de `Descontos concedidos`, com `previsto` 0 e
`contratos` 0, só quando houver valor no período.

- [x] **Passo 1: testes que falham:** faturamento (recebido com acréscimos; coluna; previsto e inadimplência cheios); por cliente
  (recebido e média); por produto (linhas e total igual ao `resumo.recebido` do painel no mesmo período); CSV byte a byte
  (`FaturamentoTest::test_csv_para_o_excel_em_portugues`); sem acréscimos, valores iguais aos de antes e sem linhas novas;
  contas a receber sem mudança.
- [x] **Passo 2:** `php artisan test --compact tests/Feature/Relatorios` → FALHAM.
- [x] **Passo 3:** implementar, sem `ORDER BY` junto de agregado (PostgreSQL).
- [x] **Passo 4:** testes → PASSAM. Pint.
- [x] **Passo 5:** commit `feat: relatórios mostram multas, juros e outros acréscimos`.

**Registro (Tarefa 7):** o `get([...])` do faturamento tinha o mesmo defeito da lista: sem `multa_centavos`, `juros_centavos` e `outros_centavos` no select o Recebido ignorava os acréscimos
(o teste do CSV mostrou `950,00` onde devia sair `990,00`); as três colunas entraram no select e a coluna nova usa `acrescimosCentavos()`. Por produto, as linhas de acréscimos e a de descontos saem de
uma consulta só (`toBase()`, sem `ORDER BY`), e um teste com `DB::listen` falha se alguma consulta de relatório juntar agregado e `ORDER BY`. Por cliente já usava `VALOR_PAGO_SQL`: só o teste e o texto
mudaram. Contas a receber ficou intocado (teste novo prova o valor original numa vencida de contrato com regra). Suíte: PHPUnit 830, JS 278. Sem `.vue`/`.js` alterado: sem `npm run build`.

### Tarefa 8 — Recibo e PIX — **Estado: testada**

**Arquivos:** `app/Services/RenderizadorDeRecibo.php` (`dados`, `pagamento`), `app/Models/Cobranca.php`,
`tests/Feature/Recibos/RenderizadorDeReciboTest.php`, `PdfReciboTest.php`, `AmostrasVisuaisTest.php`. **Os templates quase não mudam:** usam
`$desconto`, `$valor_pago ?? $valor` e `$informacoes` (a única mudança é a altura da linha da descrição do WebISS, ver o registro).

**Interfaces:**
- Produz: `Cobranca::fraseDaCondicao(CarbonImmutable $data): ?string` — pendente com regra, nos textos exatos da spec §3
  (antes e depois do vencimento), sem a parte de `+ multa e juros` quando o contrato não tem; `null` sem regra e quando o valor do
  dia é igual ao valor.
- Produz: `Cobranca::detalheDosAcrescimos(): ?string` — paga: `Acréscimos por atraso: multa R$ 4,20; juros R$ 21,00; outros R$ 5,00.`,
  só os maiores que zero; `null` sem acréscimo.
- `dados()`: `desconto` e `valor_pago` também em pendente com regra, pela tabela da spec §3; `informacoes` acrescenta a frase, o
  detalhe e o aviso do boleto, e o valor por extenso segue o Valor Líquido.

- [x] **Passo 1: prova de igualdade (permanente):** teste que renderiza WebISS e DANFSe, sem regra e sem valores, paga e pendente,
  com e sem PIX, e compara com o HTML de uma fixture gravada a partir do código atual, com a data congelada (`Carbon::setTestNow`), **antes** de mudar
  `dados()` → PASSA.
- [x] **Passo 2: testes que falham:** paga com acréscimos (líquido, detalhe, extenso pelo líquido; contrato alterado depois não muda:
  ponto 4); pendente antes do vencimento (desconto, líquido, frase); pendente vencida (líquido, frase com a data);
  sem contrato (ponto 3); cancelada sem nada novo; PIX: `payload` com o valor do dia; valor do dia 0 → `forma` nula e sem
  QR; boleto com o aviso exato; `AmostrasVisuaisTest` com as combinações novas, 1 página.
- [x] **Passo 3:** rodar `php artisan test --compact tests/Feature/Recibos` → FALHAM.
- [x] **Passo 4:** implementar; `pagamento()` usa `condicoesNaData($agora)['total_centavos']` só com regra (senão, o snapshot, como hoje).
- [x] **Passo 5:** suíte inteira → verde. Pint.
- [x] **Passo 6:** commit `feat: recibo e PIX mostram o valor do dia e os acréscimos`.

**Registro (Tarefa 8):** a prova de igualdade (`ReciboIdenticoSemRegraTest`, 12 casos: WebISS e DANFSe × pendente, pendente com PIX, pendente com boleto, vencida com PIX de contrato sem regra, paga e cancelada) foi gravada em
`tests/Feature/Recibos/fixtures/` com o código **anterior** (nenhum arquivo de `app/` ou `resources/` alterado naquela hora) e continua passando no fim. O PNG do QR sai da comparação (depende do GD): o teste exige antes que o HTML contenha o
`QrCodePix::dataUri` do copia e cola que está nele. Os testes novos de `AmostrasVisuaisTest` pegaram um defeito que os de dados não pegam: no WebISS com 25 linhas de descrição e PIX a última palavra fica a ~10pt do limite da página, e uma linha a mais em "Outras
informações" (~10pt) levava o recibo à página 2. Correção: com mais de uma informação, as linhas da descrição têm 8pt em vez de 8,4pt (sem regra continua 8,4pt, e a prova de igualdade confirma). `array_merge` no helper `pdf()` de
`AmostrasVisuaisTest` (com `+` a base vencia o `vencimento` pedido). Suíte: PHPUnit 862, JS 278 (sem `.vue`/`.js` alterado).

### Tarefa 9 — Mensagem de WhatsApp — **Estado: testada**

**Arquivos:** `app/Http/Controllers/AvisoWhatsappController.php`, `resources/js/mensagemWhatsapp.js` e teste,
`resources/js/Components/AvisarWhatsapp.vue`, `MensagensWhatsapp.vue`, `tests/Feature/AvisoWhatsappTest.php`.

**Interfaces:** consome `condicoesNaData` e `fraseDaCondicao`. A resposta ganha `condicao` (`string`, frases únicas juntadas por espaço).
`montarMensagem` recebe `condicao`; `{condicao}` entra nas variáveis. `MENSAGEM_SUGERIDA` usa `Total: {total} {condicao}`.

- [x] **Passo 1: testes que falham:** `total_centavos` soma o valor do dia (vencida com regra; a vencer com desconto; sem regra igual);
  `condicao` com a frase; modelo antigo sem `{condicao}` continua igual; `{condicao}` vazia não deixa texto literal; legenda nova.
- [x] **Passo 2:** rodar `AvisoWhatsappTest` e `mensagemWhatsapp.test.js` → FALHAM.
- [x] **Passo 3:** implementar; `{itens}` continua com os valores originais.
- [x] **Passo 4:** testes + build → PASSAM. Pint.
- [x] **Passo 5:** commit `feat: mensagem de WhatsApp com o valor do dia e a variável {condicao}`.

**Registro (Tarefa 9):** `total_centavos` soma `condicoesNaData(hoje)['total_centavos']` das pendentes (hoje = `CarbonImmutable::today()`, como a lista) e `{itens}` fica com os valores originais; `condicao` junta as frases únicas de `fraseDaCondicao`
por espaço, na ordem do vencimento (a consulta ganhou `orderBy('vencimento')->orderBy('id')`: sem `ORDER BY` a ordem não é garantida: no SQLite dos testes saiu por vencimento sem a consulta pedir). **R12** (decisão do controlador): `fraseDaCondicao` diz `+ multa`, `+ juros` ou
`+ multa e juros` conforme o contrato; testes para os quatro casos (os três e nenhum dos dois) no modelo, no recibo e na mensagem; a spec §3 ganhou a frase. `{condicao}` vazia leva embora o espaço antes dela (senão `Total: R$ 89,90 ` ficaria com espaço solto
antes da quebra de linha), e o teste antigo de `MENSAGEM_SUGERIDA` passou sem mudar. A legenda (`VARIAVEIS`) mudou de `MensagensWhatsapp.vue` para `mensagemWhatsapp.js` para ser testável: o teste exige que toda variável da legenda seja reconhecida
(não fique literal). A prévia da tela de modelos agora usa o exemplo com desconto (total R$ 76,41 e a frase). Suíte: PHPUnit 876, JS 283, `npm run build` ok. Não conferido (sem navegador): a janela de aviso e a prévia na tela, 390 px.

### Tarefa 10 — Wizard, conferência e documentação — **Estado: concluída** (para virar `testada` faltam o Passo 4 e o merge, do controlador com o Marcos)

**Arquivos:** `.claude/skills/recibos/SKILL.md`, `.claude/plans/2026-10-10-roteiro-modulos.md`, README (só se algo de uso mudou: nada mudou, o README não descreve baixa nem contrato).

- [x] **Passo 1:** suíte da Fase 25 sem mudar asserção: `php artisan test --compact tests/Feature/ProgressoDeConfiguracaoTest.php
  tests/Feature/ProgressoDeConfiguracaoMiddlewareTest.php` (11 testes, 65 asserções) e `node --test resources/js/progressoHolofote.test.js
  resources/js/progresso.test.js` (8 testes) → PASSAM. O diff desses testes contra `git merge-base main HEAD` (`6ce0e5c`) é vazio. Comparar com o `merge-base`, não com o `main`, que pode avançar com trabalho de outra sessão.
- [x] **Passo 2:** `php artisan test --compact` → 876 testes, 3901 asserções; `node --test 'resources/js/**/*.test.js'` → 283; `npm run build` ok.
- [x] **Passo 3: PORTÃO-BANCO (Postgres local):** cumprido pelo controlador, com a autorização antecipada do Marcos para migrations no banco local (2026-11-01): as duas migrações aditivas rodaram no Postgres local (host 127.0.0.1, `APP_ENV` local).
- [ ] **Passo 4: conferência no navegador** (do controlador, com o Marcos; pedir autorização antes; avisar que o browser-agent não está instalado, só o Playwright;
  conta de teste, nunca dados reais): contrato com condições; baixa em dia e em atraso, troca de data, "Recalcular"; lote com clientes
  diferentes; correção; lista e painel; reenvio e PIX; 390 px sem rolagem e 1280 px; faixa de baixa com o coach aberto e recolhido. Conferir também o que as revisões deixaram para o navegador:
  digitação com a máscara, a trava e o aviso; "12," enviado como "12" na baixa (sem `transform`); o exemplo ao vivo do contrato; o erro de renovação no `Form.vue` (só compilado); o alinhamento (`gap-1`, `mt-5`); a janela de aviso do WhatsApp com a frase e a prévia dos modelos.
  **Corrigido na revisão final:** `CampoValorOuPercentual.vue`, `slots.calculado` era verdadeiro mesmo com o `<p v-if>` oculto, e o `aria-describedby` apontava para um id inexistente; agora o slot é chamado no render e o id só entra se renderizar algo (`slotTemConteudo`).
- [x] **Passo 5:** PDFs de amostra em `storage/app/private/verificacao/` (regerados pela suíte; fora do git) para a conferência visual do Marcos, inclusive PIX com o valor do dia: `amostra-{webiss,danfse}-fase27-{a-vencer-pix,a-vencer-boleto,vencida-pix,vencida-boleto,paga-acrescimos,paga-desconto-e-acrescimos}.pdf`,
  12 arquivos, 1 página cada (`pdfinfo`). Texto conferido com `pdftotext -layout` em WebISS e DANFSe: a vencer = líquido R$ 221,51 e `Pagando até 21/09/2026: R$ 221,51. Depois: R$ 260,60 + multa e juros.`; vencida = líquido R$ 268,50 e
  `Valor atualizado em 13/09/2026: multa R$ 5,21; juros R$ 2,69.`; boleto = `O boleto foi emitido com o valor original.`; PIX com `221.51` e `268.50` no copia e cola; paga com acréscimos = líquido R$ 282,28 e `Acréscimos por atraso: multa R$ 4,95; juros R$ 24,76; outros R$ 5,00.`;
  paga com desconto e acréscimos = Desc. Cond. R$ 24,76 e líquido R$ 257,52.
- [x] **Passo 6 (documentação):** skill (seção "Fase 27" consolidada por tema, exceção ao `-v2` e aviso de deploy), roteiro (linha da Fase 27), estados e registro deste plano; commit `docs: registra a Fase 27 (multa, juros e pontualidade)`.
  **Falta (controlador):** com tudo `testada`, merge `--no-ff` no `main` + push. Avisar o Marcos de que o deploy roda as duas migrações **aditivas** (sem migração de dados) e de que, ao definir
  regra num contrato, os recibos **pendentes** dele passam a sair com o valor do dia (um PIX já enviado fica com o valor antigo).

### Tarefa 11 — "Outros" vale em qualquer data da baixa — **Estado: testada**

**Pedido do Marcos (2026-11-01, ao testar a tela):** "baixa em qualquer data tem que permitir adicionar valor no campo outros". Decisão R13: "outros" é lançamento manual, sem relação com atraso e sem regra no contrato; a regra "sem atraso, sem acréscimo" vale **só para multa e juros**.

**Arquivos:** `app/Services/ValoresDaBaixa.php`, `app/Models/Cobranca.php` (`detalheDosAcrescimos`), `resources/js/usarValoresDaBaixa.js`, `BaixaInline.vue`, `BaixaEmLote.vue`, `CorrecaoPagamentoInline.vue`, testes (`AcrescimosNaBaixaTest`, `AcoesEmLoteTest`, `CondicoesDaCobrancaTest`, `RenderizadorDeReciboTest`, `FaturamentoTest`, `ReceitaTest`, `usarValoresDaBaixa.test.js`), spec e skill.

- [x] Servidor: `resolver` zera só multa e juros sem atraso, nos três modos; `outros` é validado em qualquer data (formato, teto, erro no campo `outros`) e gravado; no lote soma em todas as linhas (mesmo R$), vencimento ou hoje.
- [x] Tela: o campo "Outros" aparece sempre (baixa, lote e correção), ao lado do desconto; `calcularBaixa` e `previaDoLote` somam `outros` em qualquer data, com os mesmos números do servidor; o aviso da correção passou a citar só multa e juros e só aparece com algum deles preenchido.
- [x] Recibo da paga: com multa ou juros, `Acréscimos por atraso: …; outros R$ 5,00.`; só `outros`, `Outros acréscimos: R$ 5,00.` (pode ter sido paga em dia). `ReciboIdenticoSemRegraTest` (12 fixtures) intacto.
- [x] Relatórios: `outros` pago em dia entra no Recebido, em Acréscimos e na linha "Outros acréscimos" (testes ponta a ponta pela baixa).
- Testes: PHPUnit e JS conforme o registro abaixo, `npm run build` ok. Sem navegador (a subagente não o abre): aparência do campo sem atraso, alinhamento e 390 px ficam para o Passo 4 da Tarefa 10.

### Tarefa 12 — Lote avisa quando multa e juros não entram e oferece a data de hoje — **Estado: testada**

**Pedido do Marcos (2026-11-01, ao testar a tela):** "na baixa em lote, ao adicionar um acréscimo não está calculando os resultados". Diagnóstico: o cálculo estava certo. Multa e juros só entram em cobrança em atraso na data da baixa, e com a data padrão do lote ("usar a data de vencimento de cada cobrança") ninguém tem atraso; o único aviso era uma frase cinza e pequena. Faltava a saída na tela (padrão de usabilidade 2). **Servidor e regra não mudam.**

**Arquivos:** `resources/js/usarValoresDaBaixa.js` (`multaOuJurosSemEfeito`), `resources/js/Components/BaixaEmLote.vue`, `resources/js/usarValoresDaBaixa.test.js`, spec §2 e skill.

- [x] Função pura `multaOuJurosSemEfeito(linhas, datas, campos, hojeIso)` → `{ semEfeito, hojeResolve }`: `semEfeito` = multa ou juros digitados com valor maior que zero (`regraDoCampo`; em branco, `0` e texto inválido não contam) e nenhuma linha com dia de atraso (`diasDeAtraso`) na data da baixa dela (as datas da prévia); `hojeResolve` = alguma linha venceu antes de hoje. Sem linhas elegíveis, não há o que avisar.
- [x] Tela: bloco laranja logo abaixo dos quatro campos, só com `semEfeito`. Com `hojeResolve` e a data de vencimento: "Com a data de vencimento não há atraso, então a multa e os juros digitados não entram no cálculo." e o botão "Usar a data de hoje" (`type="button"`, `botao-secundario`; muda o modo para `hoje`, a prévia recalcula e o foco vai para o rádio "hoje"). Sem `hojeResolve`: "Nenhuma cobrança selecionada está em atraso, então a multa e os juros digitados não entram no cálculo.", sem botão. A região `aria-live` fica sempre presente; o aviso antigo da data de vencimento continua.
- Testes: JS 294 (6 novos), PHPUnit 886 (sem mudança), `npm run build` ok. Renderização de servidor (Vite + `@vitejs/plugin-vue` + `renderToString`): aviso e botão aparecem com duas vencidas de R$ 210,00, vencimento e multa 2 (ou juros 1); somem com a multa limpa ou 0, só com "outros" ou na data de hoje; sem cobrança em atraso aparece o segundo texto, sem botão. Sem navegador: clique no botão, foco, aparência e 390 px ficam para o Passo 4.

## Registro de execução

- 2026-11-01: spec e plano aprovados pelo Marcos. Execução por subagentes, com as regras globais de subagentes (context-mode,
  ponytail lite, modelo informado e nunca Opus nem Fable, sem acesso à VPS). A Fase 26 já estava no `main`; linha de base: PHPUnit 659, JS 170.
- 2026-11-01: Tarefa 2 testada (PHPUnit 752, JS 258, `npm run build` ok). Sem conferência no navegador (a subagente não abre navegador).
- 2026-11-01: Tarefa 3 testada (PHPUnit 784, JS 277, `npm run build` ok). Sem conferência no navegador (a subagente não abre navegador).
- 2026-11-01: Tarefas 1 e 4 a 9 testadas, cada uma com revisão limpa do controlador (T7: PHPUnit 830, JS 278; T8: 862 e 278; T9: 876 e 283). Nenhuma conferência no navegador.
- 2026-11-01: Tarefa 10 concluída: suíte da Fase 25 verde sem mudar asserção, PHPUnit 876, JS 283, `npm run build` ok, 12 PDFs de amostra conferidos por texto, documentação consolidada. Faltam o Passo 4 e o merge.
- 2026-11-01: Tarefa 11 testada ("outros" vale em qualquer data, pedido do Marcos ao testar a tela): PHPUnit 886, JS 288, `npm run build` ok. Sem conferência no navegador.
- 2026-11-01: Tarefa 12 testada (lote avisa quando multa e juros não entram e oferece "Usar a data de hoje", relato do Marcos ao testar a tela): PHPUnit 886, JS 294, `npm run build` ok. Sem conferência no navegador.

### Decisões do controlador (R1 a R13)

| # | Decisão | Custo se errada |
|---|---|---|
| R1 | A Tarefa 3 confere por busca todo chamador de `paraLista` e carrega o contrato em cada um, não só nos dois `with` citados (sem isso, uma consulta por linha) | nenhum |
| R2 | Mudar só as condições de pagamento no contrato não aciona a correção de cronograma nem refaz cobranças (condição não muda período nem valor original) | ajustar a comparação do formulário |
| R3 | Pedir ao Marcos o "sim" das duas migrações aditivas no Postgres local ao fim da T1, sem parar as tarefas seguintes (usam SQLite); depois ele deu autorização antecipada e o controlador as rodou | nenhum dado alterado sem o "sim" |
| R4 | Com qualquer acréscimo, a auditoria sempre mostra o desconto, mesmo R$ 0,00 (texto exato da spec); sem acréscimos valem os textos da Fase 26 | ajustar uma condição do texto |
| R5 | Implementadores e revisores em sonnet; haiku só em correção mecânica; revisão final em sonnet, por ser o mais capaz permitido (regra global do Marcos) | revisão final menos profunda |
| R6 | Teto de R$ 99.999.999,99 (`ValorEmReais::MAXIMO_CENTAVOS`) para multa e juros em R$, na regra única `regraDaEntrada` (PHP e JS), e por herança no contrato e na baixa; "R$ sem teto" da spec vale em relação ao valor da cobrança | contrato com multa ou juros em R$ acima de ~R$ 100 milhões é recusado |
| R7 | Renovar contrato vencido recusa desconto em R$ acima do novo total (spec §1 regra 5), na rodada 1 | nenhum |
| R8 | A trava e o aviso antigos de `usarDescontoDaBaixa.js` ficam até a T4 e a T5 migrarem o lote e a correção para `CampoValorOuPercentual`; cada uma remove o que deixar de usar | duas travas numa tela por uma tarefa |
| R9 | A auditoria do lote com acréscimo é `Baixa registrada (...)`, literal da spec ("o lote usa o mesmo texto em cada cobrança") | um lote mistura "Baixa registrada" e "Baixa em lote", perdendo o marcador de origem; troca de uma linha e 3 expectativas |
| R10 | Em "Corrigir pagamento", o juros é o total em R$ (sem seletor, sem "ao mês") e abre com o gravado; o `resolver` em modo `correcao` trata o digitado como centavos totais. Baixa e lote seguem com juros ao mês (a spec §1/§2 se contradizia) | a correção não recalcula juros por % ao mês |
| R11 | Letra do WebISS de 8,4 pt para 8 pt só com mais de uma informação, para manter 1 página; não altera nenhum recibo existente (provado com as 12 fixtures) | ajuste de tamanho de letra ou de linhas |
| R12 | A frase do recibo antes do vencimento diz `+ multa`, `+ juros` ou `+ multa e juros` conforme o contrato (a mensagem usa a mesma frase) | três ramos de texto a mais |
| R13 | "Outros" vale em qualquer data (individual, lote e correção); sem atraso só multa e juros são zerados. Recibo da paga só com `outros`: `Outros acréscimos: R$ 5,00.` (sem "por atraso"). Pedido do Marcos ao testar a tela | uma condição no servidor, no espelho JS e nas telas |
| R14 | Frase do recibo da paga com só `outros`: `Outros acréscimos: R$ 5,00.` (sem "por atraso") | troca de texto |
| R15 | Aviso do lote para multa e juros sem efeito (sem atraso na data da cobrança, ou data de vencimento) | um aviso a mais na janela do lote |

### Para o Marcos decidir

1. **Lote com várias frases em `{condicao}`:** um lote com várias datas gera uma frase por (data, valor); a frase da vencida não diz a que item se refere.
2. **Mensagem sugerida emendada:** `MENSAGEM_SUGERIDA` fica `Total: {total} {condicao}`, então a frase vem na mesma linha ("Total: R$ 76,41 Pagando até ...") e repete o valor.
3. **Ordenação do aviso mudando a chave PIX:** a consulta do aviso passou a ordenar por `vencimento` e `id`; em lote com chaves PIX diferentes, a "primeira" pode mudar (antes a ordem era indefinida; sem teste com id maior e vencimento menor).
4. **Juros da correção como total em R$ (R10):** confirmar que corrigir pagamento não recalcula juros por % ao mês.

Também com o Marcos: R9 (lote que mistura os dois textos de auditoria) e R11 (letra do WebISS), a olhar nos PDFs `amostra-*-fase27-*.pdf`.

### Menores adiados (das revisões; o ledger `.superpowers/` não é versionado)

- **T1:** `condicoes.test.js` diz "mesmos casos", mas "reais gigante" só está no PHP (JS 1e22, divergência da Fase 26); a migração de contratos repete a lista de colunas e o `down()` não foi executado; `diaDoCalendario` não valida mês nem dia (a entrada vem do servidor).
- **T2:** a etiqueta "contrato" é `Etiqueta.vue` compacta (texto 10,4px a 60%), com `id` dentro do `aria-describedby` (só o texto dela); a trava em R$ do contrato só age ao digitar, sem aviso quando o total cai abaixo do desconto, e o erro do servidor não some ao editar o campo; o teto de R$ de multa/juros não entra na trava de digitação
  (só o desconto tem `valorMaximoCentavos`) e o exemplo ao vivo some sem aviso acima dele; o formulário do contrato depende do `@change` para tirar a vírgula final; `ContratoRequest::CONDICOES` e `Contrato::CONDICOES` têm o mesmo nome para coisas diferentes.
- **T3:** o servidor valida multa/juros mesmo sem atraso e a tela os esconde (valor inválido digitado antes de trocar a data daria erro em campo invisível; `outros`, desde a Tarefa 11, é visível sempre); faltam testes do separador final ("12,") em multa/juros e do pagamento antes do vencimento com desconto do contrato; só trocar o seletor % | R$ já mostra "Valores diferentes do contrato" (cosmético).
- **T4:** a prévia usa a data do aparelho (`toLocaleDateString`) e o servidor `today()` (mesmo padrão da `BaixaInline`); sem teste de cobrança alheia ou não pendente no mesmo pedido com acréscimos; o aviso da data de vencimento aparece mesmo sem regra (literal da spec).
- **T5:** o `.vue` da correção não tem teste automatizado (só o hook); `juros_tipo=percentual` por API na correção vale uma vez sobre o valor, como a multa (a tela só manda valor; não recusar); specs e plano da Fase 26 e a linha 10 da spec da Fase 27 ainda citam `usarDescontoDaBaixa.js` (histórico).
- **T6:** sem teste de `linhaDoDia` perto da meia-noite (UTC × São Paulo).
- **T7:** `ReceitaTest::cenarioComAcrescimosPorProduto` sem cobrança cancelada nem de outro usuário com multa/juros (o código está certo); a classe de defeito do `get([...])` parcial depende de comentário e teste (apareceu duas vezes).
- **T8:** `fraseDaCondicao` repete o prólogo de `linhaDoDia` e `condicoesNaData` roda 2 vezes por render; sem fixture de pendente em contrato com regra e valor do dia igual ao original (só multa antes do vencimento); o PIX usa `valor_centavos` vivo; comentário Blade novo no `webiss-v2` numa linha enorme.
- **T9:** as legendas de `{total}` e `{itens}` mudaram de texto e a spec §3 ainda cita a legenda em `MensagensWhatsapp.vue` (agora em `mensagemWhatsapp.js`); sem teste de pedido misto (paga ou cancelada junto de pendente).
