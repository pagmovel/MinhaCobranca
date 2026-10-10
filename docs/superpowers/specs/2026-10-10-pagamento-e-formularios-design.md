# Pagamento no recibo e formulários melhores — design

**Data:** 2026-10-10 · **Estado:** aprovado em conversa (partes 1 e 2), aguardando revisão da spec escrita
**Base:** [2026-10-09-sistema-recibos-design.md](2026-10-09-sistema-recibos-design.md) — tudo o que não
está aqui continua valendo de lá.

## 1. Objetivo

O usuário cobra contratos pré-pagos e pós-pagos e recebe por PIX (várias chaves) ou boleto. O recibo
enviado pelo WhatsApp precisa dizer **como pagar**: QR Code PIX com o valor já preenchido, a chave e o
"PIX copia e cola", ou a linha digitável do boleto. Os formulários precisam de máscaras e de busca de
cliente que funcione com muitos clientes. A situação "vence hoje" precisa aparecer diferente de "a vencer".

**Sucesso:** o cliente do usuário escaneia o QR do recibo e o app do banco já mostra o valor certo e o
nome do usuário; contrato pós-pago vence no mês seguinte ao período; o usuário acha um cliente digitando
parte do nome ou do documento; nenhum campo de valor, documento, CEP ou telefone aceita formato torto.

## 2. Decisões tomadas com o usuário

| Pergunta | Decisão |
|---|---|
| Vencimento do pós-pago | Mês seguinte ao período: **primeira data com o dia escolhido depois do fim do período** |
| QR do PIX | **Com o valor da cobrança** e identificação do recibo; mostra também chave e "copia e cola" |
| Boleto | O sistema não emite boleto. Campo opcional de **linha digitável** na cobrança; o recibo mostra "Pagamento por boleto bancário" + a linha |
| Layout com o bloco de pagamento | **Alterar `webiss-v1`/`danfse-v1`** (nenhum recibo foi enviado a cliente ainda). A regra "mudou o layout = nova versão" passa a valer depois do primeiro recibo enviado |
| Trocar forma de pagamento | Pode **até a baixa**, mesmo com a cobrança já enviada; o resto da cobrança continua travado após o envio |
| Situação "vence hoje" | Carimbo **VENCENDO HOJE em laranja**; VENCIDO (vermelho) só depois do dia do vencimento |
| Biblioteca de QR | **`chillerlan/php-qrcode`** (PHP puro, PNG pelo GD já instalado) — dependência aprovada |
| Máscaras e autosugestão | **Feitas no projeto**, sem biblioteca JS nova |
| Ordem de entrega | Fase 8 (formulários + vence hoje) → 9 (pré/pós-pago) → 10 (chaves PIX e forma de pagamento) → 11 (bloco no PDF) |

## 3. Dados

### 3.1 Tabela nova `chaves_pix`
| Coluna | Tipo | Regra |
|---|---|---|
| `id` | bigint | |
| `user_id` | FK users, `cascadeOnDelete` | dono |
| `tipo` | string | enum `TipoChavePix`: `cpf`, `cnpj`, `email`, `telefone`, `aleatoria` |
| `chave` | string(77) | normalizada (ver 3.4); `unique(user_id, chave)` |
| `apelido` | string(60), nulo | ex.: "Nubank PJ" — aparece nas listas de escolha |
| timestamps | | |

Chave em uso por contrato ou cobrança **não é excluída** (checagem no controller + `restrictOnDelete`
nas FKs). Pode ser editada; a edição reflete nos PDFs gerados depois (dado vivo).

### 3.2 `contratos` (colunas novas)
| Coluna | Tipo | Regra |
|---|---|---|
| `modalidade` | string, default `prepago` | enum `Modalidade`: `prepago`, `pospago`. Contratos existentes ficam pré-pagos (nada muda neles) |
| `forma_pagamento` | string, default `nenhuma` | enum `FormaPagamento`: `pix`, `boleto`, `nenhuma` |
| `chave_pix_id` | FK chaves_pix, nulo, `restrictOnDelete` | obrigatória quando `forma_pagamento = pix`; nula nos outros casos |

`modalidade` não muda quando o contrato já tem cobranças (mesma proteção de início/periodicidade).
`forma_pagamento`/`chave_pix_id` podem mudar e valem para as cobranças geradas depois.

