# Fase 29: temas visuais escolhidos pelo usuário (plano)

> **Para quem executa:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development` (recomendado) ou `superpowers:executing-plans`.
> **Não implementar sem o comando do Marcos.** Plano escrito em 2026-11-06; todas as tarefas estão **pendentes**.

**Objetivo:** o usuário clica no próprio nome, abre um painel lateral à direita e escolhe a aparência do sistema entre 6 temas, altera a senha e sai. A escolha fica gravada na tabela do usuário.

**Arquitetura:** um tema é a soma de três camadas, todas escolhidas por `users.tema`. (1) **Tokens**: variáveis CSS sob `html[data-tema='<id>']` (cores, fontes, cantos, sombras, cores das etapas). (2) **Casca**: menu lateral ou menu superior, no mesmo `CamadaApp.vue`. (3) **Composição do Painel**: 4 componentes (`empilhado`, `colunas`, `mosaico`, `regua`) que reutilizam as listas e as ações que já existem. O tema atual continua sendo o primeiro tema.

**Tecnologia:** Laravel 13, Inertia v3, Vue 3, Tailwind v4 (`@theme`), fontes Bunny via `laravel-vite-plugin/fonts`, PHPUnit, `node --test`.

**Spec:** não há documento à parte; os requisitos estão na seção 1 e os protótipos aprovados estão em `docs/` (seção 2).

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

### Tarefa 1.1: enum, `.env`, colunas e modelo. Estado: pendente
- **Criar** `TemaVisual` (`string`): cada caso entra na tarefa do seu tema; **começa só com `Talao = 'talao'`**. Métodos: `rotulo(): string`, `descricao(): string`, `casca(): string` (`lateral|superior`), `painel(): string` (`empilhado|colunas|mosaico|regua`), `comLinhaDoTempo(): bool`, `amostra(): array` (4 hex para a miniatura), `fontes(): array`, `static padrao(): self` (lê `config('temas.padrao')`, cai em `Talao` se inválido), `static paraOFront(): array`.
- **Criar** `config/temas.php` com `'padrao' => env('TEMA_PADRAO', 'talao')`; documentar `TEMA_PADRAO=talao` no `.env.example`.
- **Migração:** `tema` string(20) NOT NULL default `'talao'` e `novidade_temas_vista` boolean NOT NULL default `false`. Os usuários antigos ficam com o visual atual e com o popup pendente.
- **`User`:** cast de `tema` para o enum; `novidade_temas_vista` boolean; **fora do `#[Fillable]`**; `booted()` com `creating` que faz `tema ??= TemaVisual::padrao()` e `novidade_temas_vista ??= true` (usuário novo não vê o popup).
- **Testes** (`tests/Feature/Temas/TemaDoUsuarioTest.php`): `test_usuario_novo_nasce_com_o_tema_do_env` (`config(['temas.padrao' => ...])`), `test_env_invalido_cai_no_talao`, `test_usuario_novo_nasce_sem_novidade_pendente`, `test_cast_devolve_o_enum`, `test_tema_nao_e_preenchivel_em_massa`.
- **Banco:** antes do `migrate`, mostrar alvo, `migrate:status`, contagem e comando literal; só então `php artisan migrate`.

### Tarefa 1.2: compartilhar no Inertia e no HTML. Estado: pendente
- `HandleInertiaRequests`: `auth.user.tema` (valor, com fallback `Talao`), `temas` (`TemaVisual::paraOFront()`), `mostrarNovidadeTemas` = apresentação já vista **e** novidade não vista **e** mais de um caso no enum.
- `app.blade.php`: `<html data-tema="...">` do usuário logado, `talao` para visitante.
- **Testes** (`tests/Feature/Temas/CompartilhamentoDoTemaTest.php`): `test_html_traz_data_tema_do_usuario`, `test_visitante_recebe_talao`, `test_tema_corrompido_no_banco_vira_talao`, `test_novidade_nao_aparece_com_um_unico_tema`.

### Tarefa 1.3: rotas de preferência. Estado: pendente
- `PUT /perfil/tema` → `PerfilController@tema(TemaRequest)`; `POST /perfil/novidade-temas` → `PerfilController@novidadeDosTemas` (mesmo molde de `/perfil/apresentacao`). Ambas no grupo `auth` + `conta.aprovada`.
- `TemaRequest`: `tema` obrigatório, `Rule::enum(TemaVisual::class)`; mensagens em pt-BR.
- **Testes** (`TrocaDeTemaTest.php`): `test_troca_para_tema_valido`, `test_recusa_tema_inexistente`, `test_visitante_vai_para_o_login`, `test_conta_pendente_nao_troca`, `test_troca_nao_altera_outros_campos`, `test_marca_novidade_como_vista`.

