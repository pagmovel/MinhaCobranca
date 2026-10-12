# Roteiro do produto — onde estamos e o que vem (handoff entre sessões)

**Atualizado:** 2026-10-11 · Ler junto com a skill `.claude/skills/recibos/SKILL.md` (seção "Módulos do produto") e a
memória `.claude/MEMORY.md`. Este arquivo existe para que uma sessão nova (ou depois de compactar a conversa) retome
sem depender do histórico.

## 0. RETOMAR AQUI — estado em 2026-10-12

### 0.0 PRÓXIMO PASSO IMEDIATO (2026-10-12)
- **T17.11 (renovar/prorrogar/aviso de contrato + Reativar desfaz encerramento) implementada direto na `main`**,
  sem branch separada (plano `.claude/plans/2026-10-11-renovar-prorrogar-contrato.md`, 5 tarefas + extensões
  pós-aprovação; detalhe completo na skill, seção "T17.11"). Suíte: **520 PHP + 94 JS, tudo verde**; `main` em dia
  com `origin/main` (push feito). **Aguardando validação do usuário** (smoke test / uso real) — sem isso, não
  marcar como "validada" na tabela de Fases abaixo.
- **Fora do T17.11, mesma sessão (2026-10-12), também direto na `main`: CPF/CNPJ do cliente ficou opcional**
  (cliente informal, pedido direto do usuário) — migration aplicada no banco de dev, testado, documentado na skill.
  Sem pendência de validação visual (é só uma regra de formulário).
- Backlog **não tocado nesta rodada**: itens 3–11 da seção "Backlog" abaixo (abas de situação, ordenar por
  cabeçalho, seleção em lote, Minors adiados, validações antigas T17.9/T17.12/T17.14, sessões do SMOKE, segurança
  do Postgres de dev, módulos futuros) continuam exatamente como estavam.

### 0.1 Histórico anterior (atualizado em 2026-10-11, depois do merge das Fases 17–23)
- **Fases 17–23 mergeadas na `main` e enviadas ao remoto** (merge `da2d53f`, `main` em `github.com:pagmovel/MinhaCobranca.git`
  no commit `da2d53f`). Fase 23 (vencimento em dia útil): T23.1–T23.12 testadas; 6 Minor da 2ª revisão tratados (5
  corrigidos com TDD, 1 — limite de 100 feriados sem lock — ruling documentado na skill); spinner global "Carregando…"
  feito (pedido do usuário no meio da execução).
- **Revisão final de branch inteira** (fabe501..e93665f, 93 commits, Fases 17–23) achou 1 Critical e 4 Important — todos
  corrigidos com TDD antes do merge (commits `9f1c9de`, `a79f78a`, `f716528`, `aeee09a`, `a681ea5`): editar contrato
  pulava cobrança/desfazia escolha de retroativos; 419 em DELETE/PUT/PATCH caía em 405; "Fim do contrato" reagendava
  encerramento já agendado sem passar pelo plano; "Recomeçar" cobrava de novo período já pago; suíte saía com código 1.
  1 Minor ficou deferido (N+1 em `GeradorDeCobrancas::gerarPara`, ~580 ms/contrato — otimização, não bug).
- **Validação final do usuário: "validado" (2026-10-11).** Fase 23 encerrada — integração confirmada pelo usuário.
- **Validação final do usuário: "validado" (2026-10-11).** Fase 24 (aviso de vencimento pelo WhatsApp) encerrada —
  merge `e59745e` + 4 rodadas de extensão pós-merge, direto na `main`, todas com TDD e push próprio:
  - `{item}`/`{chave_pix}` + prévia reativa no formulário de modelo (commit `7d3d48e`).
  - Campo "pessoa de contato" no cliente + `{contato}` (commit `72f909a`).
  - Cascata `{a|b|c}` nas variáveis + `{saudacao}`/`{apelido}`/`{razao_social}` (commit `742f11e`).
  - `modelos_mensagem.tipo_situacao` (a_vencer/vence_hoje/vencida) pré-seleciona o modelo certo na janela de aviso,
    sozinho ou em lote (usa a situação mais urgente do grupo) — commit `8f5a441`.
  Suíte final: PHP 488/488, JS 91/91. Detalhe completo de cada decisão na skill (seção Acertos/Pegadinhas da Fase 24)
  e no plano `.claude/plans/2026-10-11-aviso-whatsapp.md`.
  Próximo item do backlog: **T17.11** (item 2 da lista abaixo) ou outro que o usuário escolher.

