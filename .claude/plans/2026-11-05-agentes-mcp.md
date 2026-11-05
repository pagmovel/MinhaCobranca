# Fase 28 — agentes pessoais pelo MCP — plano de implementação

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: superpowers:subagent-driven-development (recomendado) ou
> superpowers:executing-plans, tarefa por tarefa. Passos com caixa (`- [ ]`) para acompanhar.
> **Não iniciar sem o comando do Marcos** (pedido dele em 2026-11-05: "seguir, mas não implementar agora").

**Objetivo:** cada usuário aprovado conecta o próprio agente (Hermes, Claude/Cowork, ChatGPT, scripts) à própria conta
por um servidor MCP em `/mcp`, com dois níveis de acesso, e o agente consulta e age com as mesmas regras da tela.

**Arquitetura:** `laravel/mcp` serve as ferramentas; `laravel/passport` autentica (OAuth 2.1 com PKCE para conectores
web, token pessoal para Hermes e afins) com os escopos `consultar` e `agir`. As ferramentas são finas: chamam os
serviços que a tela já usa; o que hoje mora dentro de controllers (baixa, painel, aviso, filtros da lista) é extraído
para serviços usados pelos dois lados, sem mudar o comportamento da tela.

**Stack:** Laravel 13 / PHP 8.4, `laravel/mcp`, `laravel/passport`, Inertia v3 + Vue 3, PHPUnit (SQLite em memória).

**Spec:** `docs/superpowers/specs/2026-11-05-agentes-mcp-design.md` (ler inteira antes de começar).

**Estados das tarefas:** pendente / concluída / testada / validada.

## Restrições globais

- Tudo em pt-BR: código novo, nomes que nós criamos, testes, mensagens das ferramentas, commits. Nomes impostos por
  framework ou pacote ficam como são (`handle`, `schema`, `tokensCan`...). Nomes das ferramentas MCP exatamente os da
  spec §5 (`resumo_do_painel`, `listar_cobrancas`, `ver_cobranca`, `buscar_clientes`, `listar_contratos`, `relatorio`,
  `recibo_pdf`, `mensagem_de_aviso`, `previa_da_baixa`, `dar_baixa`, `marcar_avisada`, `cadastrar_cliente`,
  `criar_cobranca_avulsa`).
- Ler a skill `.claude/skills/recibos/SKILL.md` inteira antes de mexer; telas novas seguem os "Padrões de usabilidade".
- Dependências novas: **só** `laravel/mcp` e `laravel/passport` (aprovadas pelo Marcos em 2026-11-05). Nada além.
- **Toda tabela nova com prefixo `tbl_`** (regra global): `tbl_oauth_clientes`, `tbl_oauth_codigos`,
  `tbl_oauth_tokens`, `tbl_oauth_tokens_de_atualizacao`, `tbl_oauth_codigos_de_dispositivo`. Nenhuma tabela `oauth_*`.
- Escopos: `consultar` e `agir`. "Só consultar" = `['consultar']`; "Consultar e agir" = `['consultar', 'agir']`.
  Nunca emitir `'*'`. Padrão do Passport (`defaultScopes`): `['consultar']`.
- Dinheiro nas respostas: sempre `{"centavos": int, "reais": "1.234,56"}` (via `App\Support\Dinheiro::formatar`);
  datas `dd/mm/aaaa`; listas com no máximo 50 itens por chamada e `{"pagina", "por_pagina", "total"}`.
- Mensagens fixas (texto exato):
  - escopo: `Este acesso é só de consulta; peça ao usuário para reconectar com 'Consultar e agir'.`
  - não encontrado: `Cobrança {id} não encontrada.` / `Cliente {id} não encontrado.` / `Recibo {id} não encontrado.`
    (a mesma para inexistente e de outra conta).
  - conta não aprovada (403): `Sua conta ainda não está liberada para uso.`; bloqueada: `Sua conta está bloqueada.`
- Limites: `/mcp` 60 chamadas por minuto por token; registro dinâmico de clientes 10 por minuto por IP. Token OAuth
  1 hora, atualização 30 dias; token pessoal 10 anos (na prática, até revogar). Link do PDF: 30 minutos.
