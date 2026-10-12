# Renovar contrato e prorrogar cobrança — especificação (T17.11)

**Pedido do usuário (2026-10-11):** "precisamos de uma flag... não, isso foi outra tarefa — aqui o pedido foi retomar
a T17.11 do backlog: 'renovar ou prorrogar contrato perto do fim, com os mesmos dados e permitindo alterações'."
Esclarecido em brainstorming: **"Renovar" é para contrato; "prorrogar" é para cobrança** — são duas ações distintas,
não uma só.

**Decisões do usuário (2026-10-11, brainstorming em chat):**
- Renovar contrato: mesmo registro, só estende/limpa a `data_fim` (não vira contrato novo — isso já existe como
  "Recomeçar", para quando valor/itens/periodicidade mudam).
- Renovar só vale para contrato com **encerramento agendado** (`ativo = true`, `ultimo_periodo !== 'inteiro'`); contrato
  já encerrado de fato (`ativo = false`) continua usando o "reativar" que já existe, sem mudança.
- Cobranças afetadas pelo agendamento (canceladas, ou ajustadas pra proporcional) são **desfeitas automaticamente** ao
  renovar — exceto o caso em que a cobrança proporcional já tinha sido enviada (ver seção 1).
- Nova `data_fim` ao renovar: o usuário escolhe — vazio (sem fim) ou uma data nova.
- Prorrogar cobrança: só adia o `vencimento` de uma cobrança já existente, sem cancelar/recriar nada.
- Prorrogar aparece no menu "⋯" da cobrança (mesmo padrão de `resources/js/acoesCobranca.js`), vale para qualquer
  cobrança em aberto — de contrato ou avulsa, enviada ou não — e só aceita data **depois** da atual.
- Renovar aparece só na tela de editar o contrato (não na lista). Painel ganha um aviso (banner simples, estilo do
  aviso de "dia não útil") para contrato perto do fim, com antecedência configurável **separada** da de cobrança
  (`dias_aviso` já existe para cobrança/WhatsApp).
- **Desenho aprovado em chat ("sim", 2026-10-11).**

## 1. Renovar contrato

**Lacuna atual (confirmada no código):** quando um contrato tem encerramento agendado (`ultimo_periodo !== 'inteiro'`,
`ativo = true`, fim no futuro ou já passado), nem o formulário normal de edição (recusa mudar `data_fim` com esse
agendamento) nem o botão "Encerrar contrato" (mesma recusa, ver `ContratoController.php:299-303`) deixam o usuário
desfazer ou adiar esse agendamento. Essa lacuna é exatamente o que "Renovar" resolve. Um contrato com `data_fim` fixo
mas **sem** encerramento agendado (`ultimo_periodo === 'inteiro'`) já pode ter a `data_fim` editada livremente pelo
formulário normal — não precisa de "Renovar".

**Onde aparece:** na tela de editar o contrato (`Contratos/Form.vue`), substituindo a seção "Encerrar contrato" quando
`contrato.encerramento_agendado === true`.

**Prévia (`GET /contratos/{contrato}/renovacao`):** calcula, a partir da `data_fim` atual (antes de renovar) e das
cobranças do contrato:
- Cobranças com `periodo_inicio` depois da `data_fim` atual e `status = Cancelada` → entram na lista como "voltam a
  pendente".
- A cobrança do período que contém a `data_fim` atual, se ficou `ultimo_periodo = 'proporcional'` e **era editável**
  (`status = Pendente`, `enviado_em = null`) no momento do ajuste → entra como "volta ao valor cheio".
- A cobrança do período que contém a `data_fim` atual, se ficou proporcional mas **já tinha sido enviada** (o
  `EncerradorDeContratos::proporcional()` cria, nesse caso, uma cobrança avulsa nova com `contrato_id = null` e cancela
  a original) → entra só como **aviso**, sem ação automática: "Esta cobrança já gerou uma avulsa proporcional porque
  foi enviada antes do encerramento — revise manualmente".
