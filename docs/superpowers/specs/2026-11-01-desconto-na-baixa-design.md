# Fase 26 — valor e desconto na baixa da cobrança — desenho

**Módulo:** Cobrança. Não cria dependência com outros módulos.

**Pedido do usuário (2026-11-01, com print da faixa "Dar baixa"):** "Na tela da função para dar baixa na cobrança,
falta o valor e o desconto. Para o desconto, o usuário poderá digitar um percentual ou um valor monetário."

**Decisões do usuário (brainstorming em chat, 2026-11-01):**
- **Recebido = valor pago** (valor − desconto) no painel e nos relatórios. O **Previsto** continua com o valor cheio,
  e os relatórios ganham uma coluna/linha de descontos para a diferença ficar explicada.
- **O desconto aceita 100%** (valor pago R$ 0,00), registrado na auditoria como desconto integral. "Cancelar"
  continua existindo para quem não quer registrar pagamento.
- **Recibo mostra o desconto**, em modelos `webiss-v2` e `danfse-v2`, preenchendo os campos que o layout já
  tem: "Desconto condicionado" e "Valor líquido" (campo confirmado pelo usuário). **O v2 substitui o v1**: os
  recibos existentes passam para v2 por migração de dados, e o v1 sai do código.
- **Baixa em lote aceita desconto**, com o mesmo seletor `% | R$` da baixa individual e prévia por cobrança. *(Decisão de
  2026-11-01, depois de ver a tela, no lugar de "um percentual único": o Marcos pediu "campo com seletor" nas três telas, com
  um texto de ajuda, e, em R$, "mesmo valor em cada uma" — R$ 20,00 tira R$ 20,00 de cada cobrança selecionada.)*
- **Desconto errado se corrige no "Corrigir pagamento"** (senha + motivo), que passa a aceitar data e desconto.

**Estado antes desta fase (lido no código em 2026-11-01):**
- `BaixaInline.vue` só pede `pago_em`; `CobrancaController::pagar` grava `status = paga` e `pago_em`, com auditoria
  `cobranca.paga` citando o valor cheio.
- `cobrancas` não tem campo de desconto nem de valor pago.
- O "recebido" soma `valor_centavos` das pagas em `PainelController` (resumo do mês e bloco "Recebidas no mês") e em
  `App\Services\Relatorios` (faturamento, por cliente, por produto via `cobranca_itens.total_centavos`).
- `FamiliaModelo::versaoAtual()` decide o modelo dos recibos novos ("ao criar uma v2, trocar só aqui"); cada recibo
  grava o seu `modelo`. Produção tem recibos v1 emitidos, então a regra da skill vale: mudança visual = `-v2`.

## 1. Dado e regra do desconto

- **Migração:** `cobrancas.desconto_centavos` (`unsignedBigInteger`, padrão 0). Aditiva; cobranças existentes ficam
  com desconto 0. **Valor pago** é derivado, nunca gravado: `valor_centavos − desconto_centavos`.
- **Entrada:** `desconto_tipo` (`percentual` | `valor`) e `desconto` (texto).
  - `percentual`: número de 0 a 100 com até 2 casas decimais (vírgula ou ponto).
  - `valor`: reais no formato de `Dinheiro::paraCentavos` (a mesma regra dos outros campos de valor).
  - Vazio = 0.
- **Cálculo no servidor** (`App\Support\Desconto`, puro): percentual → `round(valor × p / 100)`, meio centavo para
  cima, em inteiros (sem float na conta final). A tela só exibe o mesmo cálculo (`resources/js/desconto.js`).
- **Limite: o desconto trava em 100% do valor** (pedido do usuário, 2026-11-01). A tela não deixa passar disso
  (seção 2) e o servidor confere de novo: `0 ≤ desconto ≤ valor`; acima ou negativo → erro de validação no campo
  `desconto` (pedido montado fora da tela).
- **Auditoria `cobranca.paga`:** "Baixa registrada (R$ 247,57, desconto 10% = R$ 24,76, pago R$ 222,81, em
  01/11/2026)". Sem desconto, o texto continua como hoje. Com 100%: "Baixa com desconto integral (100% = R$ 247,57,
  pago R$ 0,00, em …)".