- Depois de mexer em PHP: `vendor/bin/pint --dirty --format agent`. Depois de `.vue`/`.js`: `npm run build` (parar se
  falhar). Testes JS: `node --test 'resources/js/**/*.test.js'`.
- PORTÃO-BANCO: testes só em SQLite em memória. `php artisan migrate` no Postgres local tem autorização antecipada (memória
  `migrate-local-autorizado`, com as conferências dela); nada além disso. **VPS: nenhum acesso** (nem leitura); as chaves
  do Passport e o deploy são do Marcos (Tarefa 9).
- Branch `feat/agentes-mcp` saído do `main`; um commit por tarefa, com o estado da tarefa neste plano e a skill no mesmo
  commit. Fase concluída e testada: merge `--no-ff` no `main` + push, sem perguntar (memória
  `commit-merge-push-sem-perguntar`).
- A tela não muda de comportamento com as extrações: a suíte atual (889 PHP, 294 JS em 2026-11-05) fica verde **sem mudar
  asserção** de teste existente.

## Pontos de revisão (o que os testes de cada tarefa precisam pegar)

1. Agente manda em `dar_baixa` um id de cobrança de outra conta junto com ids próprios: nada é gravado, erro
   `Cobrança {id} não encontrada.` → Tarefa 5.
2. Agente repete a mesma chamada de `dar_baixa` (nova tentativa depois de timeout): a segunda é recusada com a mensagem de
   "só pendentes recebem baixa" e não duplica auditoria → Tarefa 5.
3. Token "Só consultar" chama cada ferramenta de agir: erro de escopo, nada gravado → Tarefas 5 e 6.
4. Usuário bloqueado depois de conectar o agente: a chamada seguinte recebe 403 → Tarefa 2.
5. Conector OAuth pede `consultar agir` e o usuário escolhe "Só consultar": o token emitido não tem `agir` → Tarefa 7.

---

### Tarefa 1 — Fundação: pacotes, Passport com `tbl_`, escopos e auditoria — **Estado: pendente**

**Arquivos:**
- `composer.json` / `composer.lock`: `composer require laravel/passport laravel/mcp`.
- Criar: `app/Models/Passport/{Cliente,Token,CodigoDeAutorizacao,TokenDeAtualizacao,CodigoDeDispositivo}.php`
  (estendem os modelos do Passport e só trocam `$table` para os nomes `tbl_` das Restrições).
- Criar: migrações publicadas do Passport, editadas para os nomes `tbl_` e para as chaves estrangeiras entre elas;
  `tbl_oauth_tokens` ganha `ultimo_uso_em` (timestamp nulo).
- Criar: migração `add_via_agente_to_auditorias_table` (`string('via_agente', 60)->nullable()`).
- Modificar: `app/Models/User.php` (`HasApiTokens`, `implements OAuthenticatable`), `app/Providers/AppServiceProvider.php`
  (`Passport::use*Model`, `tokensCan(['consultar' => 'Consultar os dados da conta', 'agir' => 'Dar baixa, cadastrar e
  registrar avisos'])`, `defaultScopes(['consultar'])`, validades das Restrições), `config/auth.php` (guard `api` com
  driver `passport`), `config/passport.php` (chaves de `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`).
- Modificar: `app/Models/Auditoria.php` (`via_agente` no `Fillable`; `registrar()` preenche sozinho).
- Criar: `app/Support/Agente.php`.
- Teste: `tests/Feature/Agentes/FundacaoTest.php`.

**Interfaces:**
- Produz: `App\Support\Agente::nomeDaConexao(?User $usuario): ?string` — nome do token pessoal, ou nome do cliente OAuth do
  token atual; `null` na sessão web. `App\Support\Agente::pode(?User $usuario, string $escopo): bool` (`tokenCan`).
- Produz: `Auditoria::registrar(...)` com a mesma assinatura de hoje, gravando `via_agente = Agente::nomeDaConexao($request->user())`.