## Fase 2: painel do usuário (Marco 1)

### Tarefa 2.1: alterar senha (back-end). Estado: pendente
- `PUT /perfil/senha` → `SenhaController@atualizar(AlterarSenhaRequest)` com `throttle:6,1` (como as rotas que pedem a senha, `routes/web.php:98`). Campos: `senha_atual` (`current_password`, mensagem "Senha incorreta."), `nova_senha` (`confirmed`, `Password::min(8)`, `different:senha_atual`).
- Registrar `Auditoria::registrar(..., 'usuario.senha_alterada', $usuario, 'Senha alterada')` **sem antes/depois**. `[A VERIFICAR]`: o PHPDoc de `registrar` cita só `Cobranca|Contrato` como alvo, e `alvo_tipo` sai de `class_basename`; conferir se a tela de Auditoria aceita `usuario.*` e dar rótulo.
- **Testes** (`AlterarSenhaTest.php`): `test_altera_com_a_senha_atual_correta` (`Hash::check`), `test_recusa_senha_atual_errada` (erro `senha_atual`, senha intacta), `test_recusa_confirmacao_diferente`, `test_recusa_menos_de_8`, `test_recusa_igual_a_atual`, `test_limita_tentativas` (7ª chamada = 429), `test_auditoria_nao_guarda_a_senha` (nenhuma coluna de `auditorias` contém a senha nem o hash), `test_visitante_vai_para_o_login`.

### Tarefa 2.2: painel lateral, nome do usuário e "Sair". Estado: pendente
- `PainelDoUsuario.vue` (props `aberto: Boolean`, emite `fechar`): `role="dialog"`, `aria-modal`, largura `min(26rem, 100%)`, entra pela direita em 220 ms, foco no primeiro controle, **armadilha de foco**, Esc e clique no fundo fecham, devolve o foco ao gatilho. Cabeçalho com nome e e-mail; botão **Sair** (`router.post('/sair')`).
- Gatilho: botão com o nome no rodapé do menu lateral e no cabeçalho de celular do `CamadaApp.vue`; remove o "Sair" do menu (seção 7, item 2).
- `armadilhaDeFoco.js`: `proximoDaArmadilha(total: number, indice: number, voltar: boolean): number`.
- **Testes** (`tests/js/armadilhaDeFoco.test.js`): `volta_ao_primeiro_depois_do_ultimo`, `vai_ao_ultimo_com_shift_no_primeiro`, `lista_vazia_devolve_menos_um`. Smoke test: abrir, Esc, foco devolvido, 390 px.

### Tarefa 2.3: formulário de senha no painel. Estado: pendente
- `FormularioDeSenha.vue`: seção recolhível "Alterar senha" com senha atual, nova e confirmação; "Salvar nova senha"; sucesso "Senha alterada." limpa os campos; erro aparece junto do campo e o foco vai ao primeiro inválido; 429 mostra "Muitas tentativas. Aguarde um minuto e tente de novo."
- **Teste** (`tests/Feature/Temas/PainelDoUsuarioPropsTest.php`): `test_props_trazem_nome_email_e_tema_sem_senha`. Smoke test: erro de senha atual, sucesso, 429.

### Tarefa 2.4: seletor de tema no painel. Estado: pendente
- `SeletorDeTema.vue`: grupo `role="radiogroup"`, um cartão por tema (nome, descrição, miniatura com `amostra`), marca o atual. **Escondido enquanto o enum tiver um só caso.** Ao escolher: aplica `data-tema` na hora e `router.put('/perfil/tema', ..., { preserveScroll: true, preserveState: true })`; se falhar, volta ao anterior e mostra "Não foi possível trocar o tema." com "Tentar de novo".
- `usarTema.js`: `usarTema()` devolve `{ atual, lista, trocar(id) }`; função pura `temaValido(id: string, lista: Array): string` (cai no primeiro da lista).
- **Testes** (`tests/js/usarTema.test.js`): `id_desconhecido_cai_no_primeiro`, `id_valido_e_mantido`.

## Fase 3: fundação visual (Marco 2)

