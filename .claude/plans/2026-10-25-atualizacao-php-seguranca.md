# Atualização de segurança do PHP (PHP2Shell) — 2026-10-25

**Objetivo:** deixar o projeto pronto para o deploy na VPS com o PHP corrigido. A VPS só é tocada pelo Marcos
(ou com autorização expressa dele, naquela mensagem).

**Estados:** pendente / concluída / testada / validada.

## O que saiu em 21/10/2026 (fontes abertas nesta sessão)
- PHP 8.5.12, **8.4.27**, 8.3.36 e 8.2.35 corrigem duas falhas críticas de execução remota de código sem login,
  CVSS v4.0 9.2: **CVE-2026-109618** (corrupção de memória em falta de memória, GHSA-w8hw-w9fx-j79w) e
  **CVE-2026-109619** (estado global velho reaproveitado entre requisições, GHSA-6wmf-4rv5-p3rp).
  Fontes: ChangeLog e avisos do php.net, os dois GHSA em github.com/php/php-src, JSON de releases do php.net.
- Todo PHP que atende web está em risco; o php.net considera **maior o risco no Apache com mod_php** (é o nosso
  caso: imagem `php:*-apache`), porque o ataque pode acontecer antes de qualquer código da aplicação.
- O php.net afirma que **só atualizar corrige por completo**; não existe mitigação de configuração completa.
- Terceiro caso, **CVE-2026-115560** (aviso de 23/10, ainda sem correção): exige que a aplicação troque
  `session.serialize_handler` em tempo de execução. O php.net sugere `session.upload_progress.enabled=0`.
  Este projeto não usa `session_start`, `ini_set` nem `serialize_handler` (conferido com `git grep`).
- Exploração: watchTowr diz ter visto ataques desde 21/10; o php.net diz não ter identificado exploits
  funcionais na publicação. As fontes divergem; **[INCONCLUSIVO]** quão disseminado está.

## Fase 1 — imagem de produção
- **T1.1 — fixar a imagem base em `php:8.4.27-apache-bookworm`** (era a tag móvel `8.4-apache-bookworm`, que o
  builder pode reaproveitar do cache sem baixar a nova). Tag conferida no Docker Hub (publicada em 21/10/2026).
  Estado: testada (T1.3).
- **T1.2 — `session.upload_progress.enabled = 0`** em `conf.d/zz-seguranca.ini`. O Laravel não usa sessão nativa do
  PHP, então não há efeito colateral. Defesa extra, não substitui a atualização. Estado: testada (T1.3).
- **T1.3 — construir a imagem localmente** e conferir: `php -v` = 8.4.27, extensões `pdo_pgsql gd opcache`,
  `upload_progress` desligado, `php artisan --version` sobe. Estado: testada (2026-10-25). A primeira tentativa
  falhou porque o Docker snap não lê `/srv`; o build foi refeito a partir de uma cópia em `$HOME`. No Apache
  (`apache2handler`) a imagem responde PHP 8.4.27 com `upload_progress` = 0. Imagem e cópia de teste apagadas.

## Fase 2 — documentação
- **T2.1 — README e skill `recibos`** (seção Deploy): versão fixa, como subir a versão, passos do deploy. Estado: concluída.

## Fase 3 — deploy (só o Marcos)
- **T3.1 — commit e push** (o Easypanel puxa do Git). Estado: concluída (`bac0ef1`, no `main`).
- **T3.2 — Deploy na VPS pelo painel** (reconstrói a imagem a partir do Git) e conferência de `php -v` no container.
  Estado: testada (2026-10-25). Deploy feito pelo Marcos. Conferência só de leitura, por SSH autorizado na mensagem.
  Container `meus_projetos_gerenciador_cobrancas-app-1` criado às 10:13. Resultados:
  - PHP 8.4.27, `upload_progress` = 0 e `zz-seguranca.ini` carregado.
  - "Nothing to migrate": as 26 migrações já estavam aplicadas, inclusive `apresentacao_vista`, no lote 2.
  - Log de subida limpo (só o aviso AH00558 de ServerName).
  - Views compiladas todas da subida, sem `eval`/`base64_decode`/`system`.
  - Volume `storage-app` de 11/10, vazio porque nenhum usuário tem logo.
  - Logs do BunkerWeb de 19 a 25/10: nenhum erro de upstream. Os POSTs em `/` e `/painel` são varreduras: 29
    barradas pelo ModSecurity (403), 4 com 400 e 6 que chegaram ao Laravel e voltaram 303. A amostra é payload de
    Next.js (`__proto__:then`), não PHP2Shell.

## Fora do escopo
- Trocar Apache + mod_php por PHP-FPM: **decidido não fazer** (Marcos, 2026-11-01). A correção já está em produção, e a troca
  seria só um reforço opcional. Só atualizar o PHP corrige estas falhas, segundo o php.net.
- PHP da máquina local: o pacote Ubuntu `8.5.4-0ubuntu1.5` traz os patches dos dois CVEs (backport, visto no changelog
  do pacote), então não está vulnerável a eles, apesar do número da versão. Não é produção.
