# Fase 29: guia de retomada e de despacho (handoff de contexto, 2026-11-07)

Para quem retoma depois de compactar a conversa ou numa sessão nova. Ler junto com a seção "Andamento" do plano
`.claude/plans/2026-11-06-temas-visuais.md` e a seção 0.00000 do roteiro `.claude/plans/2026-10-10-roteiro-modulos.md`.
O briefing comum que os agentes recebem está em `.claude/plans/2026-11-07-fase29-briefing-dos-agentes.md` (cópia do
arquivo da pasta temporária; **use esta cópia** nos despachos novos).

## 1. Situação (atualizada em 2026-11-07, 13:20)

> **Atualização:** o subagente `a4f6fb1c95cf12464` **terminou**: 2.3, 2.4 e 4.5 estão commitadas (`48151fc`, `ad4f0d0`,
> `25502c3`) e conferidas por quem coordena. **Não há nenhum subagente em andamento e a árvore está limpa** (só os dois
> protótipos `docs/` não escolhidos). O branch tem **24 commits** à frente do `main` (o último, `803acd9`, corrige o
> `TrimStrings` da troca de senha). **PHP 962 e JS 375 verdes.** Faltam só os despachos A, B, C e D da seção 2. Os
> pontos abaixo sobre o agente e os arquivos não commitados valem como **histórico**. Um servidor do Vite na porta 5173
> (`public/hot`, PIDs `vite`) estava no ar e **não é de nenhum agente despachado**: pode ser o `npm run dev` do Marcos;
> não encerrar. Ele faz o app usar o servidor de desenvolvimento em vez do `npm run build`; os agentes de interface devem
> provar numa cópia da árvore ou avisar.