### Tarefa 3.1: inventário e prova técnica. Estado: pendente
- Provar com o build que as utilidades do Tailwind v4 usam `var(--color-*)` (então sobrescrever sob `html[data-tema]` funciona). Contar `bg-white`, `text-white`, `rounded-*`, `shadow-*`, cores soltas (`#...`, `style=`) nas 33 telas/componentes. Anotar o resultado numa seção "Inventário" deste plano. Também ler a skill `recibos` inteira antes de mexer (regra do projeto).
- **Verificação:** o comando e a saída colados no plano.

### Tarefa 3.2: tokens semânticos, Talão idêntico. Estado: pendente
- Em `@theme`: `--color-superficie`, `--color-sobre-acao`, `--color-menu-fundo|texto|ativo|linha`, `--color-etapa-enviar|aguardando|recebida`, `--raio-controle`, `--raio-bloco`, `--sombra-bloco`, `--font-titulo`, `--font-numeros`; valores do Talão = aparência de hoje. Trocar os usos de `bg-white` por `bg-superficie` e os `rounded-sm` dos controles por `--raio-controle`. `color-scheme` por tema.
- **Verificação:** capturas antes/depois das telas principais no Talão sem diferença visível (browser-agent); testes PHP e JS verdes.

### Tarefa 3.3: fontes por tema. Estado: pendente
- `[A VERIFICAR]` com `search-docs`: se `@fonts` aceita carregar só as famílias do tema ativo. Plano B: declarar todas em `vite.config.js` e injetar só o `<link>` do tema no Blade a partir de `TemaVisual::fontes()`. Meta: usuário do Talão não baixa as fontes dos outros temas.
- **Teste** (`FontesDoTemaTest.php`): `test_cada_tema_declara_fontes_e_arquivo_css` (cada caso do enum tem `resources/css/temas/<valor>.css`, exceto `talao`).

### Tarefa 3.4: cascas e menu responsivo. Estado: pendente
- `menuPrincipal.js`: `itensDoMenu(props, admin: boolean): Item[]` (a lista que hoje está dentro do `CamadaApp.vue`). `menuResponsivo.js`: `quantosCabem(larguras: number[], disponivel: number, larguraMais: number): number` (a regra medida do protótipo: o que não cabe vai para "Mais ▾", nunca fica escondido).
- `MenuLateral.vue` e `MenuSuperior.vue` trocam **dentro do mesmo `CamadaApp.vue`**: `<main><slot/></main>` não muda de lugar na árvore (foco de revisão 1).
- **Testes** (`tests/js/menuPrincipal.test.js`, `menuResponsivo.test.js`): `admin_ve_usuarios_com_contador`, `item_bloqueado_segue_o_progresso`, `todos_cabem_nao_mostra_mais`, `sobra_vai_para_o_mais`, `nunca_deixa_zero_itens_na_barra`. Smoke test: digitar num campo de Cobranças, trocar de casca, o campo mantém o valor.

### Tarefa 3.5: guarda de contraste. Estado: pendente
- `contraste.js`: `razaoDeContraste(corA: string, corB: string): number` (WCAG). Teste que lê os tokens de `app.css` (`@theme`) e de `resources/css/temas/*.css` e exige ≥ 4,5 nos pares: texto/fundo, texto/superfície, sobre-ação/ação, cada tinta/superfície, cada cor de etapa/superfície, menu-texto/menu-fundo e menu-texto/menu-ativo.
- **Testes** (`tests/js/contraste.test.js`): `preto_sobre_branco_e_21`, `cores_iguais_dao_1`, `todos_os_temas_passam_nos_pares_de_texto`.

## Fase 4: Painel em colunas, Fluxo, Livro-caixa e popup (Marco 2)

### Tarefa 4.1: extrair o Painel atual e despachar por tema. Estado: pendente
- Mover o corpo de `Pages/Painel.vue` para `Components/Painel/PainelEmpilhado.vue` sem mudar nada visível; `Painel.vue` escolhe o componente por `temaAtual.painel`. `temas.js`: `componenteDoPainel(painel: string): string` (desconhecido = `empilhado`).
- **Testes:** `tests/js/temas.test.js` (`painel_desconhecido_usa_empilhado`); `PainelTest` inteiro continua verde.

