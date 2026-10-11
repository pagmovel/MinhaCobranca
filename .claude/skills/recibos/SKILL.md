---
name: recibos
description: Use ao trabalhar no sistema de cobranças e recibos (Laravel 13 + Inertia v3 + Vue 3 + dompdf) — regras de domínio, decisões, pegadinhas e acertos.
---

# Sistema de cobranças e recibos

Spec: `docs/superpowers/specs/2026-10-09-sistema-recibos-design.md`
Plano: `.claude/plans/2026-10-09-sistema-recibos.md`

## Padrões de usabilidade (obrigatórios — prioridade do usuário)
**Foco principal do produto (palavras do usuário, 2026-10-10): facilidades de uso.** "Não é legal quando estamos numa
tela de lançamentos e precisamos sair perdendo tudo que já fizemos porque faltou cadastrar outra coisa em outra tela."
O usuário espera que eu **antecipe** esses atritos e **padronize** a solução em todas as telas parecidas — não esperar
ele apontar. Ao criar ou mexer em qualquer tela, conferir esta lista:

1. **Nunca mandar sair da tela para cadastrar o que falta.** Cadastro rápido em modal, o registro volta já escolhido e
   o formulário de fundo fica intacto (cliente, produto/serviço, chave PIX já fazem isso — `CampoCliente`,
   `CadastroRapido*.vue`, `Modal.vue`, `enviarJson`). Formulário não bloqueia por falta de cadastro prévio.
2. **Mensagem de erro/trava sempre oferece a saída** (o botão que resolve, ali mesmo). Nunca "faça X" sem X na tela.
   Ação escondida (ex.: encerrar por checkbox no rodapé) é defeito.
3. **Autosugestão:** lista os primeiros ao focar/clicar, indicador girando no canto direito enquanto busca, opção
   "+ Cadastrar «texto»", teclado (setas/Enter/Esc), não reabre depois de sair do campo (`BuscaAutosugestao.vue`).
4. **Máscaras em todo campo com formato** (diretiva `v-mascara`, `resources/js/mascaras.js`: valor, documento
   CPF/CNPJ, cpf, telefone, cep, linhaDigitavel, minusculas; colar valor usa `valorColado`). Servidor continua
   normalizando e validando.
5. **Animações** (o usuário pediu: "encanta os usuários"): transições curtas em inputs/selects/botões, listas
   (`TransitionGroup name="lista"`), avisos e listas de sugestão (`surgir`), modais (`modal`), troca de tela
   (`.aparecer`), número que mudou (`.destacar`). Só CSS/Vue, sem biblioteca; `prefers-reduced-motion` desliga.
6. **Valor negociado:** preço do catálogo é sugestão; o foco vai para o preço ao incluir item.
7. **Filtros aplicam ao escolher** (select/mês), campo nunca fica bloqueado sem explicação.
8. **Edição na própria linha** (lista com edição rápida abre o formulário dentro da linha clicada) e **ação destrutiva
   com cara própria** (`botao-perigo` / `botao-perigo-confirmar`, confirmação no segundo clique).