- [ ] **Passo 1: testes que falham** em `FundacaoTest`:
  - `test_tabelas_do_passport_tem_prefixo_tbl`: `Schema::hasTable('tbl_oauth_tokens')` e as outras quatro são `true`;
    `Schema::hasTable('oauth_access_tokens')` e `Schema::hasTable('oauth_clients')` são `false`.
  - `test_token_pessoal_grava_e_le_nas_tabelas_tbl`: `$usuario->createToken('Hermes', ['consultar'])` cria linha em
    `tbl_oauth_tokens` com `name = 'Hermes'` e `scopes` contendo só `consultar`.
  - `test_escopos_definidos`: `Passport::hasScope('consultar')` e `Passport::hasScope('agir')` são `true`.
  - `test_auditoria_da_tela_fica_sem_via_agente`: uma baixa pela rota web (`POST /cobrancas/{id}/pagar`) grava
    auditoria com `via_agente` nulo.
  - `test_auditoria_pelo_agente_grava_o_nome_da_conexao`: com `Passport::actingAs($usuario, ['consultar','agir'])` e o
    token pessoal "Hermes do celular" como token atual, `Auditoria::registrar(...)` grava `via_agente = 'Hermes do celular'`.
- [ ] **Passo 2:** rodar `php artisan test --compact tests/Feature/Agentes/FundacaoTest.php` → falha.
- [ ] **Passo 3:** instalar os pacotes, criar modelos, migrações e configuração acima. `[A VERIFICAR]` antes de
  escrever: se a versão instalada do Passport aceita trocar o modelo de todas as cinco tabelas. Se alguma não aceitar,
  parar esta tarefa, registrar a exceção em "Para o Marcos decidir" e seguir só depois da decisão dele.
- [ ] **Passo 4:** teste verde; suíte inteira verde sem mudar asserção; pint.
- [ ] **Passo 5:** commit `feat: fundação dos agentes (Passport com tbl_, escopos, via_agente)`.

### Tarefa 2 — Servidor `/mcp`, acesso e primeira ferramenta (`resumo_do_painel`) — **Estado: pendente**

**Arquivos:**
- Criar: `routes/ai.php`, `app/Mcp/Servers/ServidorDoMinhaCobranca.php`, `app/Mcp/Tools/Ferramenta.php` (base),
  `app/Mcp/Tools/ResumoDoPainel.php`, `app/Http/Middleware/RegistrarUsoDoAgente.php`, `app/Services/ResumoDoPainel.php`.
- Modificar: `app/Http/Controllers/PainelController.php` (usa o serviço), `bootstrap/app.php` (rotas `ai.php`, alias do
  middleware, limitador `mcp`), `app/Http/Middleware/GarantirContaAprovada.php` (em `/mcp`, 403 com as mensagens das
  Restrições em vez de redirecionar).
- Teste: `tests/Feature/Agentes/AcessoAoMcpTest.php`, `tests/Feature/Agentes/ResumoDoPainelTest.php`.

**Interfaces:**
- Consome: `Agente::pode`, `Agente::nomeDaConexao` (Tarefa 1).
- Produz (base `App\Mcp\Tools\Ferramenta extends Laravel\Mcp\Server\Tool`), usada por todas as ferramentas:
  - `protected function usuario(Request $request): User`
  - `protected function exigirAgir(Request $request): ?Response` — `Response::error(<mensagem de escopo>)` sem `agir`; `null` com.
  - `protected function json(array $dados): Response` — JSON com `JSON_UNESCAPED_UNICODE` (usar o tipo de resposta
    estruturada do `laravel/mcp` instalado, se houver; senão `Response::text`).
  - `protected function dinheiro(int $centavos): array{centavos: int, reais: string}`
  - `protected function comValidacao(callable $acao): Response` — executa e converte `ValidationException` em
    `Response::error` com as mensagens campo a campo, e `App\Exceptions\AcaoRecusada` em `Response::error($e->getMessage())`.
