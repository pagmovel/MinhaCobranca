# Fase 29: temas visuais escolhidos pelo usuário (plano)

> **Para quem executa:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development` (recomendado) ou `superpowers:executing-plans`.
> **Em execução desde 2026-11-07**, por comando do Marcos (escopo na seção 0). Plano escrito em 2026-11-06. **O quadro "Andamento" logo abaixo diz em que fase estamos e o que falta**; o Estado de cada tarefa (nos títulos `### Tarefa N.N`) é a fonte da verdade.

**Objetivo:** o usuário clica no próprio nome, abre um painel lateral à direita e escolhe a aparência do sistema entre 6 temas, altera a senha e sai. A escolha fica gravada na tabela do usuário.

**Arquitetura:** um tema é a soma de três camadas, todas escolhidas por `users.tema`. (1) **Tokens**: variáveis CSS sob `html[data-tema='<id>']` (cores, fontes, cantos, sombras, cores das etapas). (2) **Casca**: menu lateral ou menu superior, no mesmo `CamadaApp.vue`. (3) **Composição do Painel**: 4 componentes (`empilhado`, `colunas`, `mosaico`, `regua`) que reutilizam as listas e as ações que já existem. O tema atual continua sendo o primeiro tema.

**Tecnologia:** Laravel 13, Inertia v3, Vue 3, Tailwind v4 (`@theme`), fontes Bunny via `laravel-vite-plugin/fonts`, PHPUnit, `node --test`.

**Spec:** não há documento à parte; os requisitos estão na seção 1 e os protótipos aprovados estão em `docs/` (seção 2).

---

## Andamento (atualizado em 2026-11-07, depois da conferência geral: tarefa 9.1)

**Onde estamos:** Fase 29 em execução no branch `feature/temas-visuais` (37 commits à frente do `main`; **não mesclado, não enviado**, nada em produção). Das 23 tarefas do escopo, **22 estão prontas e 1 falta** (9.2, a documentação final); nenhum agente de interface em andamento. Os **Marcos 1, 2, 3 e 4** do escopo estão prontos (painel do usuário com senha em popup, seletor de temas, popup da novidade, fundação visual, linha do tempo e os temas **Régua** e **Almanaque**) e a conferência geral foi feita (matriz de 132 células sem reprovação, ver a Tarefa 9.1). PHP 965 e JS 420 verdes.

| Fase | Tarefa | Estado | Commit |
|---|---|---|---|
| 1 dados e rotas | 1.1 enum, `.env`, colunas | pronta | `8696a2e` |
| | 1.2 Inertia e `data-tema` | pronta | `d4fa4ac` |
| | 1.3 rotas de tema e novidade | pronta | `e7ee850` |
| 2 painel do usuário | 2.1 alterar senha (back-end) | pronta | `b7d7414`, `1c948aa` |
| | 2.2 painel lateral e "Sair" | pronta | `47f7a69` |
| | 2.3 formulário de senha (revisada: popup centralizado, pedido do Marcos) | pronta | `48151fc`, `3adae1d` |
| | 2.4 seletor de tema, sem recarregar | pronta | `6481d9d`, `ad4f0d0` |
| 3 fundação visual | 3.1 inventário | pronta | `3ee011b` |
| | 3.2 tokens, Talão idêntico | pronta | `0cc8820` |
| | 3.3 fontes por tema | pronta | `e5a0fbe` |
| | 3.4 cascas e menu responsivo | pronta | `3c60af3` |
| | 3.5 guarda de contraste | pronta (feita ao longo da 3.1, 3.2, 3.4 e da base dos temas) | `c53199c` e seguintes |
| 4 Painel e popup | 4.1 Painel extraído | pronta | `6af185f` |
| | 4.5 popup da novidade | pronta | `25502c3` |
| 5 dados do mês | 5.1 `GET /painel/mes` | pronta | `79fdee0` |
| | 5.2 `GET /painel/cobrancas/{id}` | pronta | `da91134` |
| 6 Mosaico | 6.1 `PainelMosaico` (variante Almanaque) | pronta (testada) | `9817e58` |
| 7 Régua | 7.1 funções da linha do tempo | pronta (entregue junto da 3.1) | `c53199c` |
| | 7.2 `LinhaDoTempoDoMes.vue` | pronta (testada) | `97071a0` |
| | 7.3 `PainelRegua` e tema Régua | pronta (testada) | `24873a2`, `cc7d1c2`, `58929b9` |
| 8 Almanaque | 8.1 tema Almanaque | pronta (testada) | commit da 8.1 (`git log --grep 'Tarefa 8.1'`), mais `24873a2` e `cc7d1c2` da base |
| 9 fechamento | 9.1 conferência geral | pronta (testada) | `7d3fc73`, `0dc6b4d`, `3a60d18` (defeitos achados) e o commit da 9.1 (`git log --grep 'Tarefa 9.1'`) |
| | 9.2 documentação final | falta | |

**O que falta, em ordem:**
1. **Feitas:** a 2.4 (seletor, com troca de tema **sem recarregar** a página) e a 4.5 (popup para os usuários antigos).
2. **Feitas:** 7.2 e 7.3 (linha do tempo e composição da Régua).
3. **Feitas:** 6.1 (o Mosaico em blocos com a linha do tempo, composição do Almanaque) e 8.1 (ícones e pílulas do menu, contador dourado, ajuste fino do tema).
4. **Feita:** 9.1 (matriz de conferência: 3 temas, 22 telas logadas, 1440 e 390 px). **Falta:** 9.2 (documentação), o merge `--no-ff` no `main` e o push.
5. **Deploy é do Marcos**, e a variável `TEMA_PADRAO` no `.env` de produção também. **A migração das duas colunas de `users` não precisa de passo manual:** o `CMD` do `Dockerfile` roda `php artisan migrate --force` em toda subida do container (decisão do Marcos, 2026-10-12, reafirmada em 2026-11-07; guarda em `tests/Unit/DockerfileMigraNoDeployTest.php`).

**Já feito fora do código:** migração das colunas `tema` e `novidade_temas_vista` rodada no **Postgres local** (12 usuários antigos ficaram com `talao` e a novidade pendente). **Nada** foi feito em produção.

**Testes agora:** PHP 965 verdes (os 3 testes a mais, da guarda do Dockerfile, vieram do commit `e0d16eb`) e JS 420 verdes (414 do despacho B mais 6 da 9.1: `camposDoUseForm` 2, `pilhaDeDialogos` 3 e `aparecerSemPreenchimento` 1; `npm run build` ok na árvore e na cópia).

**Fora desta execução (pendentes para o futuro):** os temas Fluxo, Livro-caixa e Mosaico (tarefas 4.2, 4.3, 4.4 e 6.2) e os blocos "Semana à frente" e "Recebido por dia".

**Achados que pedem atenção do Marcos:**
- **Corrigidos na 9.1, três defeitos que já existiam no `main`:** (1) **"Cadastrar feriado" e "Salvar feriado" no Perfil não gravavam nada** (o campo `data` do `useForm` de `Feriados.vue` sobrescreve o método `data()` do Inertia; era o erro de console de `/perfil`; `7d3fc73`); (2) o **Coach "Primeiros passos" e o Holofote** ficavam no fim do conteúdo e não no canto da janela (a animação da página usava `both`; no Almanaque a 1440x900 o Coach caía fora da tela; `3a60d18`); (3) com qualquer `Modal` por cima do painel do usuário, um Esc fechava os dois (hoje só alcançável forçando, `0dc6b4d`). **O deploy do `main` ainda leva o defeito dos feriados**: sai com o merge desta branch.
- Contraste (9.1): no Talão, 12 usos de `text-grafite/60` (4,05 sobre a superfície) e, em todos os temas, o item bloqueado do menu (`text-menu-texto/50`: Talão 3,06, **Régua 2,36**, Almanaque 3,52). Sobre o papel, `grafite/60` fica em 4,46 (Régua) e 4,36 (Almanaque). Nenhum corrigido.
- Exceção conhecida da 9.1: no próprio Painel, trocar de tema refaz a composição e um formulário de baixa aberto numa linha se perde (o `<main>` e as outras telas ficam intactos).
- **Corrigido (`803acd9`):** a troca de senha aparava espaços nas pontas (`TrimStrings`), e quem tinha espaço na senha nunca conseguia trocá-la. **Aberto:** o campo `senha` das baixas e correções ainda é aparado (mesmo defeito para esse tipo de senha); não mexi por mudar uma tela antiga.
- Defeito antigo, não corrigido: `desfazer-baixa` e `corrigir-pagamento` dão erro 500 se alguém mandar `senha[]=x`.
- Fontes do Vite em dobro (woff2 e woff por peso; a página usa o `.woff`): os preloads de woff2 são em parte inúteis. Já era assim; tamanho não medido.
- Há um servidor do Vite na porta 5173 (`public/hot`) que não é de nenhum agente despachado, talvez o `npm run dev` do Marcos; não encerrei.
- Contraste do Talão: 11 textos `text-grafite/60` (4,05:1) e 1 `/50` (3,06:1) ficam abaixo de 4,5:1. Não mexi no Talão.
- As fontes estáticas do Bunny não têm eixo de largura: os títulos da Bricolage saem mais largos que nos protótipos da Régua.
- `docs/modelos-painel.html` e `docs/painel-lista.html` continuam sem commit (protótipos não escolhidos). Apagar?
- O Tailwind v4 lê também os `.md` de `.claude/` e gera estilo solto a partir de exemplos de classe no texto (inofensivo).

