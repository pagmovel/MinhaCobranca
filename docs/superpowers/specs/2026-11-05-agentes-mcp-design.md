# Agentes pessoais pelo MCP — desenho

Data: 2026-11-05 · Pedido do Marcos: "criar APIs para que agentes pessoais autorizados consigam usar este sistema,
por exemplo Hermes, coworkers diversos etc." · Decisões tomadas no brainstorming desta data.

## 1. Objetivo

Cada usuário aprovado do sistema conecta o **próprio** agente pessoal (Hermes, Claude/Cowork, ChatGPT, Cursor, scripts)
à **própria** conta. O agente consulta os dados e executa as ações do dia a dia, com as mesmas regras e validações da
tela, e nunca enxerga dados de outra conta.

**Sucesso:** um usuário conecta o Cowork (login + aprovação) ou o Hermes (token colado), pergunta "quem está vencido?",
pede "dá baixa na cobrança do João com 10% de desconto" e o resultado é o mesmo que teria pela tela, registrado na
Auditoria como feito pelo agente.

## 2. Decisões

| # | Decisão | Motivo |
|---|---|---|
| D1 | Servidor **MCP** (`laravel/mcp`), sem API REST na v1 | Os agentes falam MCP e descobrem as ferramentas sozinhos; REST pode vir depois sobre os mesmos serviços. |
| D2 | Autenticação com **Laravel Passport**: OAuth 2.1 (login + aprovação) e **token pessoal** | Conectores web exigem OAuth; Hermes e afins usam token no cabeçalho. Passport é gratuito e roda no próprio sistema (nenhum terceiro). |
| D3 | Dois níveis por conexão: **Só consultar** (escopo `consultar`) e **Consultar e agir** (escopos `consultar` + `agir`) | Controle simples, escolhido na aprovação ou ao gerar o token. |
| D4 | Ferramentas chamam os serviços existentes | Nenhuma regra de negócio duplicada. |
| D5 | Toda tabela nova com prefixo `tbl_` (regra global) | As do Passport são renomeadas por modelos próprios. |
| D6 | O sistema não envia mensagens: devolve texto e PDF; quem entrega é o agente | Igual à tela hoje (o aviso de WhatsApp é texto/link que a pessoa envia). |

**Novas dependências (aprovadas pelo Marcos ao escolher D1 e D2):** `laravel/mcp`, `laravel/passport`.

## 3. Conexão

- Endpoint único: `Mcp::web('/mcp', ServidorDoMinhaCobranca::class)` em `routes/ai.php`, com `auth:api` (Passport),
  limite de chamadas por conexão (`throttle`, 60 por minuto) e a mesma regra de conta da tela: só usuário
  `aprovado`; `pendente` ou `bloqueado` recebe 403 com mensagem em pt-BR. Bloquear o usuário corta os agentes na hora.
- `Mcp::oauthRoutes()` publica a descoberta e o registro dinâmico de clientes (OAuth 2.1 com PKCE S256).
- **Tela de aprovação** (Inertia, autenticada pela sessão normal): "**{nome do cliente OAuth}** quer acessar sua
  conta", escolha ○ Só consultar ○ Consultar e agir (padrão: Só consultar), botões **Permitir** e **Negar**.
  Login antes, se não houver sessão; conta não aprovada vê a mensagem da tela de conta pendente e não aprova nada.
- **Token pessoal:** gerado na tela "Agentes conectados" com nome (obrigatório, até 60 caracteres) e nível; a chave
  aparece **uma única vez**, com botão copiar e o endereço do `/mcp` ao lado.

## 4. Tela "Agentes conectados" (no Perfil)

- Lista as conexões ativas da conta: nome, tipo (Conector / Token pessoal), nível, criada em, **último uso**.
- **Revogar** com confirmação no próprio lugar (padrão das faixas inline); revogar invalida a chave e os tokens de
  atualização na hora.
- "Gerar token pessoal" em modal, sem sair da tela; vazio com explicação curta de como conectar (Cowork/Claude:
  endereço do `/mcp`; Hermes: token no cabeçalho `Authorization: Bearer`).
- Segue a seção "Padrões de usabilidade (obrigatórios)" da skill `recibos`.

## 5. Ferramentas da v1

Todas recebem e devolvem dinheiro em reais formatados **e** em centavos, datas em `dd/mm/aaaa` e ids. Consultas
paginadas devolvem no máximo 50 itens por chamada, com indicação de que há mais.

**Consultar** (escopo `consultar`; marcadas como somente leitura no MCP):