- Produz: `App\Exceptions\AcaoRecusada extends RuntimeException` (recusa de regra de negócio com mensagem pronta).
- Produz: `App\Services\ResumoDoPainel::numeros(User $usuario, CarbonImmutable $hoje): array` — os números dos cartões
  do painel (vencidas, vencendo hoje, recebido no mês, com quantidade e soma), extraídos do `PainelController` sem mudar o
  que a tela mostra.
- Rota: `Mcp::web('/mcp', ServidorDoMinhaCobranca::class)->middleware(['auth:api', 'conta.aprovada', 'throttle:mcp',
  RegistrarUsoDoAgente::class])`. `RegistrarUsoDoAgente` grava `ultimo_uso_em` no token atual se nulo ou com mais de
  1 minuto.

- [ ] **Passo 1: testes que falham:**
  - `AcessoAoMcpTest::test_sem_token_recebe_401`; `test_token_revogado_recebe_401`;
    `test_conta_pendente_recebe_403_com_mensagem`; `test_conta_bloqueada_depois_de_conectar_recebe_403` (token criado
    com a conta aprovada, usuário bloqueado, chamada seguinte 403 com `Sua conta está bloqueada.`);
    `test_61a_chamada_no_minuto_recebe_429`; `test_ultimo_uso_gravado_no_maximo_uma_vez_por_minuto` (duas chamadas no
    mesmo minuto, uma escrita; `travel(61)->seconds()`, nova escrita).
  - `ResumoDoPainelTest::test_mostra_so_os_numeros_da_conta` (cobranças de outro usuário não entram);
    `test_bate_com_o_painel_da_tela` (mesmos números que `GET /painel/dados` para o mesmo cenário);
    `test_marcada_como_somente_leitura` (anotação `IsReadOnly`).
- [ ] **Passo 2:** rodar → falha.
- [ ] **Passo 3:** implementar o acima.
- [ ] **Passo 4:** verde; suíte inteira verde sem mudar asserção (inclui os testes do painel); pint.
- [ ] **Passo 5:** commit `feat: servidor MCP com acesso por token e resumo do painel`.

### Tarefa 3 — Ferramentas de consulta — **Estado: pendente**

**Arquivos:**
- Criar: `app/Mcp/Tools/{ListarCobrancas,VerCobranca,BuscarClientes,ListarContratos,Relatorio}.php`.
- Criar: `app/Support/FiltrosDaLista.php`; modificar `CobrancaController` (usa-o no lugar de `filtros()`).
- Modificar: `ClienteController::busca` e `app/Models/Cliente.php` (consulta de busca extraída para um escopo do modelo).
- Modificar: `RelatorioController` só se a validação do período precisar ser compartilhada.
- Teste: `tests/Feature/Agentes/FerramentasDeConsultaTest.php`.

**Interfaces:**
- Consome: base `Ferramenta` (Tarefa 2), `ListaDeCobrancas::montar`, `Relatorios::{faturamento,porCliente,porProduto,aReceber}`.
- Produz: `App\Support\FiltrosDaLista::de(array $entrada, CarbonImmutable $hoje): array` — valida (mesmas regras e
  mensagens de hoje, inclusive "até 36 meses" e "fim antes do início") e devolve o array que `ListaDeCobrancas::montar`
  espera; lança `ValidationException`.
- Produz: escopo `Cliente::scopeBuscar(Builder $consulta, string $termo)` — o mesmo critério de `ClienteController::busca`.
- Argumentos: `listar_cobrancas` {situacao?, mes?, ate?, cliente_id?, contrato_id?, pagina?} (situação com os mesmos
  valores da tela, inclusive `proximos_7`/`proximos_15`; `por_pagina` fixo em 50); `ver_cobranca` {id};
  `buscar_clientes` {termo}; `listar_contratos` {cliente_id?, so_ativos? (padrão true)}; `relatorio` {tipo:
  `faturamento|por_cliente|por_produto|a_receber`, de?, ate?} (`Y-m`).

