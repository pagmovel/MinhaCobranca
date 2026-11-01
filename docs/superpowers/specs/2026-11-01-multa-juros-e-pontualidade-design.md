# Fase 27 — multa, juros, outros acréscimos e desconto por pontualidade — desenho

**Módulo:** Cobrança. Não cria dependência com outros módulos.

**Pedido do usuário (2026-11-01):** "percebi que não temos uma forma de adicionar valores em faturas vencidas [...] não
tem como adicionar (opcional) um valor fixo ou multas, juros, etc. Então, precisamos mexer nos contratos e nas baixas."
E depois: "podemos dar um desconto de 15,00 ou 15% se pago até a data do vencimento."

**Depende da Fase 26** (`desconto_centavos`, `App\Support\Desconto`, `resources/js/desconto.js`,
`usarDescontoDaBaixa.js`, recibo v2). Esta fase substitui o item "Juros, multa ou valor pago maior" do "Fora do escopo" da
spec da Fase 26.

**Decisões do usuário (brainstorming em chat, 2026-11-01):**
- Multa e juros são definidos **no contrato**, em valor ou percentual, ambos opcionais. Sem definição, o usuário informa
  valor ou percentual na tela de baixa, individual ou em lote, com qualquer mistura de clientes.
- A regra do contrato vem **preenchida e editável** na baixa.
- Juros: simples, **ao mês, proporcional aos dias** (mês de 30 dias), até a data do pagamento, sobre o valor original.
- Além de multa e juros, existe o campo **"Outros acréscimos"**, só em R$ e sem vínculo com o contrato.
- Lote: **um campo para todas**. Em branco, cada cobrança usa o próprio contrato; preenchido, vale para todas.
- Guardar em **três colunas na cobrança**.
- Recibo: "Valor Total" continua com o valor original, "Valor Líquido" mostra o valor pago ou o do dia, e uma linha
  detalha os acréscimos.
- O valor atualizado aparece **na baixa, na lista, no reenvio, na mensagem e no PIX**.
- Desconto por pontualidade: **no contrato** (não no produto), em % ou R$, sobre o total da cobrança.
- Antes do vencimento: **PIX com desconto, e o recibo explica a condição**.

**Estado antes desta fase (lido no código em 2026-11-01):**
- `contratos` e `cobrancas` não têm campo de multa, juros nem acréscimo.
- `Cobranca::VALOR_PAGO_SQL` é `valor_centavos - desconto_centavos`, usado em painel, lista e relatórios.
- `RenderizadorDeRecibo::dados()` gera o PDF a cada abertura; o PIX usa `snapshot.servico.valor_centavos`.
- `AvisoWhatsappController` monta `{total}` e `{itens}` a partir das cobranças selecionadas.

## 1. Dados e regra

**Contrato** (migração aditiva; tudo opcional, vazio = sem regra):

| Coluna | Tipo | Significado |
|---|---|---|
| `desconto_pontualidade_tipo` | `string` nulo | `percentual` ou `valor` |
| `desconto_pontualidade_valor` | `unsignedBigInteger` nulo | pontos-base (15% = 1500) ou centavos |
| `multa_tipo`, `multa_valor` | idem | incide uma vez |
| `juros_tipo`, `juros_valor` | idem | **ao mês** |

**Cobrança** (migração aditiva): `multa_centavos`, `juros_centavos` e `outros_centavos` (`unsignedBigInteger`, padrão 0),
gravados só na baixa e fora do `#[Fillable]`, como `desconto_centavos`. O valor original nunca muda.

**Valor pago** é sempre derivado: `valor − desconto + multa + juros + outros`. `Cobranca::VALOR_PAGO_SQL` e
`valorPagoCentavos()` passam a usar essa fórmula.

**Cálculo**, regra única em `App\Support\CondicoesDePagamento` (puro), espelhada em `resources/js/condicoes.js`, com os
mesmos casos de teste:

