# Roteiro do produto — onde estamos e o que vem (handoff entre sessões)

**Atualizado:** 2026-10-11 · Ler junto com a skill `.claude/skills/recibos/SKILL.md` (seção "Módulos do produto") e a
memória `.claude/MEMORY.md`. Este arquivo existe para que uma sessão nova (ou depois de compactar a conversa) retome
sem depender do histórico.

## 0. RETOMAR AQUI — estado em 2026-10-11 (antes de compactar a conversa)

### 0.1 PRÓXIMO PASSO IMEDIATO (2026-10-11, segunda compactação)
**Conferência visual da Fase 20 — AUTORIZADA pelo usuário em 2026-10-11 ("Sim, autorizo").** A autorização vale só
para esta conferência e foi dada para o escopo abaixo; não estende a outras escritas.
- **Escopo autorizado:** abrir as telas no Chrome de teste com o usuário SMOKE (id 2). Isso grava sessões no banco de
  dev (PostgreSQL `207.180.205.211:1`, banco `recibos`, usuário `recibos_app`; **não é produção**) e roda a geração
  preguiçosa ao abrir Painel e Cobranças (uso normal). **Não dar baixa, não bloquear, não editar nada** — qualquer ação
  que grave dado de negócio pede autorização à parte.
- **Telas:** Clientes, Contratos, Produtos e serviços, Auditoria, Usuários (o SMOKE não é admin: [A VERIFICAR] se dá
  para abrir `/admin/usuarios`; se não, registrar como não conferido), Painel, Relatórios (os 4), e Cobranças de novo
  (migrou para `usarListaPaginada`).
- **Em cada tela:** esqueleto na 1ª carga; paginador acima/abaixo (Painel: só embaixo, só com total > 10); troca de
  página dentro do bloco **sem pedido novo** (contar com `performance.getEntriesByType('resource')` filtrando
  `/dados`); busca ao digitar = 1 pedido depois de parar (Clientes `q`, Produtos `nome`); "Limpar filtros" aparece e
  volta ao padrão; F5 reabre igual; `scrollWidth === clientWidth` em 1264 px e em 390 px
  (`Emulation.setDeviceMetricsOverride`). Conferir os Minor marcados [A VERIFICAR] no plano da Fase 20 (e-mail longo
  em Usuários a 390 px; campo de data pedindo a cada dígito).
- **Como (pegadinhas já registradas na skill):** browser-use com `BU_CDP_URL=http://127.0.0.1:9333` (Chrome 114
  dedicado, `--user-data-dir` no scratchpad; nunca o Chrome do usuário). URL: `http://recibos.test/...` (Laragon; já
  havia sessão do SMOKE na aba). `goto_url`/`new_tab` estouram 5 s com o PostgreSQL remoto: esperar e ler depois.
  Antes do print, `cdp("Target.activateTarget", targetId=…)` + `cdp("Page.bringToFront")`; nunca print de página
  inteira (trava a aba). `js()` não pode devolver promessa. Senha do SMOKE em `scratchpad\smoke-senha.txt` (ler sem
  imprimir), só se a sessão tiver caído.
- **Processos de fundo da sessão anterior** (podem ter morrido com a compactação; conferir com `curl`):
  `php artisan serve --port=8000` e o Chrome headless na 9333. Se o Chrome não responder em
  `http://127.0.0.1:9333/json/version`, subir de novo como na skill (linha "browser-use (browser-harness)").
- **Depois:** registrar o resultado no plano da Fase 20 (T20.10, "Registro de execução") e na skill; corrigir o que
  aparecer (com teste quando houver lógica); pedir a validação do usuário (só ele marca **validada**); perguntar sobre
  os 10 Minor adiados.

### 0.2 Estado da branch
**Branch:** `feat/modulo-cobranca-catalogo`, já no remoto.
- Último commit: `36510fb` (correções da revisão final da Fase 20; árvore limpa).
- Suítes: PHP **384/384** (`php artisan test --compact`), JS **46/46** (`node --test resources/js/*.test.js`), build ok.
- **Ainda não foi feito merge na `main` das Fases 17 (resto), 18, 19 e 20:** falta a revisão final da branch e a decisão
  do usuário.