- Campo de nova `data_fim`: vazio (sem fim) ou uma data **depois de hoje** — igual ou anterior é rejeitado (renovar
  é sempre para frente; uma data passada reabriria o mesmo bug de contrato "preso" ativo com fim já vencido).

**Execução (`POST /contratos/{contrato}/renovar`):** numa transação:
1. Recusa se o contrato não está com encerramento agendado (`! $contrato->ativo || $contrato->ultimo_periodo === 'inteiro'`)
   — mesma letra do erro que hoje aparece em `encerrar()`/`update()`, adaptada: "Este contrato não tem encerramento
   agendado para renovar."
2. Recusa se a nova `data_fim` não é nula e não é estritamente posterior a hoje.
3. Atualiza `$contrato->update(['data_fim' => $novaData, 'ultimo_periodo' => 'inteiro'])` (`ativo` já é `true`).
4. Cobranças com `periodo_inicio` depois da `data_fim` ANTIGA e `status = Cancelada` → `status = Pendente`.
5. A cobrança do período de fronteira, se proporcional e editável → desfaz `ajustarProporcional` (volta
   quantidade/valor cheios, remove a linha "Proporcional: …" da descrição) e chama `EmissorDeRecibos::refazer()` se
   tiver recibo.
6. Audita `contrato.renovado` (segue o padrão de `contrato.encerrado`/`contrato.recomecado`).

**Novo service `app/Services/RenovadorDeContratos.php`:** `plano(Contrato, CarbonImmutable $hoje): array` (prévia) e
`renovar(Contrato, ?CarbonImmutable $novaData, CarbonImmutable $hoje): void` (execução) — espelha a dupla
`plano()`/`encerrar()` de `EncerradorDeContratos`, inclusive reaproveitando-o via injeção para não duplicar a leitura
das cobranças.

## 2. Prorrogar cobrança

**O que é:** adiar o campo `vencimento` de uma cobrança já existente — não cancela, não recria, não muda itens/valor.
Vale para qualquer cobrança **em aberto** (`status = Pendente`): de contrato ou avulsa, já enviada ou não. Paga e
cancelada não aparecem com essa ação (mesma regra das outras ações do menu).

**Onde aparece:** menu "⋯" da cobrança, em `resources/js/acoesCobranca.js` — nova entrada `'prorrogar'` na lista
`emOrdem()` do ramo "em aberto", rótulo **"Prorrogar vencimento"**, ao lado de `baixa`/`avisar`/`pagamento`/`cancelar`.

**Regra de validação:** a nova data deve ser **depois** da `vencimento` atual da cobrança (igual ou anterior é
rejeitado — isso seria "antecipar", fora do escopo).

**Execução (`POST /cobrancas/{cobranca}/prorrogar`):** numa transação:
1. Recusa se `status !== Pendente` (paga/cancelada) — 422 com mensagem.
2. Recusa se a nova data não é estritamente posterior à `vencimento` atual.
3. `$cobranca->update(['vencimento' => $novaData])`.
4. Audita `cobranca.vencimento_prorrogado` (mesmo padrão de `cobranca.vencimento_ajustado`, usado pelo ajuste de dia
   útil da Fase 23: mensagem com data antes/depois).
5. Se a cobrança já tem `recibo`, chama `EmissorDeRecibos::refazer($cobranca->recibo)` — o PDF guarda a data num
   snapshot (`Recibo::snapshot`), não lê a cobrança ao vivo; sem refazer, o PDF já emitido ficaria com a data antiga.

Reaproveita o `CobrancaController` existente (novo método `prorrogar`), sem necessidade de um service dedicado — é uma
mudança de um campo com uma validação e um `refazer()`, do mesmo tamanho que `cancelar()`/`pagar()` já no controller.

## 3. Aviso no Painel

- Banner simples (mesmo padrão do aviso de "dia não útil" em `Painel.vue`): "N contratos encerram nos próximos X dias
  → Ver", link para `/contratos`.
