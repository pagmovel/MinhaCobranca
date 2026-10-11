# Fase 17 — Facilidades de uso (módulo Cobrança e base comum)

**Pedido do usuário (2026-10-10):** "Isto tem que ser o foco principal. Não é legal quando estamos numa tela de
lançamentos e precisamos sair perdendo tudo que já fizemos porque faltou cadastrar outra coisa em outra tela." Também:
consulta ao catálogo (modal ou autosugestão), valor negociado visível e editável trazendo o preço do catálogo, incluir
vários itens, "Vencendo hoje" no filtro de cobranças e animações em transições, inclusões, inputs e selects.
Direção aprovada pelo usuário na mesma conversa.

Branch: `feat/modulo-cobranca-catalogo` (continua). Sem mudança no banco (nenhuma migração).

## Regras de design (valem para telas novas e antigas)
1. **Quem está lançando nunca sai da tela para cadastrar o que falta.** Cliente, produto/serviço e chave PIX ganham
   cadastro rápido em modal no próprio formulário; o registro novo já volta escolhido. O formulário de fundo não perde
   nada.
2. **Lista aparece ao focar.** Campos de autosugestão mostram os primeiros registros sem precisar digitar; digitar
   filtra.
3. **Valor negociado.** Preço do catálogo é sugestão: ao incluir o item, o foco vai para o preço da linha.
4. **Filtros aplicam ao escolher** (select/mês), sem clique extra.
5. **Animações** só com CSS e `<Transition>` do Vue (sem biblioteca nova), curtas (150–250 ms), e desligadas com
   `prefers-reduced-motion`.
6. Mesmo componente em todas as telas parecidas (contrato e cobrança usam os mesmos).

## Tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T17.1 | Filtro de cobranças: Vencendo hoje, Vencidas (ignoram o mês), A vencer; aplica ao escolher | testada (suíte 255/255; navegador ok) — commit `4e7fb2d` |
| T17.2 | Base: `enviarJson()` (CSRF, 422 → erros), `Modal.vue` acessível (foco preso, Esc, volta o foco), animações base em `app.css` | testada (JS 16/16; build ok) |
| T17.3 | Itens: catálogo ao focar, "Ver catálogo" (modal com busca), "Cadastrar «texto»" (modal → `POST /produtos-servicos` JSON), foco no preço, "Incluir outro" | testada (suíte 264/264; navegador: lista ao focar, indicador girando, catálogo em modal, foco no preço, erro 422 no modal) |
| T17.4 | Cliente sem sair da tela: "Cadastrar cliente «texto»" no contrato e na cobrança (modal com CNPJ/CEP → `POST /clientes` JSON) | testada (suíte; navegador: modal abre com o texto digitado, Esc fecha sem perder o formulário) — gravação real não exercitada no smoke (sem autorização para criar dados) |
| T17.5 | Chave PIX sem sair da tela: "Nova chave PIX" na forma de pagamento (modal → `POST /perfil/chaves-pix` JSON) | testada (suíte; navegador: modal na forma de pagamento, erro de chave inválida, 390 px) |
| T17.6 | Animações padronizadas: linhas de itens e chaves PIX, listas de sugestão, avisos, troca de página, total | testada (navegador: 6 telas animadas sem erro no console; filtro preserva estado; build ok) |
| T17.7 | Smoke no navegador (1280 e 390 px), revisão, merge e push | testada (revisão opus: 0 Critical, 2 Important + 1 Minor de segurança corrigidos; suíte 264/264; JS 16/16; navegador ok) — merge na main |
| T17.8 | Descrição opcional; recibo lista os itens (`Nome: q unidade × R$ p = R$ t`) e a descrição complementar abaixo; limite de 25 linhas somando os dois (pedido do usuário, aprovado 2026-10-10) | testada (suíte 271/271; navegador ok) |
| T17.9 | Encerrar contrato (botão visível) e "Recomeçar a partir desta data", com prévia e pro-rata — desenho aprovado abaixo | concluída; testada na suíte (293/293, JS 16/16, build ok) com as correções da revisão e o encerramento agendado; migração aplicada no dev (autorizada); smoke pendente (pede autorização: grava dados do SMOKE) |
| T17.10 | Corrigir início errado de contrato sem cobrança paga + perguntar na hora se gera os períodos retroativos (também ao criar contrato com início no passado) | concluída; testada na suíte (303/303, JS 16/16, build ok; `tests/Feature/CorrecaoDoInicioTest.php`); migração aplicada no dev com autorização (contratos 6 linhas, migrations 14→15); **validada** pelo usuário em 2026-10-10 ("aqui funcionou"): contrato nº 6 corrigido para 17/01/2026, 10 cobranças geradas (jan–out, retroativas escolhidas), a antiga cancelada desvinculada |
| T17.11 | Renovar ou prorrogar contrato perto do fim, com os mesmos dados e permitindo alterações antes de concluir | pendente (desenho a fazer com o usuário) |

