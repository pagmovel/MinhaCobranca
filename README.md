# Gerenciador de Cobranças

Cobranças recorrentes com recibo em PDF A4 inspirado nos modelos de NFS-e (WebISS e DANFSe),
para envio antecipado ao cliente e baixa manual quando o pagamento é confirmado.

## Requisitos
PHP 8.4, Composer, Node 22, SQLite (PostgreSQL no futuro).

## Instalação
    composer install
    npm install
    cp .env.example .env
    php artisan key:generate
    php artisan migrate
    php artisan app:criar-admin seu@email.com --nome="Seu Nome"
    npm run build

## Testes
    php artisan test

## Ferramentas do Claude Code usadas no desenvolvimento
- **superpowers** (plugin): brainstorming, planos e execução com TDD.
- **frontend-design** (plugin): direção visual das telas.
- **context-mode** (plugin/MCP): leitura e execução de comandos com economia de contexto.
- **laravel-boost** (pacote dev + MCP): guidelines, skills e ferramentas do Laravel.
- **browser-use** (plugin/MCP): smoke tests no navegador.

Detalhes em `CLAUDE.md` e na skill `.claude/skills/recibos/SKILL.md`.