---

## 0. Escopo desta execução (comando do Marcos em 2026-11-07)

> "implemente painel-bento-timeline e painel-linha-do-tempo. Vai ficar como pendente pro futuro os outros."

- **Entram:** os temas **Almanaque** e **Régua** (mais o Talão, que já existe). Tarefas: 1.1 a 1.3, 2.1 a 2.4, 3.1 a 3.5, 4.1, 4.5, 5.1, 5.2, 6.1 (só a variante com linha do tempo), 7.1 a 7.3, 8.1, 9.1 (com 3 temas) e 9.2.
- **Pendentes para o futuro, fora desta execução:** os temas **Fluxo**, **Livro-caixa** e **Mosaico**. Ou seja, as tarefas 4.2, 4.3, 4.4 e 6.2 e, dentro da 6.1, os blocos "Semana à frente" e "Recebido por dia". O enum começa com `Talao`, `Regua` e `Almanaque` e ganha os outros casos quando cada tema for feito.
- **Ajustes de escopo:** a emissão de `resumo` pelo `ListaDoPainel` e o `fatiasDoMes` (que o bloco Dinheiro precisa) passam da 4.2 para a 6.1. `almanaque.css` traz os próprios tokens; o Mosaico os reaproveita quando vier. Com 3 temas, o seletor (2.4) e o popup (4.5) já valem.
- **Decisões da seção 7 assumidas pela recomendação** (nomes, "Sair" só no painel, senha mantém as outras sessões e é auditada, popup só para usuários antigos, pastas novas aprovadas ao mandar implementar, linha do tempo só do mês corrente). A do rosa do Livro-caixa fica para quando esse tema vier.
- **Execução:** por subagentes (modelo `sonnet`), no branch `feature/temas-visuais`; merge `--no-ff` no `main` e push ao fim; migração só no Postgres local, rodada por quem coordena depois da conferência do alvo. Deploy é do Marcos.

## 1. Pedido do Marcos (2026-11-06)

1. Implementar como temas: `modelo-b-painel.html`, `painel-bento.html`, `painel-bento-timeline.html`, `painel-linha-do-tempo.html` e `painel-escuro.html` (citado duas vezes, contado uma). `painel-lista.html` **não** entra.
2. Clicar no **nome do usuário** abre um **painel lateral à direita** com: lista de temas, **alterar senha** e **sair do sistema**.
3. O tema atual permanece, com o nome "Tema Padrão" ou um nome interessante.
4. **`.env` define o tema padrão dos usuários novos.**
5. **Usuários antigos veem um popup** com a novidade e como trocar de tema.
6. **A troca fica gravada no banco, na tabela do usuário** (`users`).
7. Depois de criado o plano, **esperar o comando** para implementar.

## 2. Temas

Nome do tema atual: **Talão** (de talão de recibos). Motivo de não usar "Padrão": com o `.env` o tema padrão pode ser outro, e "padrão" ficaria ambíguo. No painel o Talão aparece como "Talão" e o tema escolhido pelo `.env` não ganha rótulo especial.

| id (`users.tema`) | Nome | Origem | Casca | Painel | Fontes |
|---|---|---|---|---|---|
| `talao` | Talão | visual atual | lateral clara | `empilhado` | Archivo, Archivo Narrow |
| `fluxo` | Fluxo | `docs/modelo-b-painel.html` | lateral azul-marinho | `colunas` | Hanken Grotesk |
| `livro-caixa` | Livro-caixa | `docs/painel-escuro.html` | lateral verde-tinta | `colunas` | Source Serif 4, Public Sans |
| `mosaico` | Mosaico | `docs/painel-bento.html` | lateral azul-marinho | `mosaico` | Bricolage Grotesque, Onest |
| `regua` | Régua | `docs/painel-linha-do-tempo.html` | **superior** (menu com "Mais") | `regua` | Bricolage Grotesque, Figtree |
| `almanaque` | Almanaque | `docs/painel-bento-timeline.html` | lateral azul-marinho | `mosaico` + linha do tempo | Bricolage Grotesque, Onest |

"Régua" é o termo de cobrança para a sequência de lembretes, e combina com a linha do tempo. Os nomes são proposta; o Marcos troca à vontade (só muda o texto do enum).

## 3. O que um tema muda, e o que não muda

- **Muda em todas as telas logadas:** cores, fontes, cantos, sombras, menu (lateral ou superior), cor das etapas, `color-scheme` (campos nativos em tema escuro).
- **Muda de forma estrutural só no Painel** (as outras telas não têm protótipo): a composição do Painel.
- **Não muda:** Cobranças, Contratos, Clientes, Produtos e serviços, Relatórios, Auditoria, Perfil e Usuários mantêm o layout; só recebem a roupa do tema. Telas públicas (Entrar, Cadastro) ficam sempre no Talão. PDF do recibo não é afetado.

## 4. Restrições globais

- pt-BR em tudo, inclusive nomes que criarmos (variáveis, funções, classes, arquivos, testes). Chaves de enum em TitleCase.
- **Sem tabela nova** (então sem `tbl_`); só **duas colunas novas** em `users`. DDL só pelo `php artisan migrate` local já autorizado (ver `migrate-local-autorizado`), depois de mostrar `engine.url`, dizer que é local, `SELECT count(*) FROM users` e o comando literal. Nunca tocar `config_dev.json`/`config_prod.json`. Deploy e produção são do Marcos; **nenhum acesso à VPS**, inclusive de subagentes.
- Testes novos ficam em `tests/` (PHP em `tests/Feature/Temas/`, JS em `tests/js/`). Os testes JS que já existem ao lado do código continuam onde estão.
- `env()` só dentro de `config/`. Sem dependência nova. Pint em todo PHP alterado: `vendor/bin/pint --dirty --format agent`.
- Padrões de usabilidade da skill `recibos`: nunca mandar sair da tela (senha e tema ficam no painel lateral), erro sempre com a saída na tela, animações curtas com `prefers-reduced-motion` desligando tudo, 390 px sem rolagem horizontal, contraste mínimo 4,5:1.
- Smoke test com o **browser-agent** (plugin `browser-use`), nunca Playwright: subir o Chromium do Playwright com `--headless=new --remote-debugging-port=9333` e perfil temporário.
- `.claude/` é commitada. Commit com o rodapé `Co-Authored-By` do modelo que executar.

## 5. Foco de revisão (cada linha tem teste na tarefa indicada)

1. **Trocar de tema com formulário preenchido não pode apagar o que foi digitado.** O `<main><slot/></main>` fica na mesma posição da árvore; só o `<nav>` e as classes mudam (3.4, 9.1).
2. **Valor de tema inválido** (coluna corrompida, `TEMA_PADRAO` com erro de digitação, caso removido do enum) cai no Talão, sem erro 500 (1.1, 1.2).
3. **Um usuário nunca vê nem aciona cobrança de outro** nos endpoints novos do Painel (5.1, 5.2).
4. **Senha:** tentativas limitadas, nunca aparece em auditoria, log ou props, e todo erro mostra a saída na tela (2.1, 2.3).
5. **Popup de novidade** aparece uma única vez, só para usuário antigo, só quando existe mais de um tema e nunca por cima da apresentação inicial (1.2, 4.5).

## 6. Diferenças em relação aos protótipos

Os protótipos usam dados fictícios em memória. No app:

1. As listas são as reais: paginadas (blocos de 10) e recarregadas por ajax depois de cada ação. Contagens e somas vêm do total do resultado.
2. As ações são os componentes existentes (`BotaoEnviar`, `AvisarWhatsapp`, `BaixaInline` com desconto, multa e juros das Fases 26 e 27, `PagamentoInline`, `MenuAcoes`), não as simulações dos protótipos.
3. A linha do tempo mostra só o mês corrente. Vencidas de meses anteriores entram no bloco "Vencidas" e num marcador "Anteriores" no início do eixo.
4. A animação do cartão "voando" entre colunas fica fora da v1: a lista recarrega; usamos as transições de lista que já existem, número que conta, carimbo e pulso. Voo entre colunas entra na lista de "menores adiados".
5. O agrupamento "Atrasadas / Hoje / Próximos dias" do Régua fica como v2; a v1 usa as duas listas reais ("Para enviar" e "Aguardando pagamento").

## 7. Para o Marcos decidir (recomendação primeiro)

1. **Nomes** da seção 2 (Talão, Fluxo, Livro-caixa, Mosaico, Régua, Almanaque).
2. **"Sair" sai do menu** e fica só no painel do usuário (um lugar só). Recomendo sim.
3. **Rosa do botão principal do Livro-caixa** (`#F164AF`): é a escolha mais ousada do protótipo. Manter, ou trocar por ocre forte?
4. **Trocar a senha** mantém as outras sessões abertas (recomendo; encerrar as outras exige `AuthenticateSession`). A troca é registrada na auditoria **sem gravar senha** (recomendo sim).
5. **Usuário novo não vê o popup** (já tem o `ModalBoasVindas`) e nasce com o tema do `.env`. Recomendo.
6. **Pastas novas** (o `CLAUDE.md` pede aprovação): `resources/css/temas/`, `resources/js/Components/Painel/`, `tests/js/`. Recomendo aprovar.
7. Linha do tempo só do mês corrente, sem navegar entre meses (v1).

## 8. Mapa de arquivos