### Tarefa 4.2: `PainelColunas` e barra do mês. Estado: pendente (futuro, fora da execução de 2026-11-07)
- `ListaDoPainel.vue` passa a emitir `resumo` (`{ lista, quantidade, soma_centavos }`); `PainelColunas.vue` junta as três listas e o `BlocoVencendo` e alimenta `BarraDoMes.vue`.
- `fatiasDoMes({ previsto, recebido, aguardando, paraEnviar }): Array<{ chave, centavos, porcento }>` (maior resto: soma 100; `previsto` 0 devolve lista vazia).
- **Testes** (`tests/js/fatiasDoMes.test.js`): `porcentos_somam_100`, `previsto_zero_nao_divide_por_zero`, `fatia_zerada_continua_na_lista`.

### Tarefa 4.3: tema Fluxo. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `Fluxo` no enum; `resources/css/temas/fluxo.css` (paleta e fontes do protótipo); casca lateral azul-marinho; painel `colunas`. Efeitos: barra enche na abertura e números contam, número que muda pisca (`.destacar`), carimbo PAGO na baixa, vencida pulsa no máximo 3 vezes, aviso na tela com linha do tempo. Tudo desligado em `prefers-reduced-motion`.
- **Testes:** `contraste.test.js` passa com o tema novo; `test_tema_fluxo_e_aceito_na_troca` e `test_html_traz_data_tema_fluxo` (PHP). Smoke test em 1440 e 390.

### Tarefa 4.4: tema Livro-caixa. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `LivroCaixa`; `livro-caixa.css` (verde-tinta, tintas de caneta/ocre/cédula/carimbo, `color-scheme: dark`); barra do mês como **régua de tinta**; carimbo PAGO com mancha; traço de caneta ao mudar de etapa (CSS).
- Pendente da seção 7, item 3 (cor do botão principal).
- **Testes:** contraste passa; `test_tema_livro_caixa_e_aceito_na_troca`. Smoke test: campos de data e select legíveis no escuro.

### Tarefa 4.5: popup da novidade para usuários antigos. Estado: pendente
- `ModalNovidadeTemas.vue` (usa `Modal.vue`), renderizado no `CamadaApp.vue` quando `mostrarNovidadeTemas`: título "Agora você escolhe a aparência do sistema", texto "Clique no seu nome e escolha entre N temas. Também dá para alterar a senha ali.", miniaturas, botões **"Escolher meu tema agora"** (marca como visto e abre o painel do usuário já na aparência) e **"Agora não"**. Fechar por Esc ou fundo também marca como visto (`POST /perfil/novidade-temas`). Depois de "Agora não", o botão do nome pulsa até 3 vezes para mostrar onde fica.
- **Testes** (`NovidadeDosTemasTest.php`): `test_antigo_com_apresentacao_vista_ve_a_novidade`, `test_antigo_sem_apresentacao_vista_nao_ve`, `test_novo_usuario_nao_ve`, `test_some_depois_de_marcada_como_vista`, `test_nao_aparece_com_um_unico_tema`.

## Fase 5: dados do mês (Marco 3)

### Tarefa 5.1: `GET /painel/mes`. Estado: pendente
- `PainelController::mes` devolve `{ mes, hoje, dias, feriados: { dia: nome }, fim_de_semana: number[], pontos: Array<{ id, cliente, valor_centavos, vencimento_dia, etapa, situacao, pago_dia, pago_centavos }>, vencidas: { quantidade, soma_centavos, itens }, hoje_resumo: { vence_hoje, para_avisar, para_enviar }, anteriores: { quantidade, soma_centavos } }`. Vencidas e anteriores incluem meses passados; usa `DiasUteis` para feriados e fins de semana; carrega o cliente de uma vez (sem N+1).
- **Testes** (`tests/Feature/Temas/PainelMesTest.php`): `test_so_traz_cobrancas_do_usuario_logado` (foco 3), `test_vencida_de_mes_anterior_entra_em_vencidas_e_anteriores`, `test_pago_fora_do_mes_nao_entra_nos_pontos`, `test_marca_feriado_e_fim_de_semana`, `test_numero_de_consultas_nao_cresce_com_as_cobrancas`.

### Tarefa 5.2: `GET /painel/cobrancas/{cobranca}`. Estado: pendente
- Devolve a mesma linha (`paraLista($hoje)`) que a lista usa, sob demanda, para o ponto clicado abrir as ações sem repetir lógica.
- **Testes:** `test_devolve_a_linha_da_propria_cobranca`, `test_cobranca_de_outro_usuario_da_404`, `test_visitante_vai_para_o_login`.

