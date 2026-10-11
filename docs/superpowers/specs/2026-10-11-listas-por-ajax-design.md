# Fase 20 — o padrão de lista por ajax em todas as telas com lista — desenho

Data: 2026-10-11 · Situação: **aprovada pelo usuário em 2026-10-11** ("concordo"), inclusive as escolhas marcadas como minhas na revisão.

## Pedido (palavras e escolhas do usuário)
- "Se ficar bom, vamos implementar isto em todas as telas que contêm listas de dados." O usuário aprovou o
  carregamento da lista de cobranças da Fase 19 ("Aprovo o carregamento da lista de cobranças").
- Escolhas feitas em 2026-10-11:
  - **"Juntas"**: Clientes, Contratos e Produtos viram tabela no padrão das cobranças (a antiga T17.18) no mesmo
    trabalho.
  - **Painel e Relatórios também entram**, além das 5 listas.

"Isto" é o padrão da Fase 19 (skill `recibos`, padrão de usabilidade 12 e "Fase 19" nos Acertos):
- JSON só de leitura em **blocos de 4 páginas**;
- o navegador guarda as páginas por filtro e desenha só a atual;
- paginador acima e abaixo da tabela (25, 50 ou 100 por página);
- esqueleto de linhas na primeira carga e tabela esmaecida enquanto busca;
- filtros e página na URL;
- recarga depois de ações pelo evento `success` do Inertia;
- totais do resultado inteiro, onde houver soma que faça sentido.

## Situação de hoje (inventário de 2026-10-11)

| Tela | Carga | Formato | Ações por linha | Filtros |
|---|---|---|---|---|
| Clientes | `paginate(25)`, recarrega a página | `<ul>`, linha inteira é um link para editar | só editar | `q`, `cidade`, `uf`, `modelo`, `contrato` (botão Filtrar) |
| Contratos | `paginate(25)` | `<ul>` em 2 colunas | só editar | `cliente_id`, `situacao`, `modalidade`, `forma_pagamento`, `produto_servico_id` (botão) |
| Produtos e serviços | `paginate(25)`, **ordem só por nome** | `<ul>` | só editar | `nome`, `tipo`, `situacao` (botão) |
| Auditoria | `paginate(25)` | `<ul>` | link do registro | `de`, `ate` (ao escolher) |
| Usuários (admin) | **tudo** (`get()`) | `<ul>` | Liberar, Reativar, Bloquear | nenhum |
| Painel | **tudo** nas 3 listas (`get()`) | `LinhaCobranca.vue` | Enviar, Dar baixa, Forma de pagamento | nenhum |
| Relatórios | **tudo** (`tabela`) | `<table>` genérica | — | `de`, `ate` (botão), CSV |

O `Paginacao.vue` (só Anterior e Próxima) e o `App\Support\Paginacao` deixam de ser usados e saem.

## 1. Base comum (o que sai da tela de cobranças)
- **Servidor:** `App\Support\BlocoDePaginas`.
  - `de(Builder|Collection $fonte, int $pagina, int $porPagina): array{itens: Collection, pagina: array{inicio, por_pagina, total}}`.
    Página além da última volta a última.
  - **Com Builder:** usa `count()` e depois `offset`/`limit` de **4 × por página**. Não traz a lista inteira para a
    memória.
  - **Com Collection:** fatia na memória. É o caso das cobranças, que mesclam previstas.
  - `regras(): array` com as regras de `pagina` (inteiro ≥ 1) e `por_pagina` (25/50/100), para todos os `/dados`.
  - `ListaDeCobrancas` passa a usar esse helper, sem mudar comportamento: os testes da Fase 19 continuam iguais.
  - **Uma consulta só para as cobranças já existentes de todos os contratos** (achado da conferência visual de
    2026-10-11). Hoje a previsão chama `GeradorDeCobrancas::existentes()` uma vez por contrato ativo (N+1). No dev, com o
    PostgreSQL remoto a ~580 ms por consulta, 4 contratos já custam ~2,3 s; 100 contratos custariam 100 consultas. A
    regra de "cobrável" não muda, só a forma de buscar. O Painel e os relatórios também passam por aqui.
