# Módulo de Cobrança — catálogo, itens, filtros, consulta CNPJ/CEP e relatórios — design

**Data:** 2026-10-10 · **Estado:** aprovado em conversa (partes 1 e 2), aguardando revisão da spec escrita
**Módulo:** Cobrança (produto SaaS vendido por planos; ver skill `recibos`, seção "Módulos do produto").
**Base:** [2026-10-09-sistema-recibos-design.md](2026-10-09-sistema-recibos-design.md) e
[2026-10-10-pagamento-e-formularios-design.md](2026-10-10-pagamento-e-formularios-design.md) — o que não está aqui
continua valendo de lá.

## 1. Objetivo

O usuário cobra contratos compostos por vários produtos e serviços e quer saber o que mais fatura, por cliente e por
item. Precisa achar clientes e contratos rápido (filtros), cadastrar clientes sem redigitar dados públicos e
acompanhar o caixa com relatórios.

**Sucesso:** contrato montado por itens do catálogo com o total calculado; receita exata por produto/serviço mês a mês;
cadastro de cliente PJ preenchido a partir do CNPJ; relatórios que batem com a lista de cobranças; nada disso quebra se
o serviço externo cair.

## 2. Decisões tomadas com o usuário (2026-10-10)

| Pergunta | Decisão |
|---|---|
| Itens × valor e descrição do recibo | **Misto:** os itens **somam o valor**; a **descrição do recibo continua livre** |
| Cobranças levam itens? | **Sim, copiados** do contrato na geração (e escolhidos na avulsa); mudar o contrato depois não altera cobranças passadas |
| Códigos fiscais com vários itens | **Do item principal** (marcado no contrato; padrão: o de maior valor) |
| Cálculo de tributos | **Fora deste módulo.** Só faz sentido quando o cliente emitir NF; a pesquisa das regras oficiais (LC 116/2003, ISS municipal, IBS/CBS, leiaute da NFS-e) é a **primeira tarefa do módulo de Notas Fiscais**. Aqui o catálogo só guarda os códigos |
| Reajuste de preço | **O contrato guarda o preço.** O do catálogo é sugestão; ação "Reajustar contratos" aplica o novo preço aos contratos escolhidos (vale para as próximas cobranças) |
| Custo | **Não fica no catálogo da Cobrança** — vem do módulo Contas a Pagar (produto/serviço vinculado a fornecedor) |
| Cliente com vários contratos | Já permitido (sem restrição de unicidade); relatórios somam todos |
| Relatórios da 1ª versão | Faturamento por período · Receita por cliente · Receita por produto/serviço · Contas a receber |
| "Recebido" (pendência D1 do plano anterior) | **Regime de caixa:** o que foi **pago no mês** (data da baixa), nos relatórios **e** no topo do painel |
| Consulta CNPJ/CEP | Aprovado **desde que gratuito**: ViaCEP (principal p/ CEP), BrasilAPI (CNPJ e reserva do CEP) — ver seção 6 |
| Cadastro de cliente reaproveitado de outro usuário | **Rejeitado** (LGPD; decisão registrada na skill) — só dados públicos |

## 3. Dados

### 3.1 `produtos_servicos` (nova)
| Coluna | Tipo | Regra |
|---|---|---|
| `id`, `user_id` (FK, `cascadeOnDelete`) | | dono |
| `nome` | string(120) | obrigatório; `unique(user_id, nome)` |
| `tipo` | string | enum `TipoItem`: `servico`, `produto` |
| `unidade` | string(20) | ex.: "mês", "un", "hora" (texto livre curto, padrão "un") |
| `preco_centavos` | bigint | preço **sugerido** (≥ 0) |
| `codigo_servico`, `codigo_nbs`, `codigo_tributacao_nacional` | string, nulos | mesmos tamanhos de hoje no contrato (255/20/20) |
| `ativo` | boolean, default true | item inativo não aparece para escolha, mas continua nos contratos que já o usam |

Item usado por contrato ou cobrança **não é excluído** (só desativado) — checagem no controller + `restrictOnDelete`.

