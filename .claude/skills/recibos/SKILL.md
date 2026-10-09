---
name: recibos
description: Use ao trabalhar no sistema de cobranças e recibos (Laravel 13 + Inertia v3 + Vue 3 + dompdf) — regras de domínio, decisões, pegadinhas e acertos.
---

# Sistema de cobranças e recibos

Spec: `docs/superpowers/specs/2026-10-09-sistema-recibos-design.md`
Plano: `.claude/plans/2026-10-09-sistema-recibos.md`

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

## Resultados da prova do dompdf (T0.4)
- Acentos em DejaVu Sans: corretos (extração de texto). Fonte embutida `Identity-H`.
- Acentos em Helvetica: corretos (fonte nativa com `WinAnsiEncoding`).
- Imagem em data URI: embutida (2 imagens no PDF).
- PDF de 1 página.
- `transform: rotate`, absoluto dentro da caixa `relative` e rodapé `position: fixed`:
  **[A VERIFICAR]** — conferência visual do usuário em
  `storage/app/private/verificacao/prova-dompdf.pdf`.