> **Atualização 2 (pedidos novos do Marcos, 2026-11-07, depois da atualização acima):**
> 1. **"Alterar senha" vira popup**, não formulário dentro do painel lateral: no painel do usuário fica só uma
>    linha-botão; o formulário abre em `ModalAlterarSenha.vue` (usa `Modal.vue` + `FormularioDeSenha.vue`) por cima do
>    painel. Esc fecha só o popup; clicar fora não fecha; o foco volta à linha-botão; campos limpos ao fechar; sucesso
>    "Senha alterada." no popup e confirmação no painel; erros e 429 aparecem no popup. Isso revisa a 2.3.
> 2. **O popup fica no centro da tela**, como o aviso das novidades (`ModalNovidadeTemas`). `Modal.vue` alinhava no topo
>    (`items-start`, `py-10`): ganha a prop `centralizado` (Boolean, padrão `false`; as janelas antigas não mudam de
>    posição). Centraliza quando cabe; em tela baixa começa do topo e rola (contêiner com rolagem + painel `m-auto`, não
>    `items-center` puro). Usada em `ModalAlterarSenha` e `ModalNovidadeTemas`.
> 3. **FEITO e conferido por quem coordena:** commit `3adae1d` (subagente `a7137c3b4baaeff3b`, `sonnet`). Novos:
>    `ModalAlterarSenha.vue`, `pilhaDeDialogos.js` (Esc e trava de rolagem por dono; painel em `z-40`, todo `Modal`
>    acima). **PHP 962 e JS 381 verdes, build ok**; capturas dos 3 temas em 1440x900 e 390x844 mostram o popup no centro.
>    Relatório em `/tmp/claude-1000/-srv-conteiners-MinhaCobranca/84c5a1aa-b391-4976-961a-ebfec88938e7/scratchpad/relatorio-f29-s6.md`.
>    **Despacho A FEITO e conferido por quem coordena** (tarefas 7.2 e 7.3, subagente `ad8bf2a466dbcca90`, `sonnet`):
>    commits `97071a0` (linha do tempo com popover) e `58929b9` (composição Régua). PHP 962 e JS 393 verdes, build ok,
>    capturas da Régua em 1440 e 390 conferidas. Relatório em `.../scratchpad/relatorio-f29-s7.md`. **Nenhum subagente em
>    andamento (até o despacho B começar).** **Despacho B FEITO e conferido por quem coordena** (6.1 e 8.1, Almanaque,
>    subagente `a65e2bcfa40c91e75`, `sonnet`): commits `9817e58` (Mosaico: blocos Dinheiro/Vencidas/Hoje, anel, abas) e
>    `9e3f7dd` (menu em pílulas, contador dourado, `sincronizarTemaDaPagina`: o `data-tema` agora acompanha login e
>    saída; `/perfil` sem rolagem lateral em 390 px). **PHP 965 e JS 414 verdes, build ok**; capturas do Almanaque em
>    1440 e 390 conferidas. Relatório em `.../scratchpad/relatorio-f29-s8.md`. Ficaram fora do Almanaque: o carimbo
>    grande "PAGO", o voo da cobrança até a aba e o ponto dourado de novidade na aba (motivos na skill). Achado não
>    corrigido: erro de console `useForm() data` em `/perfil` (já existia antes, nos 3 temas). O parágrafo "Regra
>    permanente (Marcos, 2026-11-07)" da skill (deploy migra sozinho) entrou no commit `9e3f7dd`.
>    **Despacho C EM ANDAMENTO** (9.1, conferência geral no navegador, subagente `aa01e378b5a12b8e9`, `sonnet`; relatório
>    em `.../scratchpad/relatorio-f29-s9.md`) e, em paralelo, um **revisor somente leitura** do branch inteiro
>    (`a2337a4f093edd4d4`, `sonnet`; relatório em `.../scratchpad/relatorio-f29-revisao.md`, sem navegador, sem commit).
>    Ao retomar: `git status`, `ListAgents`, ler os dois relatórios; não redespachar enquanto estiverem vivos. Quando
>    ambos terminarem: tratar os achados altos e médios do revisor (despachar correção ou corrigir), depois **D**
>    (documentação final, 9.2), depois o merge.
>    **Revisor TERMINOU** (relatório `relatorio-f29-revisao.md`; alta: nenhuma; média: 2; baixa: 11; autorização,
>    rota de fontes, senha, mass assignment, ouvintes e migração sem defeito). Conferi no código que M1 e M2 procedem.
>    **Despacho C FEITO e conferido por quem coordena** (9.1, subagente `aa01e378b5a12b8e9`): matriz 3 temas × 22
>    telas × 1440 e 390 = 132 células sem reprovação; prova do foco de revisão 1 ok (exceção do Painel confirmada);
>    modais acima do painel lateral ok. Quatro commits: `7d3fc73` (**Feriados.vue: bug que já existe no `main`**,
>    `form.data` sobrescrevia o método `data` do `useForm` e "Cadastrar/Salvar feriado" quebrava com `form.data is not a
>    function`; `[A VERIFICAR]` se a produção atual está quebrada, não acessei), `0dc6b4d` (Esc/Tab do painel do usuário
>    com qualquer Modal por cima), `3a60d18` (`.aparecer` com `backwards`: Coach e Holofote voltam ao canto da janela,
>    também no Talão), `c7f1ea9` (docs). PHP 965 e JS 420 verdes, build ok. Relatório em `.../scratchpad/relatorio-f29-s9.md`.
>    Não coberto pela matriz: listas vazias, erros de validação, usuário não administrador; Safari, Firefox, leitor de tela.
>    **Despacho E FEITO e conferido por quem coordena** (subagente `a0bf56cb61958c5da`): `c353a54` (M1: voltar no
>    histórico não desfaz o tema; reproduzido no navegador antes e provado depois), `0b05ac5` (M2: teto de 6 níveis por
>    dia e pílula "+N"), `62acaf4` (baixas 1, 2, 3, 4, 10, 11), `2ebdff4` (`--color-grafite` escurecida só na Régua e no
>    Almanaque, `text-grafite/60` passa de 4,5:1), `a508686` (docs). **PHP 965 e JS 434 verdes.** Relatório em
>    `.../scratchpad/relatorio-f29-s10.md`. `[A VERIFICAR]` do E: a 390 px o bloco com 6 níveis cheios tem 768 px (se
>    o Marcos achar alto, baixar o teto só no celular); `TemaVisual::amostra` guarda a tinta antiga (só a miniatura do
>    seletor); uma baixa que cai num dia já cheio (6 ou mais pagamentos) joga o ponto novo na pílula, sem salto.
>    **Despacho D EM ANDAMENTO** (9.2, documentação final, só texto; subagente `a32e7ed7c023ca3e4`, `sonnet`; relatório
>    em `.../scratchpad/relatorio-f29-s11.md`; um commit; não mexe neste guia). Ao retomar: `git status`, `ListAgents`,
>    ler o relatório. **Depois do D: merge `--no-ff` no `main` e push** (com PHP, JS e build verdes). Escopo do E, já
>    cumprido (era "só depois que o C terminar"):
>    **M1** voltar no histórico restaura as props antigas (Inertia 3.9.0 dispara `navigate` com `type:'history'`,
>    `usarTema.js` `sincronizarTemaDaPagina` + `app.js:13`) e a tela volta ao tema antigo: provar no navegador e
>    corrigir guardando o último tema confirmado; **M2** linha do tempo sem teto de pontos por dia
>    (`linhaDoTempo.js` `empilharPontos`, `LinhaDoTempoDoMes.vue`): limitar a pilha e mostrar "+N" (as listas abaixo
>    continuam mostrando tudo); e as baixas **1** (`senha.js:26-28` recusa espaço nas pontas e o servidor já não apara),
>    **2** (fontes do Talão não carregam ao sair e entrar como usuário Talão), **3** (`novidadeDispensada` atravessa a
>    troca de usuário), **4** (`PopoverDaCobranca.carregar` sem guarda de versão), **10** (`grade.value` sem checar
>    nulo), **11** (`aria-label` do botão do usuário: "Minha conta, nome"). **Não corrigir, só informar o Marcos:** 5
>    (regra da etapa triplicada), 6 ("Para avisar" conta cobranças e "Vencendo" conta clientes), 7 ("Previsto"
>    difere entre temas), 8 (throttles `30,1` e `6,1` compartilham contador, herdado), 9 (corrida da troca de tema,
>    não reproduzida).
>    Para o Marcos decidir (do relatório do A): (1) "Previsto em novembro" na Régua = recebido + aguardando + a enviar do
>    mês (regime de caixa); no Talão é a soma de quem vence no mês, então os dois números diferem; (2) trocar entre Talão
>    e Régua **na própria tela do Painel** troca a composição inteira: um formulário de baixa aberto numa linha da lista
>    se perde (o `<main>` e as outras telas ficam intactos). **A prova do foco de revisão 1 (9.1) deve usar um formulário
>    de outra tela e registrar essa exceção do Painel**; (3) o popover fecha sozinho depois de enviar ou dar baixa, para o
>    salto do ponto aparecer; (4) ficaram fora da Régua o botão "Nova cobrança" do protótipo e o agrupamento Atrasadas/
>    Hoje/Próximos dias; (5) Safari, Firefox e leitor de tela não testados; a régua animada usa transição CSS do `d` do
>    SVG (Chrome).
>    Pontos em aberto dessa revisão: (a) o aviso "Senha alterada." também aparece no `<main>` por trás do popup (vem do
>    `back()->with('sucesso')`; tirar mexe no PHP e no `AlterarSenhaTest`); (b) se o usuário cancelar com o envio em
>    andamento e a senha for trocada, o painel não mostra a confirmação; (c) Safari, Firefox e leitor de tela não
>    testados; (d) `ModalBoasVindas` aberto junto do painel não foi exercitado; (e) o ícone de "link externo" na linha
>    "Alterar senha" sugere abrir outra página: trocar por um ícone neutro, se o Marcos achar estranho.
> 4. **Pergunta aberta ao Marcos, ainda sem resposta:** apagar `docs/modelos-painel.html` e `docs/painel-lista.html`? E
>    corrigir o 500 com `senha[]=x` e o `trim` do campo `senha` das baixas (seção 5)?