**Criar**
- `app/Enums/TemaVisual.php`, `config/temas.php`, `database/migrations/2026_11_06_100000_add_tema_e_novidade_to_users_table.php`
- `app/Http/Controllers/Auth/SenhaController.php`, `app/Http/Requests/AlterarSenhaRequest.php`, `app/Http/Requests/TemaRequest.php`
- `resources/css/temas/{fluxo,livro-caixa,mosaico,regua,almanaque}.css`
- `resources/js/{temas,usarTema,menuPrincipal,menuResponsivo,armadilhaDeFoco,contraste,fatiasDoMes,linhaDoTempo}.js`
- `resources/js/Components/{PainelDoUsuario,SeletorDeTema,FormularioDeSenha,ModalNovidadeTemas,MenuSuperior,MenuLateral,BarraDoMes,LinhaDoTempoDoMes}.vue`
- `resources/js/Components/Painel/{PainelEmpilhado,PainelColunas,PainelMosaico,PainelRegua}.vue` e os blocos do Mosaico
- Testes: `tests/Feature/Temas/*.php`, `tests/js/*.test.js`

**Modificar**
- `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Controllers/PerfilController.php`, `app/Http/Controllers/PainelController.php`, `routes/web.php`, `.env.example`
- `resources/views/app.blade.php`, `vite.config.js`, `resources/css/app.css`, `resources/js/Layouts/CamadaApp.vue`, `resources/js/Pages/Painel.vue`, `resources/js/Components/ListaDoPainel.vue`

## 9. Ciclo padrão de cada tarefa

1. Escrever os testes listados e vê-los **falhar**. 2. Implementar o mínimo. 3. Rodar os testes da tarefa, `vendor/bin/pint --dirty --format agent` e `npm run build`. 4. Commit em pt-BR. 5. Atualizar o **Estado** (pendente → concluída → testada → validada pelo Marcos). Comandos: `php artisan test --compact --filter=<Classe>`; `node --test 'tests/js/**/*.test.js' 'resources/js/**/*.test.js'`.

---

## Fase 1: dados e rotas (Marco 1)

### Tarefa 1.1: enum, `.env`, colunas e modelo. Estado: concluída (testes verdes; migração ainda não rodada no Postgres; `npm run build` não se aplica a esta tarefa PHP)
- **Criar** `TemaVisual` (`string`): cada caso entra na tarefa do seu tema; **começa só com `Talao = 'talao'`**. Métodos: `rotulo(): string`, `descricao(): string`, `casca(): string` (`lateral|superior`), `painel(): string` (`empilhado|colunas|mosaico|regua`), `comLinhaDoTempo(): bool`, `amostra(): array` (4 hex para a miniatura), `fontes(): array`, `static padrao(): self` (lê `config('temas.padrao')`, cai em `Talao` se inválido), `static paraOFront(): array`.
- **Criar** `config/temas.php` com `'padrao' => env('TEMA_PADRAO', 'talao')`; documentar `TEMA_PADRAO=talao` no `.env.example`.
- **Migração:** `tema` string(20) NOT NULL default `'talao'` e `novidade_temas_vista` boolean NOT NULL default `false`. Os usuários antigos ficam com o visual atual e com o popup pendente.
- **`User`:** cast de `tema` para o enum; `novidade_temas_vista` boolean; **fora do `#[Fillable]`**; `booted()` com `creating` que faz `tema ??= TemaVisual::padrao()` e `novidade_temas_vista ??= true` (usuário novo não vê o popup).
- **Testes** (`tests/Feature/Temas/TemaDoUsuarioTest.php`): `test_usuario_novo_nasce_com_o_tema_do_env` (`config(['temas.padrao' => ...])`), `test_env_invalido_cai_no_talao`, `test_usuario_novo_nasce_sem_novidade_pendente`, `test_cast_devolve_o_enum`, `test_tema_nao_e_preenchivel_em_massa`.
- **Banco:** antes do `migrate`, mostrar alvo, `migrate:status`, contagem e comando literal; só então `php artisan migrate`.

### Tarefa 1.2: compartilhar no Inertia e no HTML. Estado: concluída (testes verdes; sem front nesta tarefa)
- `HandleInertiaRequests`: `auth.user.tema` (valor, com fallback `Talao`), `temas` (`TemaVisual::paraOFront()`), `mostrarNovidadeTemas` = apresentação já vista **e** novidade não vista **e** mais de um caso no enum.
- `app.blade.php`: `<html data-tema="...">` do usuário logado, `talao` para visitante.
- **Testes** (`tests/Feature/Temas/CompartilhamentoDoTemaTest.php`): `test_html_traz_data_tema_do_usuario`, `test_visitante_recebe_talao`, `test_tema_corrompido_no_banco_vira_talao`, `test_novidade_nao_aparece_com_um_unico_tema`.

### Tarefa 1.3: rotas de preferência. Estado: concluída (testes verdes; sem front nesta tarefa)
- `PUT /perfil/tema` → `PerfilController@tema(TemaRequest)`; `POST /perfil/novidade-temas` → `PerfilController@novidadeDosTemas` (mesmo molde de `/perfil/apresentacao`). Ambas no grupo `auth` + `conta.aprovada`.
- `TemaRequest`: `tema` obrigatório, `Rule::enum(TemaVisual::class)`; mensagens em pt-BR.
- **Testes** (`TrocaDeTemaTest.php`): `test_troca_para_tema_valido`, `test_recusa_tema_inexistente`, `test_visitante_vai_para_o_login`, `test_conta_pendente_nao_troca`, `test_troca_nao_altera_outros_campos`, `test_marca_novidade_como_vista`.

## Fase 2: painel do usuário (Marco 1)

### Tarefa 2.1: alterar senha (back-end). Estado: concluída (testes verdes; sem front nesta tarefa; o `[A VERIFICAR]` da auditoria está resolvido na skill, "Acertos (Tarefa 2.1)")
- `PUT /perfil/senha` → `SenhaController@atualizar(AlterarSenhaRequest)` com `throttle:6,1` (como as rotas que pedem a senha, `routes/web.php:98`). Campos: `senha_atual` (`current_password`, mensagem "Senha incorreta."), `nova_senha` (`confirmed`, `Password::min(8)`, `different:senha_atual`).
- Registrar `Auditoria::registrar(..., 'usuario.senha_alterada', $usuario, 'Senha alterada')` **sem antes/depois**. `[A VERIFICAR]`: o PHPDoc de `registrar` cita só `Cobranca|Contrato` como alvo, e `alvo_tipo` sai de `class_basename`; conferir se a tela de Auditoria aceita `usuario.*` e dar rótulo.
- **Testes** (`AlterarSenhaTest.php`): `test_altera_com_a_senha_atual_correta` (`Hash::check`), `test_recusa_senha_atual_errada` (erro `senha_atual`, senha intacta), `test_recusa_confirmacao_diferente`, `test_recusa_menos_de_8`, `test_recusa_igual_a_atual`, `test_limita_tentativas` (7ª chamada = 429), `test_auditoria_nao_guarda_a_senha` (nenhuma coluna de `auditorias` contém a senha nem o hash), `test_visitante_vai_para_o_login`.

### Tarefa 2.2: painel lateral, nome do usuário e "Sair". Estado: testada (2026-11-07; aberto e fechado por clique, Esc e fundo nos 3 temas, foco preso e devolvido, 390 px sem rolagem lateral; 350 testes JS, `--filter=Painel` e `npm run build` verdes; detalhes na skill, "Acertos (Tarefa 2.2)")
- `PainelDoUsuario.vue` (props `aberto: Boolean`, emite `fechar`): `role="dialog"`, `aria-modal`, largura `min(26rem, 100%)`, entra pela direita em 220 ms, foco no primeiro controle, **armadilha de foco**, Esc e clique no fundo fecham, devolve o foco ao gatilho. Cabeçalho com nome e e-mail; botão **Sair** (`router.post('/sair')`).
- Gatilho: botão com o nome no rodapé do menu lateral e no cabeçalho de celular do `CamadaApp.vue`; remove o "Sair" do menu (seção 7, item 2).
- `armadilhaDeFoco.js`: `proximoDaArmadilha(total: number, indice: number, voltar: boolean): number`.
- **Feito:** `PainelDoUsuario.vue` com o cabeçalho (iniciais, nome, e-mail) e o "Sair" no rodapé; o corpo está vazio e organizado para as seções da 2.4 ("Aparência") e da 2.3 ("Alterar senha"), nesta ordem. `saindoDaArmadilha` (novo em `armadilhaDeFoco.js`) faz a armadilha intervir só nas bordas. Estado `painelAberto` no `CamadaApp.vue`; o gatilho é o botão do nome dos dois menus (3.4).
- **Testes** (`tests/js/armadilhaDeFoco.test.js`): `volta_ao_primeiro_depois_do_ultimo`, `vai_ao_ultimo_com_shift_no_primeiro`, `lista_vazia_devolve_menos_um`. Smoke test: abrir, Esc, foco devolvido, 390 px.