### 0.2 Estado da branch
**Branch de trabalho `feat/modulo-cobranca-catalogo`:** mergeada na `main` (`da2d53f`), segue existindo no remoto (não
apagada — decisão de não apagar branches sem pedido explícito).
- **`main`:** suítes PHP **440/440** (saída limpa, exit 0), JS **60/60**, build ok. Workspace do plano da Fase 23
  (`.superpowers/sdd/2026-10-11-vencimento-em-dia-util/`) apagado após o merge (git log é o registro).
- **Banco de dev:** todas as migrações aplicadas (inclusive `feriados`, 2026-10-11, com o "sim" do usuário).
- Commit do usuário incorporado no merge: `761f6d0` (PHP ^8.4 no composer) — já estava na `main` antes do merge.

**Fases recentes**

| Fase | O quê | Plano | Estado |
|---|---|---|---|
| 18 | Previsão de recebimentos (previstas calculadas, nada gravado) | `2026-10-11-previsao-de-recebimentos.md` | **validada** |
| 19 | Cobranças por ajax: blocos de 4 páginas, cache, paginador, totais, esqueleto | `2026-10-11-paginacao-cobrancas.md` | carregamento **validado**; falta conferir "dar baixa e a lista recarregar" (grava pagamento: pede autorização) |
| 20 | Todas as listas por ajax e em tabela (T17.18) | `2026-10-11-listas-por-ajax.md` | **validada** (2026-10-11); Minor 2–7 e o "Enviar" do Painel a 390 px adiados (motivos no plano) |
| 21 | Apelido do cliente (T17.13): telas, busca, mesmo CNPJ em outra unidade, botão na descrição, nome do PDF | `2026-10-11-apelido-do-cliente.md` | **validada** (2026-10-11) |
| 22 | Trocar o modelo do recibo refaz as cobranças em aberto; enviadas não pagas com opção | `2026-10-11-troca-de-modelo.md` | **validada** (2026-10-11); depois, iniciais do WebISS centralizadas (`bf1a814`, decisão do usuário: no próprio `-v1`) |
| 23 | Vencimento em dia útil: calendário de feriados, ajuste em lote, aviso na avulsa e no Painel, spinner global | `2026-10-11-vencimento-em-dia-util.md` | **validada** (2026-10-11, merge `da2d53f`) |
| 24 | Aviso de vencimento pelo WhatsApp: modelos de mensagem, bloco "Vencendo" no Painel, ação no menu de cobranças, link wa.me, variáveis `{item}`/`{chave_pix}`/`{contato}`/`{apelido}`/`{razao_social}`/`{saudacao}` com cascata `{a\|b\|c}`, pré-seleção automática do modelo pela situação da cobrança | `2026-10-11-aviso-whatsapp.md` · spec `docs/superpowers/specs/2026-10-11-aviso-whatsapp-design.md` | **validada** (2026-10-11, merge `e59745e` + extensões pós-merge) |

**Backlog — o que ainda falta (para virar spec + plano quando o usuário escolher)**
0. **PEDIDO NOVO (2026-10-11), PRÓXIMO TRABALHO — vencimento em dia útil.** Palavras do usuário: "antes de gerar uma
   cobrança, verificar se o vencimento cai num final de semana ou feriado e se sim, alterar o vencimento daquela
   cobrança para o primeiro dia útil seguinte". Desenho aprovado em chat ("sim", 2026-10-11). **Spec escrita:**
   `docs/superpowers/specs/2026-10-11-vencimento-em-dia-util-design.md` — **aprovada pelo usuário** (D1–D3 "CONCORDO": Sexta-feira
   da Paixão automática; avulsas fora do ajuste em lote; boleto ajustado com aviso). **Plano escrito (2026-10-11):**
   `.claude/plans/2026-10-11-vencimento-em-dia-util.md` — aguarda revisão do usuário; depois, execução.
   Migração `feriados`: "SIM" antecipado do usuário; no portão, mostrar conexão/contagem/comando e confirmar.
   Fontes legais conferidas no planalto.gov.br (Leis 662/1949, 6.802/1980, 9.093/1995, 14.759/2023).
   - Fato do código: o vencimento sai só de `CalculadoraDePeriodos::periodo()` (usado por gerador, previsão,
     encerramento e retroativos); ajustar ali mantém o teste-guarda `test_previsao_bate_com_o_que_o_gerador_cria`.
     Não há nada de feriado no código.
   - **Respostas do usuário:** (1) feriados = **nacionais + locais** (estaduais/municipais cadastrados por ele numa tela).
     (2) locais = **da cidade/estado do prestador** (uma lista no perfil, vale para todas as cobranças).
     (3) **Carnaval conta** (segunda e terça, pela Páscoa). (4) **Avulsa: avisar e sugerir** o próximo dia útil (a data
     digitada é respeitada). (5) **Já geradas: ajustar as em aberto e não enviadas** (recibo refeito, mesmo número);
     enviadas e pagas não mudam.
   - **Rascunho do desenho (a apresentar ao usuário):** `DiasUteis` (puro, testável) com nacionais fixos + móveis pela
     Páscoa + Carnaval + lista local do usuário; `CalculadoraDePeriodos::periodo()` passa o vencimento por ele (o período
     não muda, só o vencimento; antecedência conta do vencimento ajustado; previsão igual ao gerador). Tela "Feriados"
     no perfil: lista local (dia/mês todo ano, ou data móvel como Corpus Christi) e **prévia** das cobranças em aberto
     não enviadas que caem em dia não útil, com "Ajustar" (padrão 9) — serve também para o ajuste inicial. Avulsa: aviso
     com botão "Usar <data>". Verificação feita (ver spec, seção 2).
