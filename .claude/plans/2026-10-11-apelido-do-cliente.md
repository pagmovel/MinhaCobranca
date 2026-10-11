# Fase 21 — Apelido do cliente (T17.13)

**Pedido do usuário:** campo opcional de apelido (nome curto) no cliente, "para facilitar a identificação nas telas e nas
buscas" (lembrado de novo em 2026-10-11). Exemplo: sindicato com CNPJ e endereço da sede, serviço prestado à regional
ARACI/BA.

**Desenho aprovado pelo usuário (2026-10-11, seção T17.13 de `.claude/plans/2026-10-10-facilidades-de-uso.md`):**
1. Listas, filtros e autosugestão mostram o **apelido no lugar do nome**, com o **nome oficial embaixo, menor**; a busca
   acha pelos dois.
2. **Mesmo CNPJ em mais de um cadastro, se o apelido for diferente** (cada regional vira um cliente, com contratos e
   relatórios próprios).
3. ~~No recibo, linha "Unidade atendida" em modelos `-v2`~~ — **mudado pelo usuário em 2026-10-11:** "Não é para exibir o
   apelido nos recibos. Talvez seja legal apenas no campo de descrição do serviço, logo na primeira linha. Mas tem que ser
   opcional." Escolha dele: **botão no contrato/cobrança** que põe o apelido como 1ª linha da descrição (editável).
   Texto antigo do item: Já existe
   recibo emitido em produção: os `-v1` ficam intactos; a linha entra em modelos novos **`webiss-v2` / `danfse-v2`**.

**Execução:** inline, TDD em cada tarefa (testes PHP no SQLite; nada escreve no banco de dev). O usuário delegou
("você pode seguir conforme acha melhor", 2026-10-11); a migração no banco de dev é **PORTÃO-BANCO** (pede o "sim").

## Decisões tomadas na execução (o desenho não dizia)
- `clientes.apelido` texto de até **60** caracteres, **`''` por padrão (não nulo)**: o índice único
  `(user_id, documento, apelido)` funciona igual no PostgreSQL e no SQLite (com nulo, dois cadastros sem apelido
  passariam). Substitui o único `(user_id, documento)`.
- Um nome só para exibir: `Cliente::exibicao()` = apelido, ou o nome se não houver. Listas que ordenam por nome passam a
  ordenar pela exibição (`coalesce(nullif(apelido, ''), nome)`, SQL padrão).
- O "nome oficial embaixo" aparece onde há espaço de lista (Clientes, Contratos, Cobranças, Painel, autosugestão). Nos
  **relatórios** (tela e CSV) e no reajuste sai só a exibição (uma coluna "Cliente").
- ~~Os recibos novos usam `-v2`~~ (desfeito: o apelido não vai para o recibo nem para o snapshot).
- Documento repetido com o mesmo apelido: mensagem que oferece a saída — "Este CPF/CNPJ já está cadastrado. Para outra
  unidade do mesmo cliente, preencha um apelido diferente."

## Tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T21.1 | Servidor do cadastro: migração, `Cliente` (exibição, busca pelo apelido, ordem), `ClienteRequest` (unicidade com apelido), lista e autosugestão | testada: `ClientesTest` (mesmo CNPJ com outro apelido; lista e busca pelo apelido, ordem pela exibição), RED → GREEN |
| T21.2 | Telas do cadastro: formulário, cadastro rápido, lista de clientes e opção da autosugestão com o nome oficial embaixo | concluída (só telas; build ok); conferência visual na T21.5 |
| T21.3 | Apelido nas outras telas: cobranças, painel, previstas, contratos, filtros de cliente, reajuste, relatórios | testada: `ApelidoNasTelasTest` (painel, cobranças, previstas, contratos, filtros, edição, relatórios) RED → GREEN |
| T21.4 | ~~Recibo -v2 com "Unidade atendida"~~ **desfeito** (decisão do usuário). No lugar: botão "Incluir o apelido na descrição" no contrato e na cobrança; o snapshot não guarda o apelido | testada: `RenderizadorDeReciboTest::test_apelido_do_cliente_nao_vai_para_o_recibo` RED → GREEN; `apelido.test.js` (3) RED → GREEN; PHP 394/394, JS 50/50 |
| T21.5 | PORTÃO-BANCO (migração no dev, com "sim"), conferência visual (pede autorização) e validação do usuário | pendente |

