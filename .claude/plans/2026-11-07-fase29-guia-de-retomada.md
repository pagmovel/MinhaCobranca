# Fase 29: guia de retomada e de despacho (handoff de contexto, 2026-11-07)

Para quem retoma depois de compactar a conversa ou numa sessão nova. Ler junto com a seção "Andamento" do plano
`.claude/plans/2026-11-06-temas-visuais.md` e a seção 0.00000 do roteiro `.claude/plans/2026-10-10-roteiro-modulos.md`.
O briefing comum que os agentes recebem está em `.claude/plans/2026-11-07-fase29-briefing-dos-agentes.md` (cópia do
arquivo da pasta temporária; **use esta cópia** nos despachos novos).

## 1. Situação em 2026-11-07, ~12:45

- Branch `feature/temas-visuais`, **20 commits à frente do `main`**, nada enviado ao `origin`, nada em produção.
- **Há um subagente rodando:** `a4f6fb1c95cf12464` (modelo `sonnet`), tarefas **2.4** (seletor de tema, com troca **sem
  recarregar** e fontes carregadas dinamicamente) e **4.5** (popup para usuários antigos). A 2.3 dele já está commitada
  (`48151fc`). Quando terminar, ele avisa e entrega o relatório em
  `/tmp/claude-1000/-srv-conteiners-MinhaCobranca/84c5a1aa-b391-4976-961a-ebfec88938e7/scratchpad/relatorio-f29-s5b.md`.
- **Trabalho dele ainda não commitado na árvore** (não é lixo; não apague nem faça `git checkout`/`git stash`): modificados
  `TemaVisual.php`, `PerfilController.php`, `routes/web.php`, `MenuLateral.vue`, `MenuSuperior.vue`,
  `PainelDoUsuario.vue`, `contraste.js`, `usarTema.js`, `TemaDoUsuarioTest.php`, `contraste.test.js`, `usarTema.test.js`;
  novos `MiniaturaDoTema.vue`, `SeletorDeTema.vue`, `botaoDoUsuario.js`, `fontesDoTema.js`,
  `RotaDeFontesDoTemaTest.php`, `fontesDoTema.test.js`. Os dois protótipos `docs/modelos-painel.html` e
  `docs/painel-lista.html` também aparecem como não rastreados: **não são desse agente** (protótipos não escolhidos; o
  Marcos ainda não disse se apaga).
- O Chromium da porta 9333 que está no ar é desse agente. **Um agente por vez usa o browser-agent.**
- **Se o agente morreu ou a sessão reiniciou sem ele terminar:** `git status`; rode `php artisan test --compact` e
  `node --test 'tests/js/**/*.test.js' 'resources/js/**/*.test.js'`; leia o relatório se existir; use `ListAgents` e
  `SendMessage` para retomá-lo, ou redespache só o que falta (2.4 e/ou 4.5) dizendo no prompt **quais arquivos já
  existem**. Encerre o Chromium dele só pelo PID (`pgrep -af 'remote-debugging-port=9333'`), nunca `pkill` genérico.

## 2. O que ainda falta e como despachar (um despacho por vez no navegador; tarefas dependem das anteriores)

Ordem: terminar 2.4 e 4.5; depois **A**, **B**, **C**, **D**; depois merge. Cada despacho segue o checklist da seção 3.

**A. Tarefas 7.2 e 7.3: linha do tempo e tema Régua** (um agente, um commit por tarefa).
- `LinhaDoTempoDoMes.vue`: usa `GET /painel/mes` (resposta: `mes, hoje, dias, feriados{dia:nome}, fim_de_semana[], pontos[]
  {id, cliente, valor_centavos, vencimento_dia, etapa('enviar'|'aguardando'|'recebida'), situacao, pago_dia,
  pago_centavos}, vencidas{quantidade,soma_centavos,itens[]}, hoje_resumo{vence_hoje{quantidade,soma_centavos},
  para_avisar, para_enviar}, anteriores{quantidade,soma_centavos}`) e as funções puras de `resources/js/linhaDoTempo.js`
  (`diaDoPonto`, `empilharPontos`, `acumuladoPorDia`, `posicaoDoDia`). O clique num ponto busca
  `GET /painel/cobrancas/{id}` (a mesma linha das listas) e mostra um popover com a ação principal usando os
  componentes de ação que já existem (`BotaoEnviar`, `AvisarWhatsapp`, `BaixaInline`...). Referência visual e de
  comportamento: `docs/painel-linha-do-tempo.html` (eixo de dias, fim de semana sombreado, feriado hachurado,
  "Hoje", pílulas na cor da etapa com valor legível, vencida com "!" e pulso limitado, régua de dinheiro com o
  previsto pontilhado, marcador "Anteriores", rolagem **dentro do bloco** em tela estreita já mostrando "hoje").
  Pontos são botões na ordem de tabulação, setas movem, Esc fecha o popover; hover e foco ligam ponto e linha da lista
  nos dois sentidos.
