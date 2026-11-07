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

## Temas visuais

Cada usuário escolhe a aparência do sistema clicando no próprio nome (painel lateral à direita, seção
"Aparência"). A troca é imediata, sem recarregar a página, e fica gravada em `users.tema`. Quem já usava o
sistema vê um aviso da novidade uma vez.

| Tema (`id`) | Visual |
|---|---|
| Talão (`talao`) | O visual de sempre: menu lateral claro e o painel em duas listas. |
| Régua (`regua`) | Menu no topo e o mês numa linha do tempo, com cada cobrança no dia do vencimento. |
| Almanaque (`almanaque`) | Menu lateral azul-marinho, painel em blocos de cantos bem arredondados e a linha do tempo do mês. |

**Variável de ambiente `TEMA_PADRAO`:** tema de quem se cadastra daqui para frente (`talao`, `regua` ou
`almanaque`; padrão `talao`). Valor desconhecido cai no Talão. É lida em `config/temas.php` (o `.env.example` traz
`TEMA_PADRAO=almanaque`, decisão do Marcos de 2026-11-07; o padrão do código, sem a variável, continua `talao`) e não muda o tema de quem já tem conta. Em produção a configuração é cacheada na subida do
container: depois de mudar a variável, reinicie ou refaça o deploy.

As fontes de cada tema são baixadas do Bunny Fonts no `npm run build` (essa etapa precisa de rede) e servidas pelo
próprio site; a página só carrega as do tema ativo. Para criar um tema novo, ver a skill `recibos`, seção
"Fase 29: temas visuais".

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
- **Logo depois de um Deploy o domínio pode dar 502 por uns 3 minutos, e some sozinho.** Medido em 2026-11-07: de 21:14:41
  a 21:17:47, 11 erros `connect() failed (113: Host is unreachable) ... upstream: "http://10.11.0.22:80"` no log do
  BunkerWeb. O container novo ganha IP novo (10.11.0.22 → 10.11.0.23) e o BunkerWeb ainda tenta o antigo; o app estava de
  pé o tempo todo (`restart=0`, Apache em `resuming normal operations`). Não vem do `Dockerfile`, do compose nem da
  migração. Para conferir: `docker exec meus_projetos_bunker-bunkerweb-1 nslookup meus_projetos_gerenciador_cobrancas_app`
  (tem de dar o IP atual do container) e abrir o endereço de reserva `*.easypanel.host/up` (vai direto no app, sem o
  bunker). **Ajuste aplicado em 2026-11-07 (Marcos, pela UI do bunker):** `DNS_RESOLVERS` = `127.0.0.11 valid=10s`
  (Global Settings → General → "DNS resolvers", dois valores). O nginx do bunker agora relê o IP do container a cada
  10 s (`resolver 127.0.0.11 valid=10s ipv6=off;` em `/etc/nginx/http.conf`; recarregado sozinho, sem derrubar os sites).
  O efeito só se confirma no próximo Deploy do app (esperado: ~10 s de 502 em vez de ~3 min) `[A VERIFICAR]`. Esse valor
  mora no **banco do bunker**, não no compose: se o bunker for recriado do zero, refazer (ou pôr
  `DNS_RESOLVERS: "127.0.0.11 valid=10s"` no `environment` do `bw-scheduler`). Houve 1 erro igual em 2026-11-05 09:17.
- Configuração do site, limites e como desfazer: `/srv/CONTABOO/bunker/README.md` (fora deste repositório).
- **Migração no deploy:** o `CMD` do `Dockerfile` roda `php artisan migrate --force` a cada subida do container
  (só aplica o que está pendente; decisão do Marcos, reafirmada em 2026-11-07; travado por
  `tests/Unit/DockerfileMigraNoDeployTest.php`). Não há passo manual de migração. A da Fase 29 (temas) só
  adiciona duas colunas em `users` (`tema` e `novidade_temas_vista`), nenhuma tabela. Depois do deploy, defina
  `TEMA_PADRAO` no `.env` de produção se quiser outro tema para os usuários novos.
- **Versão do PHP fixa no `Dockerfile`** (`php:8.4.27-apache-bookworm`, desde 2026-10-25, correção do PHP2Shell:
  CVE-2026-109618 e CVE-2026-109619). A tag móvel `8.4-apache-bookworm` pode reaproveitar uma imagem antiga em cache.
  Ao sair release de segurança do PHP: trocar o número no `Dockerfile`, conferir a tag no Docker Hub e fazer o deploy
  pelo **Deploy** do painel, que reconstrói a imagem a partir do Git (trocar o número já invalida as camadas do PHP;
  não precisa limpar cache). Reiniciar o container antigo não atualiza o PHP. Depois, `php -v` no console do container
  deve mostrar a versão nova.

## Testes
    php artisan test
    node --test 'tests/js/**/*.test.js' 'resources/js/**/*.test.js'

## Ferramentas do Claude Code usadas no desenvolvimento
- **superpowers** (plugin): brainstorming, planos e execução com TDD (a Fase 29 foi executada por subagentes,
  com `subagent-driven-development`).
- **frontend-design** (plugin): direção visual das telas.
- **context-mode** (plugin/MCP): leitura e execução de comandos com economia de contexto.
- **laravel-boost** (pacote dev + MCP): guidelines, skills e ferramentas do Laravel.
- **browser-use** (plugin/MCP): smoke tests no navegador.

Detalhes em `CLAUDE.md` e na skill `.claude/skills/recibos/SKILL.md`.