- [ ] **Passo 1: testes que falham** (um cenário com duas contas, A e B):
  - `test_listar_cobrancas_com_filtros_da_tela`: `situacao=vencida` traz as vencidas de A de qualquer mês;
    `situacao=paga&mes=2026-10` traz as pagas em outubro; resposta tem `pagina.total` e no máximo 50 itens.
  - `test_listar_cobrancas_periodo_invalido_volta_erro`: `mes=2026-10&ate=2026-08` → erro com
    `O fim do período deve ser igual ou depois do início.`
  - `test_ver_cobranca_de_outra_conta_nao_encontrada`: id de B → `Cobrança {id} não encontrada.`
  - `test_ver_cobranca_traz_valor_do_dia`: vencida com contrato que tem multa → resposta com multa e juros do dia.
  - `test_buscar_clientes_por_nome_apelido_e_documento` e sem resultados de B.
  - `test_listar_contratos_so_ativos_por_padrao_e_so_da_conta` (`so_ativos=false` traz os encerrados; nenhum de B).
  - `test_relatorio_bate_com_a_tela`: mesmo resultado de `GET /relatorios/faturamento/dados` para o período.
  - `test_ferramentas_de_consulta_funcionam_so_com_consultar`: `Passport::actingAs($a, ['consultar'])` → todas ok.
- [ ] **Passo 2:** rodar → falha. **Passo 3:** implementar. **Passo 4:** verde; suíte inteira verde sem mudar
  asserção; pint.
- [ ] **Passo 5:** commit `feat: ferramentas de consulta do MCP`.

### Tarefa 4 — `recibo_pdf` e `mensagem_de_aviso` — **Estado: pendente**

**Arquivos:**
- Criar: `app/Mcp/Tools/{ReciboPdf,MensagemDeAviso}.php`, `app/Services/AvisosDeWhatsapp.php`.
- Modificar: `routes/web.php` (rota `GET /recibos/{recibo}/pdf-assinado`, nome `recibos.pdf-assinado`, middleware
  `signed`, fora do grupo `auth`), `ReciboPdfController` (atende as duas rotas; a assinada acha o recibo sem usuário da
  sessão), `AvisoWhatsappController` (usa o serviço).
- Teste: `tests/Feature/Agentes/ReciboEAvisoTest.php`.

**Interfaces:**
- Produz: `App\Services\AvisosDeWhatsapp::montar(User $usuario, array $entrada): array` — o corpo de
  `AvisoWhatsappController::dados` (entrada = o que o controller valida hoje); `::marcarAvisadas(User $usuario, array
  $ids, Request $request): int` — o corpo de `avisadas`, com a mesma auditoria.
- `recibo_pdf` {cobranca_id} → `{"url": URL::temporarySignedRoute('recibos.pdf-assinado', 30 minutos, ...),
  "valido_ate": "dd/mm/aaaa hh:mm"}`; cobrança sem recibo → erro `A cobrança {id} ainda não tem recibo.`

- [ ] **Passo 1: testes que falham:** `test_link_do_pdf_abre_sem_sessao_e_expira_em_30_minutos` (abre 200 com
  `application/pdf`; `travel(31)->minutes()` → 403); `test_link_adulterado_recebe_403`; `test_recibo_de_outra_conta_nao_encontrado`;
  `test_mensagem_de_aviso_igual_a_da_tela` (mesmo texto de `GET /cobrancas/aviso` para os mesmos ids);
  `test_tela_de_aviso_continua_igual` (testes atuais do aviso verdes).
- [ ] **Passo 2–4:** falha, implementar, verde + suíte + pint.
- [ ] **Passo 5:** commit `feat: recibo em PDF por link assinado e mensagem de aviso no MCP`.

### Tarefa 5 — Baixa: extração para serviço, `previa_da_baixa` e `dar_baixa` — **Estado: pendente**

**Arquivos:**
- Criar: `app/Services/BaixaDeCobrancas.php`, `app/Mcp/Tools/{PreviaDaBaixa,DarBaixa}.php`.
- Modificar: `app/Http/Controllers/CobrancaController.php` (`pagar` e `pagarEmLote` delegam; `registrarBaixa`,
  `resumoDaBaixa`, `conferirDescontoDoLote` vão para o serviço; `valoresGravados` fica onde a correção e o desfazer
  precisarem).