- O usuário fez um commit dele na `main` (`761f6d0`, requisito de PHP ^8.4 no composer); a branch tem o mesmo ajuste
  (`b83f22d`).

**Fases desta sessão**

| Fase | O quê | Plano | Estado |
|---|---|---|---|
| 18 | Previsão de recebimentos: previstas calculadas pelos contratos, nada gravado | `.claude/plans/2026-10-11-previsao-de-recebimentos.md` | **validada** |
| 19 | Lista de cobranças por ajax: blocos de 4 páginas, cache, paginador acima e abaixo, totais por situação, esqueleto | `.claude/plans/2026-10-11-paginacao-cobrancas.md` | carregamento **validado**; revisão final feita e corrigida (`1a84e9a`) |
| 20 | Levar o padrão da 19 a todas as listas, mais tabelas (a antiga T17.18) | `.claude/plans/2026-10-11-listas-por-ajax.md` (especificação aprovada: "concordo") | T20.1–T20.10 **testadas**; revisão final feita e corrigida; **falta a conferência visual** (pede autorização) e a validação do usuário |

Detalhes da Fase 19: as 5 Minor adiadas, a pegadinha do `<tfoot>` e as decisões estão no "Registro de execução" do
plano.

**Fase 20:** todas as listas (Clientes, Contratos, Produtos e serviços, Auditoria, Usuários, Painel, Relatórios) por
ajax; especificação e plano aprovados pelo usuário em 2026-10-11 ("concordo"). Executada inline em 2026-10-11; detalhes,
decisões e os 10 Minor adiados da revisão final no "Registro de execução" do plano; padrão na skill ("Fase 20").

**Pendências que dependem do usuário**
1. Fase 20: autorizar a **conferência visual** (grava sessões no banco de dev) e validar; decidir sobre os 10 Minor
   adiados.
2. ~~Conferência visual da lista de cobranças~~ **feita em 2026-10-11** (ver plano da Fase 19). Falta só "dar baixa e
   ver a lista recarregar": **grava pagamento**, então pede autorização à parte. Usar o Chrome dedicado da porta 9333
   (`BU_CDP_URL`), nunca o do usuário.
3. Validações antigas: T17.9 (smoke), T17.12, T17.14.
4. **T17.13 (apelido do cliente):** o usuário respondeu **"Sim"** em 2026-10-11 — **já existe recibo emitido em
   produção.** Logo, mostrar o apelido no recibo exige criar `webiss-v2`/`danfse-v2`; os `-v1` não mudam.
5. T17.11 (renovar ou prorrogar contrato): desenho a fazer com o usuário.
6. ~~`MATERIAIS/lists-m05.html` vazio~~ — o usuário excluiu o arquivo (2026-10-11).