- `PainelRegua.vue` (composição `regua`): declare as **11 props** de `PainelEmpilhado`, registre em
  `Pages/Painel.vue` no mapa `composicoes`, reutilize as listas reais ("Para enviar", "Aguardando pagamento") e
  "Recebidas"; cabeçalho "Novembro de 2026" e números do mês no estilo do protótipo. Abertura: a linha se desenha e os
  pontos caem; na baixa o ponto salta até o dia do pagamento. Tudo desligado em `prefers-reduced-motion`.
  **A página não rola sozinha** ao dar baixa. `font-stretch` não existe nas fontes do Bunny: não usar.
- Já pronto para esta tarefa: enum `Regua`, `resources/css/temas/regua.css` (tokens), fontes (Bricolage Grotesque +
  Figtree), casca superior com "Mais ▾".

**B. Tarefas 6.1 e 8.1: Almanaque** (um agente).
- `PainelMosaico.vue` na variante com linha do tempo (`comLinhaDoTempo`): fileira de cima com blocos **Dinheiro**
  (anel SVG com `fatiasDoMes`), **Vencidas** e **Hoje**; a `LinhaDoTempoDoMes` em largura total; e a lista com abas
  deslizantes (Enviar, Aguardando, Recebidas, com contagem). Para isso `ListaDoPainel.vue` passa a **emitir `resumo`**
  (`{ lista, quantidade, soma_centavos }`); as três listas ficam montadas e só a aba ativa aparece. Dados dos blocos vêm
  de `/painel/mes` (`vencidas`, `hoje_resumo`). **Fora** desta execução: os blocos "Semana à frente" e "Recebido por dia"
  (são do Mosaico). Referência: `docs/painel-bento-timeline.html`.
- Tema Almanaque (8.1): `resources/css/temas/almanaque.css` já tem os tokens; falta o que a 3.4 deixou: ícones e itens
  em pílula no menu lateral, contador dourado, e os efeitos (anel preenche, carimbo PAGO com pulso do anel, confete
  discreto ao esvaziar uma etapa). Registre `mosaico` no mapa `composicoes`.

**C. Tarefa 9.1: conferência geral.** Matriz **3 temas (Talão, Régua, Almanaque) × telas logadas × 1440 e 390 px** com o
browser-agent: sem erro de console, sem rolagem horizontal, sem texto ilegível, modais (`Modal`, `ModalBoasVindas`,
popup, `CoachDeConfiguracao`) acima do painel lateral. **Repetir a prova do foco de revisão 1**: digitar num
formulário, trocar Talão, Almanaque, Régua, Talão pelo seletor, valores e `<main>` permanecem e a página não recarrega.
Suítes completas PHP, JS e `npm run build`. Rodar também uma revisão (`code-review` ou subagente revisor com resposta
limitada a ~15 linhas) do branch inteiro antes do merge.

**D. Tarefa 9.2: documentação final.** Skill `recibos`, seção "Fase 29: temas visuais" (acertos, pegadinhas, `TEMA_PADRAO`,
como criar um tema novo: enum + `temas/<id>.css` + fontes no `vite.config.js` e em `TemaVisual::fontes()` + build),
README (variável de ambiente e inventário), `.claude/MEMORY.md`, e estas notas do roteiro.

**Fim:** `git switch main && git merge --no-ff feature/temas-visuais && git push origin main` (permissão permanente do
Marcos: memória `commit-merge-push-sem-perguntar`), só com testes verdes. **Deploy, migração em produção e `TEMA_PADRAO`
no `.env` de produção são do Marcos**; nenhum acesso à VPS.

## 3. Checklist de todo despacho de subagente (regras globais do Marcos; esquecer é falha a corrigir na hora)

1. Abrir o prompt mandando invocar `Skill` com `skill: "ponytail:ponytail"` e `args: "lite"` (e `frontend-design:frontend-design`
   nos agentes de interface).