- **Dias de atraso:** dias corridos do vencimento até a data de referência (data do pagamento; antes da baixa, hoje).
- **Base:** sempre o valor original da cobrança.
- **Multa:** % → `intdiv(valor × pontosBase + 5000, 10000)`; R$ → o próprio valor. Uma vez só.
- **Juros:** % → `intdiv(valor × pontosBase × dias + 150000, 300000)`; R$ → `intdiv(centavos × dias + 15, 30)`.
- **Desconto por pontualidade:** a regra de `Desconto`, limitado ao valor da cobrança (um contrato reajustado ou uma
  cobrança proporcional podem ter valor menor que o desconto em R$).
- **Exemplo:** R$ 210,00, multa 2%, juros 1% ao mês, 300 dias → multa R$ 4,20, juros R$ 21,00.

**Valor do dia** (usado na lista, no recibo, na mensagem, no PIX e como preenchimento inicial da baixa):

| Na data de referência | Valor |
|---|---|
| Até o vencimento, inclusive | valor − desconto por pontualidade |
| Depois do vencimento | valor + multa + juros |
| Sem regra no contrato ou cobrança avulsa | valor original |

**Regras:**
1. **Sem atraso, sem acréscimo.** Com data de pagamento até o vencimento, multa, juros e outros são zerados pelo servidor,
   mesmo se vierem preenchidos.
2. **Vale a regra atual do contrato** para o que está em aberto. O que já foi baixado não muda, pois os valores ficam
   gravados. Consequência aceita: um PIX já enviado fica com o valor antigo.
3. **Cobrança avulsa** não tem regra e só aceita lançamento manual na baixa.
4. **Desconto** continua sobre o valor original e limitado a ele (`0 ≤ desconto ≤ valor`). Para perdoar a multa, zera-se o
   campo dela.
5. **Limites:** cada percentual vai até 100% e até 2 casas decimais; R$ de multa e juros (e de desconto) vão até
   R$ 99.999.999,99, o mesmo teto dos preços, só para a conta caber no inteiro (não é limite do valor da cobrança). No
   contrato, desconto em R$ acima do valor do contrato é recusado, inclusive ao renovar contrato vencido com itens novos.
6. **Sem trava legal.** A multa não é limitada a 2%. `[A VERIFICAR]`: pela memória, o CDC, art. 52, §1º, limita a multa de
   mora a 2% em relações de consumo; a fonte não foi consultada. O sistema atende também empresas, então a escolha do
   percentual é de quem preenche o contrato.
7. **Corrigir pagamento** altera data, desconto, multa, juros e outros, com senha e motivo. **Desfazer baixa** zera os
   quatro valores.
8. Cobrança não pendente continua recusando baixa; cobrança alheia continua 404 antes da validação.

**Entrada na baixa:** `multa_tipo`, `multa`, `juros_tipo`, `juros`, `outros`, além de `desconto_tipo` e `desconto`, da
Fase 26. `multa` e `juros` em % ou R$; `juros` digitado é sempre ao mês. Formatos e mensagens seguem o desconto.
Na correção, os juros são o total em R$, pois o gravado é o total.

| Campo no pedido | Baixa individual | Lote | Corrigir pagamento |
|---|---|---|---|
| Ausente | regra do contrato | regra do contrato | mantém o gravado |
| Vazio | sem valor | regra do contrato | sem valor |
| `0` | sem valor | zera em todas | sem valor |
| Preenchido | esse valor | vale para todas | esse valor |

"Outros" não tem regra: ausente ou vazio é 0 na baixa e no lote. No lote, % é calculado por cobrança e R$ é igual em cada uma. Multa, juros e outros só se aplicam às cobranças em atraso
na data; desconto digitado vale para todas, com a trava em R$ pela menor cobrança, da Fase 26.

**Auditoria `cobranca.paga`:** `Baixa registrada (R$ 210,00, multa R$ 4,20, juros R$ 21,00, outros R$ 5,00, desconto
R$ 0,00, pago R$ 240,20, em 01/11/2026)`. Sem acréscimos, o texto continua como o da Fase 26. O lote usa o mesmo texto em
cada cobrança. `pagamento.corrigido` grava antes/depois dos quatro valores. Registro novo `contrato.condicoes` quando as
regras do contrato mudam, com antes/depois.

