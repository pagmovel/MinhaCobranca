# Configurar o envio de e-mail do sistema

Guia para pôr o sistema (VPS `207.180.205.211`, Easypanel, serviço `gerenciador_cobrancas`) para enviar e-mail
usando a revenda cPanel. Levantamento feito em 2026-11-08.

Estados das tarefas: **pendente / concluída / testada / validada**.

## Situação em 2026-11-08

- **Não existe servidor de e-mail** na VPS nem no BunkerWeb: nenhuma porta de e-mail escutando, nenhum Postfix,
  Exim ou Dovecot, nenhum container de e-mail. O BunkerWeb só cuida do tráfego web.
- **O app em produção não envia nada:** `MAIL_MAILER=log` (as mensagens só vão para o log) e remetente
  `hello@example.com`.
- **O sistema ainda não tem nenhuma função que mande e-mail** (nenhum Mailable, Notification ou "esqueci minha
  senha"). Configurar o SMTP é o pré-requisito; o que enviar é outra decisão.
- **DNS do `smartcontroladoria.com.br`** (Cloudflare, `bonnie`/`drew.ns.cloudflare.com`) está fechado para e-mail:
  MX nulo (`0 .`), SPF `v=spf1 -all`, DMARC `v=DMARC1; p=reject;`. O registro `*` é curinga para a VPS.
- **Revenda cPanel:** servidor `br4.host.srv.br` (`85.155.127.8`, Exim). DNS do `paguemovel.net.br` na icloudzinc,
  com SPF e DKIM (`default._domainkey`) publicados e **sem DMARC**.
- **Conexão testada em 2026-11-08** (só conexão, sem autenticar nem enviar): da máquina local, do host da VPS e de
  dentro do container `meus_projetos_gerenciador_cobrancas-app-1`, as portas 465 (TLS direto) e 587 (STARTTLS) do
  `br4.host.srv.br` respondem, com certificado `CN=br4.host.srv.br` válido. O provedor da VPS não bloqueia a saída.

## Fase A: enviar como `smart-noreplay@paguemovel.net.br` (sem mexer no DNS da Smart)

Caminho rápido: a conta já existe na revenda e o domínio já tem SPF e DKIM. O cliente vê o remetente
`@paguemovel.net.br`.

| # | Tarefa | Estado |
|---|---|---|
| A1 | Criar a conta no cPanel | concluída (`smart-noreplay@paguemovel.net.br`) |
| A2 | Decidir a grafia: `noreplay` ficou com "a"; o comum é `noreply`. Se for corrigir, criar a conta nova antes da A4 | pendente |
| A3 | Criar o DMARC do `paguemovel.net.br` (DNS na icloudzinc): TXT `_dmarc` = `v=DMARC1; p=none;` | pendente |
| A4 | Pôr as variáveis no Easypanel (abaixo) e fazer o Deploy | pendente |
| A5 | Teste de envio para `marcos.ronaldo@gmail.com` (abaixo) e conferir se não caiu no spam | pendente |

Variáveis do Easypanel:

```
MAIL_MAILER=smtp
MAIL_HOST=br4.host.srv.br
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=smart-noreplay@paguemovel.net.br
MAIL_PASSWORD=<senha da caixa>
MAIL_FROM_ADDRESS=smart-noreplay@paguemovel.net.br
MAIL_FROM_NAME="Smart Controladoria"
```

- `MAIL_FROM_ADDRESS` tem de ser a mesma caixa do `MAIL_USERNAME`: servidor cPanel recusa ou marca como suspeito
  e-mail com outro remetente.
- Para usar a porta 587, trocar para `MAIL_PORT=587` e `MAIL_SCHEME=smtp` (o STARTTLS é automático).
- `MAIL_HOST` é o hostname do servidor, não `mail.<domínio>`, porque o certificado válido é o dele.

## Fase B: enviar como `no-reply@smartcontroladoria.com.br`

Para o cliente ver o domínio da Smart. Só pôr o endereço no `MAIL_FROM_ADDRESS` **não funciona**: o DNS atual
proíbe o envio (o Gmail e os outros rejeitam por causa do DMARC `p=reject`), e o servidor da revenda só assina o DKIM
dos domínios que estão nele.

| # | Tarefa | Estado |
|---|---|---|
| B1 | cPanel → **Domínios → Criar domínio**: `smartcontroladoria.com.br` na mesma conta do paguemovel. A pasta de site que ele cria não é usada; o site continua na VPS | pendente |
| B2 | cPanel → **Contas de e-mail**: criar `no-reply@smartcontroladoria.com.br` | pendente |
| B3 | cPanel → **Roteamento de e-mail**: escolher **Trocador de e-mail local** para o domínio (no automático, com DNS fora do cPanel, ele pode não entregar nas caixas) | pendente |
| B4 | cPanel → **Capacidade de entrega de e-mail**: abrir o domínio e copiar o SPF e o DKIM que ele sugerir | pendente |
| B5 | Cloudflare: aplicar as mudanças de DNS (abaixo) | pendente |
| B6 | Conferir o DNS (comandos abaixo) | pendente |
| B7 | Easypanel: trocar `MAIL_USERNAME`, `MAIL_PASSWORD` e `MAIL_FROM_ADDRESS` para a caixa nova e fazer o Deploy | pendente |
| B8 | Teste de envio (abaixo) e, no Gmail, "Mostrar original": SPF, DKIM e DMARC devem aparecer como `PASS` | pendente |
| B9 | Depois de uns dias sem problema, apertar o DMARC para `p=quarantine` e depois `p=reject` | pendente |

Mudanças no DNS (Cloudflare):

| Ação | Registro |
|---|---|
| Apagar | MX `0 .` (com MX nulo, os provedores podem recusar e-mail do domínio) |
| Criar | MX `@` → `br4.host.srv.br`, prioridade 0 |
| Trocar | SPF `v=spf1 -all` → **exatamente o valor que o cPanel sugerir** |
| Criar | TXT `default._domainkey` → o DKIM que o cPanel mostrar |
| Trocar | DMARC `v=DMARC1; p=reject;` → `v=DMARC1; p=none;` |
| Manter | `@`, `www` e o curinga `*` (são do site, na VPS) |

Pegadinhas:

- **SPF: copiar, não montar à mão.** A revenda repassa os envios por terceiros (MailBaby, MailChannels,
  emailemnuvem; ver `dig +short TXT spf.host.srv.br`), e o SPF sugerido pelo cPanel já inclui esses serviços.
- `[A VERIFICAR]` o MailChannels às vezes exige um TXT `_mailchannels.<domínio>` ("Domain Lockdown") para cada
  domínio que envia. O `paguemovel.net.br` não tem esse registro e envia, então provavelmente não é preciso; se os
  e-mails voltarem com erro do MailChannels, perguntar à revenda.
- Se no futuro for preciso um `mail.smartcontroladoria.com.br`, criar um `A mail` → `85.155.127.8` com a **nuvem
  cinza** ("DNS only"): a Cloudflare não repassa tráfego de e-mail, e sem o registro o curinga manda o `mail.` para a
  VPS.
- **Limite de envio:** revenda cPanel costuma limitar e-mails por hora. Perguntar o limite antes de mandar recibos em
  lote.

## Conferências

DNS (consulta pública, não precisa de acesso à VPS):

```bash
d=smartcontroladoria.com.br
dig +short MX $d @1.1.1.1
dig +short TXT $d @1.1.1.1
dig +short TXT default._domainkey.$d @1.1.1.1
dig +short TXT _dmarc.$d @1.1.1.1
```

Conexão com o servidor da revenda (a partir da VPS: **só com autorização expressa do Marcos**):

```bash
openssl s_client -connect br4.host.srv.br:465 -servername br4.host.srv.br -brief </dev/null
openssl s_client -connect br4.host.srv.br:587 -starttls smtp -servername br4.host.srv.br -brief </dev/null
```

Teste de envio, de dentro do container, depois do Deploy com as variáveis novas (**só com autorização expressa do
Marcos**):

```bash
docker exec meus_projetos_gerenciador_cobrancas-app-1 php artisan tinker --execute \
  'Illuminate\Support\Facades\Mail::raw("Teste de envio do sistema.", fn ($m) => $m->to("marcos.ronaldo@gmail.com")->subject("Teste SMTP"));'
```

Se der erro de autenticação, conferir a senha e se o `MAIL_USERNAME` é o endereço completo. Se der timeout, testar a
conexão com os comandos `openssl` acima.

## Depois do SMTP

Decidir o que o sistema vai enviar primeiro (recibo em PDF para o cliente, aviso de vencimento, recuperação de senha)
e implementar. Isso é código e entra no plano do projeto.