### 3.3 `cobrancas` (colunas novas)
| Coluna | Tipo | Regra |
|---|---|---|
| `forma_pagamento` | string, default `nenhuma` | copiada do contrato na geração; escolhida na avulsa |
| `chave_pix_id` | FK chaves_pix, nulo, `restrictOnDelete` | idem contrato |
| `linha_digitavel` | string(48), nulo | só dígitos; só com `forma_pagamento = boleto` |

Cobranças existentes ficam `nenhuma` (sem bloco de pagamento) até o usuário escolher.

### 3.4 Validação e normalização
- Toda FK `chave_pix_id` validada com `Rule::exists('chaves_pix','id')->where('user_id', usuário)` (isolamento).
- Chave por tipo: CPF → 11 dígitos com DV (`Documento`); CNPJ → 14 caracteres com DV, inclusive
  alfanumérico **[A VERIFICAR: se o DICT do PIX já aceita CNPJ alfanumérico como chave; se não, só numérico]**;
  e-mail → `email`, minúsculas; telefone → guardado como `+55` + DDD + número (10 ou 11 dígitos);
  aleatória → UUID (36 caracteres com hífens), minúsculas.
- Linha digitável: 47 dígitos (boleto bancário) ou 48 (arrecadação/convênio), só dígitos. Sem
  validação de DV nesta versão (o usuário cola do banco).

## 4. Regras

### 4.1 Pré-pago e pós-pago (`CalculadoraDePeriodos`)
- **Pré-pago (como hoje):** vencimento = dia escolhido no mês em que o período **começa** (limitado ao
  último dia do mês).
- **Pós-pago:** vencimento = **primeira data com o dia escolhido estritamente depois do fim do período**,
  limitada ao último dia do mês. Ex.: período 01/10–31/10, dia 10 → 10/11; período 15/09–14/10, dia 20 →
  20/10; dia 10 → 10/11; dia 31 com fim em 31/01 → 28/02 (ou 29/02).
- "Devido" continua `vencimento − antecedência ≤ hoje`. No pós-pago a cobrança pode nascer antes do fim do
  período se a antecedência for grande — comportamento aceito (o usuário controla a antecedência).

### 4.2 Situação da cobrança (`Cobranca::situacao`)
`paga` · `cancelada` · pendente com vencimento < hoje → `vencida` · vencimento = hoje → **`vence_hoje`** ·
vencimento > hoje → `a_vencer`.

| Situação | Carimbo (tela e PDF) | Cor |
|---|---|---|
| `a_vencer` | A VENCER | azul-carbono `#2F3C9E` |
| `vence_hoje` | **VENCENDO HOJE** | **laranja (token novo `tinta-laranja`, ~`#C2410C`; contraste ≥ 4,5:1 sobre branco verificado no plano)** |
| `vencida` | VENCIDO | vermelho `#B3261E` |
| `paga` | PAGO em dd/mm/aaaa | verde `#2E7D4F` |
| `cancelada` | CANCELADO | cinza |

### 4.3 Forma de pagamento na cobrança
- Geração pelo contrato copia `forma_pagamento` e `chave_pix_id`.
- Edição completa (cobrança não enviada) inclui os campos de pagamento.
- Cobrança **enviada e pendente**: rota própria `PUT /cobrancas/{cobranca}/pagamento` troca só
  `forma_pagamento`, `chave_pix_id` e `linha_digitavel`. Paga ou cancelada → recusa com mensagem.
- O recibo **não congela** a forma de pagamento: o PDF lê da cobrança na hora (como a situação).

### 4.4 PIX "copia e cola" (BR Code estático com valor)
Montado por `App\Support\PixCopiaECola` (testado), no padrão EMV do Banco Central:
`00` versão `01` · `26` conta (`00` = `br.gov.bcb.pix`, `01` = chave) · `52` `0000` · `53` `986` ·
`54` valor com ponto e 2 casas · `58` `BR` · `59` nome do usuário · `60` cidade do usuário ·
`62` (`05` = txid `REC` + ano + número com 6 dígitos, ex. `REC2026000016`) · `63` CRC16.
- Nome e cidade em maiúsculas, sem acento, cortados no limite do campo.
- **[A VERIFICAR no "Manual de Padrões para Iniciação do Pix" do Banco Central, na implementação]:**
  limites de 25 (nome) e 15 (cidade) caracteres; CRC16-CCITT (polinômio `0x1021`, início `0xFFFF`) em 4
  hexadecimais maiúsculos; uso ou não do campo `01` (método de iniciação); o exemplo oficial do manual
  vira caso de teste. Sem essa conferência, a tarefa não passa de `concluída`.
