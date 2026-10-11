# Lista de cobranças paginada por ajax, com totais por situação — desenho

Data: 2026-10-11 · Situação: desenho aprovado em conversa pelo usuário ("sim"), aguardando revisão deste documento.

## Pedido (palavras do usuário)
1. "Só falta trazer a somatória dos valores listados no final da tabela, separados por status."
2. "Precisa limitar a quantidade de registros por página, e adicionar um paginador antes e depois da tabela. De
   preferência que esta tela carregue os dados via ajax e monte o resultado no frontend."
3. Sobre onde paginar: "podemos misturar no navegador (cache de algumas páginas de início) e no servidor para os
   demais. Porque se houver muitos registros vai demorar pra renderizar na tela e depende muito do hardware do usuário."

Hoje `CobrancaController@index` manda **todas** as cobranças do filtro, mais as previstas da Fase 18, como prop do
Inertia, e a tela desenha todas. Com "Até" de 36 meses e muitos contratos, isso passa de milhares de linhas, e cada
linha carrega recibo e histórico de um banco remoto com latência alta.

## Decisão: o servidor pagina e o navegador guarda um bloco de páginas
- O servidor sempre devolve um **bloco de 4 páginas** a partir da página pedida, com os **totais do resultado
  inteiro**.
- O navegador guarda os blocos que já recebeu: ir da página 1 à 4 não faz pedido novo.
- O navegador **desenha só a página atual**, com no máximo 100 linhas. O custo de desenhar não depende do tamanho do
  resultado nem do hardware.

Alternativas descartadas com o usuário: só no navegador (o resultado inteiro iria de uma vez e pesaria em listas
grandes) e só no servidor (toda troca de página esperaria o servidor).

## 1. Servidor
### Página (`GET /cobrancas`, Inertia)
- Continua rodando a geração preguiçosa (`GeradorDeCobrancas::gerarPara`), **uma vez, ao abrir**.
- Manda só o necessário para montar a tela: `filtros` (validados como hoje, mais `pagina` e `por_pagina`),
  `clienteFiltro`, `chavesPix` e `formasPagamento`. **Não manda mais `cobrancas`.**
- **A página não recusa o período.** Ela valida só o formato (`date_format`, `in`, `integer`) e repassa os filtros
  como vieram. A checagem de "até antes do mês" e de "36 meses" fica no JSON, que responde 422, e o erro aparece
  embaixo do campo "Até" com a tela de pé. Assim, um link com período ruim não deixa a pessoa sem saída.

### Dados (`GET /cobrancas/dados`, JSON, mesma autenticação e isolamento)
- **Parâmetros:** os filtros de hoje (`mes`, `ate`, `status`, `cliente_id`, `canceladas`), mais `pagina` (≥ 1,
  padrão 1) e `por_pagina` (25, 50 ou 100; padrão 25).
- **Validação:** as mesmas regras e mensagens da Fase 18. Erro volta como **422 JSON** (`errors.ate` etc.).
- **Não grava nada:** sem geração preguiçosa aqui.
- **Como monta a resposta:**
  1. **Lista leve.** As cobranças reais do filtro só com as colunas que ordenam e somam (`id`, `vencimento`,
     `status`, `valor_centavos`), mais as previstas da Fase 18, quando cabem. Mescladas por vencimento, na mesma
     ordem e com as mesmas regras de hoje.
  2. **Totais por situação** sobre a lista leve inteira: `{situacao, quantidade, soma_centavos}`, nesta ordem:
     `vencida`, `vence_hoje`, `a_vencer`, `prevista`, `paga`, `cancelada`. Situação sem linha não entra.
  3. **Total geral** = soma de todas as situações **menos `cancelada`**. Cancelada não é dinheiro a receber.
  4. **Bloco.** Fatia de `(pagina − 1) × por_pagina` com `4 × por_pagina` linhas. Só as cobranças reais do bloco são
     carregadas por inteiro (`cliente`, `recibo`, `auditorias`) e viram `paraLista`. As previstas já vêm prontas.
     `paraLista` passa a incluir `valor_centavos`.
- **Resposta:**

  ```
  {
    linhas: [...],
    totais: [{ situacao, quantidade, soma_centavos }],
    total_geral: { quantidade, soma_centavos },
    pagina: { inicio, por_pagina, total }
  }
  ```

  `inicio` é a página da primeira linha do bloco e `total` é a quantidade de linhas do resultado inteiro.
- **Página além da última** (por exemplo depois de uma ação que tirou linhas do filtro): devolve o bloco da última
  página válida. `inicio` diz qual é.
- **Desempenho:** a lista leve tem uma linha por cobrança do filtro, sem relações. As consultas pesadas ficam
  limitadas ao bloco. `// ponytail:` a lista leve fica inteira na memória (até 36 meses); trocar por agregação no
  SQL se passar de dezenas de milhares de linhas.