## Fase 6: Mosaico (Marco 3)

### Tarefa 6.1: `PainelMosaico` e blocos. Estado: pendente
- Blocos: Dinheiro (anel SVG com `fatiasDoMes`), Vencidas, Hoje, Semana à frente, Recebido por dia, e a lista com abas deslizantes (Enviar, Aguardando, Recebidas, com contagem). As três listas ficam montadas; só a aba ativa aparece.
- **Testes:** `fatiasDoMes` reutilizado; `tests/js/recebidoPorDia.test.js` (`agrupa_pagamentos_por_dia`, `mes_sem_pagamento_devolve_zeros`). Smoke test em 1440, 1100 e 390 (ordem dos blocos no celular: dinheiro, hoje, vencidas, lista, semana, recebido).

### Tarefa 6.2: tema Mosaico. Estado: pendente (futuro, fora da execução de 2026-11-07)
- Caso `Mosaico`; `mosaico.css`; efeitos: anel preenche, carimbo PAGO com pulso do anel, barra do dia cresce, confete discreto ao esvaziar uma etapa.
- **Testes:** contraste; `test_tema_mosaico_e_aceito_na_troca`.

## Fase 7: Régua (Marco 4)

### Tarefa 7.1: funções puras da linha do tempo. Estado: pendente
- `linhaDoTempo.js`: `empilharPontos(pontos): Array<{ ponto, nivel }>` (mesmo dia sobe um nível), `acumuladoPorDia(pontos, dias: number): number[]` (régua de dinheiro), `posicaoDoDia(dia: number, dias: number): number` (0 a 1).
- **Testes** (`tests/js/linhaDoTempo.test.js`): `dois_no_mesmo_dia_empilham`, `acumulado_so_soma_recebidos`, `mes_de_28_dias`, `mes_de_31_dias`, `sem_pontos_devolve_zeros`.

### Tarefa 7.2: `LinhaDoTempoDoMes.vue`. Estado: pendente
- Eixo de dias (fim de semana sombreado, feriado hachurado, "Hoje"), pontos como botões na ordem de tabulação com setas, pílulas com a cor da etapa e valor legível (≥ 12 px), vencida em vermelho com "!", marcador "Anteriores", régua de dinheiro com o previsto pontilhado. Clicar num ponto abre um popover que busca a linha (5.2) e mostra a ação principal com os componentes existentes. Foco e hover ligam ponto e linha da lista nos dois sentidos. Em tela estreita rola dentro do próprio bloco, já mostrando "hoje".
- **Testes:** as funções puras da 7.1; `test_ponto_de_outro_usuario_nao_abre` (a busca da 5.2 devolve 404 e o popover mostra "Não foi possível abrir esta cobrança." com "Tentar de novo"). Smoke test: Tab percorre os pontos, setas movem, Esc fecha o popover.

### Tarefa 7.3: `PainelRegua` e tema Régua. Estado: pendente
- Caso `Regua`; `regua.css`; casca **superior** com o menu "Mais ▾" (`menuResponsivo`, tarefa 3.4); abertura: linha se desenha, pontos caem; baixa: ponto salta até o dia do pagamento.
- **Testes:** contraste; `test_tema_regua_e_aceito_na_troca`. Smoke test em 1920, 1280, 860, 390 e 320: nenhum item do menu fica escondido e a página não rola de lado.

## Fase 8: Almanaque (Marco 4)

### Tarefa 8.1: Mosaico com linha do tempo. Estado: pendente
- Caso `Almanaque`: `PainelMosaico` com `comLinhaDoTempo` (sem os blocos "Semana à frente" e "Recebido por dia"; `LinhaDoTempoDoMes` em largura total entre a fileira de cima e a lista). `almanaque.css` herda os tokens do Mosaico. A página nunca rola sozinha ao dar baixa.
- **Testes:** contraste; `test_tema_almanaque_e_aceito_na_troca`.

## Fase 9: fechamento

### Tarefa 9.1: conferência geral. Estado: pendente
- Matriz **6 temas × telas logadas × 1440 e 390** com o browser-agent: sem erro de console, sem rolagem horizontal, sem texto ilegível, modais (`Modal`, `ModalBoasVindas`, popup, `CoachDeConfiguracao`) acima do painel lateral e legíveis. Repetir a prova do foco de revisão 1. Suíte completa: PHP, JS e `npm run build`.

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