### Tarefa 2.3: formulário de senha no painel. Estado: testada (2026-11-07; erro de senha atual, confirmação diferente, curta, sucesso e 429 conferidos no navegador a 1440 e 390 px, com Tab e Esc; `tests/js/senha.test.js` e `PainelDoUsuarioPropsTest` verdes e `npm run build` ok; detalhes na skill, "Acertos (Tarefa 2.3)")
- `FormularioDeSenha.vue`: seção recolhível "Alterar senha" com senha atual, nova e confirmação; "Salvar nova senha"; sucesso "Senha alterada." limpa os campos; erro aparece junto do campo e o foco vai ao primeiro inválido; 429 mostra "Muitas tentativas. Aguarde um minuto e tente de novo."
- **Teste** (`tests/Feature/Temas/PainelDoUsuarioPropsTest.php`): `test_props_trazem_nome_email_e_tema_sem_senha`. Smoke test: erro de senha atual, sucesso, 429.
- **Revisão pedida pelo Marcos em 2026-11-07: a senha agora é popup.** No painel, "Alterar senha" é uma linha-botão (`aria-haspopup="dialog"`) e o formulário abre num popup centrado (`ModalAlterarSenha.vue`, por cima do painel que continua aberto): Esc fecha só o popup, clique fora não fecha, campos limpos ao fechar, "Fechar" com foco depois do sucesso e "Senha alterada agora há pouco." ao lado da linha. O recolhimento da seção saiu. Para isso: `pilhaDeDialogos.js` (`quemFechaComEsc`, trava de rolagem por dono; `tests/js/pilhaDeDialogos.test.js`), `Modal.vue` com `centralizado` (também usado no popup da novidade dos temas) e painel em `z-40`. Estado continua **testada** (JS 381 e PHP 962 verdes, `npm run build` ok, conferido no navegador nos 3 temas a 1440 e 390 px); detalhes na skill, "Acertos (Tarefa 2.3, revisão "senha em popup")" e "Acerto: `Modal` com `centralizado`".
- **Feito:** `FormularioDeSenha.vue` (campo de usuário escondido para o gerenciador de senhas, "Mostrar senhas"), `resources/js/senha.js` com `validarCamposDeSenha` (vazio, confirmação diferente, menos de 8, igual à atual, espaço nas pontas) e os textos, e o 429 tratado com `onHttpException` devolvendo `false` (sem o modal do Inertia). **Achado para o Marcos:** o servidor apara o espaço das pontas da senha nova (`TrimStrings`), e o login não; ver a skill, "Pegadinhas (Fase 29)".

### Tarefa 2.4: seletor de tema no painel. Estado: testada (2026-11-07; trocar Talão, Almanaque, Régua e Talão com formulário preenchido não recarrega a página nem apaga o texto; fontes do tema novo entram no `<head>` sem recarregar e o Talão não baixa as dos outros; falha da gravação reverte e oferece "Tentar de novo"; 370 testes JS, `--filter=Temas` e `npm run build` verdes; detalhes na skill, "Acertos (Tarefa 2.4)")
- `SeletorDeTema.vue`: grupo `role="radiogroup"`, um cartão por tema (nome, descrição, miniatura com `amostra`), marca o atual. **Escondido enquanto o enum tiver um só caso.** Ao escolher: aplica `data-tema` na hora e `router.put('/perfil/tema', ..., { preserveScroll: true, preserveState: true })`; se falhar, volta ao anterior e mostra "Não foi possível trocar o tema." com "Tentar de novo".
- `usarTema.js`: `usarTema()` devolve `{ atual, lista, trocar(id) }`; função pura `temaValido(id: string, lista: Array): string` (cai no primeiro da lista).
- **Composable `usarTema` feito em 2026-11-07, junto da 4.1 (esta tarefa 2.4 continua pendente: falta o `SeletorDeTema.vue`).** `resources/js/usarTema.js` + `tests/js/usarTema.test.js` (11 testes). `lista` e `atual` são `computed`; `trocar(id)` aplica o `data-tema`, grava com `router.put('/perfil/tema', ..., { preserveScroll: true, preserveState: true })` e resolve `{ ok }` (nunca rejeita); se falhar, volta o `data-tema` ao tema gravado; ~~se der certo e o tema for outro, `window.location.reload()` (`precisaRecarregar`)~~ **revogado pelo Marcos em 2026-11-07: trocar de tema nunca recarrega** (ver "Feito" abaixo). O seletor chama `const resultado = await trocar(id)` e mostra "Não foi possível trocar o tema." quando `resultado.ok` é falso. Detalhes na skill, "Acertos (Tarefa 2.4)".
- **Testes** (`tests/js/usarTema.test.js`): `id_desconhecido_cai_no_primeiro`, `id_valido_e_mantido`.
- **Feito (2026-11-07):** `SeletorDeTema.vue` (rádios nativos num `role="radiogroup"`, cartão com miniatura, nome, descrição e a marca "Atual"; some com um tema só; "Tentar de novo" ali mesmo), `MiniaturaDoTema.vue` (SVG da casca, da composição do Painel e das 5 cores de `amostra`; a quinta, nova, é a cor do menu), **sem recarga**: `GET /perfil/tema/{tema}/fontes` (`PerfilController@fontesDoTema`: JSON `{ html, familias }`, o HTML é o que o `@fonts` gera para os aliases do tema; 404 fora do enum) e `resources/js/fontesDoTema.js` (insere `<link>`/`<style>` no `<head>` uma vez por tema e espera `document.fonts.load`; falhou ou demorou mais de 4 s = `'reserva'`, o tema vale do mesmo jeito). `usarTema.js`: `precisaRecarregar` saiu; `temaEmTroca` faz o menu e o Painel trocarem junto com as cores; `trocar` resolve `{ ok: true, fontes }` ou `{ ok: false, substituida }`. Testes: `tests/Feature/Temas/RotaDeFontesDoTemaTest.php` (5), `tests/js/fontesDoTema.test.js` (9) e `tests/js/usarTema.test.js` (12).

## Fase 3: fundação visual (Marco 2)

### Tarefa 3.1: inventário e prova técnica. Estado: testada (2026-11-07; resultado na seção "Inventário (Tarefa 3.1)", no fim)
- Provar com o build que as utilidades do Tailwind v4 usam `var(--color-*)` (então sobrescrever sob `html[data-tema]` funciona). Contar `bg-white`, `text-white`, `rounded-*`, `shadow-*`, cores soltas (`#...`, `style=`) nas 33 telas/componentes. Anotar o resultado numa seção "Inventário" deste plano. Também ler a skill `recibos` inteira antes de mexer (regra do projeto).
- **Verificação:** o comando e a saída colados no plano.

### Tarefa 3.2: tokens semânticos, Talão idêntico. Estado: testada (2026-11-07; 36 capturas antes/depois sem diferença de estilo computado, prova do `html[data-tema]` no navegador, 329 testes JS e `npm run build` ok; detalhes na skill, "Acertos (Tarefa 3.2)")
- Em `@theme`: `--color-superficie`, `--color-sobre-acao`, `--color-menu-fundo|texto|ativo|linha`, `--color-etapa-enviar|aguardando|recebida`, `--raio-controle`, `--raio-bloco`, `--sombra-bloco`, `--font-titulo`, `--font-numeros`; valores do Talão = aparência de hoje. Trocar os usos de `bg-white` por `bg-superficie` e os `rounded-sm` dos controles por `--raio-controle`. `color-scheme` por tema.
- **Feito nesta execução:** tudo acima **exceto `color-scheme`** (nenhum tema escuro entra agora) e o uso de `--raio-bloco`, `--sombra-bloco`, `--font-titulo|numeros` e `--color-etapa-*` (definidos, sem uso ainda). Os 7 `rounded-sm` soltos também foram para `--raio-controle`. Menu: só as classes atuais do `CamadaApp.vue` trocaram por `bg-menu-fundo`, `border-menu-linha`, `text-menu-texto`, `bg-menu-ativo`; o resto fica para a 3.4.
- **Verificação:** capturas antes/depois das telas principais no Talão sem diferença visível (browser-agent); testes PHP e JS verdes.