1. ~~Revisão final da branch e merge na `main`~~ — **feito em 2026-10-11** (Fases 17–23, merge `da2d53f`, ver seção 0.1).
2. ~~T17.11 — renovar ou prorrogar contrato perto do fim~~ — **implementada em 2026-10-12** (ver seção 0.0);
   falta só a validação do usuário.
3. **T17.16 (parte que falta) — abas de situação** na lista de cobranças com contagem e soma (Vencidas · Hoje · A vencer ·
   Pagas), modelo `MATERIAIS/tables-m04.html` (só a estrutura). Os totais por situação já vêm do `/cobrancas/dados`.
4. ~~T17.17 — ordenar cobranças pelo cabeçalho~~ — **cancelada a pedido do usuário (2026-10-12)**. Não implementar.
5. **T17.19 — seleção de várias cobranças e ação em lote com prévia** (padrão de usabilidade 9). Deixada para depois.
6. **Minor adiados da Fase 20** (lista no "Registro de execução" do plano): 2 pedido a mais, 3 e-mail longo em Usuários a
   390 px (só conferível com conta admin), 4 e 5 período inválido, 6 foco, 7 "Tentar de novo"; e o botão "Enviar" do
   Painel a 390 px (`LinhaCobranca`). Minor adiados da Fase 19 no plano dela.
7. **Validações antigas:** T17.9 (smoke do encerramento), T17.12 (corrigir/desfazer baixa), T17.14 (área mais larga).
8. ~~Conferência pendente da Fase 19: dar baixa e ver a lista recarregar~~ — **validada pelo usuário (2026-10-12,
   "OK")**.
9. ~~Sessões do SMOKE na tabela `sessions`~~ — **apagadas (2026-10-12, autorizado pelo usuário)**: 1 linha do
   usuário 2 (smoke@recibos.test); a tabela tinha 3 linhas no total (não as "5 de 18" do registro antigo — estado
   mudou desde então), ficaram 2.
10. **Módulos seguintes** (seção 4): Contas a Pagar (a desenhar), Notas Fiscais e Integração com emissores
    (planejados). **Mantidos como pendência de propósito** (confirmado pelo usuário em 2026-10-12) — não
    antecipar o desenho sem pedido.
11. **Segurança do PostgreSQL de dev** (detalhe na skill, seção "Segurança do PostgreSQL de dev — instruções"):
    ~~credenciais vazadas~~ (senha do `postgres` e `APP_KEY` trocadas) e ~~SSL~~ (ligado, `hostssl` exigido,
    confirmado TLSv1.3) **feitos em 2026-10-12**. Falta: senha do `recibos_app` e fechar a porta pública do
    Postgres pra internet em geral (só pela rede interna do EasyPanel + túnel SSH pra dev).

**Como retomar numa conversa nova:** ler esta seção 0, a skill `.claude/skills/recibos/SKILL.md` inteira e o plano da
fase em questão. Tarefa nova de produto segue o fluxo superpowers (brainstorming → spec em `docs/superpowers/specs/` →
plano em `.claude/plans/` com fases/tarefas e estado → execução com TDD); escrita no banco sempre pelo PORTÃO-BANCO.

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
