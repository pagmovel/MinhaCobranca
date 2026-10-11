# Roteiro do produto — onde estamos e o que vem (handoff entre sessões)

**Atualizado:** 2026-10-10 · Ler junto com a skill `.claude/skills/recibos/SKILL.md` (seção "Módulos do produto") e a
memória `.claude/MEMORY.md`. Este arquivo existe para que uma sessão nova (ou depois de compactar a conversa) retome
sem depender do histórico.

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

## 3. Pendências em aberto (de planos anteriores)

| Item | Onde está | Estado |
|---|---|---|
| V1–V3 conferências visuais (amostras x notas, carimbo/rodapé, prints do smoke) | plano `2026-10-09-sistema-recibos.md` | pendente (usuário) |
| V4 visual de VENCENDO HOJE e bloco de pagamento | plano `2026-10-10-pagamento-e-formularios.md` | pendente; **QR já validado pelo usuário no celular** |
| D1 "recebido" | — | **decidida: pago no mês (caixa)** → implementar na Fase 15 |
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