### Tarefa 3.3: fontes por tema. Estado: testada (2026-11-07; 4 testes PHP verdes em `FontesDoTemaTest`; decisão abaixo, sem Plano B)
- `[A VERIFICAR]` com `search-docs`: se `@fonts` aceita carregar só as famílias do tema ativo. Plano B: declarar todas em `vite.config.js` e injetar só o `<link>` do tema no Blade a partir de `TemaVisual::fontes()`. Meta: usuário do Talão não baixa as fontes dos outros temas.
- **Teste** (`FontesDoTemaTest.php`): `test_cada_tema_declara_fontes_e_arquivo_css` (cada caso do enum tem `resources/css/temas/<valor>.css`, exceto `talao`).
- **Decisão (2026-11-07), o Plano B não é necessário:** (1) **`@fonts` aceita aliases** (`@fonts('x')` ou `@fonts(['x', 'y'])`, documentado em Blade > Fonts e lido em `Illuminate\Foundation\Vite::fonts()`); o alias padrão é o slug da família (`Archivo Narrow` vira `archivo-narrow`). Com aliases, saem só o `@font-face` e as variáveis dessas famílias e só os `<link rel="preload">` delas; alias que não existe no manifesto dá `ViteException` (a página inteira cai). (2) O plugin (3.2.0) **baixa as fontes do Bunny no `npm run build`** (cache em `node_modules/.cache/laravel-vite-plugin/fonts`) e as emite em `public/build/assets/`; o CSS aponta para `/build/assets/...`, então o navegador não vai ao Bunny; só o build precisa de rede. (3) Declarar mais famílias custa **tempo de build e disco** (`public/build/assets`), não peso para o Talão, **desde que o layout passe os aliases**: sem eles, `@fonts` emite e pré-carrega todas. Medido com o build atual: Talão com e sem filtro = 5458 bytes de HTML, 6 preloads, 12 `@font-face`; só `archivo` = 3554 bytes, 4 preloads, 8 `@font-face`.
- **Feito:** `TemaVisual::aliasesDasFontes()` (slug das chaves de `fontes()`), `app.blade.php` com `@fonts($tema->aliasesDasFontes())` e `$tema` calculado uma vez no topo (também alimenta o `data-tema`). Testes: `test_aliases_seguem_o_slug_que_o_plugin_do_vite_usa`, `test_vite_config_declara_as_familias_e_os_pesos_de_cada_tema` (o enum e o `vite.config.js` não podem se separar: alias faltando no manifesto derruba todas as páginas do tema) e `test_fontes_do_tema_ativo_saem_no_html` (manifesto falso com a família de cada tema mais uma "fora de tema": o HTML de cada tema só traz as dele, preloads inclusive).
- **Passo exato para as tarefas 7.3 e 8.1:** (a) acrescentar a família ao `vite.config.js` com `weights` **primeiro** nas opções (ex.: `bunny('Bricolage Grotesque', { weights: [500, 700] })`; se quiser, `preload: [{ weight: 500 }]` para pré-carregar só o principal); (b) acrescentar o mesmo `'Família' => [pesos]` ao ramo do caso em `TemaVisual::fontes()` (o teste de `vite.config.js` compara os pesos); (c) `resources/css/temas/<valor>.css` (o teste de existência já cobra) definindo `--font-sans`, `--font-titulo` etc. sob `html[data-tema='<valor>']` com o **nome da família entre aspas** igual ao do `@font-face`; (d) `npm run build` (precisa de rede, para o Bunny). Nada muda em `app.blade.php`. **Cuidado para o front:** o `<head>` (com as fontes) vem do Blade e **não é refeito** por navegação do Inertia. ~~recarga completa depois do sucesso~~ **Revogado em 2026-11-07 (Tarefa 2.4):** as fontes do tema novo entram no `<head>` por `GET /perfil/tema/{tema}/fontes`, sem recarregar (a rota lê `TemaVisual::fontes()`, então tema novo não pede nada além dos passos acima). `[A VERIFICAR]` ao rodar o build da 7.3 e da 8.1: se Bricolage Grotesque, Figtree e Onest existem no Bunny com os pesos escolhidos (não testado aqui; não sei o que o plugin faz com família ou peso inexistente).

### Tarefa 3.4: cascas e menu responsivo. Estado: testada (2026-11-07; Talão igual no `<main>` em 8 telas a 1440 e 390 px, as únicas diferenças do menu são o `Sair` que saiu e o botão do usuário; Régua sem item escondido nem rolagem lateral de 1920 a 320 px; 350 testes JS e `npm run build` verdes; detalhes na skill, "Acertos (Tarefa 3.4)")
- `menuPrincipal.js`: `itensDoMenu(props, admin: boolean): Item[]` (a lista que hoje está dentro do `CamadaApp.vue`). `menuResponsivo.js`: `quantosCabem(larguras: number[], disponivel: number, larguraMais: number): number` (a regra medida do protótipo: o que não cabe vai para "Mais ▾", nunca fica escondido).
- `MenuLateral.vue` e `MenuSuperior.vue` trocam **dentro do mesmo `CamadaApp.vue`**: `<main><slot/></main>` não muda de lugar na árvore (foco de revisão 1).
- **Feito:** `MenuLateral.vue`, `MenuSuperior.vue` (com "Mais ▾"), `LogoRecibo.vue` (logo por variável CSS: `--logo-exibicao` e `--marca-exibicao`), `iniciais.js`, `explicacaoDoBloqueio` e o token `--color-menu-texto-ativo` (com os pares no `contraste.test.js`). O botão do usuário abre o painel da 2.2.
- **Testes** (`tests/js/menuPrincipal.test.js`, `menuResponsivo.test.js`): `admin_ve_usuarios_com_contador`, `item_bloqueado_segue_o_progresso`, `todos_cabem_nao_mostra_mais`, `sobra_vai_para_o_mais`, `nunca_deixa_zero_itens_na_barra`. Smoke test: digitar num campo de Cobranças, trocar de casca, o campo mantém o valor.

### Tarefa 3.5: guarda de contraste. Estado: testada (2026-11-07; `contraste.js` e `tests/js/contraste.test.js` entregues na 3.1 e ampliados na 3.2, na base dos temas e na 3.4; o teste percorre `resources/css/temas/*.css` e exige 4,5:1 nos pares de texto, ação, etapas e menu)
- `contraste.js`: `razaoDeContraste(corA: string, corB: string): number` (WCAG). Teste que lê os tokens de `app.css` (`@theme`) e de `resources/css/temas/*.css` e exige ≥ 4,5 nos pares: texto/fundo, texto/superfície, sobre-ação/ação, cada tinta/superfície, cada cor de etapa/superfície, menu-texto/menu-fundo e menu-texto/menu-ativo.
- **Testes** (`tests/js/contraste.test.js`): `preto_sobre_branco_e_21`, `cores_iguais_dao_1`, `todos_os_temas_passam_nos_pares_de_texto`.

## Fase 4: Painel em colunas, Fluxo, Livro-caixa e popup (Marco 2)

### Tarefa 4.1: extrair o Painel atual e despachar por tema. Estado: testada (2026-11-07; Painel a 1440 e 390 px em 2 estados, antes e depois: 0 pixels e 0 estilos computados diferentes e `innerHTML` idêntico; testes JS, `--filter=Painel` e `npm run build` verdes; detalhes na skill, "Acertos (Tarefa 4.1)")
- Mover o corpo de `Pages/Painel.vue` para `Components/Painel/PainelEmpilhado.vue` sem mudar nada visível; `Painel.vue` escolhe o componente por `temaAtual.painel`. `temas.js`: `componenteDoPainel(painel: string): string` (desconhecido = `empilhado`).
- **Testes:** `tests/js/temas.test.js` (`painel_desconhecido_usa_empilhado`); `PainelTest` inteiro continua verde.

### Tarefa 4.2: `PainelColunas` e barra do mês. Estado: pendente (futuro, fora da execução de 2026-11-07)
- `ListaDoPainel.vue` passa a emitir `resumo` (`{ lista, quantidade, soma_centavos }`); `PainelColunas.vue` junta as três listas e o `BlocoVencendo` e alimenta `BarraDoMes.vue`.
- `fatiasDoMes({ recebido, aguardando, paraEnviar }): Array<{ chave, centavos, porcento }>` (maior resto: soma 100; a base é a soma das três partes, sem `previsto`; soma 0 devolve lista vazia). Módulo e testes já prontos (ver "Inventário", item D).
- **Testes** (`tests/js/fatiasDoMes.test.js`): `porcentos_somam_100`, `soma_zero_nao_divide_por_zero`, `fatia_zerada_continua_na_lista`, `maior_resto_desempata_pela_ordem`.

### Tarefa 4.3: tema Fluxo. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `Fluxo` no enum; `resources/css/temas/fluxo.css` (paleta e fontes do protótipo); casca lateral azul-marinho; painel `colunas`. Efeitos: barra enche na abertura e números contam, número que muda pisca (`.destacar`), carimbo PAGO na baixa, vencida pulsa no máximo 3 vezes, aviso na tela com linha do tempo. Tudo desligado em `prefers-reduced-motion`.
- **Testes:** `contraste.test.js` passa com o tema novo; `test_tema_fluxo_e_aceito_na_troca` e `test_html_traz_data_tema_fluxo` (PHP). Smoke test em 1440 e 390.

### Tarefa 4.4: tema Livro-caixa. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `LivroCaixa`; `livro-caixa.css` (verde-tinta, tintas de caneta/ocre/cédula/carimbo, `color-scheme: dark`); barra do mês como **régua de tinta**; carimbo PAGO com mancha; traço de caneta ao mudar de etapa (CSS).
- Pendente da seção 7, item 3 (cor do botão principal).
- **Testes:** contraste passa; `test_tema_livro_caixa_e_aceito_na_troca`. Smoke test: campos de data e select legíveis no escuro.

### Tarefa 4.5: popup da novidade para usuários antigos. Estado: testada (2026-11-07; usuário antigo vê uma vez, "Escolher meu tema agora" abre o painel na Aparência, "Agora não", Esc e fundo marcam como visto e o botão do nome pulsa 3 vezes; usuário novo e usuário com apresentação aberta não o veem; `POST` falho fecha mesmo assim; 375 testes JS, 961 PHP e `npm run build` verdes; detalhes na skill, "Acertos (Tarefa 4.5)")
- `ModalNovidadeTemas.vue` (usa `Modal.vue`), renderizado no `CamadaApp.vue` quando `mostrarNovidadeTemas`: título "Agora você escolhe a aparência do sistema", texto "Clique no seu nome e escolha entre N temas. Também dá para alterar a senha ali.", miniaturas, botões **"Escolher meu tema agora"** (marca como visto e abre o painel do usuário já na aparência) e **"Agora não"**. Fechar por Esc ou fundo também marca como visto (`POST /perfil/novidade-temas`). Depois de "Agora não", o botão do nome pulsa até 3 vezes para mostrar onde fica.
- **Testes** (`NovidadeDosTemasTest.php`): `test_antigo_com_apresentacao_vista_ve_a_novidade`, `test_antigo_sem_apresentacao_vista_nao_ve`, `test_novo_usuario_nao_ve`, `test_some_depois_de_marcada_como_vista`, `test_nao_aparece_com_um_unico_tema`.
- **Feito (2026-11-07):** `ModalNovidadeTemas.vue` (usa `Modal.vue`, que ganhou a propriedade `fecharNoFundo`), renderizado em `CamadaApp.vue` só com `mostrarNovidadeTemas` e `! mostrarApresentacao`; `resources/js/novidadeDosTemas.js` com `textoDoLugar(casca, celular = false)` (o segundo parâmetro é um acréscimo: no celular o botão do nome da casca lateral fica no cabeçalho, "no canto de cima, à direita"), `textoDaNovidade` e `novidadeDispensada`; botão do nome pulsa por `.pulsar-botao` e `.pulsar-botao-linha` (`app.css`), desligado em `prefers-reduced-motion`. O lado PHP (os cinco testes acima, com outros nomes) já estava em `CompartilhamentoDoTemaTest` (tarefa 1.2 e a base dos temas), então não há `NovidadeDosTemasTest.php` novo. Teste JS: `tests/js/novidadeDosTemas.test.js`.