- **Navegador:** `resources/js/listaPaginada.js` (`criarListaPaginada`) **já existe**. Foi tirado da tela de cobranças
  na correção da revisão da Fase 19 e tem teste com `fetch` falso.
  - **Entrada:** `{ url, filtro, buscar, aoMostrar }`.
  - **Saída:** `{ linhas, resumo, carregando, erros, falha, estado, parametros, irPara, filtrar, recarregar,
    aoTerminarAcao, escolheuPorPagina }`.
  - **`estado`:** `carregando` | `erro-filtro` | `falha` | `vazio` | `lista`.
  - **Cada tela fornece:**
    - o `aoMostrar`, com `router.replace` para a URL e o foco no paginador de cima;
    - o `router.on('success')` para o `aoTerminarAcao`.
  - **A mudar nesta fase:**
    - `chaveDoFiltro` aceita qualquer objeto de filtros: todas as chaves menos `pagina`;
    - o `aoMostrar` e o `success` viram um wrapper Vue comum, `usarListaPaginada`, para não repetir em 7 telas.
- **Componentes:**
  - `EsqueletoLista.vue` (`linhas`, `colunas`), tirado do esqueleto das cobranças;
  - `Paginador.vue` (já genérico);
  - `Etiqueta.vue`: situação de contrato, produto e usuário. É um selo reto, sem a rotação do carimbo, que fica
    reservado a cobranças e recibos.
- **Filtros aplicam ao escolher** (padrão de usabilidade 7). Hoje só a Auditoria faz isso.
  - Selects e datas aplicam no `change`.
  - Campos de texto aplicam 400 ms depois de parar de digitar, ou no Enter. O botão "Filtrar" fica para quem quiser.
  - "Limpar filtros" em todas as listas que têm filtro.

## 2. Telas
### Clientes (tabela; `GET /clientes/dados`)
- **Colunas:** Cliente (nome e, embaixo, o documento formatado) · Cidade/UF · Recibo (modelo) · Contratos ativos
  (`withCount`, à direita) · Ações.
- **Ação principal:** "Editar".
- **Menu "⋯":** "Ver contratos" (`/contratos?cliente_id=`) e "Ver cobranças" (`/cobrancas?cliente_id=`).
- **Filtros e ordem:** os de hoje, mesma ordem (`nome`, `id`).
- **Sem totais:** só a contagem no "Mostrando X–Y de N".

### Contratos (tabela; `GET /contratos/dados`)
- **Colunas:**
  - Cliente (com o resumo da descrição embaixo);
  - Cobrança: periodicidade, modalidade e "vence dia N";
  - Vigência: início e, se houver, fim;
  - Valor (à direita);
  - Situação, numa `Etiqueta`: Ativo / Encerra em dd/mm/aaaa / Encerrado;
  - Ações.
