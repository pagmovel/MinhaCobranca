# Aviso de vencimento pelo WhatsApp — especificação (Fase 24)

**Pedido do usuário (2026-10-11):** "Adicione também uma forma de compartilhar mensagens via whatsapp avisando sobre
vencimento. Um campo para que possamos digitar modelos de mensagens a serem utilizadas por whatsapp que contenham os
serviços ou produtos que estão vencendo nos proximos 5 dias, a data de vencimento e o valor total da fatura que está
vencendo."
**Respostas do usuário (2026-10-11):** bloco no Painel agrupado por cliente + ação no menu "⋯" de cada cobrança em
aberto; vários modelos com um padrão; prazo configurável (padrão 5 dias); marcar "avisado em". **Desenho aprovado em chat
("sim", 2026-10-11).** Depende da Fase 23 (vencimento em dia útil) só na ordem de execução, não no código.

## 1. Envio
- **Sem API paga.** O envio automático pela API do WhatsApp é pago (regra do projeto: serviço externo só se gratuito).
  O sistema monta a mensagem e abre o WhatsApp com ela pronta; a pessoa toca em enviar.
- Link "clique para conversar": `https://wa.me/<número com DDI, só dígitos>?text=<mensagem codificada>`
  **[A VERIFICAR na documentação oficial antes de implementar]** (faq.whatsapp.com é montada por JavaScript; o `curl` e o
  Chrome de teste não trouxeram o texto em 2026-10-11). Abrir no clique (gesto do usuário) para o navegador não bloquear.
- **Número:** o telefone do cliente (`clientes.telefone`, texto com máscara) vira só dígitos com `55` na frente. Celular
  = DDD + 9 dígitos começando com 9. Sem celular válido: a janela pede o número (com máscara de telefone) e oferece
  **"Copiar mensagem"** como alternativa. O número digitado não é gravado no cliente (fora do escopo).

## 2. Modelos de mensagem
- Tabela `modelos_mensagem` (`user_id`, `nome`, `texto`, `padrao` booleano), por usuário (isolamento pela relação,
  404 em alheio). Exatamente um padrão quando houver modelos (marcar outro desmarca o anterior, na mesma transação).
- Na página do perfil, seção **"Mensagens de WhatsApp"**: lista com incluir/editar/excluir na própria linha (padrão 8,
  como as chaves PIX) e **"Avisar com [N] dias de antecedência"** (`users.dias_aviso`, inteiro 1–30, padrão 5).
- Sem modelo cadastrado, a janela de envio já vem com um **texto sugerido** (não gravado) e o link "Salvar como modelo".
- **Variáveis** (botões que inserem no texto, com legenda):
  - `{cliente}` — apelido do cliente, ou o nome (regra de exibição da Fase 21);
  - `{itens}` — uma linha por item das cobranças do aviso: `- Hospedagem de site: R$ 89,90`; cobrança sem itens usa a
    primeira linha da descrição ou "Serviço";
  - `{vencimento}` — a data (`15/10/2026`); várias datas: `15/10 e 20/10/2026`;
  - `{total}` — soma das cobranças do aviso (`R$ 349,80`);
  - `{prestador}` — nome fantasia do prestador, ou o nome.
  Variável desconhecida fica como está no texto (a prévia mostra antes de abrir).
- Texto até 2000 caracteres. Montagem num módulo puro `resources/js/mensagemWhatsapp.js` (teste `node --test`), com os
  dados prontos vindos do servidor; a prévia atualiza ao trocar o modelo e pode ser editada antes de abrir.

## 3. Onde aparece
- **Painel — bloco "Vencendo nos próximos N dias"** (N = `dias_aviso`): cobranças **pendentes** (enviadas ou não) com
  vencimento de hoje até hoje + N, **agrupadas por cliente**: cliente (apelido), quantas, datas, total, "Avisado em
  dd/mm" (o mais recente do grupo) e o botão **"Avisar no WhatsApp"**. Carregado por ajax como as outras listas do painel
  (`/painel/dados?lista=vencendo`, só leitura, `BlocoDePaginas`, 10 por página).
- **Lista de cobranças — menu "⋯"** de cada cobrança pendente: **"Avisar no WhatsApp"** (a mensagem só dessa cobrança).
- Previstas não entram (não há cobrança gerada).

## 4. "Avisado em"
- Coluna `cobrancas.avisado_em` (data e hora, nula). Ao clicar em "Abrir WhatsApp" (ou "Copiar mensagem"), a tela chama
  `POST /cobrancas/avisadas` com os ids do aviso: grava `avisado_em = agora` só nas cobranças **do usuário** e
  **pendentes** (ids alheios ou de outra situação são ignorados; validação `integer` antes de `exists`, pegadinha dos ids
  não numéricos). A linha mostra "Avisado em dd/mm"; avisar de novo é permitido (atualiza a data).
- Não é auditoria (não entra em `auditorias`).

## 5. Banco (PORTÃO-BANCO — migração só com o "sim" do usuário, com conexão, contagem e comando literal)
- `modelos_mensagem` (nova), `users.dias_aviso` (inteiro, padrão 5), `cobrancas.avisado_em` (nula).

## 6. Fora do escopo
- Envio automático, agendado ou em massa sem a pessoa tocar em enviar; API do WhatsApp; outros canais (e-mail, SMS).
- Anexar o PDF pelo link (o link do WhatsApp só leva texto; o PDF continua pelo "Enviar" de sempre).
- Gravar no cliente o número digitado na janela.

## 7. Testes (resumo)
- Modelos: CRUD isolado por usuário (404), um padrão só, validação (nome, texto até 2000).
- `dias_aviso`: validação 1–30 e padrão 5.
- `/painel/dados?lista=vencendo`: só pendentes do usuário no intervalo, agrupadas por cliente, total certo, só leitura
  (entra no `DadosDasListasTest`).
- `POST /cobrancas/avisadas`: grava só nas próprias pendentes; ignora alheias e pagas; exige sessão.
- `mensagemWhatsapp.js`: substituição das variáveis, várias datas, cobrança sem itens, número com/sem DDI, celular
  inválido, codificação do texto no link.