## Fase 5: dados do mês (Marco 3)

### Tarefa 5.1: `GET /painel/mes`. Estado: testada (2026-11-07; 10 testes PHP verdes em `PainelMesTest`; sem front, `npm run build` não se aplica)
- `PainelController::mes` devolve `{ mes, hoje, dias, feriados: { dia: nome }, fim_de_semana: number[], pontos: Array<{ id, cliente, valor_centavos, vencimento_dia, etapa, situacao, pago_dia, pago_centavos }>, vencidas: { quantidade, soma_centavos, itens }, hoje_resumo: { vence_hoje, para_avisar, para_enviar }, anteriores: { quantidade, soma_centavos } }`. Vencidas e anteriores incluem meses passados; usa `DiasUteis` para feriados e fins de semana; carrega o cliente de uma vez (sem N+1).
- **Testes** (`tests/Feature/Temas/PainelMesTest.php`): `test_so_traz_cobrancas_do_usuario_logado` (foco 3), `test_vencida_de_mes_anterior_entra_em_vencidas_e_anteriores`, `test_pago_fora_do_mes_nao_entra_nos_pontos`, `test_marca_feriado_e_fim_de_semana`, `test_numero_de_consultas_nao_cresce_com_as_cobrancas`.

### Tarefa 5.2: `GET /painel/cobrancas/{cobranca}`. Estado: testada (2026-11-07; 4 testes PHP verdes em `PainelCobrancaTest`; sem front, `npm run build` não se aplica)
- Devolve a mesma linha (`paraLista($hoje)`) que a lista usa, sob demanda, para o ponto clicado abrir as ações sem repetir lógica.
- **Testes:** `test_devolve_a_linha_da_propria_cobranca`, `test_cobranca_de_outro_usuario_da_404`, `test_visitante_vai_para_o_login`.

## Fase 6: Mosaico (Marco 3)

### Tarefa 6.1: `PainelMosaico` e blocos. Estado: testada (2026-11-07; só a variante com linha do tempo, a do Almanaque; 408 JS e 962 PHP verdes e `npm run build` ok; conferida no browser-agent a 1440x900 e 390x844, larguras de 1920 a 320 px sem rolagem lateral, `prefers-reduced-motion`, falha dos dados com "Tentar de novo"; detalhes na skill, "Acertos (Tarefa 6.1)")
- Blocos: Dinheiro (anel SVG com `fatiasDoMes`), Vencidas, Hoje, a `LinhaDoTempoDoMes` em largura total e a lista com abas deslizantes (Enviar, Aguardando, Recebidas, com contagem). As três listas ficam montadas; só a aba ativa aparece (`ListaDoPainel` emite `resumo`). **Fora desta execução:** "Semana à frente" e "Recebido por dia" (do tema Mosaico, tarefa 6.2).
- **Testes:** `fatiasDoMes` reutilizado; `tests/js/painelMosaico.test.js` (10: arcos do anel, texto do anel, setas das abas, `esvaziou`, nota do dia, "há N dias") e `tests/js/avisosDoPainel.test.js` (4); `tests/js/contraste.test.js` ganhou os pares dos blocos (só nos temas com `mosaico`) e os arcos do anel (3:1). O `tests/js/recebidoPorDia.test.js` do plano original não existe porque o bloco "Recebido por dia" ficou fora.

### Tarefa 6.2: tema Mosaico. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `Mosaico`; `mosaico.css`; efeitos: anel preenche, carimbo PAGO com pulso do anel, barra do dia cresce, confete discreto ao esvaziar uma etapa.
- **Testes:** contraste; `test_tema_mosaico_e_aceito_na_troca`.

## Fase 7: Régua (Marco 4)

### Tarefa 7.1: funções puras da linha do tempo. Estado: testada (2026-11-07; `resources/js/linhaDoTempo.js` e `tests/js/linhaDoTempo.test.js` entregues junto da 3.1, commit `c53199c`)
- `linhaDoTempo.js`: `empilharPontos(pontos): Array<{ ponto, nivel }>` (mesmo dia sobe um nível), `acumuladoPorDia(pontos, dias: number): number[]` (régua de dinheiro), `posicaoDoDia(dia: number, dias: number): number` (0 a 1).
- **Testes** (`tests/js/linhaDoTempo.test.js`): `dois_no_mesmo_dia_empilham`, `acumulado_so_soma_recebidos`, `mes_de_28_dias`, `mes_de_31_dias`, `sem_pontos_devolve_zeros`.

### Tarefa 7.2: `LinhaDoTempoDoMes.vue`. Estado: testada (2026-11-07; 19 testes JS em `tests/js/linhaDoTempo.test.js` (os da 7.1 mais os novos), 393 JS e 962 PHP verdes e `npm run build` ok; conferida no browser-agent a 1440x900 e 390x844: pontos, dica, popover, Tab, setas, Esc, baixa com o salto, rolagem interna com "hoje" à vista, `prefers-reduced-motion`, falhas com "Tentar de novo"; o teste PHP do 404 já é o da 5.2; detalhes na skill, "Acertos (Tarefa 7.2)")
- Eixo de dias (fim de semana sombreado, feriado hachurado, "Hoje"), pontos como botões na ordem de tabulação com setas, pílulas com a cor da etapa e valor legível (≥ 12 px), vencida em vermelho com "!", marcador "Anteriores", régua de dinheiro com o previsto pontilhado. Clicar num ponto abre um popover que busca a linha (5.2) e mostra a ação principal com os componentes existentes. Foco e hover ligam ponto e linha da lista nos dois sentidos. Em tela estreita rola dentro do próprio bloco, já mostrando "hoje".
- **Testes:** as funções puras da 7.1; `test_ponto_de_outro_usuario_nao_abre` (a busca da 5.2 devolve 404 e o popover mostra "Não foi possível abrir esta cobrança." com "Tentar de novo"). Smoke test: Tab percorre os pontos, setas movem, Esc fecha o popover.

### Tarefa 7.3: `PainelRegua` e tema Régua. Estado: testada (2026-11-07; Régua a 1440x900 e 390x844 no browser-agent com listas reais, números do mês, linha do tempo, popover, baixa com o salto e envio; sem erro de console nem rolagem lateral de 1920 a 320 px; `prefers-reduced-motion` sem movimento; Talão e Almanaque intactos; 393 JS e 962 PHP verdes e `npm run build` ok; detalhes na skill, "Acertos (Tarefa 7.3)")
- Caso `Regua`; `regua.css`; casca **superior** com o menu "Mais ▾" (`menuResponsivo`, tarefa 3.4); abertura: linha se desenha, pontos caem; baixa: ponto salta até o dia do pagamento.
- **Testes:** contraste; `test_tema_regua_e_aceito_na_troca`. Smoke test em 1920, 1280, 860, 390 e 320: nenhum item do menu fica escondido e a página não rola de lado.
- **Base pronta (2026-11-07, commits `24873a2` e o seguinte; o Estado acima NÃO mudou):** feitos o caso `Regua` do enum (casca `superior`, painel `regua`, sem linha do tempo, `descricao`, `amostra`, `fontes`: Bricolage Grotesque 700 e 800 + Figtree 400 a 700), as famílias no `vite.config.js` (o `npm run build` baixou do Bunny), `resources/css/temas/regua.css` (todos os tokens do protótipo, importado no `app.css`), os pares de contraste (passam sem mudar cor) e `test_tema_regua_e_aceito_na_troca` e `test_html_traz_data_tema_regua`. **Falta** a parte de interface: `PainelRegua`, `MenuSuperior` com o "Mais ▾", as animações e o smoke test. Limites a lembrar: (1) o Bunny não serve o eixo de largura, então **não usar `font-stretch`** (os títulos saem na largura normal; compensar com `tracking-tight` e tamanho menor); (2) `--font-estreita` da Régua é o Figtree, não a Bricolage; (3) o protótipo tem `--linha-forte` (borda de botão e campo) e `--faixa` (fundo da faixa do mês) sem token no app.

## Fase 8: Almanaque (Marco 4)