## Registro de execução
- **T21.1:** `Cliente::exibicao()`, `nomeOficial()` e `paraBusca()` (formato único da autosugestão, do cadastro rápido e
  dos filtros `clienteFiltro`/`clienteAtual`); escopo `ordemDeExibicao`; `comTermo` busca também no apelido.
  `ClienteRequest` grava o apelido aparado (`''` sem apelido) e a unicidade do documento leva o apelido junto. Dois testes
  antigos mudaram de propósito: a mensagem de documento repetido e o formato da autosugestão (`nome_oficial`).
  *Pegadinha repetida:* `sed` apagando `use App\Support\Documento;` falhou calado (pegadinha 1 da Fase 20); feito no editor.
- **T21.3:** `cliente` = exibição e `cliente_oficial` = nome oficial (só com apelido) em `Cobranca::paraLista`,
  `PrevisaoDeCobrancas` e contratos. **Pegadinha:** as consultas que carregam só `cliente:id,nome` (painel, lista de
  cobranças, contratos, previsão, reajuste, contas a receber) precisaram de `apelido` na lista de colunas; senão a
  exibição cai no nome sem erro. O teste cobre cada uma.
- **T21.4:** `ModeloRecibo::WebissV2`/`DanfseV2`, `FamiliaModelo::versaoAtual()` aponta para eles; snapshot do tomador
  guarda o apelido (`Cliente::CAMPOS`); `RenderizadorDeRecibo::parte()` devolve `apelido` sem traço (vazio = sem linha).
  **Ruling (cabe na página):** no WebISS, uma linha a mais (rótulo + valor, ~17pt) e depois uma linha compacta (~10pt)
  levaram a amostra de 25 linhas para 2 páginas (transbordava o "Valor por extenso" e o carimbo). Solução sem altura:
  com apelido, a linha do nome vira duas células — nome oficial (62%, 8pt) e "Unidade atendida" (6pt negrito, cabe 60
  caracteres). No DANFSe, a linha própria (`c4`) coube. Conferido com pypdf: nome e apelido numa linha só, 1 página nas
  9 combinações. Custo se errado: nome oficial com mais de ~78 caracteres **e** apelido **e** descrição no limite pode
  quebrar o nome e ir para 2 páginas — **[A VERIFICAR]** se aparecer nome assim.
- **T21.4 refeita (2026-10-11, decisão do usuário):** apelido **fora do recibo**. Removidos `webiss-v2`/`danfse-v2`,
  os casos `V2` do enum e a chave `apelido` do renderizador; `versaoAtual()` voltou ao `-v1`; `Cliente::CAMPOS` (snapshot)
  sem o apelido — o formulário recebe o apelido à parte. No lugar: `DescricaoComplementar.vue` (contrato e cobrança) com o
  botão "Incluir o apelido na descrição (…)" que põe o apelido como primeira linha (`resources/js/apelido.js`: só com
  cliente de apelido escolhido; some quando a 1ª linha já é o apelido). `CampoCliente` emite `escolhido`. Como vira texto
  da descrição, conta nas 25 linhas pela validação de sempre. O ruling de layout do `-v2` deixou de valer.
- **Nome do arquivo do PDF com o apelido (pedido do usuário, 2026-10-11):** "o apelido pode ser usado pro nome do arquivo
  pdf com o recibo gerado". `Recibo::nomeArquivo(?string $apelido)` usa o apelido **atual** do cliente (não o snapshot: o
  nome do arquivo não é conteúdo do recibo) e cai no nome oficial do snapshot sem apelido. Passado por `Cobranca::paraLista`
  (cliente já carregado com `apelido`) e pelo `ReciboPdfController` (`with('cobranca.cliente:id,apelido')`; o renderizador
  não lê o cliente). Teste `ApelidoNasTelasTest::test_nome_do_arquivo_do_pdf_usa_o_apelido` RED → GREEN; PHP 399/399.