- QR gerado com `chillerlan/php-qrcode` em PNG (GD), embutido no PDF como data URI (como o logo).

## 5. Recibo em PDF

### 5.1 `webiss-v1`
- Tabelas TRIBUTOS FEDERAIS e VALORES passam a ocupar ~75% da largura; o QR (~70pt) fica **ao lado**
  delas, à direita — sem consumir altura (a página está no limite com 25 linhas de descrição).
- Em OUTRAS INFORMAÇÕES: "Pagamento por PIX — chave: … (tipo) · favorecido: …" e o "copia e cola" em
  fonte pequena (1–2 linhas). Boleto: "Pagamento por boleto bancário" + linha digitável formatada.
  `nenhuma`: nada.
- O teste "25 linhas cabem em 1 página" continua valendo com o bloco de pagamento (com PIX e com boleto).

### 5.2 `danfse-v1`
- Bloco **PAGAMENTO** antes de INFORMAÇÕES COMPLEMENTARES, na grade de 4 colunas: QR na 1ª coluna,
  chave/tipo/favorecido e "copia e cola" nas outras 3. Boleto: aviso + linha digitável. `nenhuma`: sem bloco.

### 5.3 Carimbo
Novo estado `vence_hoje` nos dois templates e no `Carimbo.vue`.

## 6. Telas

- **Meu perfil → Chaves PIX:** lista com apelido, tipo e chave; incluir/editar/excluir em linha;
  exclusão recusada com mensagem se a chave estiver em uso.
- **Contrato:** cliente por **autosugestão**; modalidade (pré-pago/pós-pago, com frase explicando o
  vencimento); forma de pagamento (PIX/Boleto/Não informar); seletor de chave só com PIX (as chaves são
  poucas, select serve). Sem chave cadastrada e PIX escolhido → link "Cadastrar chave PIX" no perfil.
- **Cobrança (avulsa/edição):** os mesmos campos de pagamento + linha digitável (com boleto).
- **Lista de cobranças / painel:** ação "Forma de pagamento" nas cobranças enviadas e pendentes, abrindo
  só esses campos; filtro de cliente da lista também por autosugestão.

### 6.1 Máscaras (componente do projeto, sem biblioteca)
Formatação enquanto digita; o servidor continua normalizando e validando (a máscara é conforto, não
segurança).

| Campo | Máscara |
|---|---|
| Valor | `1.234,56` (dígitos entram pelos centavos) |
| CPF/CNPJ | `000.000.000-00` ou `XX.XXX.XXX/XXXX-00` (CNPJ alfanumérico: letras maiúsculas nas 12 primeiras posições) |
| CEP | `00000-000` |
| Telefone | `(00) 0000-0000` / `(00) 00000-0000` |
| Chave PIX | conforme o tipo escolhido (CPF/CNPJ/telefone mascarados; e-mail e aleatória livres) |
| Linha digitável | grupos do boleto (47) ou da arrecadação (48) |
| Datas | continuam `type="date"` (calendário nativo) |

### 6.2 Autosugestão de cliente
- `GET /clientes/busca?q=` (JSON, autenticado, só clientes do usuário): até 10 por nome **ou** documento,
  sem diferença de maiúsculas (`lower(nome) like ?`, portátil SQLite/PostgreSQL); acentos contam
  (limitação aceita). `q` com menos de 2 caracteres → lista vazia.
- Componente `BuscaCliente.vue` com papel ARIA de combobox: setas, Enter, Esc; mostra nome + documento;
  guarda o `id`; ao editar contrato/cobrança abre com o cliente atual preenchido.

## 7. Testes e verificação
- PHPUnit: calculadora pós-pago (bordas da 4.1), situação `vence_hoje`, isolamento de `chave_pix_id` e da
  busca, exclusão de chave em uso, troca de pagamento em cobrança enviada/paga/cancelada,
  `PixCopiaECola` contra o exemplo oficial, PDF com PIX/boleto/nenhuma cabendo em 1 página.
- Máscaras e autosugestão: funções de formatação puras testadas via smoke test no navegador
  (browser-agent) — o projeto não tem runner de testes JS (não adicionar sem pedir).
- Migrações: PORTÃO-BANCO (autorização por comando) no PostgreSQL de desenvolvimento.
- Conferência visual (QR escaneável num celular, posição dos blocos) entra nas pendências do final.

## 8. Fora do escopo
Emissão de boleto, PIX dinâmico (cobrança via API do banco / webhook de baixa), validação de DV da linha
digitável, busca de cliente ignorando acentos.