### Tarefa 8.1: Mosaico com linha do tempo. Estado: testada (2026-11-07; Almanaque a 1440x900 e 390x844 no browser-agent: menu com ícones, pílula e contador dourado, Painel (tarefa 6.1), 12 telas logadas sem rolagem lateral e sem erro de console (salvo um erro antigo do Inertia em `/perfil`); Talão sem nenhuma diferença de estilo e Régua só com a correção do Perfil, em 5 telas a 1440 e 390 px; 414 JS e 965 PHP verdes e `npm run build` ok; detalhes na skill, "Acertos (Tarefa 8.1)")
- Caso `Almanaque`: `PainelMosaico` com `comLinhaDoTempo` (sem os blocos "Semana à frente" e "Recebido por dia"; `LinhaDoTempoDoMes` em largura total entre a fileira de cima e a lista). `almanaque.css` herda os tokens do Mosaico. A página nunca rola sozinha ao dar baixa.
- **Testes:** contraste; `test_tema_almanaque_e_aceito_na_troca`.
- **Base pronta (2026-11-07, commits `24873a2` e o seguinte; o Estado acima NÃO mudou):** feitos o caso `Almanaque` do enum (casca `lateral`, painel `mosaico`, com linha do tempo, `descricao`, `amostra`, `fontes`: Bricolage Grotesque 700 e 800 + Onest 400 a 700), as famílias no `vite.config.js`, `resources/css/temas/almanaque.css` (tokens do protótipo `painel-bento-timeline.html`, já com `--raio-bloco: 28px` e `--sombra-bloco`; o "herda os tokens do Mosaico" acima não se aplica porque o Mosaico não entra nesta execução), os pares de contraste e `test_tema_almanaque_e_aceito_na_troca` e `test_html_traz_data_tema_almanaque`. **Falta** `PainelMosaico` com a linha do tempo e o smoke test. **Atenção na 3.4:** o item ativo do menu usa `text-carbono`, que no Almanaque (`#3B47C4` sobre o ativo `#2C3768`) dá 1,56:1; é preciso o token `--color-menu-texto-ativo` (Almanaque `#FFFFFF`) e o par no teste de contraste. Mesmos limites de fonte da 7.3 (sem `font-stretch`; `--font-estreita` = Onest).
- **Feito na execução de 2026-11-07:** `PainelMosaico` (6.1); menu lateral do Almanaque por variáveis (`--menu-*` e `--contador-*` em `almanaque.css`; ícones por `iconeDoItem`); contador dourado (`--color-contador-*`, também no Talão e na Régua, com os valores de antes); pares de contraste novos (contador e blocos do Mosaico); **correção do tema da tela depois de entrar e sair** (`sincronizarTemaDaPagina`, que faltava: o Almanaque abria com as cores do Talão depois do login); `min-w-0 grid-cols-1` nos `fieldset` do Perfil (rolagem lateral a 390 px). **Fora, com motivo (ver a skill):** carimbo grande "PAGO" sobre a linha recém-paga, voo da cobrança até a aba e pontinho dourado numa aba com novidade.

## Fase 9: fechamento

### Tarefa 9.1: conferência geral. Estado: testada (2026-11-07; matriz de 3 temas x 22 telas logadas x 1440x900 e 390x844 = 132 células no browser-agent, todas sem erro de console, sem rolagem lateral, sem texto cortado, com tema e fonte certos desde o primeiro desenho e o painel do usuário abrindo e fechando; foco de revisão 1 provado com o seletor de verdade; modais acima do painel nos 3 temas e 2 larguras; 3 defeitos antigos corrigidos com teste; 965 PHP e 420 JS verdes e `npm run build` ok; detalhes na skill, "Acertos (Tarefa 9.1)". Não é "validada": falta o Marcos olhar as telas.)
- Matriz **3 temas (Talão, Régua, Almanaque) × telas logadas × 1440 e 390** com o browser-agent: sem erro de console, sem rolagem horizontal, sem texto ilegível, modais (`Modal`, `ModalBoasVindas`, popup, `CoachDeConfiguracao`) acima do painel lateral e legíveis. Repetir a prova do foco de revisão 1. Suíte completa: PHP, JS e `npm run build`. (Os outros 3 temas do plano original, Fluxo, Livro-caixa e Mosaico, estão fora desta execução.)

### Tarefa 9.2: documentação. Estado: pendente
- Skill `recibos`: seção "Fase 29, temas" (acertos e pegadinhas, `TEMA_PADRAO`, como criar um tema novo em 4 passos). Handoff no roteiro, `.claude/MEMORY.md`, `README` (variável de ambiente e inventário). Avisar o Marcos de que `TEMA_PADRAO` precisa estar no `.env` de produção e que o deploy roda a migração.

---

## 10. Execução e entregas

- **Marcos de entrega** (cada um pode ser mesclado e publicado sozinho): **M1** = Fases 1 e 2 (painel do usuário com senha e sair; um só tema, então sem seletor nem popup); **M2** = Fases 3 e 4 (Fluxo e Livro-caixa e o popup); **M3** = Fases 5 e 6 (Mosaico); **M4** = Fases 7 e 8 (Régua e Almanaque), depois a Fase 9.
- Por subagentes (modelo `sonnet`, nunca Opus nem Fable). Todo prompt de despacho leva: o parágrafo do context-mode, `ponytail:ponytail` em `lite`, o modelo, a proibição de acessar a VPS, o limite de ~15 linhas na resposta e o caminho do relatório.
- Em cada subagente de interface: invocar `frontend-design:frontend-design`; conferir tela com o browser-agent.
- Branches curtos por marco, merge `--no-ff` no `main` e push (permissão permanente); migração só local; **deploy é do Marcos**.

## 11. Riscos

- **Cores soltas no código** (`bg-white`: 49 usos, `text-white`, `Carimbo.vue`, `Etiqueta.vue`) quebram o tema escuro. Mitigação: 3.1, 3.2, 3.5 e a matriz da 9.1.
- **Troca de casca remontar a página** e apagar formulário. Mitigação: foco de revisão 1.
- **Peso das fontes.** Mitigação: 3.3.
- **Sobreposição** do painel com `Modal`, `ModalBoasVindas` e o coach flutuante (que já cobre a última linha do Painel em 1280 px, defeito antigo). Mitigação: 9.1.
- **Volume** de `/painel/mes` em conta com muitas cobranças. Mitigação: só campos compactos, teste de consultas na 5.1.
- **Escopo:** são 27 tarefas. A ordem dos marcos permite parar depois de qualquer um.

## 12. Ferramentas usadas

superpowers (writing-plans, subagent-driven-development, test-driven-development), frontend-design, context-mode, laravel-boost (`search-docs`, `database-query` só leitura), browser-use (smoke test). Todas já estão instaladas.

## Menores adiados

Voo do cartão entre colunas; agrupamento "Atrasadas / Hoje / Próximos dias" na Régua; navegar entre meses na linha do tempo; prévia do tema ao passar o mouse; tema `painel-lista`.

## Inventário (Tarefa 3.1)

Feito em 2026-11-07, só leitura mais `npm run build` (o `public/build` é ignorado pelo git). Contagens sobre `resources/js` (61 `.vue`; o plano falava em 33 telas/componentes, o número real é maior) e `resources/css/app.css`.

### A. Prova: as utilidades do Tailwind v4 usam `var(--color-*)`

- **Comando:** `npm run build` (saída: `✓ built in 1.05s`, CSS em `public/build/assets/app-<hash>.css`, 56 KB). Tailwind instalado: 4.3.3.
- **Camadas do CSS gerado** (blocos, nesta ordem): `@layer properties`, `theme`, `base`, `components`, `utilities`. As variáveis do `@theme` estão em `@layer theme { :root,:host{ ... } }` (55 variáveis `--color-*` distintas).
- **Utilidades de cor leem a variável** (trecho literal do CSS gerado): `.text-carbono{color:var(--color-carbono)}`, `.bg-white{background-color:var(--color-white)}`, `.border-linha{border-color:var(--color-linha)}`, `.bg-papel{...var(--color-papel)}`, `.bg-carbono{...var(--color-carbono)}`, `.text-tinta-vermelha{color:var(--color-tinta-vermelha)}`, `.font-estreita{font-family:var(--font-estreita)}`, `.rounded-sm{border-radius:var(--radius-sm)}`. Conclusão: sobrescrever as variáveis sob `html[data-tema='x']` troca as cores, as fontes e os cantos sem mexer nos componentes.
- **Variantes com opacidade** (`border-carbono/10`, `hover:bg-carbono/90`, `text-grafite/60`; 185 usos nas `.vue`) saem em duas regras: um hex fixo (plano B, `#2f3c9e1a`) e, dentro de `@supports (color:color-mix(in lab, red, red))`, `color-mix(in oklab, var(--color-carbono) 10%, transparent)`. Em navegador com `color-mix` (o caso real) a variável do tema vale; o hex fixo só aparece em navegador antigo. `[A VERIFICAR]` no navegador na 3.2.
- **Sombras não vêm de variável:** `.shadow-sm` e as outras saem com valor fixo (`0 1px 3px 0 var(--tw-shadow-color,#0000001a)`); o CSS não tem nenhuma `--shadow-*`. Para o `--sombra-bloco` valer, o componente precisa usá-lo de forma explícita (ex.: `shadow-(--sombra-bloco)` ou uma classe própria em `@layer components`). Sobrescrever só a variável não troca as 6 sombras atuais.
- **Cantos:** como `rounded-sm` lê `--radius-sm`, sobrescrever essa variável mudaria **todos** os `rounded-sm` de uma vez (controles, `Carimbo`, `Etiqueta`, `EstadoDaLista`). Por isso a 3.2 cria `--raio-controle` e troca só os controles.
- **`--color-white: #fff` existe no `:root`** e é lida por `bg-white` **e** por `text-white`. Sobrescrevê-la num tema escuro deixaria o texto sobre ação escuro junto com a superfície. Por isso os dois tokens são separados (`--color-superficie` e `--color-sobre-acao`, tarefa 3.2).
- **Pegadinha de camada:** regra **fora** de `@layer` vence qualquer utilidade, mesmo com especificidade menor. Os arquivos `resources/css/temas/*.css` devem trazer **só variáveis** sob `html[data-tema='x']`; regra de classe nesses arquivos vai dentro de `@layer components` ou `@layer utilities`, senão atropela as utilidades do Tailwind.
- **Não provado em navegador:** tentei `chromium --headless=new --dump-dom` numa página de teste com `html[data-tema]` sobrescrevendo as variáveis; o processo ficou pendurado (o stderr só mostrou erros de registro GCM) e não devolveu o DOM em 40 s. A prova acima é a leitura do CSS gerado. A conferência visual real é a captura antes/depois da 3.2 com o browser-agent.
- Observação: as variáveis `--color-amber-*`, `--color-emerald-*` etc. do `:root` vêm das views Blade compiladas em `storage/framework/views` (pegas pelo `@source` do `app.css`), não de `resources/js`. Não afetam os temas.

