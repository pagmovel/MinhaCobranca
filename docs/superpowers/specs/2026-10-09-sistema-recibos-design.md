# Sistema de cobranças e recibos de serviços prestados — Design

- **Data:** 2026-10-09
- **Estado da spec:** aguardando revisão do usuário
- **Origem:** sessão de brainstorming (superpowers:brainstorming), decisões aprovadas seção a seção

## 1. Objetivo

Prestadores de serviço com clientes recorrentes (ex.: hospedagem mensal) precisam gerar a cobrança
de cada período **com o recibo já pronto**, enviar o PDF ao cliente **antecipadamente** pelo
WhatsApp (envio manual) e **dar baixa** quando o pagamento for confirmado.

O recibo deve ser **muito parecido com as NFS-e** de referência em `MATERIAIS/`, inclusive na
disposição dos dados, sem se apresentar como documento fiscal.

### Critérios de sucesso

1. Contrato cadastrado gera sozinho a cobrança e o recibo de cada período, com a antecedência
   configurada, sem duplicar.
2. Recibo em PDF A4 visualmente fiel ao modelo escolhido para o cliente (WebISS ou DANFSe),
   validado pelo usuário em comparação lado a lado com as notas originais.
3. Reabrir um recibo antigo produz o mesmo documento que foi enviado (dados e layout congelados).
4. No celular, "Enviar" abre a folha de compartilhamento nativa com o PDF anexado.
5. Dados de cada usuário isolados; acesso só depois de liberação pelo admin.

## 2. Referências analisadas

| Arquivo | Conteúdo |
|---|---|
| `MATERIAIS/NFS-e em Edição _ WebISS®.pdf` | NFS-e Avulsa da Prefeitura de Nossa Senhora do Socorro/SE (WebISS), A4 (594,96 × 841,92 pt), marca d'água "RASCUNHO" |
| `MATERIAIS/NFS-e-28048051100059762713591202600000001626090039848880.pdf` | DANFSe v2.0 (padrão nacional), A4 (595 × 842 pt), grade de 4 colunas em x = 12 / 156 / 301 / 446 pt |

A disposição de cada campo foi extraída por coordenadas (pypdf) e conferida com prints enviados
pelo usuário.

## 3. Decisões aprovadas