| Ferramenta | Faz | Usa |
|---|---|---|
| `resumo_do_painel` | Vencidas, vencendo hoje, recebido no mês | o mesmo cálculo do `PainelController` |
| `listar_cobrancas` | Filtros da tela: situação (inclui `proximos_7`/`proximos_15`), período, cliente, contrato | `ListaDeCobrancas` |
| `ver_cobranca` | Detalhe, com valor do dia (multa, juros, outros, desconto por pontualidade) | `Cobranca`, `ValoresDaBaixa` |
| `buscar_clientes` | Nome, apelido ou documento | a busca de `ClienteController::busca` |
| `listar_contratos` | Da conta ou de um cliente | consulta de contratos da tela |
| `relatorio` | Os relatórios da tela, por período | `Relatorios` |
| `recibo_pdf` | Link temporário assinado (30 min) para o PDF | `ReciboPdfController` / `RenderizadorDeRecibo` |
| `mensagem_de_aviso` | Texto de aviso de WhatsApp pronto, para uma ou mais cobranças | o mesmo de `AvisoWhatsappController::dados` |

**Agir** (escopo `agir`):

| Ferramenta | Faz | Usa |
|---|---|---|
| `previa_da_baixa` | Valor, desconto e acréscimos que a baixa gravaria; **não grava** (somente leitura) | `ValoresDaBaixa` |
| `dar_baixa` | Uma cobrança ou várias **do mesmo cliente** (regra do lote), com data, forma, desconto/acréscimos | mesmas regras de `pagar` / `pagarEmLote` |
| `marcar_avisada` | Registra o aviso enviado | `AvisoWhatsappController::avisadas` |
| `cadastrar_cliente` | Cadastro com as validações de `ClienteRequest` | `ClienteController::store` |
| `criar_cobranca_avulsa` | Cobrança avulsa com as validações de `CobrancaRequest` | `CobrancaController::store` |

**Fora da v1:** cancelar, desfazer ou corrigir baixa, prorrogar, contratos (criar, renovar, encerrar), configurações.

Para as ferramentas reaproveitarem as regras, a lógica que hoje mora dentro dos controllers (`pagar`, `pagarEmLote`,
`store` de cliente e cobrança, dados do painel e do aviso) é movida para serviços ou ações chamados pelos dois lados.
A tela não muda de comportamento.

## 6. Erros

- Validação: devolve como erro da ferramenta, em pt-BR, campo a campo, com as mesmas mensagens da tela.
- Regra de negócio: mensagem com a saída ("A cobrança 123 já está paga; para mudar o valor, corrija o pagamento
  pela tela").
- Escopo insuficiente: "Este acesso é só de consulta; peça ao usuário para reconectar com 'Consultar e agir'".
- Registro inexistente ou de outra conta: a mesma mensagem de "não encontrado" (não revela que existe).

## 7. Dados

- **Tabelas do Passport** com prefixo `tbl_` (`tbl_oauth_clientes`, `tbl_oauth_codigos`, `tbl_oauth_tokens`,
  `tbl_oauth_tokens_de_atualizacao`, e a de códigos de dispositivo se o Passport exigir), via modelos próprios
  registrados em `AppServiceProvider`. `[A VERIFICAR]` no plano: o Passport desta versão aceita trocar o modelo de
  todas; se alguma não aceitar, o plano registra a exceção e o Marcos decide.
- Nome e tipo da conexão: do cliente OAuth (conector) ou do token pessoal; o nível vem dos escopos.
- **Último uso:** gravado no token a cada chamada, no máximo uma escrita por minuto por conexão.
- `auditorias` ganha `via_agente` (string 60, nula). Toda gravação feita pelo MCP preenche com o nome da conexão; a
  tela de Auditoria mostra "via {nome}".

## 8. Segurança

- Escopo da conta: toda consulta parte de `$usuario->cobrancas()`, `clientes()` etc., como na tela.
- Chaves do Passport fora do repositório: `PASSPORT_PRIVATE_KEY` e `PASSPORT_PUBLIC_KEY` no ambiente.
- Validade: token de acesso OAuth 1 hora com atualização; token pessoal sem validade até ser revogado.
- `/mcp` só em https em produção (`APP_URL` com https).

## 9. Testes

- Cada ferramenta: caminho feliz e isolamento entre contas (usuário B não vê nem altera nada de A).
- Níveis: token só de consulta recebe o erro de escopo em todas as ferramentas de agir.
- Conta pendente/bloqueada: 403. Token revogado: 401.
- OAuth de ponta a ponta: descoberta, registro, autorização com escolha de nível, troca do código, chamada.
- Auditoria com `via_agente` preenchido nas gravações pelo MCP e nulo nas da tela.
- A tela continua igual depois da extração das regras dos controllers (testes atuais verdes).

## 10. Produção (depende do Marcos)

Gerar o par de chaves (o Claude gera e explica), cadastrar `PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY` e conferir
`APP_URL` com https no Easypanel, e fazer o deploy. As migrações rodam sozinhas no deploy (CMD do Dockerfile). O Claude
não acessa a VPS.