> **Atualização 3 (FASE 29 MESCLADA NO `main` e enviada ao GitHub, `5404af9`; pedidos novos do Marcos depois do merge):**
> 1. **Feito (commits `4acc8c2`, `7ffcc34`, `4d0a731`, PHP 967 e JS 434 verdes):** 500 do `senha[]=x` nas baixas e `trim` do
>    campo `senha` corrigidos (com testes); ícone da linha "Alterar senha" trocado de "link externo" por uma chave;
>    `TEMA_PADRAO=almanaque` no `.env` local (não versionado) e no `.env.example`, e `TEMA_PADRAO=talao` fixo no
>    `phpunit.xml`. **Produção:** a variável no `.env` do painel continua do Marcos (e só vale depois de reiniciar o
>    container).
> 2. **Em andamento:** (a) subagente `ac7b3affc8521592c`: **"o Wizard (Fase 25) quebra com os novos temas?"**, prova no
>    navegador nos 3 temas, mais usuário novo caindo no Almanaque e o ícone novo; relatório em
>    `.../scratchpad/relatorio-wizard-temas.md`; (b) subagente `a8fec0fb411f32a71`, **só leitura**: investigar por que o
>    "Previsto" difere entre o Talão e a Régua/Almanaque (o Marcos respondeu "não sei, precisa ser investigado");
>    relatório em `.../scratchpad/relatorio-previsto.md`. Ao retomar: `git status`, `ListAgents`, ler os relatórios.
>    Depois do (b): decidir com o Marcos (ou aplicar a recomendação, se for inequívoca) e corrigir com testes.

