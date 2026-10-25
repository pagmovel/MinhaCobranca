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
- **T3.1 — commit e push** (o Easypanel puxa do Git). Estado: pendente, aguarda o "sim" do Marcos.
- **T3.2 — Deploy na VPS pelo painel** (reconstrói a imagem a partir do Git) e conferência de `php -v` no container.
  Estado: pendente, feita pelo Marcos.

## Fora do escopo (decisão a tomar)
- Trocar Apache + mod_php por PHP-FPM reduziria a exposição, mas muda a imagem inteira; não foi pedido.
- PHP da máquina local é 8.5.4, anterior à 8.5.12 (vulnerável). Não é produção; atualizar fica com o Marcos.