## 2. Telas

**Contrato:** seção "Condições de pagamento" com os três campos (`Desconto até o vencimento`, `Multa por atraso`,
`Juros ao mês`), cada um com seletor `% | R$`, ajuda e o exemplo que recalcula a cada tecla: `Exemplo com o valor deste
contrato (R$ 100,00): em dia R$ 85,00 · 30 dias de atraso R$ 103,00`. Vazio e `0` significam sem regra. Campos opcionais:
não entram no progresso da Fase 25.

**Baixa individual** (`BaixaInline.vue`): os campos seguem a data de pagamento.
- Até o vencimento: Valor, Desconto (com a regra do contrato), Valor pago.
- Com atraso: Valor, Multa, Juros, Outros, Desconto (vazio, para negociação), Valor pago, com os dias de atraso e o valor
  calculado ao lado de cada campo.
- A regra do contrato vem preenchida e editável, com a etiqueta "contrato". Sem regra, os campos vêm vazios e a ajuda
  explica que dá para digitar.
- Trocar a data recalcula os campos não editados. O que o usuário alterou fica, e um link "Recalcular" volta à regra.
- Valor pago com `.destacar` ao mudar. 390 px sem rolagem horizontal, com o coach aberto e recolhido.

**Baixa em lote** (`BaixaEmLote.vue`): Multa, Juros, Outros e Desconto no topo, com a ajuda "Em branco: cada cobrança usa o
próprio contrato. Preenchido: vale para todas." A prévia lista Vencimento, Valor, Acréscimos, Desconto e Pago, com totais
iguais à soma gravada. Com "usar a data de vencimento de cada cobrança", aviso: "Com essa data não há multa nem juros; o
desconto do contrato vale."

**Corrigir pagamento** (`CorrecaoPagamentoInline.vue`): os mesmos campos, abrindo com os valores **gravados** em R$. Trocar
a data não recalcula, pois ali se corrige o que aconteceu de fato. Na correção, os juros são o total em R$, pois o gravado é o total.

**Lista e painel:** pendente com regra mostra, embaixo do valor, `até 05/01: R$ 85,00` ou `hoje R$ 235,20`. Sem regra, nada
muda. Paga continua com `pago R$ …` sempre que o valor pago diferir do valor. Abas e rodapé das pendentes continuam com
o valor cheio; as pagas somam o valor pago.

**Componente:** `CampoValorOuPercentual.vue` substitui o bloco de campo com seletor repetido em `BaixaInline`, `BaixaEmLote`
e `CorrecaoPagamentoInline` (ajuda, erro, `aria-describedby`, máscara por tipo) e serve também ao contrato.

## 3. Recibo, PIX e mensagem

**Recibo** (dados vivos; sem novo número nem snapshot novo):

| Campo | Paga | Pendente com regra |
|---|---|---|
| Valor Total | valor original | valor original |
| Desc. Cond. | desconto gravado | desconto do contrato, até o vencimento |
| Valor Líquido | valor pago | valor do dia |
| Linha em informações | `Acréscimos por atraso: multa R$ 4,20; juros R$ 21,00; outros R$ 5,00.` | antes do vencimento: `Pagando até 05/01/2026: R$ 85,00. Depois: R$ 100,00 + multa e juros.`; depois: `Valor atualizado em 01/11/2026: multa R$ 4,20; juros R$ 21,00.` |

- Na linha "antes do vencimento", o trecho `+ multa e juros` só aparece se o contrato tiver multa ou juros. No detalhe,
  só os valores maiores que zero.
- **Valor por extenso** segue o Valor Líquido quando ele difere do total (corrige também o caso de desconto da Fase 26).
- Sem regra e sem valores, o recibo sai **idêntico** ao atual, e isso é coberto por teste. Nenhum campo muda de posição.
- **Sem v3:** os novos dados só aparecem quando existem. Mesma exceção consciente da Fase 26 à regra de versões.
- Cancelada não mostra nem calcula nada novo. Todas as combinações cabem em 1 página (`AmostrasVisuaisTest`).

