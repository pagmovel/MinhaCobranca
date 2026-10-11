---
name: recibos
description: Use ao trabalhar no sistema de cobranças e recibos (Laravel 13 + Inertia v3 + Vue 3 + dompdf) — regras de domínio, decisões, pegadinhas e acertos.
---

# Sistema de cobranças e recibos

Spec: `docs/superpowers/specs/2026-10-09-sistema-recibos-design.md`
Plano: `.claude/plans/2026-10-09-sistema-recibos.md`

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
- Recibo = snapshot congelado (prestador, tomador, serviço) + modelo com versão (`webiss-v1`).
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