### B. Contagem em `resources/js` (comandos: scripts `node` com `readdirSync` e regex, e `grep -rEoh`)

| Item | Total | Onde |
|---|---|---|
| `bg-white` | **49** em 32 arquivos `.vue` (+3 em `app.css`: `.entrada`, `.botao-secundario`, `.botao-perigo`) | `EstadoDaLista` 4, `CamadaApp` 4, `Contratos/Form` 4, `Painel` 3, `BaixaEmLote` 2, `Feriados` 2, `CamadaPublica` 2, `Cobrancas/Form` 2, `Relatorios/Ver` 2, `ProdutosServicos/Reajuste` 2, os outros 1 cada |
| `text-white` | **3** `.vue` (+2 em `app.css`: `.botao`, `.botao-perigo-confirmar`) | `IndicadorDeCarregamento`, `CamadaApp` (contador de pendentes, `bg-tinta-vermelha`), `Cobrancas/Index` (`text-white/80` na aba ativa) |
| `border-white` | 1 linha | `IndicadorDeCarregamento` (`border-white/30 border-t-white`, o girador) |
| `rounded-*` | **16**: `rounded-full` 9 e `rounded-sm` 7 (+5 `rounded-sm` em `app.css`: `.entrada`, `.botao`, `.botao-secundario`, `.botao-perigo`, `.botao-perigo-confirmar`) | `rounded-sm`: `EstadoDaLista` 5, `Carimbo` 1, `Etiqueta` 1. `rounded-full`: contadores, bolinhas, `Paginador`, girador |
| `shadow-*` | **6**: `shadow-lg` 4, `shadow-md` 1, `shadow-sm` 1 | `Modal`, `CoachDeConfiguracao`, `HolofoteDePendencia`, `IndicadorDeCarregamento` (lg); `BuscaAutosugestao` (md); `MenuAcoes` (sm) |
| cor solta `#hex` | **0** | nenhuma |
| `rgb(` / `rgba(` / `hsl(` | **0** | nenhuma |
| cor da paleta Tailwind (`blue-500`, `gray-200`...) | **0** | nenhuma |
| `bg-black` / `text-black` | **0** | nenhuma |
| `style=` / `:style=` | **2**, ambos `:style="{ width }"` | `Relatorios/Ver.vue` linhas 81 e 82: só largura de barra, **sem cor** |
| `font-estreita` | **99** em 34 arquivos | `Cobrancas/Index` 13, `Clientes/Index` 6, `CorrecaoPagamentoInline` 5, `Admin/Usuarios` 5, `Contratos/Index` 5, `ProdutosServicos/Index` 5... (a 3.3 troca a fonte pela variável `--font-estreita`) |
| `accent-carbono` | 7 | checkboxes e rádios |
| tokens do projeto mais usados | `text-grafite` 165, `border-linha` 48, `text-tinta-vermelha` 41, `border-carbono` 36, `text-carbono` 32, `bg-papel` 16, `divide-linha` 10, `text-tinta-laranja` 8 | |

Resumo: **não há cor solta no código**; tudo passa pelos tokens do projeto (`carbono`, `grafite`, `papel`, `linha`, `tinta-*`) e por `white`. Por isso um tema muda quase tudo só trocando variáveis. Os pontos que **precisam de token novo** (tarefa 3.2) são:

1. **`bg-white` (49 + 3)** vira `bg-superficie`: superfície de lista, cartão, modal, menu e campo. É a troca mais volumosa e mecânica.
2. **`text-white` sobre fundo de ação** vira `text-sobre-acao`: `.botao`, `.botao-perigo-confirmar`, `IndicadorDeCarregamento` (texto e girador), contador de `CamadaApp`, aba ativa de `Cobrancas/Index`.
3. **Menu** (`CamadaApp.vue` linhas 32, 48, 55, 65, 66, 73, 79): hoje `bg-white`, `border-linha`, `hover:bg-papel`, item ativo `bg-papel text-carbono`, `text-grafite/50`. Para a lateral azul-marinho e a superior precisa dos `--color-menu-fundo|texto|ativo|linha`.
4. **Etapas** (enviar, aguardando, recebida): `Carimbo.vue` e `Etiqueta.vue` não têm cor fixa, usam `carbono`, `tinta-verde|laranja|vermelha` e `grafite/40|50|60|70`, então seguem os tokens sozinhos. As cores novas de etapa (`--color-etapa-*`) valem para a linha do tempo e os blocos novos, não para estes dois.
5. **`.carimbo { mix-blend-mode: multiply }`** (`app.css`): em fundo escuro o carimbo some. Só importa para tema escuro (Livro-caixa, futuro): ali precisa de `mix-blend-mode: normal`. Não afeta Talão, Régua nem Almanaque se eles tiverem superfície clara.
6. **Sombras (6) e cantos (`rounded-sm`, 7 + 5 em `app.css`)**: ver a seção A.
7. **`color-scheme`** por tema (campos nativos): `app.blade.php` não declara nenhum hoje.
8. **`<html data-tema>`**: já feito na 1.2 (conferir ao integrar).

### C. Achado de contraste do Talão (para a 3.5; sem correção aqui)

Calculado com `razaoDeContraste` (mistura do `grafite` com o fundo). Sobre branco: `text-grafite/60` = **4,05**, `/50` = **3,06**, `/70` = 5,56; `/75` e `/80` ficam acima de `/70`. Pares sólidos do Talão: grafite sobre papel 13,74; carbono sobre branco 9,29; tinta-verde 5,05 (sobre papel 4,70); tinta-vermelha 6,54; tinta-laranja 5,18 (sobre papel 4,82). Portanto o Talão **tem 11 usos de `text-grafite/60` e 1 de `text-grafite/50` abaixo de 4,5:1** (entre eles `Etiqueta` tom `apagado`, `Carimbo` cancelada e o item bloqueado do menu; os demais pontos não foram listados). `[A VERIFICAR]`: se a regra dos 4,5:1 vale para esses textos "apagados" (estado desativado costuma ficar de fora do WCAG) ou se sobem para `/70`. Não mexi: o Talão deve continuar idêntico (3.2).

### D. Módulos JS puros já prontos (Tarefas 2.2, 2.4, 3.4, 3.5, 4.1, 4.2 e 7.1)

Só os módulos puros e seus testes foram feitos nesta rodada; **as tarefas continuam no estado em que estão** (falta a parte de interface e de PHP de cada uma). Arquivos novos: `resources/js/{armadilhaDeFoco,temas,menuPrincipal,menuResponsivo,contraste,fatiasDoMes,linhaDoTempo}.js` e `tests/js/*.test.js` com o mesmo nome (35 testes). Diferenças em relação ao texto das tarefas:

- **`temaValido` mora em `temas.js`** (com `componenteDoPainel`), não em `usarTema.js`; o teste é `tests/js/temas.test.js`. A 2.4 só precisa importar de lá.
- **`itensDoMenu(progresso, administrador, pendentes)`** (3 parâmetros, não `(props, admin)`): extraído fielmente da lista de `CamadaApp.vue` (mesmos itens, mesma ordem, mesmo `itemBloqueado`), **sem** "Sair". A 3.4 troca a lista do `CamadaApp.vue` por esta função.
- **`componenteDoPainel`** aceita `empilhado | mosaico | regua`; `colunas` entra com a 4.2 (futuro).
- **`fatiasDoMes({ recebido, aguardando, paraEnviar })`** não tem `previsto`: a base dos porcentos é a soma das três partes. A assinatura da 4.2 foi corrigida acima.
- **`linhaDoTempo.js`** ganhou `diaDoPonto(ponto)` além das três funções da 7.1; `posicaoDoDia` devolve o centro da célula, `(dia - 0.5) / dias`.
- **`contraste.js`** tem `razaoDeContraste`, `lerTokens` e `tokensDoTema`. `tests/js/contraste.test.js` tem `const PARES` no topo (hoje os 5 pares de texto do Talão, mínimo 4,5) e percorre `resources/css/temas/*.css` **se a pasta existir**. Quem criar tokens novos (3.2) amplia os `PARES`; um par com `#FFFFFF` literal só serve para tema de superfície branca, para os demais usar o token da superfície.
- `tests/js/recebidoPorDia.test.js` (6.1) **não** foi feito: o bloco "Recebido por dia" está fora desta execução.