### 3.2 `contrato_itens` (nova)
| Coluna | Tipo | Regra |
|---|---|---|
| `id`, `contrato_id` (FK, `cascadeOnDelete`) | | |
| `produto_servico_id` | FK, `restrictOnDelete` | item do catálogo **do mesmo usuário** |
| `quantidade` | decimal(10,3) | > 0 (aceita fração: 1,5 hora) |
| `preco_unitario_centavos` | bigint | preço **deste contrato** (≥ 0) |
| `principal` | boolean | exatamente um por contrato (o servidor garante) |
| `ordem` | smallint | ordem de exibição |

`total do item = round(quantidade × preço_unitário)` em centavos (arredondamento meio-para-cima, só inteiros).
`contratos.valor_centavos` = soma dos totais, **recalculada pelo servidor ao salvar** (o valor enviado pela tela é
ignorado). `contratos.codigo_*` = códigos do item principal, copiados ao salvar (o emissor de recibos continua lendo
do contrato/cobrança como hoje).

Contratos existentes (sem itens) **continuam valendo** com o valor atual. Ao **editar** um contrato sem itens, o
formulário exige incluir pelo menos um. Contratos novos exigem ≥ 1 item.

### 3.3 `cobranca_itens` (nova)
| Coluna | Tipo | Regra |
|---|---|---|
| `id`, `cobranca_id` (FK, `cascadeOnDelete`) | | |
| `produto_servico_id` | FK nula, `nullOnDelete` | referência (o item pode ser desativado depois) |
| `nome`, `unidade` | string | **cópia** no momento da geração |
| `quantidade`, `preco_unitario_centavos`, `total_centavos` | | cópia |
| `principal` | boolean | cópia |

A geração copia os itens do contrato; a avulsa e a edição de cobrança não enviada gravam os itens escolhidos e
recalculam `cobrancas.valor_centavos` = soma. Cobranças **anteriores** ao catálogo ficam sem itens.

## 4. Regras

- **Isolamento:** `produto_servico_id` validado com `Rule::exists(...)->where('user_id', ...)`; rotas com id de outro
  usuário dão 404 **antes** da validação (`authorize()` do request, padrão da skill).
- **Item principal:** se nenhum vier marcado, o servidor marca o de maior total (empate: o primeiro).
- **Reajustar contratos** (`/produtos-servicos/{id}/reajuste`): lista os contratos **ativos** que usam o item, com o
  preço atual de cada um; o usuário escolhe quais e informa o novo preço (sugestão: o do catálogo); o servidor
  atualiza `preco_unitario_centavos` desses itens e recalcula `valor_centavos` dos contratos. Cobranças já geradas não
  mudam.
- **Recebido = pago no mês** (caixa): painel ("R$ X recebidos de R$ Y previstos") passa a somar `pago_em` no mês;
  "previsto" continua = não canceladas com **vencimento** no mês. A frase pode mostrar recebido > previsto (esperado).

## 5. Telas

- **Produtos e serviços** (menu do módulo): lista paginada (25) com filtros nome/tipo/situação; formulário com máscara
  de valor; ação "Reajustar contratos".
- **Seção "Itens"** em Contrato, Cobrança avulsa e edição de cobrança não enviada: busca do item por autosugestão
  (componente no padrão do `BuscaCliente`), quantidade, preço unitário (máscara de valor, sugerido do catálogo),
  total do item, marcar principal, remover; total geral ao vivo (só exibição — o servidor recalcula).
- **Filtros + paginação (25):**
  - Clientes: nome ou documento, cidade/UF, modelo do recibo, com/sem contrato ativo.
  - Contratos: cliente (autosugestão), situação (ativo/encerrado), modalidade, forma de pagamento, produto/serviço.
  Filtros na URL (dá para voltar e compartilhar o link).

## 6. Consulta de dados públicos (CNPJ e CEP)