### Decisões de 2026-10-10, após a revisão da T17.9 (respostas do usuário)
- **Relato:** contrato nº 6 (usuário 1) criado com início 10/10/2026 em vez de 17/01/2026; a única cobrança está
  cancelada, mas a trava e o recomeço (só aceita início posterior) não deixavam corrigir.
- **Corrigir direto:** sem cobrança **paga**, o início/periodicidade/modalidade podem mudar; a prévia mostra as pendentes
  que serão canceladas (número do recibo não volta) e as canceladas são desvinculadas do contrato (`contrato_id` nulo)
  para não bloquear períodos novos no índice único. Com cobrança paga: só recomeçar (início posterior).
- **Retroativos — perguntar na hora:** quando o início fica no passado, a tela pergunta se gera os períodos anteriores ao
  atual; a escolha é guardada no contrato (coluna nova `cobrar_a_partir_de`, data, nula = desde o início) e respeitada
  pelo gerador. Vale ao corrigir e ao criar contrato.
- **Vencida de período que começa depois do fim → cancelada** ("vencida continua" só para períodos até o fim).
- ~~Data de fim ao encerrar: até 1 ano à frente~~ — o usuário reconsiderou ("não sei se é uma boa ideia limitar").
  **Decisão final: fim no futuro = encerramento agendado, sem limite.** O contrato continua ativo com `data_fim`; as
  cobranças saem no ritmo de sempre (sem recibos adiantados); o último período sai proporcional ou não sai, conforme a
  escolha guardada na coluna nova `contratos.ultimo_periodo` (`inteiro` padrão — contratos antigos e o campo "Fim do
  contrato" do formulário não mudam —, `proporcional`, `nao_cobrar`), respeitada pelo `GeradorDeCobrancas::criar`.
  Já emitidas são acertadas na hora (depois do fim: canceladas; em curso: proporcional/cancelada). Fim até hoje encerra
  na hora, como antes. Mesma migração da T17.10 (PORTÃO-BANCO).
- **Revisão — demais correções:** recibo proporcional sai **sem a linha digitável** do boleto antigo (o valor mudou; a
  prévia avisa); aba antiga não reativa contrato encerrado (reativar só com o campo explícito `reativar`); botão de
  confirmar travado enquanto a prévia se atualiza; escolha do período em curso só quando muda algo; sem prévia com
  início vazio. Adiado (menor): linha "Proporcional" pode passar das 25 linhas do recibo.
- **Encerramento agendado não é reagendado** (`ultimo_periodo` ≠ `inteiro` → erro): a cobrança em curso já ficou
  proporcional e reagendar reduziria a quantidade já reduzida. Mudar a data entra na prorrogação (T17.11).
- Pendência para a T17.11: contrato com fim já passado continua `ativo` na lista e no filtro (já acontecia com o campo
  "Fim do contrato"); tratar junto com renovar/prorrogar.

Estados: `pendente` → `concluída` (código pronto) → `testada` (suíte/smoke) → `validada` (usuário confirmou).

## Contratos entre tarefas
- `enviarJson(url, dados, metodo = 'POST'): Promise<{dados}|{erros}|{mensagem}>` (nunca lança) — em `resources/js/http.js`.
- Endpoints existentes respondem JSON quando o pedido aceita JSON (`$request->expectsJson()`): 201 com o registro no
  formato da autosugestão; validação continua no mesmo FormRequest (422 automático).
- `BuscaAutosugestao.vue` ganha: busca com termo vazio ao focar (servidor devolve os primeiros 10 quando `q` vazio,
  só para `GET` com `todos=1`), slot/emit `cadastrar(texto)` mostrado quando não há resultado ou sempre no fim.

## Revisão final (2026-10-10, subagente opus)
- Corrigidos: (I1) lista da autosugestão reabria depois do blur — `saiu()` cancela a busca e a resposta só abre a
  lista se o campo ainda tem foco; (I2) Esc/Tab perdidos no catálogo depois de "Incluir" — listener do Modal no
  `document` + foco vai para o próximo "Incluir"; (M8→Important) trava do banco recusa `DB_URL` preenchida
  (`TravaDoBancoDeTesteTest` RED→GREEN); (M3) `enviarJson` trata 200 sem JSON como falha (`http.test.js` RED→GREEN);
  (M1) 1 letra mostra só "Cadastrar"; (M2) clique não duplica a busca; (M4) chave PIX nova recarrega `chavesPix`;
  (M5) `temClientes` removido; (M6) linha que sai de `ul` fica `absolute`; (M7) teste com cabeçalhos reais do Inertia.
- Não julgados (decisão): durações das animações (regra 5), `color-mix()` (navegadores suportados não definidos),
  vários `Modal` por linha (só renderizam abertos), heurística CPF/CNPJ do cadastro rápido de cliente.

## T17.9 — Encerrar e recomeçar contrato (desenho aprovado pelo usuário em 2026-10-10)
**Problema relatado:** ao mudar a data de início de contrato com cobranças aparece "Este contrato já tem cobranças.
Para mudar o início ou a periodicidade, encerre-o e crie outro." e não há nada na tela para encerrar (o encerramento
hoje é a caixa "Contrato ativo (desmarque para encerrar)" no rodapé) nem para fazer a mudança.