- **Desfazer baixa** zera `desconto_centavos` (a cobrança volta a pendente com o valor cheio).
- **Corrigir pagamento** aceita `pago_em` e desconto (mesmos campos e regra), exige senha e motivo como hoje, e grava
  antes/depois na auditoria `pagamento.corrigido`.
- Cobrança não pendente continua recusando baixa; cobrança alheia continua 404 antes da validação.

## 2. Telas

**Baixa individual** (`BaixaInline.vue`; usada na faixa da lista de cobranças e no bloco "Aguardando pagamento" do
painel — os dois passam a informar o valor da cobrança):

```
Valor            Desconto              Valor pago        Data do pagamento
R$ 247,57        [ 10   ] [% | R$]     R$ 222,81         [01/11/2026]     [Confirmar baixa] [Voltar]
```

- Valor só leitura. Seletor % / R$ troca a máscara (`valor` para reais; percentual com até 2 casas) e **limpa o
  número** ao trocar (10% não vira R$ 0,10).
- **Trava em 100%:** em %, o campo não passa de 100 (digitar 150 fica 100); em R$, não passa do valor da cobrança
  (digitar mais fica no valor). Ao travar, o campo mostra por um instante "Máximo: 100%" ou "Máximo: R$ 247,57"
  (com `aria-live`), para a pessoa entender por que o número mudou. A regra de travar fica em `desconto.js`, com
  teste.
- Valor pago recalculado a cada tecla, com `.destacar` quando muda. Com 100%: aviso laranja "Desconto integral:
  nada será recebido."
- **Texto de ajuda sempre visível embaixo do campo** (pedido do Marcos, 2026-11-01: a tela só mostrava o percentual e
  nada dizia que também dava para digitar um valor): "Escolha % ou R$ e digite o desconto." Ligado ao campo por
  `aria-describedby`, junto do id do erro quando houver, e sem alargar a coluna do campo.
- "10," (separador no fim, digitação em curso) vale 10 na prévia e no valor pago, e é **enviado** como "10": o servidor
  não recebe o separador sobrando.
- Erro do servidor embaixo do campo que errou (o do desconto e o da data, dentro da coluna de cada campo). 390 px sem
  rolagem horizontal (os campos quebram linha).

**Baixa em lote** (`BaixaEmLote.vue`): rótulo "Desconto para todas" e o **mesmo campo com seletor `% | R$`** da
baixa individual (mesma lógica, `usarDescontoDaBaixa.js`: trava, trocar limpa o número, aviso). Opcional (vazio = sem
desconto).
- **%:** o mesmo percentual em cada cobrança, arredondado por cobrança. Trava em 100.
- **R$:** o mesmo valor sai de **cada** cobrança selecionada (R$ 20,00 tira R$ 20,00 de todas). O campo trava no valor da
  **menor** cobrança elegível (pendente), para nenhuma ficar negativa; aviso "Máximo: R$ X,XX (valor da menor
  cobrança)". Em %, "Máximo: 100%".
- Texto de ajuda: "Escolha % ou R$. O desconto vale para cada cobrança: em R$, o mesmo valor sai de todas."
- A prévia lista, por cobrança, valor, desconto e valor pago, com totais, nos dois tipos (o total da prévia é igual à soma
  gravada). Confirmação em dois cliques, como hoje.
- **Servidor** (`desconto_tipo` + `desconto`, as mesmas regras da baixa individual): o desconto vale só para as
  **pendentes** selecionadas; paga ou cancelada selecionada não entra no limite nem recebe desconto. O lote é recusado
  **inteiro**, sem gravar nada, com erro no campo `desconto`: formato inválido ("Desconto inválido."), percentual acima de
  100 ("Percentual inválido: use de 0 a 100, com até 2 casas decimais.") e R$ acima da menor pendente ("O desconto não
  pode passar do valor da menor cobrança (R$ X,XX)."). R$ igual à menor é aceito (essa fica com pago R$ 0,00 e audita como
  integral).