- **Onde:** formulário de cliente e perfil. CNPJ completo e válido → botão "Buscar dados do CNPJ" preenche **só campos
  vazios** (razão social, nome fantasia, endereço, CEP). CEP completo → preenche logradouro, bairro, cidade, UF
  (campos vazios). **CPF nunca é consultado.**
- **Como:** pelo servidor (`GET /consultas/cnpj/{cnpj}`, `GET /consultas/cep/{cep}`, autenticado), com tempo limite
  de 5 s, cache de 30 dias por documento/CEP e limite por usuário (ex.: 30 consultas/min) — uso com "natureza de uma
  pessoa real", como os serviços pedem. Falha/indisponível/não encontrado → mensagem "Não foi possível consultar agora;
  preencha manualmente." e o formulário segue normal.
- **Provedores** (verificados em 2026-10-10, gratuitos, sem chave):
  - **ViaCEP** (`viacep.com.br`) — "Webservice gratuito"; aviso de bloqueio para uso massivo de validação de bases →
    **principal para CEP**.
  - **BrasilAPI** (`brasilapi.com.br`, README "Termos de Uso": iniciativa sem fins comerciais, **em beta, termos em
    elaboração**, sem crawling automatizado) → **CNPJ** e **reserva do CEP**. Testado: CNPJ público respondeu em ~0,4 s
    com razão social, endereço e situação; aceita o formato alfanumérico (CNPJ fictício → 404 "não encontrado").
  - Cada provedor numa classe própria (troca fácil se termos/preço mudarem). Testes usam `Http::fake()` — nenhum teste
    chama a internet.

## 7. Relatórios (menu "Relatórios"; período padrão: últimos 12 meses; exportação CSV)

1. **Faturamento por período:** por mês — previsto (não canceladas com vencimento no mês), recebido (pago no mês),
   inadimplência (vencidas e pendentes no fim do mês). Gráfico de barras em CSS/SVG, sem biblioteca.
2. **Receita por cliente:** recebido no período, nº de contratos ativos, valor médio por cobrança paga, nº de cobranças
   vencidas em aberto e dias médios de atraso.
3. **Receita por produto/serviço:** soma de `cobranca_itens.total_centavos` das cobranças **pagas no período**
   (recebido) e das que **vencem** no período (previsto); nº de contratos ativos que usam o item. Cobranças sem itens
   (anteriores ao catálogo) aparecem numa linha "Sem item".
4. **Contas a receber:** pendentes vencendo em 30/60/90 dias e vencidas (com dias de atraso), por cliente.

Tudo filtrado pelo usuário logado; SQL portável (SQLite nos testes, PostgreSQL 18 no banco); somas em centavos.

## 8. Ordem de entrega (fases)

| Fase | Conteúdo | Banco |
|---|---|---|
| 12 | Catálogo + itens no contrato e na cobrança + geração copiando itens + reajuste | 3 migrações (PORTÃO-BANCO) |
| 13 | Filtros e paginação de Clientes e Contratos | — |
| 14 | Consulta de CNPJ/CEP (proxy, cache, limite) | — |
| 15 | Relatórios (4) + CSV + "recebido = pago no mês" no painel | — |

Cada fase: testes primeiro, commit por tarefa, merge no `main` + push ao fechar (autorização permanente).

## 9. Testes e verificação

- PHPUnit: soma e arredondamento dos itens, principal automático, isolamento (`produto_servico_id` alheio, rotas de
  outro usuário → 404), contrato legado sem itens, geração copiando itens, reajuste só nos escolhidos, filtros,
  consultas com `Http::fake()` (sucesso, 404, tempo esgotado, cache), relatórios com cobranças de vários meses/status.
- Smoke test com o browser-agent (PORTÃO-BANCO para os dados SMOKE).
- Conferência visual dos relatórios e da seção de itens entra nas pendências do final.

## 10. Fora do escopo

Custo e rentabilidade (módulo Contas a Pagar), cálculo de tributos e emissão de NF (módulo Notas Fiscais), estoque de
produtos, dashboards além dos 4 relatórios, liberação de módulos por plano de assinatura.