- Conta contratos `ativo = true` com `data_fim` não nula entre hoje e hoje + `dias_aviso_contrato`, **qualquer que seja
  a origem do fim** (agendado via "Encerrar" ou só uma `data_fim` simples digitada no formulário, sem
  `ultimo_periodo` alterado) — um fim simples já pode ser editado livremente hoje, então o aviso serve só para não
  esquecer, mesmo quando "Renovar" (seção 1) não é a ação certa para aquele caso.
- Novo campo `users.dias_aviso_contrato` (inteiro, **separado** de `dias_aviso`, que é de cobrança/WhatsApp), com
  validação 1–365 e padrão **30** (prazo de contrato é mais longo que o de cobrança). Editável no perfil, mesma seção
  de preferências onde já fica `dias_aviso`.
- Contagem exposta via `PainelController` (mesmo padrão de `AjusteDeVencimentos::quantas()`), sem bloco cheio por ajax
  — isso é bloco de cobrança ("Vencendo"), fora de escopo aqui.

## 4. Banco (PORTÃO-BANCO — migração só com o "sim" do usuário, com conexão, contagem e comando literal antes)

- `users.dias_aviso_contrato`: `unsignedSmallInteger`, padrão `30`, `after('dias_aviso')`.
- Nenhuma coluna nova em `contratos` ou `cobrancas` — `data_fim`, `ultimo_periodo`, `vencimento` já existem.
- Nenhuma tabela nova — auditoria usa a tabela `auditorias` já existente, com novos tipos de evento
  (`contrato.renovado`, `cobranca.vencimento_prorrogado`).

## 5. Fora do escopo

- Renovar com troca de valor/itens/periodicidade — isso é o fluxo "Recomeçar" que já existe.
- Renovar contrato já encerrado de fato (`ativo = false`) — continua usando o "reativar" que já existe.
- Desfazer automaticamente a avulsa criada quando o período proporcional já tinha sido enviado — a prévia só avisa,
  sem ação automática (risco de reemitir algo que o cliente já recebeu com outro valor).
- Bloco cheio por ajax de contratos no Painel (como o "Vencendo" de cobranças) — banner simples resolve, pela raridade
  do evento comparado a cobrança vencendo.
- Prorrogar "pra trás" (antecipar o vencimento) — só aceita data posterior à atual.
- Mudar `dias_aviso` existente — o novo campo (`dias_aviso_contrato`) é separado, não reaproveita o de cobrança.

## 6. Testes (resumo)

- `RenovadorDeContratos::plano()`: lista cobrança cancelada (depois do fim) para reverter; cobrança proporcional
  editável para reverter; cobrança proporcional não-editável (já enviada, virou avulsa) só como aviso, sem reversão
  automática.
- `RenovadorDeContratos::renovar()`: cobrança cancelada volta a pendente; cobrança proporcional editável volta ao
  valor/quantidade cheios e o recibo é refeito; cobrança proporcional não-editável não é tocada; nova `data_fim`
  (vazia ou futura) é gravada; `ultimo_periodo` volta a `'inteiro'`; recusa se não há encerramento agendado; recusa
  se a nova `data_fim` é hoje ou no passado.
- Rotas de contrato: 404 em contrato alheio; recusa (422) sem encerramento agendado.
- `CobrancaController::prorrogar`: grava nova `vencimento`; recusa data igual/anterior (422); recusa cobrança
  paga/cancelada (422); refaz o recibo quando existe; não refaz (nem erra) quando não existe; grava auditoria com
  data antes/depois; 404 em cobrança alheia; vale para avulsa e de contrato, enviada ou não.
- `acoesCobranca.js`: `'prorrogar'` aparece no menu para cobrança a vencer/vencendo/vencida, enviada ou não; não
  aparece para paga/cancelada/prevista.
- Perfil: `dias_aviso_contrato` validado (1–365), padrão 30 ao criar usuário.
- Painel: contador de contratos perto do fim usa `dias_aviso_contrato` (não `dias_aviso`); conta contrato com fim
  simples e com fim agendado; não conta contrato inativo nem sem `data_fim`.