**Decisões do usuário:**
1. Botão **"Encerrar contrato"** visível (`botao-perigo`, confirmação no 2º clique), com campo **data de fim**
   (padrão hoje, ≥ início do contrato) e **prévia** do que acontece antes de confirmar.
2. Na trava de início/periodicidade: aviso ao lado do campo + botão **"Encerrar este contrato e criar um novo a partir
   de DD/MM/AAAA"** (recomeçar): encerra o atual com fim = véspera do novo início e cria outro copiando cliente, itens
   (preços combinados), descrição, modalidade, dia de vencimento e forma de pagamento; muda só início/periodicidade;
   leva ao contrato novo. Em contrato com cobranças, o campo de início avisa antes ("mudar cria um contrato novo").
   Novo início anterior/igual ao atual: recusar com explicação (sobreporia períodos já cobrados).
3. **Cobranças pendentes do contrato ao encerrar** (pagas e canceladas não mudam):
   - vencida ou vencendo hoje → **continua em aberto** (é dívida; "só interessam as vencidas");
   - período inteiro **até** a data de fim, ainda a vencer (ex.: pós-pago) → continua (serviço prestado);
   - período que começa **depois** da data de fim → **cancelada**;
   - período que **contém** a data de fim → o usuário escolhe **pro-rata** (padrão) ou **cancelar**.
   - períodos até a data de fim **ainda não emitidos** (pós-pago emite perto do vencimento) → emitidos agora
     (inteiros; o último proporcional ou não emitido, conforme a escolha).
4. **Pro-rata:** dias usados = início do período até a data de fim (inclusive) ÷ dias do período (inclusive).
   Quantidade de cada item × usados ÷ dias, 3 casas, meio-para-cima: `intdiv(2·q·usados + dias, 2·dias)` (mín. 1);
   total pela conta de sempre (`ItensDeVenda::total`); cobrança antiga sem itens: valor × usados ÷ dias.
   Descrição ganha a linha "Proporcional: 18 de 31 dias (01/10/2026 a 18/10/2026)"; `periodo_fim` = data de fim.
   Exemplo aprovado: 1 mês × R$ 247,57, 18/31 → quantidade 0,581 → R$ 143,84.
   Não enviada → ajusta e refaz o recibo (mesmo número). Já enviada → cancela a original e emite avulsa nova
   (`contrato_id` nulo: o índice único `(contrato_id, periodo_inicio)` bloquearia a cópia).
5. Fora do escopo: devolução de período já pago (pré-pago) quando o contrato termina antes.

**Rascunho técnico (a confirmar no código):** serviço `App\Services\EncerradorDeContratos` com `plano()` (lista de
ações manter/cancelar/ajustar/reemitir/emitir — a mesma usada na prévia e na execução) e `encerrar()`; helper
`App\Support\ProRata`; extrair do `GeradorDeCobrancas` a criação de cobrança de um período para reaproveitar;
rotas `GET /contratos/{contrato}/encerramento?fim=` (prévia JSON), `POST /contratos/{contrato}/encerrar`
(`fim`, `em_curso`=proporcional|cancelar), `POST /contratos/{contrato}/recomecar` (`data_inicio`, `periodicidade`,
`em_curso`); contrato encerrado: `ativo=false`, `data_fim=fim`. Calculadora: `devidos()` com `hoje` distante e
`dataFim` lista todos os períodos até o fim. Sem migração. TDD; exemplo de teste: hoje 15/10/2026, contrato mensal
pré-pago desde 01/08 — set/vencida mantém; out (01–31/10, vence 20/10) com fim 18/10 → 143,84; nov cancelada.