- Teste: `tests/Feature/Agentes/BaixaPeloAgenteTest.php`.

**Interfaces:**
- Consome: `ValoresDaBaixa::{resolver,regras,MENSAGENS,INDIVIDUAL,LOTE}`, `Auditoria::registrar` (Tarefa 1), base `Ferramenta`.
- Produz:
  - `BaixaDeCobrancas::individual(User $usuario, int $cobrancaId, CarbonImmutable $pagoEm, array $dados, Request $request): Cobranca`
    — lança `AcaoRecusada('Só cobranças pendentes recebem baixa.')` (o controller converte em `back()->with('erro', ...)`,
    como hoje) e `ModelNotFoundException` para id de outra conta.
  - `BaixaDeCobrancas::emLote(User $usuario, array $ids, string $modo, array $dados, Request $request): array{baixadas: int, pedidas: int}`
    — mesma regra de hoje (desconto conferido antes de gravar, transação única).
- `previa_da_baixa` {cobranca_id, pago_em?, desconto?, desconto_tipo?, multa?, multa_tipo?, juros?, juros_tipo?, outros?}
  → valor, desconto, multa, juros, outros e valor pago (formato dinheiro); não grava; `IsReadOnly`.
- `dar_baixa` {ids: int[] (1 a 200), pago_em? (padrão hoje) | modo? (`vencimento|hoje`, só com mais de um id), forma de
  desconto e acréscimos como na tela}. Um id → `individual`; vários → `emLote`, que exige o mesmo cliente (mensagem:
  `Na baixa em lote, todas as cobranças precisam ser do mesmo cliente.`). Antes de gravar qualquer uma, confere que todos
  os ids são da conta; senão erro `Cobrança {id} não encontrada.` e nada gravado. Anotações: não somente leitura, não
  idempotente.

- [ ] **Passo 1: testes que falham:**
  - `test_previa_nao_grava_nada` (nenhuma auditoria, status continua pendente) e bate com o que `dar_baixa` grava.
  - `test_dar_baixa_individual_grava_igual_a_tela` (mesmos valores e mesmo texto de auditoria de `POST /cobrancas/{id}/pagar`,
    com `via_agente` preenchido).
  - `test_dar_baixa_repetida_e_recusada_sem_duplicar_auditoria` (Ponto de revisão 2).
  - `test_lote_com_id_de_outra_conta_nao_grava_nada` (Ponto de revisão 1).
  - `test_lote_de_clientes_diferentes_e_recusado`.
  - `test_so_consultar_nao_da_baixa` (Ponto de revisão 3: erro de escopo, nada gravado).
  - `test_rotas_da_tela_continuam_iguais` (suíte atual de baixa, lote, correção e desfazer verde sem mudar asserção).
- [ ] **Passo 2–4:** falha, implementar, verde + suíte + pint.
- [ ] **Passo 5:** commit `feat: baixa pelo agente com prévia, reaproveitando as regras da tela`.

### Tarefa 6 — `marcar_avisada`, `cadastrar_cliente`, `criar_cobranca_avulsa` — **Estado: pendente**

**Arquivos:**
- Criar: `app/Mcp/Tools/{MarcarAvisada,CadastrarCliente,CriarCobrancaAvulsa}.php`, `app/Mcp/ValidacaoPorFormRequest.php`.
- Teste: `tests/Feature/Agentes/AcoesDeCadastroTest.php`.

**Interfaces:**
- Consome: `AvisosDeWhatsapp::marcarAvisadas` (Tarefa 4), `EmissorDeRecibos::emitir`, `ProgressoDeConfiguracao::de`.
- Produz: `App\Mcp\ValidacaoPorFormRequest::validar(string $classe, array $argumentos, User $usuario): FormRequest` —
  cria a FormRequest (`ClienteRequest`, `CobrancaRequest`) a partir dos argumentos, com o usuário resolvido, roda a
  validação dela e devolve a instância validada (lança `ValidationException`). Assim valem as mesmas regras,
  normalizações (`dadosCobranca()`, `itensDaCobranca()`) e mensagens da tela, sem copiá-las.
