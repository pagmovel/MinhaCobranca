# Gerenciador de Cobranças

Cobranças recorrentes com recibo em PDF A4 inspirado nos modelos de NFS-e (WebISS e DANFSe),
para envio antecipado ao cliente e baixa manual quando o pagamento é confirmado.

## Configuração inicial obrigatória

Antes de cadastrar produtos/serviços, clientes, contratos ou cobranças, a conta precisa ter o perfil do
prestador completo (dados, uma chave PIX e um modelo de mensagem) e pelo menos um produto/serviço e um
cliente. Um modal de boas-vindas no primeiro acesso e um guia fixo na tela orientam os passos que faltam; os
itens do menu ficam bloqueados até lá — nunca edição, exclusão ou listagem do que já existe.

## Requisitos
PHP 8.4, Composer, Node 22.

Banco de dados: PostgreSQL é o banco usado tanto em desenvolvimento quanto em produção (instâncias dedicadas e
separadas para cada ambiente). O `.env.example` traz `DB_CONNECTION=sqlite` só para facilitar rodar o projeto do
zero sem precisar de um servidor PostgreSQL à mão — a suíte de testes roda em SQLite em memória
independentemente do banco configurado (`phpunit.xml`).

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