### Histórico (12:45)

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

Ordem: 2.4 e 4.5 já feitas; agora **A**, **B**, **C**, **D**; depois merge. Cada despacho segue o checklist da seção 3.

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
Marcos: memória `commit-merge-push-sem-perguntar`), só com testes verdes. **O deploy e o `TEMA_PADRAO` no `.env` de
produção são do Marcos**; a migração das colunas de `users` roda sozinha na subida do container (`CMD` do `Dockerfile`:
`php artisan migrate --force`, com guarda em `tests/Unit/DockerfileMigraNoDeployTest.php`). Nenhum acesso à VPS.

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

- ~~`desfazer-baixa` e `corrigir-pagamento` dão erro 500 com `senha[]=x`~~ **corrigido em 2026-11-07** (`4acc8c2`, junto do `trim` do campo `senha`).
- 11 textos `text-grafite/60` (4,05:1) e 1 `/50` (3,06:1) do Talão abaixo de 4,5:1.
- Fontes estáticas do Bunny sem eixo de largura: títulos da Bricolage mais largos que nos protótipos.
- O Tailwind v4 lê também os `.md` de `.claude/` e gera estilo solto de exemplos de classe no texto (inofensivo).
- A suíte JS mostrou um teste instável uma vez, durante edição de agente; reconferir ao fim.

## 6. Onde estão as evidências (pasta temporária, pode sumir; o essencial já está na skill e no plano)

`/tmp/claude-1000/-srv-conteiners-MinhaCobranca/84c5a1aa-b391-4976-961a-ebfec88938e7/scratchpad/`: relatórios
`relatorio-f29-s1.md` (1.1 a 1.3 e 2.1), `s2a` (3.1 e módulos puros), `s2b` (3.2), `s3` (5.1, 5.2, 3.3), `s4a` (base dos
temas), `s4b` (4.1 e `usarTema`), `s5a` (3.4 e 2.2), `s5b` (2.3, 2.4, 4.5, em andamento); capturas em `f29-3-2/` e
`f29-5-1/telas/`.