**Corrigir pagamento** (`CorrecaoPagamentoInline.vue`): além da data, os campos de desconto (preenchidos com o
desconto atual em R$) e o valor pago novo, com o mesmo texto de ajuda da baixa individual.

**Lista de cobranças:** paga com desconto mostra o valor cheio e, embaixo, menor, "pago R$ 222,81". A aba e o rodapé
"Pagas" somam o valor pago (coerente com o Recebido); as demais situações continuam com o valor cheio.

## 3. Recibo

- **O v2 substitui o v1** (decisão do usuário, 2026-11-01): `ModeloRecibo` passa a ter só `WebissV2 = 'webiss-v2'` e
  `DanfseV2 = 'danfse-v2'`; os templates v2 nascem como cópia dos v1, e os arquivos `webiss-v1`/`danfse-v1` e os
  casos `WebissV1`/`DanfseV1` saem do código. Exceção consciente à regra da skill ("mudança depois de recibo emitido
  = nova versão, a antiga continua renderizando"): é aceitável porque, sem desconto, o v2 sai idêntico ao v1, então
  nenhum recibo já emitido muda de aparência.
- **Única diferença do v2:** cobrança paga com desconto preenche "Desc. Cond. (R$)" / "Desconto Condicionado" com o
  desconto e "Valor Líquido" com o valor pago. "Valor Total" continua com o valor cheio. Sem desconto, o v2 é igual
  ao v1. Nenhum campo novo nem mudança de posição.
- `FamiliaModelo::versaoAtual()` passa a devolver o v2.
- **Migração de dados** (escolha do usuário, 2026-11-01): `UPDATE recibos SET modelo = 'webiss-v2' WHERE modelo =
  'webiss-v1'` e o mesmo para `danfse`. O `down` faz o inverso. Só o campo `modelo` muda; número, snapshot e datas
  ficam. A coluna não tem valor padrão (nada mais a migrar). Com isso não existe troca de modelo na hora da baixa.
- **Atenção:** em produção essa migração **escreve em todos os recibos** (60 em 2026-11-01) e roda sozinha no deploy
  (`migrate --force`). No Postgres local (cópia de produção) passa pelo PORTÃO-BANCO.
- O renderizador passa `desconto` e `valor_pago` (dados vivos da cobrança, como já acontece com situação e
  `pago_em`).

## 4. Relatórios e painel

| Onde | Muda para |
|---|---|
| Painel: resumo do mês e "Recebidas no mês" | soma de `valor_centavos − desconto_centavos` |
| Faturamento | Recebido = valor pago; coluna nova "Descontos"; Previsto e inadimplência com o valor cheio |
| Por cliente | Recebido e média por cobrança com o valor pago |
| Por produto | Recebido dos itens como hoje + linha "Descontos concedidos" (negativa), sem rateio entre itens, para o total bater com o painel |
| Contas a receber | não muda (só pendentes) |

O CSV usa a mesma tabela genérica e acompanha as colunas novas.

## 5. Banco (PORTÃO-BANCO)

- Testes só em SQLite em memória (`phpunit.xml`).
- **Postgres local** (`postgres` no Docker, banco `cobrancas`) tem a **cópia dos dados reais de produção** desde
  2026-11-01: rodar a migração nele é escrita e passa pelo portão (conexão, contagem, comando literal, "sim").
- Duas migrações: `cobrancas.desconto_centavos` (aditiva, padrão 0) e a troca `recibos.modelo` v1 → v2 (escreve
  em todos os recibos v1; `down` reverte).
- Produção: as duas rodam sozinhas no deploy (`migrate --force`); o deploy é do usuário, que fica avisado de que
  ele **altera os recibos existentes** (só o campo `modelo`). Vale fazer backup do banco antes.

## 6. Wizard da Fase 25 não pode quebrar (exigência do usuário, 2026-11-01)

Pontos de contato conferidos no código e o que esta fase garante:
- **Progresso** (`App\Support\ProgressoDeConfiguracao`): depende só de dados do prestador, chave PIX, modelo de
  mensagem, produto/serviço e cliente. Esta fase não mexe em nenhum deles nem no cálculo.
- **"Parametrizar o recibo"** grava a **família** (`users.modelo_padrao` e `clientes.modelo`, enum `FamiliaModelo`),
  nunca a versão. Trocar `versaoAtual()` para v2 não muda perfil, cliente nem validação. **Proibido** expor
  `webiss-v2`/`danfse-v2` como opção nos selects de modelo (`FamiliaModelo::opcoes()` continua com 2 famílias).
- **Trava de criação** (`progresso:perfil` / `progresso:liberado`): baixa, baixa em lote, corrigir e desfazer **não**
  criam registro e continuam fora da trava. Rota nova desta fase (se houver) também fica fora.
- **Coach e holofote na tela:** o coach é fixo no canto inferior direito a partir de `sm` (`z-40`) e o painel tem
  holofote. Uma conta retroativa (com cobranças e ainda com pendência) vê o coach junto da faixa de baixa, que fica
  mais larga. A faixa não pode ficar inutilizável por baixo do coach: conferir no navegador em 1280 px e 390 px com
  o coach aberto e recolhido.
- **Critério de aceite:** a suíte da Fase 25 continua verde sem alteração de asserções:
  `ProgressoDeConfiguracao*`, `ProgressoDeConfiguracaoMiddlewareTest` (inclusive `test_usuario_retroativo_*`) e
  `resources/js/progressoHolofote.test.js`.

## 7. Fora do escopo

- Juros, multa ou valor pago maior que o valor da cobrança.
- Pagamento parcial (várias baixas na mesma cobrança).
- Rateio do desconto entre itens no relatório por produto.
- Desconto definido no contrato ou na emissão (desconto incondicionado).

## 8. Testes (resumo)

- `Desconto` (PHP) e `desconto.js` (`node --test`) com os mesmos casos: vazio, 0, percentual com arredondamento,
  100%, acima do valor, negativo, formato inválido (`1e3`, `+5`, texto).
- Trava da tela (`desconto.js`): 150% vira 100%; R$ acima do valor vira o valor; dentro do limite fica como digitado.
- Baixa individual: grava desconto e auditoria (inclusive integral); 404 para cobrança alheia antes da validação;
  não pendente recusada.
- Migração v1 → v2: troca só `modelo` dos recibos v1 (WebISS e DANFSe), não toca nos outros campos; `down` volta.
  Testes que citam v1 (`TrocaDeModeloTest`, `AcoesCobrancaTest`, `UsuarioModeloTest`, `PdfReciboTest`,
  `EmissaoReciboTest`) passam a esperar v2.
- Lote (decisão de 2026-11-01): percentual aplicado a cada cobrança com arredondamento próprio e R$ igual em todas; prévia
  igual ao gravado nos dois tipos (mesmo exemplo no PHP e no JS: 10001 e 24757, 10% → 1000 e 2476; R$ 20,00 → 2000 e 2000).
  Limite em R$ pela menor **pendente** (a paga selecionada não conta), R$ igual à menor aceito, acima recusado sem gravar
  nada; desconto sem tipo, tipo inválido e formato inválido recusados; vazio = sem desconto. Em JS: trava pela menor e envio
  sem o separador final.
- Corrigir: muda desconto com senha e motivo, auditoria com antes/depois. Desfazer: zera o desconto.
- Painel e relatórios: recebido = valor − desconto; CSV byte a byte atualizado; total por produto bate com o painel.
- Recibo: v2 preenche os dois campos e cabe em 1 página (`AmostrasVisuaisTest`, combinações com desconto); v2 sem
  desconto produz o mesmo texto que o v1 produzia (comparação feita antes de apagar o v1).
- Wizard: suíte da Fase 25 verde sem mudar asserções; teste novo prova que `FamiliaModelo::opcoes()` continua com
  as 2 famílias depois de `versaoAtual()` apontar para o v2.
- Conferência visual do PDF: do usuário. Smoke no navegador: só com autorização na hora, conta de teste (o
  browser-agent não está instalado nesta máquina; só o Playwright), incluindo a faixa de baixa com o coach aberto.