9. **Ação em lote mostra a prévia antes** (o que muda, quanto, o que fica) e só então confirma.
10. **390 px sem rolagem horizontal** (fieldset com `min-w-0`; contêiner de tabela `relative overflow-x-auto`).
11. **Largura da área de conteúdo** (pedido do usuário em 2026-10-11, "está desperdiçando muito espaço à esquerda e à
    direita"): o `<main>` do `CamadaApp.vue` ocupa toda a largura ao lado do menu, **sem `mx-auto` e sem `max-w`**, com
    `min-w-0` (ele é item de grid `13rem_1fr`; sem `min-w-0` uma tabela larga estica a página). Listas e tabelas usam a
    largura toda; **formulários e textos continuam em `max-w-3xl`** (linha de leitura). Não voltar a centralizar em
    `max-w-5xl`. *Pegadinha:* a primeira tentativa limitou a `max-w-[96rem]` (1536 px) e numa tela de 1920 px ainda
    sobrava ~170 px à direita — o usuário recusou; nenhum limite.
12. **Listas em tabela** (direção aprovada em 2026-10-11, modelos em `MATERIAIS/tables-m0*.html` — só a estrutura, nunca
    a paleta azul, fotos externas ou ícones do Google): colunas alinhadas, valor à direita em `tabular-nums`, **uma
    ação principal por linha conforme a situação** e as demais num menu "⋯", formulário da ação numa faixa abaixo da
    linha, abas de situação com contagem e soma, ordenação pelo cabeçalho; em 390 px a tabela vira cartões.
    **Lista longa carrega por ajax, com paginação e totais** (Fase 19, aprovado em 2026-10-11):
    - endpoint JSON só de leitura que devolve um **bloco de 4 páginas** e os totais do resultado inteiro;
    - o navegador guarda as páginas por filtro e desenha só a atual;
    - paginador acima e abaixo da tabela;
    - rodapé de totais;
    - esqueleto de linhas na primeira carga.

    **Desde a Fase 20, vale para todas as listas** (cobranças, clientes, contratos, produtos e serviços, auditoria,
    usuários, painel e relatórios). Filtros: selects, datas e autosugestão aplicam na hora; texto 400 ms depois de
    parar de digitar ou no Enter (`adiar`); "Limpar filtros" é um link para a tela sem parâmetros e só aparece quando
    algum filtro difere do padrão (`filtrosAlterados`). Tela nova com lista: usar `usarListaPaginada` +
    `EstadoDaLista` + `Paginador` + `BlocoDePaginas`.

    Ver "Fase 19" e "Fase 20" nos Acertos.

Detalhes, pegadinhas e testes de cada item estão nos "Acertos" abaixo (Fase 17 e anteriores).

## Módulos do produto (SaaS por planos de assinatura — definição do usuário em 2026-10-10)
Cada módulo é **vendável sozinho**: não depende de outro para funcionar; integrações entre módulos são opcionais e
degradam com elegância. Liberação de módulos por plano ainda **não existe** (não construir sem pedido).

| Módulo | Escopo (palavras do usuário) | Situação |
|---|---|---|
| **Cobrança** | cadastro de clientes, contratos, emissão e controle de cobranças (recibos, envio, baixa, PIX/boleto), produtos e serviços, dashboards, relatórios | em construção (clientes, contratos, cobranças e recibos prontos; produtos/serviços, dashboards e relatórios a desenhar) |
| **Contas a Pagar** | cadastro de fornecedores, clientes, produtos e serviços vinculados aos fornecedores, dashboards, relatórios | a desenhar |
| Notas fiscais | tabelas que espelham a NFS-e, importação de XML (ABRASF 2.02 e nacional) | planejado |
| Integração com emissores | sincronizar notas emitidas e emitir NFS-e (opcional) | planejado |

**Cadastros compartilhados:** produtos e serviços (na Cobrança: o que é vendido, com códigos fiscais e preço; no
Contas a Pagar: vinculados a fornecedores, com custo). **Clientes são do módulo Cobrança**: quem compra só Contas a
Pagar não cadastra clientes (confirmado pelo usuário em 2026-10-10). Com os dois módulos, a despesa do Contas a Pagar
pode ser **atribuída a um cliente** (campo só aparece se Cobrança estiver contratado).
**Rentabilidade** (receita − custo mensal): com os dois módulos, sai **por cliente e por produto/serviço**; com um só,
o relatório mostra a parte que existe.
**Base comum** (fora dos módulos): contas e aprovação pelo admin, perfil do prestador, chaves PIX.

## Regras de domínio
- Cobrança nasce com o recibo (mesma transação), antes do vencimento (`users.dias_antecedencia`).
- Recibo = snapshot congelado (prestador, tomador, serviço) + modelo com versão (`webiss-v1`; desde a Fase 21 os
  novos saem em `webiss-v2`/`danfse-v2`).
  Situação, data de pagamento e NFS-e vinculada são lidas na hora de gerar o PDF.
- Modelo é do cliente; trocar vale só para recibos futuros.
- Numeração por usuário e ano; número cancelado não volta.
- Dinheiro em centavos (`integer`).

## Decisões (não reverter sem falar com o usuário)
- Inertia + Vue + dompdf (sem API, sem Chrome no servidor).
- Geração de cobranças "preguiçosa" ao abrir painel/cobranças; sem cron.
- Isolamento por relação do usuário + `findOrFail` (404), não por policy.
- Recibo não imita identidade oficial: sem brasão, sem "NFS-e", sem chave/QR, com aviso
  "Este recibo não substitui a Nota Fiscal de Serviços Eletrônica."
- `MATERIAIS/` fora do git (NFS-e reais com dados pessoais do usuário) — decisão do usuário.
- Documentação (estado no plano + esta skill) atualizada a cada tarefa, no commit da tarefa.
- Trabalho no branch `feat/sistema-recibos`; commit por tarefa autorizado pelo usuário.

## Pegadinhas
- `pdftotext` instalado é xpdf 4.00: sem `-bbox-layout`. Coordenadas dos PDFs de referência
  foram extraídas com `pypdf` (`extract_text(visitor_text=...)`).
- A ferramenta Read não renderiza PDF nesta máquina (falta `pdftoppm`); Chrome DevTools não abriu
  PDF local (timeout). Para ver o visual, pedir print ao usuário. Texto e fontes de um PDF gerado
  podem ser conferidos com `pypdf` (extract_text + `/Resources/Font`).
- Inertia está na **v3** (inertia-laravel v3.5.1, @inertiajs/vue3 3.9): root view com
  `<x-inertia::head>`/`<x-inertia::app>`, `Inertia::lazy()` removido (usar `optional()`),
  `config/inertia.php` reorganizado (`pages`, `testing`).
- Laravel 13: models usam atributos `#[Fillable]`/`#[Hidden]`; `config/app.php` tem `timezone`
  fixo (sem variável de ambiente).
- CNPJ alfanumérico em vigor desde julho/2026 (Receita Federal). DV: módulo 11 com valor
  ASCII − 48 por caractere. Exemplo oficial: `12ABC34501DE` → DV `35`.
- `withoutVite()` nos testes também neutraliza a diretiva `@fonts`.
- Bunny Fonts serve Archivo e Archivo Narrow estáticas (sem eixo de largura): rótulos usam
  Archivo Narrow em vez de "Archivo semicondensada".
- `boost:install` sem interação: usar `-n --guidelines --skills --mcp`; instala para Claude Code,
  Codex, Cursor e OpenCode. As guidelines exigem `vendor/bin/pint --dirty --format agent` após
  mudar PHP — o Pint troca nome totalmente qualificado por `use` (ex.: `bootstrap/app.php`).
- Apagar `tests/Unit/ExampleTest.php` remove a pasta `tests/Unit`, que o `phpunit.xml` exige:
  manter `tests/Unit/.gitkeep`.
- Scripts `task-start`/`task-done` do superpowers procuram títulos "Task N"; o plano usa
  "Tarefa" — controlar BASE e ledger manualmente.
- laravel-dompdf: `stream()` responde `Content-Type: application/pdf` e
  `Content-Disposition: inline`; `setPaper` vem por `__call`.

- `php artisan migrate --pretend` **não é só leitura**: sem a tabela `migrations`, ele a cria antes
  de simular (`MigrateCommand.php:140-146`). Leitura segura: `db:show`, `db:show --counts`,
  `migrate:status` (este só avisa "Migration table not found").
- O banco de desenvolvimento já veio com as 3 migrações padrão aplicadas (lote 1) da instalação
  básica do usuário.

- O usuário mexe no banco em paralelo: entre a autorização e o `migrate` da T1.6, `users` passou
  de 0 para 1 (ele se cadastrou pela tela). Recontar **em comando separado** imediatamente antes
  da escrita e parar se mudou. Migração aditiva: conta existente ganha `status=pendente`.
- Administrador do sistema = `users.is_admin` (único nível; o usuário chama de "superadmin").
  Conta do usuário: id 1, `marcos.ronaldo@gmail.com`, promovida em 2026-10-09.

- Default de coluna só existe depois do INSERT + releitura: `User` criado pela factory e usado no
  `actingAs` vinha com `modelo_padrao` nulo (500 no `/perfil`). Solução: `User::$attributes` com os
  mesmos defaults da migração — manter os dois sincronizados.
- `UploadedFile::fake()->createWithContent('x.png', 'texto')` passa na regra `image`: o fake informa
  o tipo pela extensão do nome (`Http/Testing/File.php:134`). Para testar conteúdo, usar
  `new UploadedFile($caminhoReal, 'x.png', null, null, true)`.

- `@vitejs/plugin-vue` transforma `src="/caminho"` estático em `import` e quebra o build
  (`UNRESOLVED_IMPORT '/perfil/logo'`). Corrigido em `vite.config.js` com
  `template.transformAssetUrls: { base: null, includeAbsolute: false }`. Os testes PHP não pegam
  isso: **rodar `npm run build` e parar se falhar antes de commitar**.

- Teste que só afirma `assertSessionHasNoErrors()` passa com 404 (rota inexistente não deixa erro
  na sessão). Sempre afirmar também o redirecionamento ou o estado gravado.

- Teste de isolamento (404 para recurso alheio) passa à toa se a rota ainda não existe. Antes do
  `assertNotFound`, chamar a mesma rota com recurso próprio e afirmar sucesso.

- **Banco migrado para PostgreSQL em 2026-10-09** (pedido do usuário, antes do previsto na Fase 7):
  servidor de desenvolvimento `207.180.205.211`, **porta 1** (a 5432 recusa), PostgreSQL 18.6,
  banco `recibos`, dono e usuário da aplicação `recibos_app` (senha gerada, só no `.env`);
  `REVOKE CONNECT ... FROM PUBLIC`. O mesmo servidor tem o banco `meus_projetos` (dev, de outro
  projeto) — não tocar. O SQLite `database/database.sqlite` ficou como histórico.
- **Segurança do servidor PostgreSQL (pendente, fora do repositório):** conexão **sem SSL** pela
  internet (`pg_stat_ssl.ssl = false`), superusuário `postgres` exposto em IP público. A aplicação
  não usa o superusuário.
- `DB_HOST=meus_projetos_postgres_dev` (nome de container) não resolve fora da rede Docker.
- Sem a tabela `migrations`, **não** rodar `migrate --pretend` (ele cria a tabela). No portão,
  listar os arquivos de migração pendentes.

- Migrações rodaram no PostgreSQL 18.6 sem ajuste (portabilidade confirmada). Latência alta: cada
  migração levou 2–12 s (servidor remoto) — as telas também sentem.
- Sessões ficam no banco (`SESSION_DRIVER=database`): ao trocar de banco, todo mundo precisa
  entrar de novo.

## Acertos
- T0.2: mensagens de validação em `lang/pt_BR/validation.php` só com as regras usadas; o resto cai
  no inglês do framework (`APP_FALLBACK_LOCALE=en`).
- T0.4: PDF de prova em `storage/app/private/verificacao/` (fora do git) — conferível sem
  renderizador, por extração de texto.

- T1.1: `status` e `is_admin` fora do `#[Fillable]` (teste prova que cadastro não se autoaprova);
  factory padrão = aprovado e com perfil completo, com estados `pendente()`, `bloqueado()`,
  `admin()`, `semPerfil()`.
- Testes: `LazilyRefreshDatabase` e `$this->travelTo()` (skill testing-best-practices do Boost).

- T1.2: Inertia v3 cuida do CSRF sozinho (lê o cookie `XSRF-TOKEN` e envia `X-XSRF-TOKEN`); não
  pôr meta `csrf-token` no layout. Nos testes o CSRF é desligado, então erro de token só aparece
  no navegador (419) — conferir no smoke test.

- T1.3: status checado no login **e** no middleware `conta.aprovada` (bloquear derruba quem já
  está logado). Login limitado pelo RateLimiter `login` (5/min, chave e-mail|IP — regra de
  segurança do Boost), não por `throttle:6,1`.

- T1.4: área admin protegida por Gate `admin` + `can:admin`; pendentes primeiro com
  `orderByRaw("case when status = 'pendente' ...")` (SQL padrão, portátil). Padrões numéricos de
  rota (`Route::pattern`) para `cliente`, `contrato`, `cobranca`, `recibo`, `usuario`, `nota`.

- T1.5: `app:criar-admin {email} {--nome=}` pede a senha com `secret` — o shell do agente não
  responde prompt, então **o usuário roda o comando no terminal dele**. Nos testes,
  `expectsQuestion('Senha', ...)` funciona com `secret`.

- Hook de design (impeccable) acusa borda lateral grossa (`border-l-4`) como clichê de UI gerada:
  item ativo do menu usa fundo `bg-papel` + texto `text-carbono`, sem faixa lateral.

- T2.1: `Documento` valida CPF e CNPJ (numérico e alfanumérico) com o mesmo cálculo `ASCII − 48`;
  documento guardado normalizado (só `[0-9A-Z]`, maiúsculas, 14 caracteres no máximo).
- Menu mostra itens de fases futuras; até a tarefa deles ficar pronta, dão 404 — o usuário
  estranhou na Fase 1. Avisar ao entregar fases parciais.

- T2.2: logo no disco `local` (privado) em `logos/{user_id}/<hash>`, servido por `/perfil/logo`;
  arquivos antigos não são apagados (recibos emitidos apontam para eles). Perfil usa `POST` (upload
  multipart com `PUT` não funciona no PHP).

- T2.3: cliente com CPF/CNPJ único **por usuário** (`unique(user_id, documento)` + regra
  `Rule::unique(...)->where('user_id')`); modelo do recibo no cliente, aviso na tela ao trocar.

- T3.1: valor digitado vira centavos por `Dinheiro::paraCentavos` ("1.234,56", "260,6", "R$ 260",
  "260.60"); ambíguo ou negativo → `null` e a regra `ValorEmReais` recusa. Formatação sem float
  (`intdiv`).

- T3.2: período *i* = `data_inicio + i×meses` com `addMonthsNoOverflow`, **sempre a partir da data
  original** (31/01 → 28/02 → 31/03, sem escorregar); vencimento = `dia_vencimento` no mês do início,
  limitado ao último dia; devido quando `hoje >= vencimento − antecedência`. Calculadora é pura
  (recebe `hoje`), testável sem banco nem relógio.

- T3.3: contrato não tem exclusão — encerra desmarcando "ativo". Descrição limitada a 2000
  caracteres e 25 linhas (`LinhasMaximas`) para caber na caixa do recibo A4.

- T3.4: idempotência da geração garantida pelo banco (`unique(contrato_id, periodo_inicio)`;
  avulsas com contrato nulo não colidem). Contrato com cobranças não muda início nem periodicidade
  (geraria períodos sobrepostos); valor e descrição podem mudar. Cliente com contrato/cobrança não
  é excluído (controller + `restrictOnDelete`).

- T3.5: número = `max(numero)` do usuário no ano + 1, dentro da transação; índice único
  `(user_id, ano, numero)` + até 3 tentativas em `UniqueConstraintViolationException`. Snapshot
  lido do banco (`user()->firstOrFail()`), não da relação em cache — senão `refazer` usaria dado velho.

- T3.6: gerador junta os períodos devidos de todos os contratos ativos, ordena por vencimento
  (depois id do contrato) e cria cobrança + recibo um a um em transação; corrida entre abas cai no
  índice único e é ignorada. Perfil incompleto = não gera nada. Nos testes, congelar o relógio no
  mesmo `hoje` passado ao gerador (o emissor usa `now()` para o ano do recibo).

- T3.7: cobrança enviada não é editada (só cancelar + avulsa nova); editar não enviada refaz o
  snapshot com o mesmo número; cobrança de contrato não muda cliente nem período. Baixa só com data
  até hoje. `marcarEnviada` é chamado pelo front depois do compartilhamento/download.

- Seed do perfil inicial (`PerfilInicialSeeder`, pedido do usuário em 2026-10-09): código no git,
  dados em `database/seeders/dados/perfil-inicial.json` **fora do git** (CPF, endereço, hash da
  senha); formato em `perfil-inicial.exemplo.json`. Copia o hash (cast `hashed` mantém hash válido,
  `HasAttributes.php:1509-1518`), então a senha atual continua valendo. Idempotente pelo e-mail.
  `DatabaseSeeder` só chama essa seed — o "Test User" padrão nasceria aprovado com senha `password`.
  Rodar a seed é escrita no banco: PORTÃO-BANCO.

- T4.1: valor por extenso só com inteiros (`intdiv`); "e" antes do último grupo quando ele é menor
  que cem ou centena exata ("mil e dez", "mil e duzentos", mas "mil duzentos e trinta"); milhão exato
  leva "de reais". Casos fixados em `tests/Unit/ValorPorExtensoTest.php` (inclui o valor da nota de
  referência, R$ 260,60).

- T4.2: `RenderizadorDeRecibo::dados()` monta tudo a partir do **snapshot** (prestador/tomador/serviço)
  e lê da cobrança só o que é vivo (situação, `pago_em`); campo vazio vira `-`, como nas notas.
  Logo vai como data URI (o dompdf não lê disco nem rede). `GET /recibos/{recibo}/pdf` responde inline
  com `Recibo::nomeArquivo()`; isolamento por `$request->user()->recibos()->findOrFail()`.
- `Recibo` não tem relação `user` — o dono se alcança por `$recibo->cobranca->user` (ou `user_id`).
- Ao copiar código do plano por script, o nome do arquivo aparece antes na lista "Arquivos" da tarefa:
  ancorar a busca no cabeçalho do bloco (início de linha), senão copia o bloco errado.

- T4.3: `danfse-v1` usa as mesmas chaves do renderizador; o modelo vem do cliente e fica gravado no
  recibo (`recibos.modelo`), então trocar o modelo do cliente não muda PDF já emitido. View ausente
  não derruba a página: o controller registra o erro e volta com `flash.erro`.

- T4.4 (pegadinhas do dompdf, medidas com `pypdf` nas amostras):
  - `table-layout: fixed` com largura só em parte das colunas deixou a coluna livre estreita
    (~170pt): nome e endereço do cabeçalho quebraram em 7 linhas. **Dar largura (%) a todas as colunas.**
  - `line-height` (sem unidade ou em pt) **não vale** para linhas quebradas por `<br>` (`nl2br`): saíram
    com 10,7pt em fonte de 7pt. Descrição do WebISS renderiza **uma `div` por linha com `height` fixa**.
  - Rodapé `position: fixed; bottom: 0` fica dentro da área do conteúdo e se sobrepõe a ele: usar margem
    inferior no `@page` (34pt) e `bottom` negativo (-24pt).
  - Carimbo absoluto foi para a página 2 quando o conteúdo transbordou; cabendo em 1 página, fica no lugar.
  - laravel-dompdf traz `enable_font_subsetting => false`: o WebISS (DejaVu) saía com 860 KB.
    `setOption('isFontSubsettingEnabled', true)` no renderizador → ~30 KB, acentos intactos.
  - Coordenada y com `pypdf` em texto girado: `tm[4]*cm[1] + tm[5]*cm[3] + cm[5]` (sem o termo de
    rotação a posição do carimbo sai errada em ~180pt).
  - Teste `AmostrasVisuaisTest` (grupo `visual`) prova 1 página com 25 linhas de descrição nos dois
    modelos e PDF < 200 KB; grava as amostras em `storage/app/private/verificacao/`.
- **A partir da aprovação visual do usuário, mudança em `webiss-v1`/`danfse-v1` só vale antes de existir
  recibo emitido em produção; depois, criar `-v2`.**

- Conferências visuais (amostras x notas, carimbo, rodapé) ficaram **para o final** por decisão do
  usuário (2026-10-10): lista "Pendências para o final" no plano. Não bloquear tarefas por elas.

- T5.1: painel gera as cobranças devidas ao abrir e mostra três blocos: **Para enviar** (pendente, sem
  `enviado_em`), **Aguardando pagamento** (pendente, enviada) e **Recebidas no mês** (`pago_em` no mês).
  Resumo: previsto = não canceladas com vencimento no mês; recebido = parte paga delas (comparáveis).
  `BotaoEnviar` baixa o PDF por `fetch`, abre a folha de compartilhamento (Web Share com arquivo, só
  celular + HTTPS) ou faz download; só marca "enviada" depois disso — fechar a folha (`AbortError`) não
  marca. `NotAllowedError` (o navegador perdeu o "toque" durante o `fetch`) cai no download.
- Aviso de perfil incompleto no painel usa borda inteira (`border`), não `border-l-4` (hook de design).

- T5.2: `/cobrancas` filtra por `mes=AAAA-MM` (padrão: mês atual, pelo **vencimento**), `status` e
  `cliente_id`, sempre pela relação do usuário; abrir a lista também roda a geração preguiçosa. Editar
  cobrança já enviada redireciona para a lista com `flash.erro` (cancelar + avulsa nova).

- T5.3 (smoke test, 2026-10-10, usuário `smoke@recibos.test` id 2): login (sem 419), perfil + logo,
  clientes WebISS/DANFSe, contratos, painel gerando 4 cobranças, Enviar (download + "Aguardando"),
  baixa (PAGO no PDF), filtros, avulsa, edição (número mantido), cancelamento (CANCELADO no PDF),
  teclado com foco visível, 390 px sem rolagem horizontal. Prints em `storage/app/private/verificacao/smoke/`.
- **`php artisan serve` no Windows perdia o upload do logo sem erro**: o `ServeCommand` só repassa ao
  `php -S` as variáveis de `$passthroughVariables` (`ServeCommand.php:82-97`), sem `TEMP`/`TMP`; o PHP
  responde "unable to create a temporary file" (HTML com 200) e o POST chega vazio. Correção no
  `AppServiceProvider` (acrescenta `TEMP` e `TMP`), testada em `ConfiguracaoTest`. Vale também para
  `composer run dev`.
- Shell do agente (git-bash) tem `TEMP=/tmp`: servidor PHP iniciado dali precisa de `TEMP` no formato
  Windows (`C:\Users\...\Temp`).
- Latência do PostgreSQL remoto: páginas levam de 5 a 40 s no `artisan serve` (sessão e cache no banco
  somam idas e voltas). No `browser-use`, `goto_url` estoura o tempo do canal: navegar com
  `js("location.href=...")` e esperar pelo texto.
- `browser-use` (browser-harness) usa `BU_CDP_URL=http://127.0.0.1:9333`: subir um Chrome dedicado
  (`--headless=new --remote-debugging-port=9333 --user-data-dir=<scratchpad>`), nunca o do usuário.
  Campos Vue: setter nativo de `value` + eventos `input`/`change`. Downloads: `Browser.setDownloadBehavior`.
- Web Share: no Chrome desktop `navigator.canShare` existe, mas não aceita arquivo → cai no download
  (esperado). Celular com HTTPS: **[INCONCLUSIVO]** até haver ambiente HTTPS.

- Revisão final (2026-10-10) — correções:
  - **Linha longa sobrepunha texto no WebISS**: o dompdf quebra sozinho com ~10,7pt e ignora `line-height`,
    mas a `div` seguinte começava 8,4pt abaixo. `App\Support\LinhasDoRecibo::quebrar()` quebra a
    descrição em linhas de até 110 caracteres (medidos 137–146 na caixa; folga para maiúsculas), por
    palavra, cortando palavra gigante sem estragar acento (`mb_str_split`). O template desenha uma `div`
    de altura fixa por linha, e `LinhasMaximas` conta **as mesmas** linhas — o que passa na validação
    cabe na caixa. Trocar `height` por `min-height` não serve: todas as linhas passam a 10,7pt e 25
    linhas estouram a página. Pior caso coberto em `AmostrasVisuaisTest`.
  - E-mail em minúsculas (e sem espaços) no cadastro e no login.
  - Editar avulsa **trocando o cliente** refaz o recibo com o modelo do novo cliente
    (`refazer($recibo, trocouCliente: true)`); trocar o modelo do mesmo cliente continua sem mexer em
    recibo emitido.

- T8.1: situação `vence_hoje` (vencimento = hoje) com carimbo laranja `#C2410C` (contraste 5,18:1 sobre
  branco, fórmula de luminância da WCAG); fonte do carimbo menor para caber "VENCENDO HOJE" (22pt no WebISS,
  10pt no DANFSe). A lista de cobranças trata `vence_hoje` como aberta (baixa e cancelar continuam).

- T8.2: máscaras = funções puras em `resources/js/mascaras.js` (testes: `node --test resources/js/mascaras.test.js`,
  runner embutido do Node 22, sem dependência; o Vite não empacota o teste) + diretiva global `v-mascara="'nome'"`
  (`resources/js/diretivas/mascara.js`), que dispara `input` para o `v-model` guardar o valor formatado. O `v-model`
  registra o ouvinte no `created` e a diretiva no `mounted`: a ordem dos atributos no template não importa.
  Valor entra pelos centavos (digitar 260 dá 2,60). O servidor continua normalizando e validando.

- T8.3: `GET /clientes/busca?q=` (2+ caracteres, até 10, `lower(nome) like ? escape '\'` portátil SQLite/PostgreSQL,
  `%` e `_` digitados escapados, prefixo do documento normalizado). `BuscaCliente.vue` = combobox ARIA (setas,
  Enter, Esc; `mousedown.prevent` na opção para o `blur` não fechar a lista antes do clique; digitar limpa a
  escolha). Formulários de contrato/cobrança recebem `temClientes` + `clienteAtual`; a lista recebe `clienteFiltro`.
  `lower()` do SQLite só converte ASCII (acentos contam na busca — limitação aceita).

- T9.1: pós-pago = primeira data com o dia escolhido **estritamente** depois do fim do período (limitada ao último
  dia do mês); pré-pago inalterado (padrão do parâmetro `Modalidade`, então chamadores antigos não mudam).
- Pegadinha repetida na T9.1: extrair código do plano buscando o nome do arquivo pega a lista "Arquivos" da tarefa.
  **Sempre ancorar em `\n`caminho`:`** (cabeçalho do bloco).

- T9.2: `contratos.modalidade` (`prepago`/`pospago`, default `prepago` na migração **e** em `Contrato::$attributes`);
  obrigatória no request (sem default silencioso); não muda se o contrato já tem cobranças; o gerador passa a
  modalidade à calculadora. Formulário explica o vencimento de cada modalidade.

- T10.1: chaves PIX por usuário (`chaves_pix`, único por usuário+chave), normalizadas no enum `TipoChavePix` antes da
  validação de unicidade. **Fonte: Manual Operacional do DICT v8.5 (bcb.gov.br, 29/07/2026), seção 1:** CPF 11 dígitos;
  **CNPJ alfanumérico** com 14 caracteres (mudança de 01/07/2026, v8.4); e-mail até 77 caracteres; **telefone só
  celular**, padrão E.164 (`+55` + DDD + 9 dígitos); aleatória = UUID. Telefone fixo é recusado (o DICT não aceita).
- **Pegadinha (isolamento):** com FormRequest, a validação roda **antes** do `findOrFail` do controller. No PUT de
  recurso alheio, o erro de unicidade respondia 302 e revelava que o recurso existe. `ChavePixRequest::authorize()`
  dá 404 se o id da rota não for do usuário. Usar o mesmo padrão em requests novos com id na rota.

- T10.2: forma de pagamento (`pix`/`boleto`/`nenhuma`) no contrato (copiada para a cobrança na geração) e na
  cobrança, com `chave_pix_id` (FK `restrictOnDelete`) e `linha_digitavel` (só dígitos, 47 ou 48). Trait
  `RegrasDePagamento` compartilhada: `mergeIfMissing` deixa `nenhuma` como padrão (formulários e testes antigos
  continuam válidos), chave só com PIX, linha só com boleto, chave validada com `exists` limitado ao usuário. Chave em
  uso não é excluída (controller + FK). Mensagens próprias em `lang/pt_BR/validation.php` → `custom`.

- T10.3: `PUT /cobrancas/{id}/pagamento` troca só forma/chave/linha de cobrança **pendente**, mesmo já enviada (o PDF
  lê a forma na hora). `PagamentoRequest::authorize()` dá 404 para cobrança alheia antes da validação. Ação
  "Forma de pagamento" (`PagamentoInline`) na lista de cobranças abertas e no bloco "Aguardando pagamento" do painel.
- **Decisão do usuário (2026-10-10) — cadastro de clientes:** preencher automaticamente **só com dados públicos**
  (CNPJ pela base pública da Receita; endereço pelo CEP). **Rejeitado** reaproveitar o cadastro de cliente feito por
  outro usuário: conflita com a LGPD (Lei 13.709/2018, conferida no planalto.gov.br): o usuário é controlador e o
  sistema operador (art. 5º, VI e VII); o operador só trata conforme instruções do controlador (art. 39); finalidade,
  adequação e necessidade (art. 6º, I–III); base legal (art. 7º); proteção contra acesso/comunicação indevida (art. 46).
  Também revelaria a carteira de clientes de outro assinante. Preenchimento por CNPJ/CEP entra na spec do módulo Cobrança.

- T11.1: `App\Support\PixCopiaECola` monta o BR Code estático com valor. **Fontes (conferidas em 2026-10-10):**
  Manual de Padrões para Iniciação do Pix v2.10.0 (bcb.gov.br, 19/08/2026) — exemplo oficial
  `…62070503***63041D3D`, CRC16 polinômio `0x1021` e valor inicial `0xFFFF` calculado **incluindo `6304`**, txid
  (62-05) até 25 caracteres `[A-Za-z0-9]` e `***` quando não há, campo 01 opcional; Manual do BR Code
  (bcb.gov.br/content/estabilidadefinanceira/spb_docs/ManualBRCode.pdf) — 59 nome 01..25 e 60 cidade 01..15
  (obrigatórios), 54 valor `123.99`. Nome e cidade saem em maiúsculas ASCII (o exemplo do manual usa
  "NOME DO RECEBEDOR"). O teste do CRC usa o exemplo oficial; o txid do recibo é `REC` + ano + número (6 dígitos).

- T11.2: `chillerlan/php-qrcode` **v6.0.1** (dependência aprovada pelo usuário; requer `ext-mbstring` e GD). Na v6 a
  saída é `'outputInterface' => QRGdImagePNG::class` (as constantes `outputType`/`GDIMAGE_PNG` da v5 não existem);
  `outputBase64` já vem `true` e devolve data URI. `QrCodePix::dataUri()` gera PNG 265×265 (scale 5, margem 2).
  **Verificação forte:** o QR gerado foi decodificado com OpenCV (`cv2.QRCodeDetector`, instalado na máquina) e é
  idêntico ao copia e cola — usar o mesmo método se mudar algo no QR.

- T11.3: `RenderizadorDeRecibo::dados()['pagamento']` (dado vivo da cobrança; valor, nome e cidade vêm do snapshot;
  txid `REC`+ano+número). WebISS: QR ao lado de TRIBUTOS/VALORES (tabela 78% + coluna 22%) e copia e cola em
  OUTRAS INFORMAÇÕES; DANFSe: bloco PAGAMENTO antes de INFORMAÇÕES COMPLEMENTARES.
- **Pegadinha (T11.3):** com o QR ao lado, as tabelas de valores ficam a 78% da largura, os rótulos das 8 colunas
  quebram e o bloco cresce ~8pt; com o copia e cola em 2 linhas, o WebISS de 25 linhas foi para 2 páginas (e o
  carimbo absoluto junto). Acertos medidos com pypdf: QR 56pt; copia e cola em **uma linha a 4,5pt** (154 caracteres
  ≈ 430pt); OUTRAS INFORMAÇÕES com 14pt mínimos quando há PIX; `@page` com margem inferior 30pt e rodapé a −21pt.
  `AmostrasVisuaisTest` prova 1 página nas 9 combinações (WebISS lista/parágrafo e DANFSe × PIX/boleto/nenhuma).

- Revisão final das fases 8–11 (2026-10-10) — correções:
  - **Máscara de telefone com DDI corrompia a chave PIX**: `+55 (91) 98765-4321` virava `(55) 91987-6543`, e o servidor
    gravava **outra chave** (até ao só editar o apelido). `formatadores.telefone` agora descarta um `55` inicial quando
    há mais de 11 dígitos — corrige também telefones antigos com `+55` no perfil e no cliente.
  - Colar valor ("1.500", "260,6") era lido pelos centavos (15,00 / 26,06): a diretiva usa `valorColado` (mesma regra de
    `Dinheiro::paraCentavos`) quando `inputType === 'insertFromPaste'`.
  - Recibo **pago ou cancelado não mostra como pagar** (`pagamento.forma = null`): o comprovante enviado ao cliente não
    pode ter um QR pagável de novo.
  - Copia e cola em **uma linha só** (`white-space: nowrap`, fonte pelo comprimento: `min(4,5; 520/(n×0,62))` no WebISS,
    `min(5,5; 540/(n×0,62))` no DANFSe em largura total). Quebrar no espaço do nome/cidade perdia o espaço na cópia e
    invalidava o código. Conferido com pypdf: 158 e 223 caracteres (chave mais longa) em 1 linha, inteiros, 1 página.

- T11.4 (smoke, 2026-10-10): fluxo de pagamento conferido de ponta a ponta (ver estado da T11.4 no plano). **QR validado
  pelo usuário no celular.** PDFs e QR extraído em `storage/app/private/verificacao/smoke-pagamento/`.
- Pegadinhas do smoke: (1) o `cv2.QRCodeDetector` padrão falha em QR PIX válido (denso); `cv2.QRCodeDetectorAruco` lê —
  usar o Aruco para conferir. Para tirar o QR do PDF: `pypdf` → `page.images` (a imagem de 265×265). (2) Cookie de sessão
  velho no perfil do Chrome de teste faz a página cair em `/entrar` no meio do roteiro: conferir a URL e entrar de novo.
  (3) `capture_screenshot(full=True)` estourou o tempo do canal do browser-use; usar print só da área visível.
  (4) O usuário mexe no banco em paralelo (cadastrou 4 chaves PIX durante o pedido de autorização): a recontagem do
  portão pegou — parar e pedir de novo.
- QR com margem branca de 4 módulos (padrão do QR Code e da biblioteca; o plano tinha 2). Não muda o conteúdo do código.

- **Usabilidade das chaves PIX (relato do usuário, 2026-10-10):** "Editar" abria o formulário depois do fim da lista e
  quem não tem prática não o via. Agora o formulário abre **dentro da linha clicada** (fundo `bg-papel`), com foco e
  rolagem para o campo "Chave"; a chave nova entra como linha extra no fim da lista (`linhas` computada), então o
  formulário existe num lugar só do template. Esc ou "Cancelar" fecha. Usar o mesmo padrão (edição na própria linha)
  em listas novas com edição rápida. Conferência visual: pendência V5.
- **Ação destrutiva tem cara própria (relato do usuário, 2026-10-10):** "Editar" e "Excluir" eram iguais
  (`botao-secundario`). Classes em `resources/css/app.css`: `botao-perigo` (contorno e texto vermelhos, primeiro clique de
  excluir/cancelar) e `botao-perigo-confirmar` (vermelho cheio, o clique que apaga). Aplicadas em chaves PIX, cliente e
  cancelamento de cobrança. **Toda exclusão ou cancelamento novo usa essas classes**, nunca `botao-secundario`.

- **Decisões do módulo Cobrança (2026-10-10, spec `2026-10-10-modulo-cobranca-catalogo-relatorios-design.md`):** itens
  do catálogo somam o valor do contrato (descrição do recibo continua livre); cobranças copiam os itens; códigos fiscais
  do item principal; contrato guarda o próprio preço (reajuste por ação explícita); custo fica no Contas a Pagar;
  tributos só no módulo de Notas (pesquisa oficial primeiro); **"recebido" = pago no mês (caixa)**, inclusive no painel.
- T12.1: catálogo `produtos_servicos` (único por usuário+nome); preço sugerido pode ser 0
  (`new ValorEmReais(aceitaZero: true)`; `ValorEmReais::MAXIMO_CENTAVOS` público); busca de autosugestão só traz ativos;
  `App\Support\Busca::contem()` (padrão `like` com `%`/`_` escapados) e `App\Support\Paginacao::dados()` + `Paginacao.vue`
  compartilhados pelas listas. Rota com `{produto}` numérico (`Route::pattern`). Resource com `->parameters(['produtos-servicos' => 'produto'])`.
- T12.2: valor do contrato = soma dos itens **recalculada no servidor** (o `valor` enviado pela tela é ignorado;
  `RegrasDeItens::after` recusa total 0 ou acima do máximo); quantidade em milésimos, total `intdiv(q×p+500, 1000)`
  (`App\Support\ItensDeVenda`; `itens.js` faz a mesma conta com BigInt só para exibir); códigos fiscais do item principal
  (o marcado ou, sem marcação, o de maior total); contrato antigo sem itens continua valendo e exige itens ao editar;
  `ContratoRequest::authorize` dá 404 para contrato alheio; item usado em contrato não é excluído (só desativado);
  `BuscaCliente` virou `BuscaAutosugestao` (props `url`, `nenhum`, `limparAoEscolher`; slot `opcao`; evento `escolhido`).
  **Pegadinha:** a regra `numeric` aceita `+1` e `1e3`; a regex `^\d{1,5}(\.\d{1,3})?$` fecha o formato antes de
  `ItensDeVenda::milesimos`.
- T12.3: cobrança guarda **cópia** dos itens (`cobranca_itens`: nome, unidade, quantidade, preço, total, principal;
  `produto_servico_id` só como referência, `nullOnDelete`); a geração copia do contrato, avulsa/edição gravam os
  escolhidos; mudar contrato ou catálogo depois **não** altera cobrança existente; cobrança antiga sem itens continua
  valendo; `CobrancaRequest::authorize` dá 404 para cobrança alheia; item usado em contrato **ou** cobrança não é excluído.
- T12.4: reajuste (`/produtos-servicos/{produto}/reajuste`) só nos contratos **ativos escolhidos** que usam o item;
  recalcula `valor_centavos`; total fora do limite desfaz a transação inteira (`ValidationException` lançada dentro de
  `DB::transaction`); cobranças já geradas não mudam; o preço do catálogo não muda (é só sugestão). Sem FormRequest: o
  `findOrFail` do item vem antes do `$request->validate()`, então item alheio dá 404 antes da validação.
- T13.1: listas paginadas de 25 com `paginate(25)->withQueryString()` (filtros sobrevivem à troca de página e ao link
  copiado); `orderBy('id')` de desempate para a paginação não repetir nem pular registro; escopo `Cliente::comTermo`
  compartilhado entre lista e autosugestão; a prop da lista continua sendo array (testes antigos valem) e a paginação
  vai em `paginacao`.
- T13.2: filtros de contratos (cliente por autosugestão, situação, modalidade, forma de pagamento, produto via
  `whereHas('itens')`); `cliente_id` alheio não traz nada (a consulta parte sempre da relação do usuário).
- T14.1: `/consultas/cnpj/{cnpj}` e `/consultas/cep/{cep}` no servidor, `throttle:30,1` por usuário, cache de 30 dias
  **só de sucesso**, QSA (sócios) descartado, CPF recusado com 422 antes de qualquer chamada; ViaCEP principal e
  BrasilAPI reserva para CEP; o que vem de fora é aparado/cortado (UF conhecida, CEP com 8 dígitos).
  **Pegadinhas:** ViaCEP responde **200** com `{"erro": "true"}` para CEP inexistente; BrasilAPI responde 403 ao
  User-Agent padrão do Python (o do Laravel passa); o CNPJ `11.222.333/0001-81` dos testes antigos é de empresa real —
  nos testes de consulta usar só `Http::fake()` com `Http::preventStrayRequests()`.
- T14.2: `preencherVazios` (`resources/js/consultas.js`) nunca sobrescreve o que a pessoa digitou; CEP automático ao
  completar 8 dígitos (o CEP já salvo não é consultado de novo; o campo CEP subiu para o topo do endereço); botão
  "Buscar dados do CNPJ" (`BuscaCnpj.vue`) aparece com 14 caracteres e a validade é conferida pelo servidor; o perfil
  usa `campo-nome="name"`. `consultar()` nunca lança: falha vira mensagem e o formulário segue manual.
- T15.1: o "recebido" do painel soma as pagas com `pago_em` no mês (regime de caixa, D1), qualquer que seja o
  vencimento; o "previsto" continua pelo vencimento — a frase pode mostrar recebido maior que previsto (esperado).
- T15.2: relatório = tabela genérica (`App\Services\Relatorios`: colunas com tipo e `soma`, linhas em centavos,
  totais) usada pela tela e pelo CSV; CSV com `;`, BOM UTF-8 e `fputcsv(..., ';', '"', '')`; célula que começa com
  `= + - @` ganha apóstrofo (injeção de CSV); período padrão 12 meses, máximo 36; "em atraso no fim do mês" é saldo
  (sem total). **Pegadinhas:** PHP 8.4 deprecia `fputcsv` sem o argumento `escape`; `fputcsv` põe aspas em campo com
  espaço (`"Em atraso no fim do mês"`).
- T15.3: receita por produto soma `cobranca_itens.total_centavos` (a cópia) — bate com o valor das cobranças porque
  valor = soma dos itens; cobranças anteriores ao catálogo entram na linha "Sem item"; "contratos ativos" não soma
  (um contrato com dois itens contaria duas vezes); receita por cliente agrupa no SQL (`selectRaw` + `groupBy`) e
  calcula dias de atraso em PHP (`diffInDays` do Carbon 3 tem sinal).
- T15.4: contas a receber é a situação de **hoje** (sem período); faixas 0–30 (vence hoje inclusive), 31–60, 61–90 e
  depois de 90; a soma das faixas é o total pendente; "maior atraso" não soma.
- **Pegadinha (revisão final, 2026-10-10): id não numérico no `exists` quebra só no PostgreSQL.** `where id = 'abc'`
  numa coluna bigint é erro de SQL (500) no PostgreSQL; no SQLite dos testes vira só "não existe". **Acerto:** o
  validador do Laravel pula `exists`/`unique` quando o campo já falhou em outra regra
  (`Validator::hasNotFailedPreviousRuleIfPresenceRule`), então basta `integer` **antes** do `exists` — sem `bail`.
  `cliente_id` (contrato/cobrança) e `chave_pix_id` não tinham `integer`; corrigido.
  `tests/Feature/IdsNaoNumericosTest.php` prova com `DB::listen` que nenhuma consulta recebe o texto.
- **Acerto (revisão final):** os serviços de consulta (`app/Services/Consultas/*`) trocam por `null` todo campo da
  resposta que não seja texto ou número (`is_scalar`) antes de qualquer `(string)`; campo com tipo errado vira
  "não veio", não erro 500.
- **Smoke T15.5 (2026-10-10, usuário SMOKE id 2, PostgreSQL de dev, autorizado):** catálogo (cadastro, filtros,
  desativar, busca só de ativos), contrato por itens (1,5 × 120,00 + 89,90 = 269,90 ao vivo e gravado), contrato antigo
  (aviso + exigência de itens), avulsa e edição (número mantido), cobrança gerada com itens copiados, reajuste só no
  contrato marcado, filtros, CNPJ/CEP, 4 relatórios + CSV (BOM, `;`, vírgula), painel, PIX. PostgreSQL devolve
  `quantidade` como `"1.500"` e as somas batem. Prints em `storage/app/private/verificacao/smoke-catalogo/`.
- **Pegadinha (390 px): `<fieldset>` tem `min-inline-size: min-content` por padrão** — uma tabela larga dentro dele
  alarga a coluna do grid e a página inteira, e o `overflow-x-auto` não age. Usar `min-w-0` no fieldset.
- **Pegadinha (390 px): `sr-only` é `position: absolute`** — dentro de tabela com rolagem, escapa do recorte se o
  contêiner `overflow-x-auto` não for `relative`, e alarga a página. Contêiner de tabela rolável: `relative overflow-x-auto`.
- **Pegadinha (smoke):** `fetch` dentro de `js()` do browser-use estoura o canal em 5 s com o PostgreSQL remoto:
  disparar o `fetch` guardando o resultado em `window.__x` e consultar em laço. **E** não deixar a expressão devolver
  a promessa: `js()` espera `awaitPromise`. Usar `(async () => …)()` no fim estourou de novo; `setTimeout(async () =>
  …, 0); 1` funciona.
- **Pegadinha (sessão, 2026-10-11, relato de 419 ao excluir produto):** com a sessão vencida (120 min) e o cookie
  "lembrar de mim" válido, **cada pedido que chega junto refaz o login e cria uma sessão própria**. Reproduzido no
  Chrome de teste: 3 `fetch` em paralelo sem cookie de sessão viraram 3 sessões novas, de 184 bytes cada. O usuário
  teve 9 sessões em 2 minutos. Uma página que guardou o token de uma delas leva 419 na próxima ação.
  - **Correção para quem usa:** `bootstrap/app.php` → `respond()`. Com 419 fora de JSON, volta para a página com
    "A página expirou. Tente de novo.", e o segundo clique funciona. Teste: `PaginaExpiradaTest`.
  - **O que não foi reproduzido:** o 419 em si. No teste os cookies chegaram consistentes e a sonda deu 404.
    **[INCONCLUSIVO]**.
  - **Sonda segura de CSRF:** `DELETE /produtos-servicos/999999` com `X-XSRF-TOKEN`. Token válido → 404; token
    vencido → 419 ou redirect. Não muda dado.
- **Filtro de situação das cobranças (pedido do usuário, 2026-10-10):** "Vencendo hoje", "Vencidas", "A vencer",
  "Pendentes (todas em aberto)", "Pagas", "Canceladas". Vencendo hoje e Vencidas **ignoram o mês** (a vencida de
  setembro aparece em outubro; o rótulo do mês avisa "buscando em todos os meses"). Trocar situação ou mês aplica o
  filtro na hora (`@change`), sem clicar em "Filtrar". **Campo de mês nunca fica bloqueado** (o usuário achou que era
  defeito): escolher um mês nesse estado volta a situação para "Todas" e mostra o mês escolhido. Teste: `tests/Feature/Cobrancas/FiltroSituacaoTest.php`.
- **Fase 17 — cadastro rápido sem sair da tela (regra do usuário, 2026-10-10):** cliente (`CampoCliente.vue` +
  `CadastroRapidoCliente.vue`), produto/serviço (`CadastroRapidoProduto.vue`, `CatalogoItens.vue` "Ver catálogo") e
  chave PIX (`CadastroRapidoChavePix.vue` em `CamposPagamento`) em **modal**; o registro novo volta já escolhido e o
  formulário de fundo fica intacto. Os `store` de sempre respondem **JSON 201** quando `expectsJson()` (formato da
  autosugestão); validação e isolamento são os mesmos FormRequests (422 automático). `enviarJson()` (`resources/js/http.js`)
  manda o `X-XSRF-TOKEN` do cookie e nunca lança. `Modal.vue`: foco preso, Esc fecha, devolve o foco, **clique fora
  não fecha** (não perder o digitado). Contrato/cobrança **não bloqueiam mais** sem cliente cadastrado.
  `tiposChavePix` é prop compartilhada (Inertia) — `PagamentoInline` também abre o modal.
- **Autosugestão (`BuscaAutosugestao.vue`):** lista os 10 primeiros ao focar/clicar com o campo vazio (`q` vazio no
  servidor; 1 letra continua sem busca), indicador girando no canto direito enquanto busca (`aria-busy` + "Buscando…"
  para leitor de tela; pedido do usuário), opção "+ Cadastrar «texto»" como última opção (setas/Enter/clique).
  Ao incluir item, o foco vai para o **preço combinado** (selecionado); preço 0 ganha borda laranja e aviso.
- **Animações (pedido do usuário):** classes em `app.css` — `<Transition name="surgir">` (avisos, listas),
  `<TransitionGroup name="lista">` (linhas), `modal`, `.aparecer`, `.destacar` (total que mudou), transição de
  foco/hover/clique em `.entrada` e botões; tudo desligado por `prefers-reduced-motion`.
- **Pegadinha (revisão Fase 17): resposta que chega depois do blur reabre a lista** e ela fica flutuando sobre o
  formulário. Toda autosugestão: no blur cancelar timer + fetch; depois do `await`, só abrir se
  `document.activeElement` ainda é o campo. **Modal:** ouvir `keydown` no `document` (não no fundo) — botão que some
  (ex.: "Incluir" → "✓ Incluído") joga o foco no `body` e o Esc deixaria de funcionar.
- **Trava do banco de teste recusa `DB_URL` preenchida** (a URL manda no banco de verdade, por cima do nome).
- **Pegadinha (smoke):** `element.focus()` por script num Chrome headless sem foco de janela **não dispara** o evento
  `focus` (parecia defeito da lista ao focar). Usar clique real: `Input.dispatchMouseEvent` (pressed/released).
- **T17.8 — o recibo lista os itens (decisão do usuário, 2026-10-10):** a descrição do contrato/cobrança virou
  **complemento opcional** (domínio, mês de referência, nº do contrato) e sai **abaixo** dos itens. `TextoDoRecibo::compor`
  monta um item por linha (`Hospedagem de site: 1 mês × R$ 247,57 = R$ 247,57`), linha em branco, descrição; `emLinha`
  (DANFSe) junta com "; ". O snapshot do recibo guarda `servico.itens` (cópia); **snapshot antigo sem `itens` sai igual a
  antes** (os templates `webiss-v1`/`danfse-v1` não mudaram — só o texto que entra neles). A validação conta itens +
  descrição com o mesmo `compor` + `LinhasDoRecibo::quebrar` (máx. 25). Descrição vazia é gravada como `''` (coluna
  NOT NULL, sem migração). Cobranças emitidas antes desta mudança só mostram os itens no PDF depois de editadas (refazer).
  Sugestão ao usuário: itens do catálogo genéricos; o específico do cliente (domínio) vai na descrição.
- **T17.9 — encerrar e recomeçar contrato (desenho do usuário, 2026-10-10):** `App\Services\EncerradorDeContratos`:
  `plano()` monta a lista de ações (`manter`, `cancelar`, `proporcional`, `emitir`, `nao_emitir`) usada **pela prévia e
  pela execução** (`encerrar()`, uma transação). Regras para as pendentes: vencida/vencendo hoje continua; período até o
  fim continua; período depois do fim é cancelado; período que contém o fim vira proporcional ou é cancelado (escolha
  da tela); períodos até o fim ainda não emitidos (pós-pago) saem agora — a lista vem de `CalculadoraDePeriodos::devidos`
  com antecedência 0 e "hoje" distante. Pro-rata em `App\Support\ProRata` (quantidade de cada item × usados ÷ dias,
  meio para cima, mínimo 1 milésimo; sem itens, o valor). Não enviada: ajusta e refaz o recibo (mesmo número); enviada:
  cancela e emite avulsa (`contrato_id` nulo). `GeradorDeCobrancas::criar()` (extraído) cria cobrança + recibo de um
  período. Rotas: `GET /contratos/{id}/encerramento?fim=&em_curso=` (prévia JSON), `POST .../encerrar`,
  `POST .../recomecar` (formulário inteiro + `em_curso`; encerra na véspera do novo início, cria o novo e leva a ele;
  início igual/anterior é recusado). Tela: `EncerramentoContrato.vue` (prévia ao vivo, escolha do período em curso só
  quando existe); o checkbox "ativo" só aparece para **reativar** contrato encerrado; com campos alterados, encerrar pede
  para salvar antes. Teste: `tests/Feature/EncerramentoContratoTest.php` (exemplo aprovado: 18/31 × R$ 247,57 = R$ 143,84).
- **Pegadinhas (T17.9):** (1) pro-rata por item arredonda a quantidade a 3 casas: 18/31 de 1 × 247,57 dá **143,84**
  (0,581 × 247,57), enquanto 247,57 × 18 ÷ 31 dá 143,75 — o desenho aprovado é por item (a quantidade aparece no recibo).
  (2) `Model::replicate()` usa `setRawAttributes` e **perde os defaults de `$attributes`** (conferido em `Eloquent/Model.php:2214-2221`; também
  copia as relações carregadas) (ex.: `status`): definir o
  status explicitamente na cópia. (3) `users.dias_antecedencia` não é fillable: nos testes, `forceFill`. (4) Em helper de
  teste, `[...] + $extra` deixa a base vencer — usar `array_merge($base, $extra)`.
- **T17.9 — ajustes depois da revisão e das decisões do usuário (2026-10-10):** fim **no futuro = encerramento agendado,
  sem limite de data** (o usuário recusou limitar a 1 ano): contrato continua ativo com `data_fim` e
  `contratos.ultimo_periodo` (`inteiro` padrão, `proporcional`, `nao_cobrar`); `GeradorDeCobrancas::criar()` deixa
  proporcional (`ajustarProporcional()`) o período que contém o fim, e `gerarPara` pula esse período com `nao_cobrar`.
  Já emitidas são acertadas na hora. Fim até hoje encerra na hora (inativo). Período que começa **depois** do fim é
  cancelado **mesmo vencido** (pré-pago vence antes de começar; manter cobraria o mês duas vezes no recomeço). Recibo
  proporcional sai **sem linha digitável** (o valor do boleto está no código). Contrato encerrado só volta com `reativar`
  marcado (aba antiga não desfaz encerramento). Encerramento agendado não é reagendado (reduziria de novo a quantidade
  já reduzida) — mudar a data fica para a prorrogação (T17.11).
- **T17.10 — corrigir início errado (relato do usuário, contrato nº 6, 2026-10-10):** contrato com cobranças e **sem
  cobrança paga** pode mudar início/periodicidade/modalidade com `corrigir=1` (a tela "Corrigir o contrato" mostra antes
  as pendentes que serão canceladas): pendentes viram canceladas e **todas** as cobranças saem do contrato
  (`contrato_id` nulo) — senão o índice único e o `$existentes` do gerador pulariam um período novo que comece no mesmo
  dia. Com cobrança paga: só recomeçar (início posterior). Sem `corrigir`, a mudança é recusada ("Confirme a correção").
  **Retroativos — perguntar na hora** (`PerguntaRetroativos.vue` + `GET /contratos/retroativos`, ao criar e ao mudar o
  início): `gerar_retroativas=0` grava `cobrar_a_partir_de` = início do período atual (o primeiro que não terminou,
  `CalculadoraDePeriodos::anteriores`); gerador e encerramento pulam períodos anteriores. Sem o campo, gera desde o
  início (como antes). A pergunta usa rádios `required` dentro do `<form>`: a validação nativa do navegador obriga a
  escolher. **Pegadinha:** a tela que o usuário via era o build anterior — depois de `npm run build`, recarregar a página.
- **T17.12 — corrigir/desfazer baixa e auditoria (pedido do usuário, 2026-10-10):** `POST /cobrancas/{id}/corrigir-pagamento`
  (`pago_em` até hoje) e `/desfazer-baixa` (volta a pendente) exigem **senha** (`current_password`,
  `ValidatesAttributes.php:602`) e **motivo**; rotas em `throttle:6,1`; `findOrFail` antes do `validate` (alheia = 404
  antes de checar senha). Tabela `auditorias` só de inclusão (`UPDATED_AT = null`): `Auditoria::registrar($request, acao,
  alvo, resumo, antes, depois, motivo)` grava usuário e IP. Ações registradas: `pagamento.corrigido`, `baixa.desfeita`,
  `cobranca.cancelada`, `cobranca.editada`, `contrato.encerrado`, `contrato.recomecado`, `contrato.corrigido` (sempre na
  mesma transação da mudança). Histórico na linha (`Cobranca::auditorias()` carregado com `with` na lista; `paraLista`
  só lê se a relação estiver carregada — sem N+1) e tela `/auditoria` (30 dias por padrão, link para a cobrança no mês do
  vencimento ou para o contrato). `CorrecaoPagamentoInline.vue` na lista de cobranças pagas. Efeito esperado: o
  "recebido" (caixa) muda de mês quando a data muda; recibo pago já enviado sai com a data nova se reenviado.
- **T17.15 — cobranças em tabela (2026-10-11):** `Cobrancas/Index.vue` é uma tabela a partir de `xl` (1280 px) e
  cartões abaixo, **com o mesmo HTML** (`block`/`flex` no celular, `xl:table`/`xl:table-row`/`xl:table-cell`; a ordem do
  cartão vem de `order-*`). Ações: `resources/js/acoesCobranca.js` (`acoesDaCobranca` → `{ principal, menu }`, testado
  com `node --test`): em aberto e não enviada → **Enviar**; em aberto e enviada → **Dar baixa**; paga/cancelada → **Ver
  PDF**; o resto no `MenuAcoes.vue` ("⋯", fecha no Esc e ao sair com foco/clique). Ações com formulário abrem numa **faixa
  abaixo da linha** (`tr` extra, uma por vez, foco no primeiro campo e de volta à linha ao fechar); `BaixaInline`,
  `PagamentoInline`, `CancelarInline` (`na-faixa`) e `CorrecaoPagamentoInline` (`modo-inicial`) emitem `fechar` — o
  painel continua usando os mesmos componentes sem essas props. `BotaoEnviar` no menu usa `classe-botao`, e o menu
  **não fecha** enquanto prepara o PDF (senão o erro some); ao marcar "enviada" a página recarrega.
  **Pegadinhas:** (1) `TransitionGroup` exige chave em todo filho e `<template v-for>` não aceita chave nos filhos: a
  linha e a faixa ficam num **`<tbody>` por cobrança** (`TransitionGroup tag="table"`, `thead` com `key`). (2) Menu
  absoluto dentro de `overflow-x-auto` fica cortado: a tabela só aparece onde cabe (xl), sem contêiner rolável.
  **Filtro "Incluir canceladas" (pedido do usuário, 2026-10-11):** "Todas" deixa as canceladas de fora; o checkbox
  (`canceladas=1`) as inclui; só aparece com "Todas" (nas outras situações não muda nada); "Canceladas" continua no
  select. A regra `boolean` do Laravel recusa `true`/`false` na URL: a tela manda `1` ou vazio.
  **Uniformidade (relato do usuário com print, 2026-10-11):** nome de cliente longo espremia as outras colunas e
  quebrava "Dar baixa" e "A VENCER" em duas linhas, e os botões ficavam de larguras diferentes. Acerto: só a coluna do
  cliente quebra; as demais têm `whitespace-nowrap`; a coluna de ações usa `xl:w-px` (encolhe ao conteúdo); o botão
  principal tem largura mínima comum (`botaoPrincipal = 'min-w-28 whitespace-nowrap'`) e o `Carimbo` tem
  `min-w-32 text-center whitespace-nowrap` (vale também no painel). Em tabela nova, aplicar o mesmo desde o início.
- **Fase 18 — previsão de recebimentos (pedido do usuário, 2026-10-11; spec
  `docs/superpowers/specs/2026-10-11-previsao-de-recebimentos-design.md`, plano
  `.claude/plans/2026-10-11-previsao-de-recebimentos.md`)**
  - **Problema.** A cobrança só nasce `dias_antecedencia` antes do vencimento. Por isso o usuário não via os
    recebimentos futuros.
  - **Decisão: calcular, não gravar.** As previstas saem de `App\Services\PrevisaoDeCobrancas::entre($usuario, $de,
    $ate)`, na hora, a partir dos contratos ativos, com o preço atual. Nada vai para o banco. O caminho de gerar tudo
    com recibo foi rejeitado com o usuário: queimaria numeração e o recibo leva o ano de emissão.
  - **Uma regra só para os dois.** `GeradorDeCobrancas::cobravel()` responde se um período vira cobrança. Não vira se
    já existe, mesmo cancelada; se é anterior ao `cobrar_a_partir_de`; ou se é o período que contém o fim com
    `nao_cobrar`. Gerador e previsão usam a mesma regra, junto com `existentes()` e `contemOFim()`.
  - **Períodos.** Saem de `CalculadoraDePeriodos::entre()`, que lista os vencimentos do intervalo sem passar do fim
    do contrato.
  - **Proporcional.** Usa a mesma conta de `ajustarProporcional`. Fora do proporcional, o valor é o `valor_centavos`
    do contrato, como no gerador, e não a soma dos itens.
  - **Teste-guarda.** `test_previsao_bate_com_o_que_o_gerador_cria` anda o relógio, gera as cobranças e compara
    período, vencimento e valor com o que estava previsto. **Não apagar esse teste.**
  - **Só depois de hoje, na tela e nos relatórios.** Até hoje, abrir a tela já gerou as cobranças de verdade. Com o
    perfil incompleto não gera, e mostrar a cobrança como prevista "a vencer" seria enganoso.
  - **Lista.** Filtro "Mês do vencimento" mais "Até" (`ate`, padrão igual ao mês). O "Até" acompanha o mês enquanto os
    dois forem iguais e nunca fica antes dele. Os erros usam as mensagens dos relatórios: até antes do mês, ou 36
    meses ou mais. As previstas entram mescladas por vencimento só em Todas, A vencer e Pendentes. Têm o carimbo
    "Prevista" (cinza, tracejado), não têm ações (`acoesDaCobranca` → `{principal: null, menu: []}`) e vêm com uma
    legenda.
  - **Relatórios.** O faturamento ganha a coluna "Ainda não emitido", que soma no "Previsto". No relatório por
    produto, as previstas somam pelos itens; contrato sem itens vai para "Sem item". Clientes e Contas a receber não
    mudam.
  - **Pegadinhas.**
    1. O `test_csv_para_o_excel_em_portugues` compara o CSV byte a byte. Coluna nova no relatório exige atualizar
       esse teste.
    2. Teste de relatório com `Contrato::factory()` ativo passa a ter previstas, porque o relatório não gera
       cobranças. O teste antigo de produto teve o previsto da Hospedagem corrigido de 30.000 para 50.000.
  - **Painel fora.** O painel ainda não mostra previstas (fora do escopo).
  - **Limitação aceita (decisão do usuário, 2026-10-11).** Os relatórios **não geram** cobranças ao abrir. A cobrança
    que já venceu (hoje ou antes) e ainda não foi gerada fica de fora do "Previsto": ela não é real ainda e já não é
    prevista, porque a previsão começa amanhã. Isso acontece quando a pessoa passa dias sem abrir o painel ou a lista e
    entra direto nos relatórios. O usuário escolheu **deixar como está**: gerar ao abrir seria uma escrita disparada
    por um GET. Não "corrigir" sem falar com ele.
  - **"Até" que se ajusta sozinho.** `resources/js/filtroPeriodo.js` (`ajustarAte`) tem teste no `node --test`. Ao
    mudar o **mês**:
    - o "Até" acompanha o mês enquanto os dois eram iguais;
    - nunca fica antes do mês;
    - se ficar a 36 meses ou mais, volta para um mês só;
    - mês apagado leva o "Até" junto.

    Ao mudar o **próprio "Até"**, só o vazio vira o mês. Fora do limite, o erro do servidor aparece embaixo dele, que é
    o campo que a pessoa mexeu.

    *Pegadinha (revisão final):* com a regra dentro do `.vue`, mudar o mês para trás deixava o erro de 36 meses
    embaixo do "Até", campo que a pessoa não tinha mexido. E um campo apagado ficava em branco, porque o
    `preserveState` não ressincroniza o `filtro` com o `props.filtros`. Regra de filtro com casos de borda vai para um
    módulo `.js` com teste, como o `acoesCobranca.js`.
- **Fase 19 — cobranças paginadas por ajax (pedido do usuário, 2026-10-11; spec
  `docs/superpowers/specs/2026-10-11-paginacao-cobrancas-design.md`, plano `.claude/plans/2026-10-11-paginacao-cobrancas.md`;
  carregamento validado pelo usuário: "Aprovo o carregamento da lista de cobranças")**
  - **Rotas.**
    - `GET /cobrancas` (Inertia) só abre a tela e roda a geração preguiçosa.
    - `GET /cobrancas/dados` (JSON, **nunca grava**) monta a resposta em `App\Services\ListaDeCobrancas::montar`:
      1. lista leve: só `id`, `vencimento`, `status` e `valor_centavos`, mais as previstas;
      2. totais por situação nesta ordem: vencida, vence_hoje, a_vencer, prevista, paga, cancelada;
      3. `total_geral` sem as canceladas;
      4. fatia de `PAGINAS_POR_BLOCO = 4` páginas;
      5. só as linhas do bloco carregam cliente, recibo e histórico.
    - Página além da última → devolve a última (`pagina.inicio` diz qual).
    - "Até antes do mês" e "36 meses" voltam como 422 no JSON. A página não recusa o período: link ruim abre a tela
      com o erro embaixo do campo.
  - **Navegador** (`Cobrancas/Index.vue`).
    - Cache por `chaveDoFiltro` (os filtros mais o "por página"); `fatiarBloco` reparte o bloco em páginas;
      `paginasVisiveis` monta o paginador (`resources/js/paginacao.js`, com teste).
    - Contador de pedidos: resposta atrasada é descartada.
    - `router.replace({ url, props })` põe filtros e página na URL sem pedir nada ao servidor.
    - `buscarJson` em `http.js` (mesmo retorno de `enviarJson`).
  - **Recarga depois de uma ação.** `post`, `put` e `delete` do Inertia preservam o estado, então a página **não
    remonta**. A lista é buscada de novo no evento global `router.on('success')` quando
    `page.component === 'Cobrancas/Index'` e não está carregando. Confirmado no `@inertiajs/core` 3.9.0: o `success`
    só sai de resposta do servidor (`dist/index.js:2715`), nunca de `router.replace`.
  - **Componentes.** `Paginador.vue`: "Mostrando X–Y de N", Anterior/números/Próxima, "Por página" (25/50/100),
    `aria-current="page"`. Trocar de página leva o foco ao paginador de cima.
  - **Telas de espera.**
    - Primeira carga: texto com o indicador girando e 6 linhas-esqueleto (`motion-safe:animate-pulse`), pedido do
      usuário "como no YouTube".
    - Carga seguinte: a tabela fica esmaecida.
    - Falha: mensagem e "Tentar de novo".
  - **Pegadinhas.**
    1. Os testes que liam a prop `cobrancas` passaram a abrir a página (que gera) e ler o JSON.
    2. Ao colar trechos do plano por script, `'<tfoot' in bloco` pegou o bloco errado: escolher pelo começo do bloco e
       contar as ocorrências.
    3. **Rolagem horizontal em ~1260 px** (achada na conferência visual de 2026-10-11): na 5ª coluna da grade de
       filtros, "Incluir canceladas" (`shrink-0`) mais o botão "Filtrar" passavam da largura da coluna em 9 px.
       Correção: `flex-wrap` no contêiner, e o botão desce para a linha de baixo. Ao montar filtros em grade, conferir
       `scrollWidth === clientWidth` em 1264 px, não só em 390 px.
    4. **Lentidão no dev não é defeito da lista:** 12 consultas × ~580 ms de ida e volta ao PostgreSQL remoto dão ~7 s
       no `/cobrancas/dados` (medido com `DB::enableQueryLog`). Mesmo assim há uma consulta por contrato ativo:
       `GeradorDeCobrancas::existentes()` (`$contrato->cobrancas()->pluck(...)`), chamada pela previsão. Com 100
       contratos seriam 100 consultas — ver a Fase 20.
  - **Conferência visual (2026-10-11, autorizada, usuário SMOKE, 64 cobranças em 3 páginas):** paginador acima e
    abaixo, página 3 tirada do cache sem pedido novo (contado em `performance.getEntriesByType('resource')`), URL com a
    página, foco no paginador de cima, F5 e link direto para a página 3, rodapé com as somas certas, 390 px sem
    rolagem. **Não conferido:** dar baixa e ver a lista recarregar (grava pagamento; pede autorização à parte).
  - **Pegadinha (browser-use):** o print (`browser_screenshot` ou `Page.captureScreenshot`) de uma aba aberta com
    `new_tab` estoura o tempo no Chrome sem janela até chamar `cdp("Target.activateTarget", targetId=...)` e
    `cdp("Page.bringToFront")`. Print de página inteira (`captureBeyondViewport`) trava a aba: rolar e tirar por partes.
- **Fase 20 — todas as listas por ajax (pedido do usuário, 2026-10-11; spec
  `docs/superpowers/specs/2026-10-11-listas-por-ajax-design.md`, plano `.claude/plans/2026-10-11-listas-por-ajax.md`)**
  - **Servidor.** Cada tela tem `GET …/dados` (JSON, **só leitura**; `tests/Feature/DadosDasListasTest.php` prova os
    11 endereços: 401 sem sessão e nenhum INSERT/UPDATE/DELETE, mesmo com cobrança devida). A página Inertia só abre a
    tela com os filtros (`filtros()` privado em cada controlador, já no formato da tela, `''` = sem filtro).
    `App\Support\BlocoDePaginas::de(Builder|Relation|Collection, pagina, porPagina)` e `::regras([25, 50, 100])`.
  - **Acerto (PostgreSQL):** `count(*)`/`sum()` com `ORDER BY` de coluna é erro no PostgreSQL e passa no SQLite dos
    testes. `BlocoDePaginas` conta com `toBase()->getCountForPagination()` (tira o ORDER BY; teste do SQL da contagem);
    contratos somam por periodicidade numa consulta **sem ordem** (`filtrada()` e a ordem só na lista); o painel soma
    antes de ordenar.
  - **Navegador.** `usarListaPaginada({ url, filtro, componente, endereco, idDoFoco, aoMostrar })` liga
    `criarListaPaginada` à tela (primeira carga, URL por `router.replace`, foco, recarga no `success` da página
    `componente`). `EstadoDaLista.vue` (esqueleto, filtro recusado, falha, vazio). `Etiqueta.vue` (selo reto de
    situação de cadastro; o carimbo girado fica para cobranças e recibos). `estilosLista.js` (`botaoPrincipal`,
    `itemMenu`). `chaveDoFiltro` vale para qualquer filtro (todas as chaves menos `pagina`).
  - **Telas.** Clientes, Contratos, Produtos e Usuários em tabela (T17.15). Contratos: situação Ativo / Encerra em /
    Encerrado e totais dos ativos **por periodicidade** (não se somam periodicidades diferentes); o filtro de produto
    virou autosugestão (só ativos; o `produtoFiltro` da página mostra o nome mesmo inativo). Usuários: ação principal
    pela situação (`acoesUsuario.js`), "Bloquear" no menu com confirmação no segundo clique, nunca na própria conta.
    Painel: três `ListaDoPainel` independentes, 10 por página (10/25/50), paginador só embaixo e só com mais de uma
    página, contagem e soma no título. Relatórios: faturamento inteiro numa página (o gráfico precisa de todas as
    linhas), os outros paginam; totais sempre do relatório inteiro; CSV inteiro pelo link de sempre; títulos em
    `relatorios.js` (aparecem antes dos dados).
  - **N+1 corrigido:** `GeradorDeCobrancas::existentes(Collection $contratos)` busca as cobranças existentes de todos os
    contratos numa consulta só (antes, uma por contrato; com o PostgreSQL remoto a ~580 ms por consulta). Usado pelo
    gerador e pela previsão; teste conta as consultas.
  - **Pegadinhas (execução).**
    1. No Git Bash, `sed` trocando linhas `use App\…` do PHP (barra invertida) falha calado: usar o editor.
    2. Script que converte asserções com regex `(.*?)\)\);` comeu o parêntese do último `->where(`: conferir o diff e
       `php -l` depois de conversão automática.
    3. Auxiliar de teste que cria produto com nome fixo estoura o `unique(user_id, nome)` se chamado duas vezes.
    4. **Vue apaga o espaço que abre um elemento antes de interpolação:** `{{ n }}<span> {{ rotulo }}</span>` sai
       "2contratos". Pôr o espaço fora (`{{ n }} <span>…`). Texto puro (`<span> em contratos</span>`) mantém o espaço.
    5. **Regra `date` aceita ano de 5 dígitos** (o `<input type="date">` do Chrome deixa digitar até 6) e lê
       `72026-08-01` como 2006-08-01, sem erro. Filtro e formulário de data: `date_format:Y-m-d` (mês: `Y-m`).
    6. **`@change` em campo de data/mês dispara a cada dígito do ano e a cada parte** (0001, 0015, 0150…; dia, mês;
       o mês pela metade deixa o valor vazio por um instante): uma data digitada virava até 12 pedidos. **Acerto:**
       filtro de data/mês = `adiar(() => anoCompleto(de) && anoCompleto(ate) && filtrar())` (400 ms, como a busca de
       texto). Formulário que **grava** data não precisa (só envia no "Salvar"), mas a regra do servidor é
       `date_format:Y-m-d` em todos (pegadinha 5).
    7. Valor em reais em coluna estreita quebra "R$" e o número em linhas diferentes: espaço inseparável
       (`R$ `). `grep -P '\xC2\xA0'` não acha o caractere no Git Bash; conferir com `od -c`.
  - **Conferência visual (2026-10-11, autorizada):** resultado e defeitos no "Registro de execução" do plano. Usuários
    não foi conferida (o SMOKE não é admin). **Browser-use:** `cdp("Page.navigate")` estoura 5 s com o PostgreSQL
    remoto; navegar com `js("setTimeout(()=>location.href='…',0);1")` e esperar por condição. A entrada de
    `performance.getEntriesByType('resource')` só aparece quando a resposta termina. Esqueleto: atrasar a rede com
    `Network.enable` + `Network.emulateNetworkConditions(latency=2500)` para dar tempo de ver.
- **Fase 21 — apelido do cliente (T17.13; desenho aprovado pelo usuário em 2026-10-11; plano
  `.claude/plans/2026-10-11-apelido-do-cliente.md`)**
  - **Dado.** `clientes.apelido` (60, `''` por padrão, nunca nulo) e único `(user_id, documento, apelido)`: o mesmo CNPJ
    pode ter outro cadastro com outro apelido (cada regional é um cliente). Com nulo, dois cadastros sem apelido passariam.
  - **Um lugar só para a regra de exibição.** `Cliente::exibicao()` (apelido ou nome), `nomeOficial()` (nome só quando há
    apelido) e `paraBusca()` (`{id, nome, nome_oficial, documento}`: autosugestão, cadastro rápido, `clienteFiltro`,
    `clienteAtual`). Listas mandam `cliente` + `cliente_oficial`; a tela mostra o oficial embaixo, menor. Relatórios e
    reajuste mostram só a exibição. Ordem pela exibição: escopo `ordemDeExibicao` (`coalesce(nullif(apelido, ''), nome)`).
  - **Pegadinha.** Consulta que carrega colunas escolhidas do cliente (`with('cliente:id,nome')`) precisa de `apelido`
    na lista; senão a exibição volta ao nome sem erro nenhum. Ao criar consulta nova com cliente, incluir `apelido`.
  - **Recibo -v2.** `webiss-v2`/`danfse-v2` = v1 + "Unidade atendida"; os v1 ficam intactos (há recibo em produção) e
    `FamiliaModelo::versaoAtual()` aponta para o v2. O snapshot do tomador guarda o apelido; snapshot antigo sem a chave
    sai igual. **Acerto (1 página):** no WebISS, linha nova (~17pt) e linha compacta (~10pt) levaram a amostra de 25
    linhas para 2 páginas; com apelido, a linha do nome vira duas células (nome 62% a 8pt, "Unidade atendida" a 6pt
    negrito, cabe 60 caracteres), sem altura extra. No DANFSe coube uma linha própria. `AmostrasVisuaisTest` usa apelido
    de 60 caracteres. Mudança visual futura nos modelos = `-v3` quando já houver recibo `-v2` em produção.
- **Pegadinha (execução do plano):** ao copiar trecho parcial do plano por texto, ancorar a busca **dentro da seção da
  tarefa** — "depois de `cobrancas()`" aparece na T12.2 (Contrato) e na T13.1 (Cliente); a primeira ocorrência colou o
  método errado no `Cliente` (o teste pegou com `undefined method comTermo`).
- **Consulta de dados públicos (só se gratuita — condição do usuário):** ViaCEP ("Webservice gratuito"; bloqueia uso
  massivo) para CEP; BrasilAPI (sem chave; README: "em beta", termos em elaboração, sem crawling) para CNPJ e reserva do
  CEP. Uso só sob demanda, com cache de 30 dias e limite por usuário; nunca consultar CPF.

## Resultados da prova do dompdf (T0.4)
- Acentos em DejaVu Sans: corretos (extração de texto). Fonte embutida `Identity-H`.
- Acentos em Helvetica: corretos (fonte nativa com `WinAnsiEncoding`).
- Imagem em data URI: embutida (2 imagens no PDF).
- PDF de 1 página.
- `transform: rotate`, absoluto dentro da caixa `relative` e rodapé `position: fixed`:
  **[A VERIFICAR]** — sem renderizador de PDF na máquina; a conferência passou para o recibo real (T4.4) — conferência visual do usuário em
  `storage/app/private/verificacao/prova-dompdf.pdf`.