- Progresso: `cadastrar_cliente` exige o bloco `perfil` e `criar_cobranca_avulsa` o bloco `liberado`, com as mesmas
  mensagens do `GarantirProgressoDeConfiguracao` (como `AcaoRecusada`).

- [ ] **Passo 1: testes que falham:** `test_cadastrar_cliente_valida_como_a_tela` (documento inválido → mesma mensagem do
  `ClienteRequest`); `test_cadastrar_cliente_devolve_o_cliente_criado`; `test_cobranca_avulsa_cria_com_recibo` (igual a
  `POST /cobrancas`); `test_cobranca_avulsa_com_cliente_de_outra_conta_recusada`; `test_perfil_incompleto_recusa_com_a_mensagem_do_wizard`;
  `test_marcar_avisada_grava_igual_a_tela`; `test_so_consultar_nao_cadastra_nem_marca` (Ponto de revisão 3).
- [ ] **Passo 2–4:** falha, implementar, verde + suíte + pint.
- [ ] **Passo 5:** commit `feat: cadastro de cliente, cobrança avulsa e aviso marcado pelo agente`.

### Tarefa 7 — OAuth: descoberta, registro e tela de aprovação com nível — **Estado: pendente**

**Arquivos:**
- Modificar: `routes/ai.php` (`Route::middleware('throttle:10,1')->group(fn () => Mcp::oauthRoutes())`),
  `AppServiceProvider` (`Passport::authorizationView(...)` renderizando a página Inertia).
- Criar: `resources/js/Pages/Agentes/Autorizar.vue`, `app/Http/Middleware/AplicarNivelEscolhido.php` (na rota de
  aprovação do Passport).
- Teste: `tests/Feature/Agentes/OAuthTest.php`.

**Interfaces:**
- Tela: título "**{nome do cliente}** quer acessar sua conta", rádio `nivel` com `consultar` ("Só consultar", marcado por
  padrão) e `agir` ("Consultar e agir"), botões **Permitir** e **Negar**; sem sessão, passa pelo login de sempre e volta.
- `AplicarNivelEscolhido`: antes da aprovação do Passport, troca os escopos do pedido de autorização guardado na sessão pelo
  nível escolhido (`consultar` → `['consultar']`; `agir` → `['consultar','agir']`), mesmo que o cliente tenha pedido
  outros. `[A VERIFICAR]` no Passport instalado: onde o pedido fica na sessão e se aceita troca de escopos. Se não
  aceitar, Ruling permitido: gravar o nível numa coluna `nivel` de `tbl_oauth_tokens` preenchida no evento de emissão
  (`AccessTokenCreated`) e fazer `Agente::pode` ler essa coluna; registrar a decisão no plano.

- [ ] **Passo 1: testes que falham:** `test_descoberta_publica_os_enderecos` (`/.well-known/oauth-protected-resource` e
  `/.well-known/oauth-authorization-server` respondem com o emissor do `APP_URL`);
  `test_registro_dinamico_limitado_a_10_por_minuto`; `test_fluxo_completo_com_pkce_chama_o_mcp` (registro → autorização
  com login → Permitir com `agir` → troca do código → chamada a `resumo_do_painel` ok e a `dar_baixa` permitida);
  `test_so_consultar_escolhido_emite_token_sem_agir` (Ponto de revisão 5); `test_negar_nao_emite_token`;
  `test_conta_pendente_nao_aprova`. As chaves do Passport do teste são geradas no `setUp` em diretório temporário.
- [ ] **Passo 2–4:** falha, implementar, verde + suíte + `npm run build` + pint.
- [ ] **Passo 5:** commit `feat: conexão por OAuth com escolha do nível de acesso`.

### Tarefa 8 — Tela "Agentes conectados" e auditoria "via agente" — **Estado: pendente**