**PIX:** o valor passa a ser o do dia; sem regra, é igual ao de hoje. Valor líquido R$ 0,00: sem QR. **Boleto:** a linha
digitável não muda, e o recibo com valor do dia diferente do original avisa `O boleto foi emitido com o valor original.`

**Mensagem de WhatsApp:** `{total}` passa a somar o valor do dia. `{itens}` continua com os valores originais. Variável
nova `{condicao}` traz a frase do recibo; entra em `MENSAGEM_SUGERIDA` e na legenda de `MensagensWhatsapp.vue`. Modelos
salvos não são alterados.

## 4. Relatórios e painel

| Onde | Muda para |
|---|---|
| Painel: resumo do mês e "Recebidas no mês" | valor pago, com a fórmula nova |
| Faturamento | coluna nova "Acréscimos" depois de "Descontos"; Previsto e inadimplência com o valor cheio |
| Por cliente | Recebido e média com o valor pago |
| Por produto | linhas "Multas recebidas", "Juros recebidos" e "Outros acréscimos", só quando houver no período, além de "Descontos concedidos"; o total bate com o painel |
| Contas a receber | não muda (valor original) |

O CSV usa a mesma tabela e acompanha as colunas novas.

## 5. Banco (PORTÃO-BANCO)

- Testes só em SQLite em memória (`phpunit.xml`).
- O Postgres local tem a cópia dos dados reais de produção: migrar nele passa pelo portão (conexão, contagem, comando
  literal, "sim").
- Duas migrações aditivas (`contratos` e `cobrancas`), sem migração de dados. Produção: rodam sozinhas no deploy.
- Consultas de lista e painel precisam carregar o contrato junto da cobrança, para não gerar uma consulta por linha.

## 6. Wizard da Fase 25 não pode quebrar

Campos novos do contrato são opcionais e não entram em `ProgressoDeConfiguracao`. Baixa e correção continuam fora de
`progresso:*`. A faixa de baixa fica mais alta: conferir no navegador em 1280 px e 390 px com o coach aberto e recolhido.
**Critério de aceite:** suíte da Fase 25 verde sem alteração de asserções.

## 7. Fora do escopo

- Juros compostos e índices de correção monetária.
- Pagamento parcial.
- Regra no produto, ou produto sugerindo a regra do contrato.
- Regra para cobrança avulsa.
- PIX dinâmico (valor que muda depois de enviado) e reemissão de boleto.
- Trava legal de percentual.
- Totais de pendentes pelo valor do dia.

## 8. Testes (resumo)

- `CondicoesDePagamento` (PHP) e `condicoes.js` (`node --test`) com os mesmos casos: 0, 1, 15, 30 e 300 dias; pagamento no
  próprio vencimento; % e R$; arredondamento; desconto em R$ maior que o valor; sem regra.
- Contrato: grava, valida e audita as condições; contrato existente continua válido sem elas.
- Baixa individual: pré-preenchido, editado, vazio, zero, sem atraso zerando; 404 para alheia antes da validação; textos
  exatos da auditoria.
- Lote: em branco usa o contrato de cada cobrança; preenchido vale para todas; só em atraso recebe acréscimo; contratos
  diferentes e clientes diferentes; prévia igual ao gravado; recusa sem gravar nada.
- Corrigir: muda os quatro com senha e motivo e não recalcula. Desfazer: zera os quatro.
- Lista, painel e relatórios: somas, CSV byte a byte, total por produto igual ao painel; sem regra, valores iguais aos de
  antes.
- Recibo: idêntico sem regra; paga e pendente, antes e depois do vencimento; PIX com o valor do dia; sem QR em valor zero;
  aviso do boleto; extenso pelo líquido; 1 página.
- Mensagem: `{total}` e `{condicao}`; modelo antigo continua funcionando.
- Wizard: suíte da Fase 25 verde sem mudar asserções.
- Conferência visual e no navegador: do usuário, ou com autorização na hora, em conta de teste (o browser-agent não está
  instalado nesta máquina; só o Playwright).