- **Ação principal:** "Editar".
- **Menu "⋯":** "Ver cobranças do cliente". O encerramento continua na tela de edição.
- **Totais no rodapé:** contratos **ativos** somados **por periodicidade** (ex.: "Mensais ativos (12) R$ 3.120,00 por
  mês" e "Trimestrais ativos (2) R$ 900,00 por trimestre") e a quantidade de encerrados. Não se somam valores de
  periodicidades diferentes, porque daria um número sem sentido.
- **Ajuste junto:** o select de produto deixa de carregar o catálogo inteiro e passa a usar `BuscaAutosugestao`
  (`/produtos-servicos/busca`, que já existe).

### Produtos e serviços (tabela; `GET /produtos-servicos/dados`)
- **Colunas:** Nome · Tipo · Unidade · Preço (à direita) · Situação (`Etiqueta`: Ativo/Inativo) · Em contratos ativos
  (`withCount`) · Ações.
- **Ação principal:** "Editar".
- **Menu "⋯":** "Reajustar preço" (`/produtos-servicos/{id}/reajuste`) e "Ver contratos"
  (`/contratos?produto_servico_id=`).
- **Correção:** ordem `nome`, **`id`**. Hoje sem desempate a paginação é instável com nomes iguais.

### Auditoria (`GET /auditoria/dados`)
- Tabela: Quando · Registro (link quando houver) · Motivo.
- Filtros `de`/`ate` como hoje. Erro de data volta como 422 embaixo do campo.

### Usuários, admin (tabela; `GET /admin/usuarios/dados`)
- **Colunas:** Nome (+ "administrador") · E-mail · Cadastro · Situação (`Etiqueta`) · Ações.
- **Ação principal pela situação:**
  - pendente → "Liberar acesso";
  - bloqueado → "Reativar";
  - liberado → nenhuma ação principal.
- **Menu "⋯":** "Bloquear", menos para a própria conta. A ação em vermelho continua com confirmação no segundo clique.
- **Filtros novos:** busca por nome ou e-mail (`q`) e situação (`status`). A ordem continua com os pendentes primeiro.

### Painel (`GET /painel/dados?lista=…`)
- As três listas ("Para enviar", "Aguardando pagamento", "Recebidas no mês") passam a carregar **cada uma por conta
  própria**, com o mesmo composable.
- **Formato:** cada lista continua em `LinhaCobranca.vue`, com as ações de hoje.
- **Paginador:** um, **embaixo** de cada lista, e só quando houver mais de uma página. Seis paginadores numa tela de
  resumo pesariam demais.
- **Por página:** 10 (opções 10/25/50).
- **Título:** passa a mostrar a contagem e a soma da lista inteira, por exemplo "Aguardando pagamento (7) ·
  R$ 1.820,00".
- **Geração preguiçosa:** continua na abertura do Painel (`GET /painel`). O `/painel/dados` só lê.
- **Ações:** depois de uma ação, as três listas recarregam. Uma baixa tira a cobrança de "Aguardando" e põe em
  "Recebidas".

### Relatórios (`GET /relatorios/{tipo}/dados`)
- Os quatro relatórios carregam por ajax, com esqueleto e com os filtros aplicando ao escolher.
- **Paginam** o "por cliente", o "por produto" e o "contas a receber".
- **Não pagina** o faturamento: tem no máximo 36 linhas e o gráfico precisa de todas.
- **A linha de totais é sempre do relatório inteiro** (o servidor já calcula hoje) e fica no rodapé de todas as
  páginas.
- **O CSV continua com todas as linhas,** pelo mesmo link de hoje.

## Fora do escopo
- Ordenar pelo cabeçalho (T17.17).
- Abas de situação (o resto da T17.16).
- Ações em lote (T17.19).
- Apelido do cliente (T17.13).

## Testes
- **`BlocoDePaginas`:** bloco a partir da página, página além da última, Builder e Collection, regras.
- **Cada `/dados`:**
  - bloco e contagem;
  - filtros de hoje (os testes atuais de filtro passam a ler o JSON, com as mesmas asserções);
  - isolamento por usuário (só os próprios dados; usuários: só administrador);
  - 422 com filtro inválido;
  - não grava nada (contagens iguais).
- **Por tela:**
  - Clientes e produtos: `withCount`.
  - Contratos: totais por periodicidade e etiqueta "Encerra em".
  - Produtos: desempate por `id`.
  - Usuários: ação principal pela situação e "não bloqueia a si mesmo".
  - Painel: contagem e soma por lista; a geração continua só na página.
  - Relatórios: totais iguais em todas as páginas; faturamento sem paginação; CSV inteiro.
- **JS:** `useListaPaginada` (com `fetch` falso: cache, pedido atrasado descartado, página além da última), a nova
  `chaveDoFiltro` e a ação principal de usuários (módulo puro, como `acoesCobranca.js`).
- **Regressão:** suíte inteira da Fase 19 verde. Conferência no navegador de cobranças com o composable.