**Arquivos:**
- Criar: `app/Http/Controllers/AgenteController.php`, `resources/js/Components/AgentesConectados.vue` (seção do
  `Perfil/Editar.vue`, no padrão das seções existentes), `resources/js/Components/GerarTokenPessoal.vue` (modal).
- Modificar: `routes/web.php` (`GET /perfil/agentes/dados`, `POST /perfil/agentes/tokens`, `DELETE /perfil/agentes/{id}`
  no grupo `auth`+`conta.aprovada`), `resources/js/Pages/Perfil/Editar.vue`, tela de Auditoria (mostra "via {nome}").
- Teste: `tests/Feature/Agentes/AgentesConectadosTest.php`.

**Interfaces:**
- `GET /perfil/agentes/dados` → lista de conexões ativas: `{id, nome, tipo: 'conector'|'token', nivel:
  'consultar'|'agir', criada_em, ultimo_uso_em}`; conector = uma linha por cliente OAuth (tokens não revogados e não
  expirados do usuário); token pessoal = uma linha por token.
- `POST /perfil/agentes/tokens` {nome (obrigatório, até 60), nivel} → `{token, endereco_mcp}` uma única vez; auditoria
  `agente.token_criado`.
- `DELETE /perfil/agentes/{id}` → revoga o token pessoal, ou todos os tokens e de atualização daquele cliente para o
  usuário; auditoria `agente.revogado`; id de outro usuário → 404.
- Tela: lista com nome, tipo, nível, criada em, último uso ("nunca" se nulo); Revogar com confirmação inline; "Gerar
  token pessoal" em modal; token mostrado com botão copiar e aviso "Copie agora: ele não aparece de novo."; estado vazio
  explica como conectar (endereço do `/mcp`; Hermes: cabeçalho `Authorization: Bearer`).

- [ ] **Passo 1: testes que falham:** `test_gera_token_com_o_nivel_escolhido`; `test_lista_so_as_conexoes_do_usuario`;
  `test_revogar_corta_o_acesso_na_hora` (chamada seguinte ao `/mcp` → 401);
  `test_revogar_conexao_de_outro_usuario_404`; `test_auditoria_mostra_via_agente` (prop da tela de Auditoria).
- [ ] **Passo 2–4:** falha, implementar, verde + suíte + `npm run build` + pint.
- [ ] **Passo 5:** commit `feat: tela de agentes conectados com token pessoal e revogação`.

### Tarefa 9 — Conferência, documentação e roteiro de produção — **Estado: pendente**

**Arquivos:**
- Modificar: `.claude/skills/recibos/SKILL.md` (seção "Fase 28: agentes pelo MCP": decisões, pegadinhas, como testar),
  `CLAUDE.md` e `README.md` (inventário: `laravel/mcp`, `laravel/passport`), `.claude/plans/2026-10-10-roteiro-modulos.md`
  (seção 0 com o estado), `.env.example` (`PASSPORT_PRIVATE_KEY=`, `PASSPORT_PUBLIC_KEY=`).
- Criar: `docs/agentes-mcp.md` — como conectar (Cowork/Claude: endereço; Hermes: token e exemplo de configuração) e o
  passo a passo de produção para o Marcos.

- [ ] **Passo 1:** conferência local: `php artisan migrate` no Postgres local (memória `migrate-local-autorizado`),
  `php artisan mcp:inspector mcp` com um token pessoal de usuário de teste, chamar cada ferramenta e registrar o resultado
  neste plano. Nenhum acesso à VPS.
- [ ] **Passo 2:** gerar um par de chaves de produção **fora do repositório** (no scratchpad), entregar ao Marcos com o
  passo a passo: cadastrar `PASSPORT_PRIVATE_KEY` e `PASSPORT_PUBLIC_KEY` no Easypanel, conferir `APP_URL` com https,
  deploy (as migrações rodam no CMD do Dockerfile).
- [ ] **Passo 3:** suíte inteira, testes JS e build verdes; commit `docs: Fase 28 — agentes pelo MCP`; merge `--no-ff` e push.

## Registro de execução

(preenchido durante a execução: decisões do controlador, "Para o Marcos decidir", menores adiados.)