2. Conter **este parágrafo, em português, inteiro**:
   > Economize tokens com o plugin context-mode: comandos cuja saída você vai analisar (testes, git log/diff, listagens, logs, medições, buscas) → `ctx_batch_execute` ou `ctx_execute`, imprimindo só o resultado derivado; ler arquivo só para analisar ou resumir → `ctx_execute_file` (`Read` só se for editar o arquivo); `Bash` direto só para mutar estado (git add/commit, mkdir, mv) ou saída curta e fixa; escrever arquivos sempre com `Write`/`Edit` nativos (o `ctx_execute` descarta o sistema de arquivos do sandbox). Se as ferramentas `ctx_*` aparecerem como deferred, carregue-as com `ToolSearch` (`select:` + nome) antes de usar. Seu relatório completo vai para o arquivo indicado; a resposta final de volta deve ter no máximo ~15 linhas (~1 mil tokens).
3. Informar o modelo **de forma explícita: `sonnet`** (nunca Opus nem Fable).
4. Proibir a VPS (SSH, painel, deploy) e qualquer servidor remoto: "só o Marcos autoriza, e não há autorização para esta
   tarefa". Proibir `pkill`/`killall` genérico e gravação em banco (testes em SQLite em memória; tela em SQLite
   temporário; **a migração no Postgres local é rodada por quem coordena**, com a conferência do alvo).
5. Mandar ler o briefing (`.claude/plans/2026-11-07-fase29-briefing-dos-agentes.md`) e os relatórios das tarefas
   anteriores, e indicar o arquivo do relatório e o limite de ~15 linhas da resposta.
6. Mensagens de retomada (`SendMessage`) a um agente também levam o parágrafo; revisores também.
7. Um commit por tarefa, só com os arquivos dela (`git add <caminhos>`, nunca `-A`), identidade
   `marcos.ronaldo@gmail.com`, rodapé `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`; documentação (skill e Estado
   no plano) no mesmo commit; sem push e sem merge dentro do agente.
8. Quem coordena **confere por conta própria** (não só o relatório): `git log`, `git status`, suítes PHP e JS, e abre
   as capturas dos agentes de interface.

## 4. Decisões já tomadas nesta execução (não reabrir sem o Marcos)

- Nomes: Talão, Fluxo, Livro-caixa, Mosaico, Régua, Almanaque. "Sair" saiu do menu e fica só no painel do usuário.
- A troca de senha mantém as outras sessões abertas e é auditada sem gravar a senha (`usuario.senha_alterada`).
- Usuário novo nasce com o tema de `TEMA_PADRAO` (`config/temas.php`; valor inválido cai no Talão) e não vê o popup;
  usuário antigo vê o popup uma vez, e só quando há mais de um tema e a apresentação inicial não está aberta.
- **Revogada:** "trocar de tema recarrega a página". A troca é **sem recarregar**; as fontes do tema novo entram no
  `<head>` dinamicamente (tarefa 2.4).
- `users.tema` não usa cast de enum do Eloquent (daria 500 com valor corrompido): `Attribute` com `tryFrom() ?? Talao`.
- `throttle:6,1,senha` (contador próprio) na troca de senha. `dontFlash` de `senha` em `bootstrap/app.php`.
- Linha do tempo só do mês corrente; vencidas de meses anteriores entram em "Vencidas" e no marcador "Anteriores".

## 5. Achados abertos (não corrigidos, para o Marcos decidir)

- `desfazer-baixa` e `corrigir-pagamento` dão erro 500 com `senha[]=x` (`CobrancaController::confirmacao()`, falta um `bail`).
- 11 textos `text-grafite/60` (4,05:1) e 1 `/50` (3,06:1) do Talão abaixo de 4,5:1.
- Fontes estáticas do Bunny sem eixo de largura: títulos da Bricolage mais largos que nos protótipos.
- O Tailwind v4 lê também os `.md` de `.claude/` e gera estilo solto de exemplos de classe no texto (inofensivo).
- A suíte JS mostrou um teste instável uma vez, durante edição de agente; reconferir ao fim.

## 6. Onde estão as evidências (pasta temporária, pode sumir; o essencial já está na skill e no plano)

`/tmp/claude-1000/-srv-conteiners-MinhaCobranca/84c5a1aa-b391-4976-961a-ebfec88938e7/scratchpad/`: relatórios
`relatorio-f29-s1.md` (1.1 a 1.3 e 2.1), `s2a` (3.1 e módulos puros), `s2b` (3.2), `s3` (5.1, 5.2, 3.3), `s4a` (base dos
temas), `s4b` (4.1 e `usarTema`), `s5a` (3.4 e 2.2), `s5b` (2.3, 2.4, 4.5, em andamento); capturas em `f29-3-2/` e
`f29-5-1/telas/`.