| Tema | Decisão |
|---|---|
| Papel do recibo | Cobrança recorrente + recibo em PDF; envio manual por WhatsApp |
| Usuários | Multiusuário; **cadastro aberto, acesso só após liberação do admin**; cada usuário é um prestador |
| Cobranças | Geradas a partir de **contratos recorrentes**; cobrança avulsa (sem contrato) também permitida |
| Momento do recibo | Recibo nasce **junto com a cobrança**, antes do pagamento; baixa só quando o pagamento é confirmado |
| Modelos | Dois: **WebISS** e **DANFSe**, ambos A4, fiéis à disposição original |
| Modelo por cliente | `clientes.modelo`; cada recibo grava o modelo **com versão** (`webiss-v1`, `danfse-v1`) |
| Tributos | Quadros mantidos **só no visual** (`0,00` ou `-`); recibo mostra apenas o valor do serviço |
| Vencimento | **Pré-pago**: `dia_vencimento` no mês de início do período |
| Numeração | Sequencial **por usuário**, reinicia a cada ano: `2026/000016` |
| Stack | Laravel 13 + **Inertia.js + Vue 3** + Vite 8 + Tailwind 4 + **dompdf** |
| Banco | SQLite agora; PostgreSQL depois — só construções portáveis |
| Banco de testes | SQLite em memória (`phpunit.xml` linhas 26–27: `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) — autorizado pelo usuário |
| NFS-e XML | Item **opcional**: importar XML, conferir com a cobrança, citar no recibo, oferecer criar cliente |
| `APP_NAME` | Corrigir para "Gerenciador de Cobranças" |

## 4. Arquitetura

- **Inertia.js + Vue 3**: rotas, controllers, validação (`FormRequest`) e autorização (policies) no
  Laravel; páginas em Vue. Sem API separada; autenticação por sessão.
- **PDF**: templates Blade dedicados, um por modelo e versão, renderizados por
  `barryvdh/laravel-dompdf`. Layout feito com `<table>` (`table-layout: fixed`), que é onde o
  dompdf é confiável (sem flex/grid).
- **Dinheiro em centavos** (`integer`) em todas as tabelas — comportamento idêntico em SQLite e
  PostgreSQL.
- **Geração preguiçosa**: cobranças devidas são geradas quando o usuário abre o painel ou a lista
  de cobranças. Sem cron.

### Unidades principais

| Unidade | Responsabilidade |
|---|---|
| `GeradorDeCobrancas` | Calcula períodos devidos de cada contrato ativo e cria cobrança + recibo numa transação |
| `CalculadoraDePeriodos` | Dado contrato e data de referência, devolve períodos e vencimentos |
| `NumeradorDeRecibos` | Próximo número `(user_id, ano)` dentro da transação |
| `ValorPorExtenso` | Converte centavos para extenso em pt-BR |
| `DocumentoFiscal` (regra de validação) | Valida CPF/CNPJ pelos dígitos verificadores |
| `RenderizadorDeRecibo` | Escolhe o template pela versão gravada, junta snapshot + dados vivos, devolve o PDF |
| `ImportadorNfse` (fase opcional) | Lê XML (nacional / municipal), extrai campos, sugere vínculo |

## 5. Modelo de dados

Todas as tabelas de negócio têm `user_id`, que isola os dados de cada usuário.

### `users` (estende a tabela padrão)
- `is_admin` (bool), `status` (`pendente` | `aprovado` | `bloqueado`)
- Prestador: `documento` (CPF/CNPJ), `nome_fantasia`, `inscricao_municipal`, `inscricao_estadual`,
  `telefone`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `cep`, `logo` (caminho)
- `modelo_padrao` (sugestão para novos clientes), `dias_antecedencia` (padrão 10)

### `clientes`
- Nome/razão social, `documento`, `inscricao_municipal`, `inscricao_estadual`, `telefone`, `email`,
  endereço (mesmos campos do prestador)
- `modelo` (`webiss` | `danfse`), obrigatório, pré-preenchido com `users.modelo_padrao`

### `contratos`
- `cliente_id`, `descricao` (multilinha), `codigo_servico` (ex.: "0102 - Programação"),
  `codigo_nbs`, `codigo_tributacao_nacional` (ex.: "01.02.01")
- `valor_centavos`, `periodicidade` (`mensal` | `trimestral` | `semestral` | `anual`),
  `dia_vencimento`, `data_inicio`, `data_fim` (opcional), `ativo`

### `cobrancas`
- `contrato_id` (opcional), `cliente_id`, `periodo_inicio`, `periodo_fim`, `vencimento`,
  `valor_centavos`, `descricao`
- `status` (`pendente` | `paga` | `cancelada`), `pago_em`, `enviado_em`
- Índice único `(contrato_id, periodo_inicio)` — garante idempotência da geração

### `recibos`
- `cobranca_id` (único), `ano`, `numero`, `modelo` (com versão, ex.: `webiss-v1`), `emitido_em`
- `snapshot` (JSON): prestador, tomador e serviço congelados na emissão
- Índice único `(user_id, ano, numero)`

### `notas_fiscais` (fase opcional)
- `cobranca_id` (opcional), `padrao` (`nacional` | `municipal`), `numero`, `chave_ou_codigo`,
  `emitida_em`, `competencia`, `tomador_documento`, `valor_servicos_centavos`,
  `valor_liquido_centavos`, `xml` (conteúdo original), `situacao_conciliacao`
  (`conciliada` | `divergente` | `sem_cobranca`)

### Congelado × vivo no PDF

| Congelado (snapshot + versão do modelo) | Vivo (lido na hora de gerar o PDF) |
|---|---|
| Dados do prestador, do tomador e do serviço; valor; período; número; layout | Situação/carimbo (A VENCER, VENCIDO, PAGO em dd/mm/aaaa, CANCELADO); data do pagamento; referência à NFS-e vinculada |

## 6. Contas e acesso

- **Cadastro aberto** (nome, e-mail, senha): conta nasce `pendente`.
- **Login**: `pendente` → "Seu cadastro aguarda liberação do administrador."; `bloqueado` → "Seu
  acesso foi bloqueado. Fale com o administrador." Checado no login **e** num middleware das rotas
  autenticadas (bloquear derruba quem já está logado no próximo clique).
- **Admin**: tela de usuários com pendentes no topo e contador; ações aprovar, bloquear, reativar.
  O admin **não** vê clientes, cobranças nem recibos de outros usuários.
- **Primeiro admin**: `php artisan app:criar-admin`. Grava no banco — só roda com autorização
  explícita do usuário para aquela execução.
- **Fora do escopo**: e-mail ao admin quando chega cadastro novo.

## 7. Fluxos

### 7.1 Geração recorrente
1. Usuário abre painel ou cobranças → `GeradorDeCobrancas` roda para ele.
2. Para cada contrato ativo (e dentro de `data_fim`), calcula os períodos a partir de `data_inicio`
   (mensal 21/09–20/10, 21/10–20/11, …).
3. Vencimento = `dia_vencimento` no mês de `periodo_inicio`; dia inexistente vira último dia do mês.
4. Gera todo período com `hoje >= vencimento - dias_antecedencia` que ainda não existe —
   inclusive atrasados, em ordem de data.
5. Na mesma transação: cria a cobrança, numera e cria o recibo com snapshot e
   `modelo = cliente.modelo + versão atual`.
6. Perfil do prestador incompleto → geração suspensa; painel mostra "Complete seu perfil para emitir
   recibos" com link.

### 7.2 Envio
- "Enviar": busca o PDF no clique; com `navigator.canShare({ files })` abre a folha nativa; sem
  suporte (desktop, HTTP sem TLS), faz download. Registra `enviado_em`. O sistema não envia nada
  sozinho e não confirma entrega.
- Envio é um por vez (cada cliente é um contato diferente); sem envio em lote.

### 7.3 Baixa
- "Dar baixa": data do pagamento (padrão hoje). Cobrança vira `paga`; recibo baixado depois sai com
  carimbo "PAGO em dd/mm/aaaa". Ação "Enviar recibo pago" disponível.

### 7.4 Edição e cancelamento
- Contrato editado: vale só para as próximas cobranças.
- Cobrança **não enviada**: editável; recibo refeito com o **mesmo número**.
- Cobrança **enviada**: só cancelar; recibo fica CANCELADO; nova cobrança avulsa substitui.
- Número cancelado não é reaproveitado.

### 7.5 Troca de modelo do cliente
- Vale só para recibos futuros. Ao salvar, a tela avisa: "Recibos já emitidos mantêm o modelo
  anterior."

## 8. Telas e direção visual

Direção aprovada **provisoriamente**; validação final com as telas prontas. Celular é alvo
principal.

### Telas
1. Entrar / Criar conta / "Seu cadastro aguarda liberação"
2. **Painel** (inicial): resumo do mês em frase ("R$ 1.040,00 recebidos de R$ 3.120,00 previstos")
   e três blocos na ordem do trabalho:
   - **Para enviar** — geradas, `enviado_em` nulo → [Enviar]
   - **Aguardando pagamento** — enviadas e pendentes, vencidas primeiro → [Dar baixa] [Reenviar]
   - **Recebidas** — pagas no mês → [Enviar recibo pago]
3. Cobranças: filtros mês / status / cliente; ações marcar paga, enviar, editar, cancelar, nova avulsa
4. Contratos: CRUD
5. Clientes: CRUD (com modelo do recibo)
6. Meu perfil: dados do prestador, logo, modelo padrão, dias de antecedência
7. Usuários (admin)
8. Notas fiscais (fase opcional): importar XML, lista com situação de conciliação

### Tokens
| Nome | Hex | Uso |
|---|---|---|
| Papel | `#F5F7F9` | fundo |
| Grafite | `#1F2933` | texto |
| Azul-carbono | `#2F3C9E` | ação primária, carimbo A VENCER |
| Linha de formulário | `#C9D1DA` | bordas e grade |
| Tinta verde | `#2E7D4F` | carimbo PAGO |
| Tinta vermelha | `#B3261E` | carimbo VENCIDO, erros |

- **Tipografia**: família **Archivo** no texto e **Archivo Narrow** nos rótulos de grade, valores
  com `tabular-nums`. Substitui a Instrument Sans do `vite.config.js`. Verificado em 2026-10-09: o
  Bunny Fonts serve as duas, estáticas (sem eixo de largura) — por isso a Narrow em vez de
  "Archivo semicondensada".
- **Elemento marcante**: carimbo de status em tinta, levemente girado, igual na tela e no PDF.
  Resto da interface quieto, em grade de formulário com rótulo pequeno acima do valor.
- Alinhamento à esquerda; sem cards de KPI; sem rótulos em caixa-alta acima de seções.
- Piso de qualidade: responsivo, foco de teclado visível, `prefers-reduced-motion` respeitado.

## 9. Recibo em PDF

Comum aos dois modelos: A4 retrato; rota `GET /recibos/{recibo}/pdf` com policy de dono; PDF
gerado na hora (não salvo em disco); nome `recibo-2026-000016-<cliente>.pdf`; template escolhido
por `recibos.modelo` (ex.: `resources/views/recibos/pdf/webiss-v1.blade.php`).

**Retirados de propósito dos originais** (fariam o recibo se apresentar como documento fiscal
emitido pelo município): brasão e nome da prefeitura, marca "NFS-e", chave de acesso, QR code,
número/série de DPS, link de validação, "Totais aproximados dos tributos (Lei 12.741)".
Todo recibo traz: **"Este recibo não substitui a Nota Fiscal de Serviços Eletrônica."**

### 9.1 `webiss-v1` — fiel à NFS-e Avulsa WebISS

| Bloco original (posição) | No recibo |
|---|---|
| Brasão + "NOSSA SENHORA DO SOCORRO" / "Secretaria Municipal da Fazenda" / endereço (topo, x≈85) | Logo do prestador (ou monograma) + nome / nome fantasia / endereço, telefone, e-mail |
| Caixa direita: "Nota: 0" + QR + "Código Verificação" | "Recibo nº 2026/000016" + data de emissão, sem QR |
| "NOTA FISCAL DE SERVIÇOS ELETRÔNICA - NFS-e AVULSA" (y≈110) | "RECIBO DE PRESTAÇÃO DE SERVIÇOS" |
| Emissão / Período de Competência / Município de Prestação (y≈132) | Emissão / `mm/aaaa` / cidade do prestador |
| Reg. Especial Tributação / Exigibilidade do ISS (y≈154) | **Vencimento / Situação** |
| PRESTADOR: Razão Social; Nome Fantasia + Email; CPF/CNPJ, Insc. Mun., Insc. Est., Simples Nacional, Incentivador Cultural, Fone; Endereço | Campo a campo; Simples e Incentivador com "-" |
| TOMADOR: Nome; CPF/CNPJ, Insc. Mun., Insc. Est., Fone, E-mail; Endereço | Campo a campo |
| SERVIÇO PRESTADO: "0102 - Programação. NBS: 115061000." | `codigo_servico` + NBS do contrato |
| Marca d'água diagonal "RASCUNHO" (centro, y≈432) | **Carimbo diagonal** de situação, mesma posição e ângulo |
| DESCRIÇÃO DOS SERVIÇOS (caixa grande) + "Obs.: Período de …" | Descrição + período |
| TRIBUTOS FEDERAIS: INSS, IR, PIS, COFINS, CSLL, Outras Retenções (y≈640) | Todos `0,00` |
| VALORES, 2 linhas (Valor dos Serviços … ISS Retido / B.C. IBS/CBS … **Valor Total**) | Valor dos Serviços, Valor Líquido e Valor Total preenchidos; demais `0,00` |
| OUTRAS INFORMAÇÕES: "CST…" | Valor por extenso + observações + "Referente à NFS-e nº …" (se vinculada) |
| Rodapé: "Visualizado em… validação…" / "Esta NFS-e é autodeclaratória…" | "Gerado em dd/mm/aaaa hh:mm" / aviso de que não substitui a NFS-e |

Fonte do PDF: **DejaVu Sans** — escolhida por parecer próxima da sans larga do original;
semelhança **[A VERIFICAR]** na comparação visual da fase 4.

### 9.2 `danfse-v1` — fiel ao DANFSe v2.0 (grade de 4 colunas: 25% cada)

| Linha original | No recibo |
|---|---|
| Logo NFS-e / "DANFSe v2.0 · Documento Auxiliar da NFS-e" / Município + Ambiente / QR | Logo do prestador / "RECIBO · Recibo de Prestação de Serviços" / Município do prestador / **carimbo** no lugar do QR |
| CHAVE DE ACESSO DA NFS-e (linha inteira) | NÚMERO DO RECIBO `2026/000016` |
| Número / Competência / Data e hora da emissão | Ano / sequência (`2026 / 000016`) / Competência / Data e hora de emissão |
| Número DPS / Série DPS / Data DPS / texto do QR | Vencimento / Período / Data do pagamento |
| Emitente / Situação / Finalidade | Prestador / situação / "-" |
| PRESTADOR / FORNECEDOR: CPF/CNPJ, Indicador Municipal, Telefone / Nome, Município-UF, IBGE-CEP / Endereço, E-mail / Simples, Regime SN | Igual; "Código IBGE / CEP" vira "CEP"; Simples e Regime com "-" |
| TOMADOR / ADQUIRENTE (mesma grade) | Igual |
| "Destinatário / Intermediário não identificado" | Removidas |
| SERVIÇO PRESTADO: Cód. Tributação Nacional / NBS / Local da Prestação; nome; Descrição | Igual, vindo do contrato |
| Tributação Municipal / Federal / IBS-CBS | Igual, tudo "-" |
| VALOR TOTAL / Descontos / Retenções / Valor Líquido / IBS-CBS | Valor e líquido preenchidos; resto "-" |
| Informações complementares: "Totais aproximados…" | Valor por extenso + período + observações + "Referente à NFS-e nº …" (se vinculada) |
| Data cientificação / Identificação e assinatura / Nº NFS-e-chave | Data do recebimento / Assinatura do prestador / Nº do recibo |

Fonte do PDF: **Helvetica** nativa do dompdf — escolhida por parecer próxima da sans estilo Arial
do original. Semelhança e suporte a acentos **[A VERIFICAR]** no primeiro PDF; fallback DejaVu Sans.

### 9.3 Versionamento de modelos
- Ajuste de layout depois de recibos emitidos cria **nova versão** (`webiss-v2`); a anterior
  continua no repositório e segue renderizando os recibos antigos.
- Modelos oferecidos para novos recibos ficam numa constante; versões antigas só renderizam.

## 10. Importação de XML de NFS-e (opcional)

**Estado: bloqueada até chegarem XMLs de exemplo.** O usuário vai gerar os XMLs e colocá-los em
`MATERIAIS/` (um do portal nacional e um do WebISS, de preferência das mesmas notas dos PDFs). A estrutura das tags de cada padrão é **[A VERIFICAR]**;
nenhum parser será escrito por suposição.

- Upload de um ou mais XMLs; leitura com `simplexml` + XPath com namespace registrado;
  `LIBXML_NONET` e sem entidades externas (proteção contra XXE); limite de tamanho no upload.
- Extrai número, chave/código, emissão, competência, tomador, valores.
- **Sugere vínculo** por documento do tomador + competência/período + valor; usuário confirma.
  Mais de um candidato → usuário escolhe; nunca vincula sozinho.
- Resultado: conciliada / divergente (mostra o campo que difere) / sem cobrança.
- **Citar no recibo**: dado vivo (não entra no snapshot) — PDF baixado após o vínculo traz
  "Referente à NFS-e nº …".
- **Criar cliente**: tomador desconhecido abre o formulário de cliente pré-preenchido; nada é
  criado sem confirmação.
- **Não** dá baixa automática.

## 11. Erros e proteções

- CPF/CNPJ validados por dígito verificador (prestador e cliente).
- Toda consulta a dado de negócio passa pela relação do usuário logado
  (`$request->user()->clientes()->findOrFail($id)`); recurso de outro usuário → 404. Escolhido no
  plano em vez de policies: mesmo efeito, menos código, impossível esquecer o filtro.
- Numeração calculada na transação + índice único; colisão no PostgreSQL → nova tentativa.
- Falha ao gerar PDF: registro em log + mensagem "Não foi possível gerar o recibo. Tente de novo."
- Upload de logo: só imagem (PNG/JPG), tamanho limitado.
- Mensagens de erro dizem o que aconteceu e como resolver, sem pedir desculpas.

## 12. Testes

Rodam no SQLite em memória (`phpunit.xml`), nunca no `database/database.sqlite`.

- **Unitários**: valor por extenso (cem/cento, mil, "um real", centavos, zero); CPF/CNPJ;
  períodos e vencimentos com `Carbon::setTestNow()` (dia 31, fevereiro, trimestral, anual).
- **Feature**:
  - cadastro → pendente → login recusado → admin aprova → login aceito; bloquear derruba sessão
  - isolamento entre usuários (404)
  - geração idempotente; recuperação de períodos atrasados; perfil incompleto suspende geração
  - numeração sequencial e reinício no ano novo; número cancelado não reaproveitado
  - recibo copia o modelo do cliente; trocar modelo do cliente não altera recibos antigos
  - edição de cobrança não enviada mantém número; cobrança enviada não é editável
  - PDF `200 application/pdf` para `webiss-v1` e `danfse-v1`; carimbo reflete status atual
  - (opcional) importação XML: conciliada, divergente, sem cobrança, XML malicioso rejeitado
- **Visual**: PDF gerado lado a lado com as notas originais — validação do usuário.
- **Smoke test**: browser-agent (regra do usuário), não Playwright.

## 13. Fora do escopo

- Envio automático (WhatsApp, e-mail); envio em lote.
- E-mail ao admin sobre cadastros novos.
- Cálculo real de tributos; emissão de NFS-e.
- Baixa automática por importação de NFS-e ou por extrato bancário.
- Comando artisan agendado para geração (entra se a geração precisar ocorrer sem acesso ao sistema).
- Pagamento parcial.

## 14. Pendências e riscos

| Item | Estado |
|---|---|
| XMLs de exemplo dos dois portais | Usuário vai gerar e colocar em `MATERIAIS/` — bloqueia fase 6 |
| Archivo com eixo de largura no Bunny Fonts | Verificado: só estáticas; usar Archivo + Archivo Narrow |
| CNPJ alfanumérico (Receita Federal, em vigor desde jul/2026) | Verificado: DV módulo 11 com ASCII − 48; validador do plano aceita |
| Acentos na Helvetica nativa do dompdf | [A VERIFICAR] na fase 4 |
| `transform: rotate` do carimbo no dompdf | [A VERIFICAR] na fase 4 |
| Versões compatíveis de Inertia/dompdf com Laravel 13.35 | [A VERIFICAR] na fase 0 |
| Web Share API só funciona em HTTPS ou `localhost` | Conhecido; no Laragon (`http://*.test`) cai para download |
| Projeto ainda sem repositório git | `git init` na fase 0; esta spec é commitada junto |
| Escrita no banco real (migrações, `app:criar-admin`, qualquer comando que grave) | Regra global: antes de cada execução, imprimir a conexão em uso (driver e caminho/host, porta, banco, usuário) e dizer se é produção, fazer `SELECT count(*)` no alvo, mostrar o comando literal e esperar o "sim" para **aquele** comando. Autorização não é transitiva. A conexão do Laravel vem do `.env` (não há `models/config.json` neste projeto) |
| Testes | Gravam só no SQLite em memória (autorizado); qualquer outra base de teste precisa ser declarada pelo usuário |

## 15. Fases e acompanhamento

Estados: `pendente` → `concluída` (código pronto) → `testada` (verificação rodada) →
`validada` (usuário confirmou). O detalhamento passo a passo fica no plano de implementação
(próxima etapa, skill writing-plans).

### Fase 0 — Concluir instalação
- [ ] `git init`; `git check-ignore -v .claude .claude/memory .claude/plans .claude/skills .claude/MEMORY.md` (saída vazia); commit inicial com esta spec, quando o usuário pedir — `pendente`
- [ ] `npm install`; adicionar Vue 3, `@vitejs/plugin-vue`, Inertia (servidor e cliente) — `pendente`
- [ ] Adicionar `barryvdh/laravel-dompdf` — `pendente`
- [ ] `.env`: `APP_NAME="Gerenciador de Cobranças"`, locale `pt_BR`, fuso `America/Sao_Paulo` — `pendente`
- [ ] Fonte Archivo no Vite; tokens de cor no Tailwind 4 — `pendente`
- [ ] Estrutura `.claude/` (skills, memória, planos); inventário de plugins no `CLAUDE.md` e `README.md` — `pendente`
- [ ] Rodar migrações no `database/database.sqlite` — só após o procedimento da seção 14 e o "sim" do usuário — `pendente`
- [ ] Skill local `.claude/skills/recibos/` com decisões, pegadinhas e acertos, atualizada a cada solução aprovada — `pendente`

### Fase 1 — Contas e acesso
- [ ] Migração de `users` (status, admin, campos do prestador) — `pendente`
- [ ] Cadastro, login com checagem de status, middleware de bloqueio — `pendente`
- [ ] Tela de usuários (admin): aprovar, bloquear, reativar — `pendente`
- [ ] Comando `app:criar-admin` — `pendente`

### Fase 2 — Perfil e clientes
- [ ] Regra de validação CPF/CNPJ + testes — `pendente`
- [ ] Meu perfil (dados, logo, modelo padrão, antecedência) — `pendente`
- [ ] CRUD de clientes com modelo do recibo — `pendente`

### Fase 3 — Contratos, cobranças e recibos
- [ ] CRUD de contratos — `pendente`
- [ ] `CalculadoraDePeriodos` + testes — `pendente`
- [ ] `NumeradorDeRecibos` + `GeradorDeCobrancas` (snapshot, modelo versionado) + testes — `pendente`
- [ ] Cobrança avulsa; edição (não enviada) e cancelamento — `pendente`

### Fase 4 — PDF
- [ ] `ValorPorExtenso` + testes — `pendente`
- [ ] Template `webiss-v1` — `pendente`
- [ ] Template `danfse-v1` — `pendente`
- [ ] Carimbo de situação e dados vivos — `pendente`
- [ ] Comparação visual com as notas originais — `pendente`

### Fase 5 — Painel, envio e baixa
- [ ] Painel em três blocos com geração preguiçosa — `pendente`
- [ ] Lista de cobranças com filtros — `pendente`
- [ ] Enviar (Web Share API + fallback download, `enviado_em`) — `pendente`
- [ ] Dar baixa; enviar recibo pago — `pendente`
- [ ] Smoke test com browser-agent — `pendente`

### Fase 6 — Importação de XML de NFS-e (opcional, bloqueada)
- [ ] Receber XMLs de exemplo dos dois portais — `pendente`
- [ ] Leitores nacional e municipal + testes com os arquivos reais — `pendente`
- [ ] Sugestão de vínculo e conciliação — `pendente`
- [ ] Citação no recibo; criar cliente a partir do tomador — `pendente`

### Fase 7 — Portabilidade para PostgreSQL (futura)
- [ ] Rodar a suíte contra um PostgreSQL de teste declarado pelo usuário — `pendente`
