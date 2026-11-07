# Briefing comum: Fase 29 (temas visuais), execução de 2026-11-07

Projeto: `/srv/conteiners/MinhaCobranca` (Laravel 13, Inertia v3, Vue 3, Tailwind v4, PHPUnit, `node --test`).
Idioma: **pt-BR em tudo** (conversa, comentários, mensagens, commits e também os nomes que você criar: variáveis,
funções, classes, arquivos, testes). Só ficam como estão os nomes impostos por framework ou biblioteca.

## Leia antes de codar (nesta ordem)

1. `/srv/conteiners/MinhaCobranca/CLAUDE.md` (diretrizes do Laravel Boost; neste projeto **não existe** `.ai/rules`).
2. O plano `/srv/conteiners/MinhaCobranca/.claude/plans/2026-11-06-temas-visuais.md`: seções 0 a 9 e a(s) tarefa(s)
   que você executa. É a fonte da verdade (nomes, assinaturas, testes). A seção 0 diz o que entra nesta execução.
3. A skill `/srv/conteiners/MinhaCobranca/.claude/skills/recibos/SKILL.md` (quase 2000 linhas). Indexe com `ctx_index`
   e consulte com `ctx_search` os assuntos da sua tarefa (usabilidade, painel, perfil, auditoria, progresso/coach,
   testes, "Pegadinha da conferência"). A seção "Padrões de usabilidade" você lê inteira.
4. Os protótipos aprovados em `docs/` são **referência visual e de comportamento**; a lógica de dados fictícios deles
   não se copia: no app valem as listas, ações e endpoints reais.

## Regras do Marcos que valem para você

- **Nunca supor.** Toda afirmação vem de arquivo ou comando que você abriu. O que não der para verificar leva
  `[A VERIFICAR]` ou `[INCONCLUSIVO]` no relatório.
- **Banco:** nunca escreva no Postgres local nem em outro banco. Não rode `php artisan migrate`, `migrate:fresh`,
  `db:seed`, `tinker` que grave, nem nada que use a conexão real. Os testes usam SQLite em memória (`phpunit.xml`) e
  podem rodar. Não leia `.env`. Quem roda a migração no Postgres local é quem coordena.
- **Tabela nova leva prefixo `tbl_`** (esta fase não deve criar tabela; só colunas em `users`).
- **Testes ficam em `tests/`**: PHP em `tests/Feature/Temas/` (ou `tests/Unit/`), JS em `tests/js/*.test.js`.
  Os testes JS antigos ao lado do código continuam onde estão. Comandos:
  `php artisan test --compact --filter=<Classe>` e `node --test 'tests/js/**/*.test.js' 'resources/js/**/*.test.js'`.
- **Sem dependência nova** (nada de `composer require` nem `npm install` de pacote).
- Convenções PHP do projeto: chaves nas estruturas de controle, tipos de retorno e de parâmetros, constructor
  promotion, enum com chaves em TitleCase, PHPDoc em vez de comentário solto. Criar arquivos com
  `php artisan make:... --no-interaction` quando existir o comando. Depois de alterar PHP:
  `vendor/bin/pint --dirty --format agent`. Fonte única dos nomes e assinaturas: o plano.
- **Pastas novas aprovadas pelo Marcos:** `resources/css/temas/`, `resources/js/Components/Painel/`, `tests/js/`.
  Nenhuma outra pasta nova na raiz.
- **Padrões de usabilidade** da skill `recibos` valem para toda tela: nunca mandar sair da tela, erro sempre com a
  saída ali mesmo, animações curtas com `prefers-reduced-motion` desligando tudo, 390 px sem rolagem horizontal,
  contraste mínimo 4,5:1, botões reais e foco visível.
- **Git:** o branch `feature/temas-visuais` já está ativo (confirme com `git branch --show-current`). A identidade
  deve ser `marcos.ronaldo@gmail.com` (`git config user.email`); se não for, **pare e avise**. Um commit por tarefa,
  só com os arquivos dela: `git add <caminhos>`; **nunca** `git add -A` nem `git add .` (há arquivos de outros em
  `docs/` e outro agente pode estar trabalhando em paralelo). Sem push, sem merge, sem rebase, sem `--force`. Se
  `git commit` reclamar de `index.lock`, espere 5 s e repita. Mensagem em pt-BR, com o rodapé
  `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.
- **Documentar na hora (regra do Marcos):** no commit de cada tarefa entram (a) uma entrada curta na skill `recibos`,
  na seção "## Fase 29: temas visuais" (crie no fim do arquivo se não existir), com "### Acertos (Tarefa X.Y)" e, se
  houver, "### Pegadinhas"; (b) o **Estado** da tarefa no plano (pendente para concluída; "testada" só com testes
  verdes e `npm run build` ok). Antes de editar a skill ou o plano, **releia o arquivo na hora**: outro agente pode ter
  mexido em outra seção. Não altere outras tarefas do plano.
- **Nunca acessar a VPS** (SSH, painel, deploy) nem qualquer servidor remoto; só o Marcos autoriza e não há
  autorização para esta fase.

## Telas (só para agentes de interface)

- Telas são conferidas com o **browser-agent** (plugin `browser-use`: `browser_exec`, `browser_screenshot`; se aparecerem
  como deferred, carregue com `ToolSearch` `select:`), **não com Playwright**. A máquina não tem Chrome de sistema:
  antes, suba o Chromium do Playwright (`ls -d ~/.cache/ms-playwright/chromium-*/chrome-linux64/chrome`) com
  `--headless=new --remote-debugging-port=9333 --user-data-dir=<pasta temporária>` (`BU_CDP_URL` já está em
  `~/.claude/settings.json`) e encerre ao terminar. Só um agente por vez usa a porta 9333.
- O app para teste de tela roda com **SQLite temporário** e dados de factories, nunca com o Postgres (receita na
  skill: "Pegadinha da conferência"). Se o browser-agent não funcionar, **diga no relatório**; não troque de
  ferramenta em silêncio.
- Olhe suas próprias capturas (Read na imagem) e pergunte "isto parece gerado por IA?". O Marcos recusa o clichê de IA
  e quer efeitos com função, sempre desligados em `prefers-reduced-motion`.

## Relatório

O relatório completo vai para o arquivo indicado no seu prompt. A resposta final tem no máximo 15 linhas: o que ficou
pronto, comandos de verificação com o resultado, commits (hash e assunto) e o que ficou `[A VERIFICAR]`.