**Fatos desta sessão que não estão no código**
- **419 ao excluir produto (relato do usuário):**
  - Causa observada: com a sessão vencida e o "lembrar de mim" válido, cada pedido simultâneo cria uma sessão nova.
  - Correção: `bootstrap/app.php` → `respond()` (419 fora de JSON volta para a página com "A página expirou. Tente
    de novo.").
  - O 419 em si não foi reproduzido: **[INCONCLUSIVO]**. Detalhes nas Pegadinhas da skill.
  - O teste no navegador deixou **5 sessões do usuário 2 (SMOKE)** na tabela `sessions` (18 linhas). Não foram
    apagadas: apagar é escrita e exige o "sim" do usuário.
- **Processos que podem ter ficado rodando:**
  - `php artisan serve` na porta 8000;
  - Chrome headless de teste na porta 9333 (`--user-data-dir` no scratchpad).

  O usuário usa `http://recibos.test` (Laragon).
- **Regras reforçadas nesta sessão:**
  - autorização de escrita no banco vale para um comando só;
  - sonda de CSRF segura: `DELETE /produtos-servicos/999999` (404 = token ok);
  - no browser-use, `js()` não pode devolver promessa (usar `setTimeout(async…, 0); 1`).

## 1. Estado agora

- **`main` no GitHub** (`git@github.com:pagmovel/MinhaCobranca.git`): Fases 0–5 (sistema base) e 8–11 (pagamento e
  formulários) mergeadas. Último merge: `d557f25`. 192 testes PHP + 7 testes de máscara (`node --test`).
- **Branch atual:** `feat/modulo-cobranca-catalogo` (saído do `main`), com a spec nova commitada (`7887219`).
- **Banco (PostgreSQL de desenvolvimento `207.180.205.211:1`, banco `recibos`):** migrações até o lote 3 aplicadas.
  Usuário real = id 1 (admin, `marcos.ronaldo@gmail.com`); usuário de teste = id 2 (`smoke@recibos.test`, senha só no
  scratchpad da sessão — não versionada).

## 2. Próximo passo imediato

1. ~~Usuário revisa a spec~~ — **aprovada em 2026-10-10** ("por enquanto não modifico nada").
2. ~~Plano das Fases 12–15~~ — **escrito** em `.claude/plans/2026-10-10-modulo-cobranca-catalogo.md` (16 tarefas,
   código completo, PORTÃO-BANCO só na T12.5). **Aprovado pelo usuário em 2026-10-10** ("por enquanto estou satisfeito").
3. **EM EXECUÇÃO (nativa, executing-plans).** Para retomar depois de compactar:
   - ledger em `.superpowers/sdd/2026-10-10-modulo-cobranca-catalogo/progress.md` (fora do git) + `git log`; a tabela
     "Acompanhamento" do plano mostra o estado de cada tarefa (T12.1 = testada).
   - Copiar código do plano com o extrator do scratchpad (`extrair.py <tarefa> <caminhos>`); se o scratchpad sumir,
     recriar: aceita só o bloco cujo cabeçalho é "`caminho`…:" no início da linha **ou** depois de "— ", dentro da
     seção da tarefa; grava com `\n` (Python 3.9: `write_bytes`, não `write_text(newline=)`).
   - Revisão final do branch por subagente opus no fim (T15.5).
4. Correções de usabilidade pedidas no meio do caminho (já no branch): formulário de chave PIX abre na linha clicada
   (`50ae99c`); botões de excluir/cancelar com classes `botao-perigo` (`273b572`).
5. Pergunta aberta do usuário (2026-10-10): testes rodam em SQLite em memória porque não há PostgreSQL de teste (T7.1).
   Oferecido criar `recibos_teste` no mesmo servidor (escrita → PORTÃO-BANCO); aguardando resposta.

### Atualização 2026-10-10 (fim da execução das Fases 12–15)
- Fases 12, 13, 14 e 15 **mergeadas na `main`** (último merge `8bfc61a`). Suíte 252/252 (SQLite), JS 13/13.
- Smoke T15.5 feito (usuário SMOKE id 2, autorizado); revisão final opus sem Critical/Important; 2 Minor corrigidos.
- Banco **`recibos_teste`** criado (dono `recibos_app`, 2026-10-10, autorizado). `TestCase` tem trava: a suíte só roda
  em `recibos_teste` (pgsql) ou `:memory:` (sqlite). `phpunit.xml` **continua em SQLite** — trocar para o PostgreSQL
  só com autorização do usuário (a suíte remota passou de 400 s).
- **Próximo (pedido do usuário, 2026-10-10):** usabilidade dos itens no contrato/cobrança (consulta ao catálogo,
  cadastrar item sem sair da tela, valor negociado visível e editável, incluir vários itens) e **animações** em
  transições, inclusões/remoções, inputs e selects — design curto aguardando aprovação. Depois: Fase 16 (importação
  de XML) e Contas a Pagar.

### Atualização 2026-10-10 (Fase 17)
- Fase 17 (facilidades de uso) e T17.8 (recibo lista os itens; descrição opcional) **mergeadas na `main`**
  (último merge `2f30cef`). Suíte 271/271 (SQLite), JS 16/16.
- **Próximo:** T17.9 (encerrar/recomeçar contrato com pro-rata) — desenho aprovado e detalhado em
  `.claude/plans/2026-10-10-facilidades-de-uso.md`; nenhum código escrito. Branch `feat/modulo-cobranca-catalogo`.
- Padrões de usabilidade obrigatórios: seção no topo da skill `recibos` + linha no `CLAUDE.md` do projeto.
- `phpunit.xml` em SQLite; `recibos_teste` existe (esquema criado por engano numa execução, sem dados) — rodar a suíte
  no PostgreSQL só com autorização. Servidor do smoke (`artisan serve` :8000) e Chrome 9333 podem estar abertos.

## 3. Pendências em aberto (de planos anteriores)

| Item | Onde está | Estado |
|---|---|---|
| V1–V3 conferências visuais (amostras x notas, carimbo/rodapé, prints do smoke) | plano `2026-10-09-sistema-recibos.md` | pendente (usuário) |
| V4 visual de VENCENDO HOJE e bloco de pagamento | plano `2026-10-10-pagamento-e-formularios.md` | pendente; **QR já validado pelo usuário no celular** |
| D1 "recebido" | — | **decidida: pago no mês (caixa)** → **implementada na T15.1 (testada)** |
| L1 limpeza dos dados SMOKE (user id 2 e tudo dele, inclusive chaves PIX 5 e 6, contrato 4, cobranças 7 e 8) | plano `2026-10-09-…` | pendente — pedir PORTÃO-BANCO com comando literal |
| M1–M2 (valor com 20+ dígitos → 500; enviada aceita cancelada) | plano `2026-10-09-…` | adiados |
| M3–M7 (corrida/acessibilidade do BuscaCliente, chave de cobrança paga, favorecido no WebISS, máscara ao trocar tipo) | plano `2026-10-10-pagamento-…` | adiados |
| Fase 6 (XML) e T7.1 (suíte em PostgreSQL de teste) | plano `2026-10-09-…` | substituídas pelo módulo Notas Fiscais (abaixo) / aguardam base de teste do usuário |

## 4. Módulos (produto SaaS; cada módulo vendável sozinho)

### 4.1 Cobrança — em construção
Pronto: clientes, contratos (pré/pós-pago), cobranças, recibos WebISS/DANFSe, envio, baixa, PIX/boleto, máscaras,
autosugestão. **Próximo:** spec do item 2 acima (catálogo, itens, filtros, CNPJ/CEP, relatórios).

### 4.2 Contas a Pagar — a desenhar (depois do Cobrança)
O que o usuário já definiu (2026-10-10):
- Escopo: **cadastro de fornecedores**, **produtos e serviços vinculados aos fornecedores** (é aqui que mora o
  **custo**), **dashboards** e **relatórios**.
- **Quem compra só Contas a Pagar não cadastra clientes.** Com os dois módulos, uma despesa pode ser **atribuída a um
  cliente** (o campo só aparece se Cobrança estiver contratado).
- **Rentabilidade** (receita − custo mensal): com os dois módulos, sai **por cliente e por produto/serviço**; com um
  só, o relatório mostra a parte que existe.
- Pergunta ainda aberta (feita e não respondida antes de reformular): o custo mensal é **fixo do serviço** (dividido
  entre clientes), **por cliente**, ou **os dois**? — retomar no brainstorming do módulo.

### 4.3 Notas Fiscais — planejado
- Objetivo do usuário: **gerar a NF (opcional)**, **registrar localmente os dados da NF** e **sincronizar as notas já
  emitidas manualmente**. Tabelas devem **espelhar a nota** (base comum = Declaração de Prestação de Serviço), com o XML
  original guardado.
- **Primeira tarefa obrigatória:** pesquisar em **fontes oficiais** as regras fiscais (LC 116/2003 lista de serviços,
  alíquotas de ISS municipais, IBS/CBS da reforma, leiaute da NFS-e nacional) — o cálculo de tributos é **opcional** e
  só vale quando o cliente emite NF.
- **Os XMLs são de notas EMITIDAS pelo usuário** (ele é o prestador; os clientes são os tomadores), não de compras
  (confirmado pelo usuário em 2026-10-10). Logo, a importação se liga ao módulo **Cobrança** (nota ↔ cobrança/recibo,
  como previa a Fase 6 original e a "NFS-e vinculada" da skill), não ao Contas a Pagar.
- **Requisito do usuário (2026-10-10): se o tomador ainda não for cliente, o importador cadastra o cliente** com os
  dados do XML (identifica pelo CPF/CNPJ, único por usuário; cliente já existente não é sobrescrito). LGPD ok: são dados
  da nota que o próprio usuário emitiu, não cadastro de outro usuário. Campos do `Tomador` nos XMLs (só nomes conferidos):
  `CpfCnpj/Cnpj`, `RazaoSocial`, `Endereco/{Endereco, Numero, Complemento, Bairro, CodigoMunicipio, Uf, CodigoPais, Cep}`,
  `Contato/Email` (só no 16). **Não há nome da cidade** (só código IBGE): preencher pela consulta de CEP da Fase 14
  (ViaCEP devolve `localidade` e `ibge` — conferir o código); falhando, cidade em branco. Sem telefone nem inscrição
  municipal no XML. Modelo do recibo do cliente novo = `modelo_padrao` do usuário.
- **Decidido pelo usuário (2026-10-10):** importação = **Fase 16 do módulo Cobrança**, depois dos relatórios; começa
  pelo formato do **WebISS (ABRASF 2.02)** — é o único XML que ele tem; se eu achar na web um **exemplo oficial de XML
  do padrão nacional** (gov.br / documentação da NFS-e nacional), posso usá-lo nos testes (autorizado). Brainstorming →
  spec → plano da Fase 16 depois da T15.5.
- Proposta aceita: importação como **Fase 16 do módulo Cobrança** (depois dos relatórios, porque
  usa a consulta de CEP da Fase 14) — importar XML guardando o original, cadastrar cliente se faltar, sugerir a cobrança
  correspondente para vincular, mostrar a NFS-e no recibo/lista. Emissão, sincronização e tributos ficam no módulo Notas.
- XMLs de exemplo em `MATERIAIS/` (fora do git; dados pessoais): `NFSe_2026000000015_EC5K-BUEZ.xml` e
  `NFSe_2026000000016_AINQ-DWPJ.xml`. **Achados (só estrutura, 2026-10-10):** padrão **ABRASF versão 2.02**
  (`CompNfse`, namespace `http://www.abrasf.org.br/nfse.xsd`), assinatura digital; têm **ChaveAcesso de 50 dígitos**
  (a nota 16 tem a mesma chave do PDF DANFSe → o WebISS de Socorro parece repassar ao ambiente nacional —
  **[A VERIFICAR]**); grupos: InfNfse (Numero, CodigoVerificacao, DataEmissao, OutrasInformacoes), ValoresNfse,
  PrestadorServico, OrgaoGerador, DeclaracaoPrestacaoServico (Competencia, Servico com ItemListaServico,
  CodigoTributacaoMunicipio, Discriminacao, CodigoMunicipio, ExigibilidadeISS, MunicipioIncidencia; Prestador; Tomador
  com endereço/contato; OptanteSimplesNacional; IncentivoFiscal). O XML **16** traz também **CodigoNbs** e o grupo
  **IBSCBS** (finNFSe, cIndOp, indDest, valores/trib/gIBSCBS com CST e cClassTrib); o 15 não.
- Pedido feito ao usuário (ainda sem resposta): baixar também o XML de uma nota no **padrão nacional** (Emissor
  Nacional) para mapear os dois formatos com base real.

### 4.4 Integração com emissores — planejado (depois de Notas)
Sincronizar notas emitidas e emitir NFS-e. Provavelmente exige **certificado digital A1** e credenciais por emissor —
**[A VERIFICAR]** quando chegar a vez.

## 5. Ideias do usuário já decididas (não reabrir)
- Reaproveitar cadastro de cliente de outro usuário: **rejeitado** (LGPD — artigos na skill). Só dados públicos
  (CNPJ/CEP).
- Telas de Contratos e Clientes com **filtros** (entra na Fase 13).
- Contrato composto por **itens** do catálogo (Fase 12); cliente pode ter **vários contratos ativos** (já suportado).