## 2. Navegador (`Cobrancas/Index.vue`)
- **Ao montar:** lê `filtros` (inclusive `pagina` e `por_pagina`) e busca o bloco da página atual.
- **Cache:** guardado por chave de filtros (`mes`, `ate`, `status`, `cliente_id`, `canceladas`, `por_pagina`), com as
  linhas por página. Trocar de página dentro de um bloco já carregado não faz pedido; fora dele, busca o bloco que
  começa na página pedida.
  - **Quando o cache é descartado:** ao mudar qualquer filtro ou a quantidade por página, quando a página remonta e
    depois de qualquer ação.
  - **Como as ações recarregam:** as ações (dar baixa, cancelar, forma de pagamento, corrigir, desfazer, enviar)
    terminam com o redirect de volta do Inertia, que remonta a página; ao montar, ela busca a página atual de novo.
    **[A VERIFICAR na implementação]:** confirmar se cada ação remonta mesmo, porque algumas usam `preserveState`. A
    que não remontar recarrega por um evento de sucesso.
- **URL:** filtros e página ficam na URL. F5, voltar e link compartilhado reabrem a mesma página.
  **[A VERIFICAR na implementação]:** atualizar a URL sem quebrar o histórico do Inertia v3 (há `router.replace` com
  atualização só no navegador, ou o equivalente da v3). Buscar em `search-docs`.
- **Corrida entre pedidos:** só a resposta do pedido mais recente é aplicada. Uma resposta antiga que chega depois é
  descartada, para a tabela não "voltar" para um filtro anterior.
- **Carregando:** um indicador girando perto do paginador, com a tabela atual esmaecida (sem piscar) até a resposta
  chegar.
  - **Na primeira carga:** em vez da tabela, a mensagem "Carregando cobranças…".
  - **Se a busca falhar:** a mensagem do erro e o botão "Tentar de novo" (padrão de usabilidade 2: o erro oferece a
    saída).
- **Paginador, igual acima e abaixo da tabela:**
  - o texto "Mostrando 26–50 de 132";
  - os botões Anterior e Próxima;
  - os números das páginas com reticências: primeira, última, atual ±1;
  - o select "Por página" (25, 50, 100).

  Some quando não há linhas. Com uma página só, ficam o texto e o "Por página". Trocar de página rola até o topo da
  tabela e leva o foco ao paginador de cima. Os botões têm `aria-label`, a página atual tem `aria-current="page"` e
  o paginador fica num `<nav aria-label="Páginas">`.
- **Rodapé da tabela (`<tfoot>`):** uma linha por situação de `totais`, com o rótulo do carimbo, a quantidade e a soma
  na coluna Valor (à direita, `tabular-nums`). Depois vem a linha **Total**, com o rótulo "Total (sem canceladas)"
  quando houver canceladas na lista. Valores formatados com `formatarCentavos`. Em telas estreitas vira um bloco
  abaixo dos cartões, no mesmo padrão da tabela.
- **Nada muda nas linhas:** a ação principal, o menu "⋯", a faixa de ação, o carimbo "Prevista", a legenda e a
  mensagem de vazio continuam iguais.
- **Lógica pura com teste `node --test`:** o cálculo das páginas mostradas no paginador, a localização de uma página
  no cache e a chave de cache ficam num módulo `.js`, como `acoesCobranca.js` e `filtroPeriodo.js`.

## Fora do escopo
Buscar o bloco seguinte antes de chegar nele; ordenar pelo cabeçalho (T17.17, que vai virar parâmetro do mesmo
endpoint); abas de situação (resto da T17.16); paginação das outras listas (clientes e contratos entram na T17.18).

## Testes
- **Endpoint (feature):**
  - bloco de 4 páginas a partir da pedida;
  - `pagina.total` e `inicio`;
  - página além da última;
  - `por_pagina` fora de 25/50/100 recusado;
  - totais por situação no resultado inteiro, mesmo com a página mostrando só uma parte;
  - canceladas fora do total geral;
  - previstas mescladas e somadas;
  - filtro de cliente;
  - isolamento por usuário: o JSON de outro usuário não aparece;
  - "não grava nada": contagens de `cobrancas` e `recibos` iguais antes e depois;
  - erros 422 do "até";
  - `paraLista` com `valor_centavos`.
- **Página (feature):** não manda mais `cobrancas`; manda `filtros` com `pagina` e `por_pagina`; a geração continua
  ao abrir.
- **JS:** as páginas visíveis do paginador (1, …, 4 5 6, …, 12), a página no cache e a chave de cache.
- Os testes atuais que leem `cobrancas` da prop do Inertia (`FiltroSituacaoTest`, `TelasCobrancaTest`,
  `PrevisaoNaListaTest`, `AuditoriaTest`) passam a ler o JSON. As asserções continuam as mesmas.
