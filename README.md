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

## Produção na VPS (Easypanel) — atrás do BunkerWeb
Desde 2026-10-18 o domínio `cobrancas.smartcontroladoria.com.br` está no serviço `bunker` do Easypanel
(BunkerWeb, WAF), não neste serviço: Traefik → `bunkerweb:8080` → `meus_projetos_gerenciador_cobrancas_app:80`.

- **Não apagar o domínio `meus-projetos-gerenciador-cobrancas.ymlmcr.easypanel.host`** deste serviço (direto em
  `app`/`80`): é o acesso de reserva e é o que mantém o container na rede `easypanel`, onde o BunkerWeb o encontra.
- **HTTPS vem do cabeçalho do proxy**: `trustProxies` (`bootstrap/app.php`) confia nas faixas privadas, e o
  BunkerWeb recebe HTTP do Traefik — ele está configurado para repassar `X-Forwarded-Proto: https`. Se as páginas
  passarem a gerar links `http://` (conteúdo misto), o problema está nesse cabeçalho, não no Laravel.
- Métodos liberados no BunkerWeb: `GET|POST|HEAD|PUT|PATCH|DELETE`. Rota nova com outro método (ou webhook de
  gateway de pagamento) precisa ser liberada lá também — senão volta 405/403 do BunkerWeb, não do Laravel.
- Configuração do site, limites e como desfazer: `/srv/CONTABOO/bunker/README.md` (fora deste repositório).
- **Versão do PHP fixa no `Dockerfile`** (`php:8.4.27-apache-bookworm`, desde 2026-10-25, correção do PHP2Shell:
  CVE-2026-109618 e CVE-2026-109619). A tag móvel `8.4-apache-bookworm` pode reaproveitar uma imagem antiga em cache.
  Ao sair release de segurança do PHP: trocar o número no `Dockerfile`, conferir a tag no Docker Hub e fazer o deploy
  pelo **Deploy** do painel, que reconstrói a imagem a partir do Git (trocar o número já invalida as camadas do PHP;
  não precisa limpar cache). Reiniciar o container antigo não atualiza o PHP. Depois, `php -v` no console do container
  deve mostrar a versão nova.

## Testes
    php artisan test

## Ferramentas do Claude Code usadas no desenvolvimento
- **superpowers** (plugin): brainstorming, planos e execução com TDD.
- **frontend-design** (plugin): direção visual das telas.
- **context-mode** (plugin/MCP): leitura e execução de comandos com economia de contexto.
- **laravel-boost** (pacote dev + MCP): guidelines, skills e ferramentas do Laravel.
- **browser-use** (plugin/MCP): smoke tests no navegador.

Detalhes em `CLAUDE.md` e na skill `.claude/skills/recibos/SKILL.md`.
