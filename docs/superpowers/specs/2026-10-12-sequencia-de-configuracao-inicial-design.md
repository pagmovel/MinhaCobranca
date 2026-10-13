# Fase 25 — sequência obrigatória de configuração inicial, com guia interativo — desenho

**Pedido do usuário (2026-10-12):** "Existe uma sequência em que um novo usuário deve lançar dados. Primeiro ele
precisa complementar o perfil dele, parametrizar o recibo, cadastrar as chaves PIX, e mensagens para o WhatsApp.
Depois ele precisa cadastrar os produtos/serviços e clientes, só depois liberar o acesso para criar os contratos
(...) vamos precisar que este passo a passo seja cumprido pelos novos usuários. E se possível com instruções
interativas na tela, tipo apresentação do sistema."

**Decisões do usuário (brainstorming em chat, 2026-10-12):**
- Vale para **todo mundo, inclusive quem já usa o sistema hoje** — não só cadastro novo. Risco aceito
  explicitamente: uma conta real ativa pode perder acesso a **criar** contrato/cobrança (e, por extensão, a criar
  produto/serviço/cliente novo) até completar o que falta; o que já existe continua acessível.
- O que trava é a **navegação pelo menu** até cada bloco ficar completo, com os itens de menu ficando desabilitados
  progressivamente. A trava real (servidor) é só sobre **criar** registro novo em cada área — nunca sobre ver,
  editar, pagar, cancelar o que já existe (ajuste feito em chat depois de uma pergunta de confirmação: o cadastro
  rápido de cliente/produto embutido no formulário de contrato continua existindo no código, só fica inalcançável
  enquanto o catálogo não está pronto, porque o próprio formulário de contrato não abre).
- "Cobranças" (avulsa) entra no mesmo nível de trava que "Contratos" — os dois só liberam depois do bloco de
  catálogo (produtos/serviços e clientes).
- O guia na tela precisa ser **didático e interativo**, não só um aviso estático — decidido com o companheiro
  visual: combinação do holofote (A, aponta e explica campo a campo) com o coach flutuante (B, lista sempre
  visível de progresso), mais um modal de boas-vindas (complemento escolhido depois, também com visual).
- O modal de boas-vindas aparece para **todo mundo** (não só cadastro novo) no próximo login, mas só enquanto
  sobrar pendência — quem já está liberado não vê nada.

## 1. Os dois blocos e o cálculo de progresso

`App\Support\ProgressoDeConfiguracao::de(User $usuario): array` — classe nova, só leitura, sem efeito colateral
(mesmo padrão de `Dinheiro`, `BlocoDePaginas`, `DiasUteis`). Nenhuma coluna nova para isso: calculado a partir do
que já existe, a cada requisição.

```php
[
    'perfil' => bool,    // perfilCompleto() && chavesPix()->count() >= 1 && modelosMensagem()->count() >= 1
    'catalogo' => bool,  // produtosServicos()->count() >= 1 && clientes()->count() >= 1
    'liberado' => bool,  // perfil && catalogo
    'pendencias' => [
        [
            'bloco' => 'perfil', 'item' => 'dados_prestador', 'rotulo' => 'Completar os dados do prestador',
            'feito' => bool, 'link' => '/perfil',
            // só quando não está feito: os campos de CAMPOS_DO_PRESTADOR que ainda estão em branco, na ordem do formulário
            'campos_faltando' => [
                ['campo' => 'name', 'rotulo' => 'Nome ou razão social', 'seletor' => '#name'],
                ['campo' => 'documento', 'rotulo' => 'CPF ou CNPJ', 'seletor' => '#documento'],
                ['campo' => 'telefone', 'rotulo' => 'Telefone', 'seletor' => '#telefone'],
                ['campo' => 'logradouro', 'rotulo' => 'Logradouro', 'seletor' => '#logradouro'],
                ['campo' => 'numero', 'rotulo' => 'Número', 'seletor' => '#numero'],
                ['campo' => 'bairro', 'rotulo' => 'Bairro', 'seletor' => '#bairro'],
                ['campo' => 'cidade', 'rotulo' => 'Cidade', 'seletor' => '#cidade'],
                ['campo' => 'uf', 'rotulo' => 'UF', 'seletor' => '#uf'],
                ['campo' => 'cep', 'rotulo' => 'CEP', 'seletor' => '#cep'],
            ],
        ],
        ['bloco' => 'perfil', 'item' => 'chave_pix', 'rotulo' => 'Cadastrar uma chave PIX', 'feito' => bool, 'link' => '/perfil', 'seletor' => '#nova-chave-pix'],
        ['bloco' => 'perfil', 'item' => 'modelo_mensagem', 'rotulo' => 'Cadastrar um modelo de mensagem', 'feito' => bool, 'link' => '/perfil', 'seletor' => '#novo-modelo-mensagem'],
        ['bloco' => 'catalogo', 'item' => 'produto_servico', 'rotulo' => 'Cadastrar um produto ou serviço', 'feito' => bool, 'link' => '/produtos-servicos', 'seletor' => '#novo-produto-servico'],
        ['bloco' => 'catalogo', 'item' => 'cliente', 'rotulo' => 'Cadastrar um cliente', 'feito' => bool, 'link' => '/clientes', 'seletor' => '#novo-cliente'],
    ],
]
```

`pendencias` sempre traz as 5 linhas (mesmo as já `feito`), pra o coach (seção 4) desenhar a lista completa com ✓
nas feitas. Os `seletor` são `id`s que **precisam ser adicionados** (hoje não existem) em:
- `resources/js/Components/ChavesPix.vue:98` (botão "Nova chave PIX") → `id="nova-chave-pix"`.
- `resources/js/Components/MensagensWhatsapp.vue:177` (botão "Novo modelo") → `id="novo-modelo-mensagem"`.
- `resources/js/Pages/ProdutosServicos/Index.vue:35` (`<Link>` "Novo produto ou serviço") → `id="novo-produto-servico"`.
- `resources/js/Pages/Clientes/Index.vue:39` (`<Link>` "Novo cliente") → `id="novo-cliente"`.

Os `id`s dos campos do prestador (`name`, `documento`, `telefone`, `logradouro`, `numero`, `bairro`, `cidade`,
`uf`, `cep`) **já existem** em `resources/js/Pages/Perfil/Editar.vue` e `resources/js/Components/CamposEndereco.vue`
— nada a adicionar ali.

## 2. Trava no servidor — só para criar, nunca para ver o que já existe

**Middleware novo:** `App\Http\Middleware\GarantirProgressoDeConfiguracao`, alias `progresso` em
`bootstrap/app.php` (mesmo padrão do `conta.aprovada` já existente), parametrizado: `progresso:perfil` ou
`progresso:liberado`. Lê `ProgressoDeConfiguracao::de($request->user())[$parametro]`; se `false`, redireciona para
`/painel` com `with('erro', ...)` — mesmo padrão do `GarantirContaAprovada`.

**Mensagens:**
- Bloco `perfil` incompleto: "Complete seu perfil (dados do prestador, uma chave PIX e um modelo de mensagem)
  antes de cadastrar produtos, serviços ou clientes."
- Bloco `liberado` incompleto: "Cadastre pelo menos um produto ou serviço e um cliente antes de criar contratos
  ou cobranças."

**Rotas que recebem o middleware** (só as que criam registro novo — `routes/web.php`):

| Middleware | Rotas |
|---|---|
| `progresso:perfil` | `GET /produtos-servicos/create`, `POST /produtos-servicos`, `GET /clientes/create`, `POST /clientes` |
| `progresso:liberado` | `GET /contratos/create`, `POST /contratos`, `POST /contratos/{contrato}/recomecar`, `POST /contratos/{contrato}/renovar`, `POST /contratos/{contrato}/renovar-vencido`, `GET /cobrancas/create`, `POST /cobrancas` |

**Nunca travado, em nenhum dos quatro cadastros:** `index`, `edit`, `update`, `destroy` (produtos/clientes), e em
contrato/cobrança qualquer rota que não crie registro novo (`encerramento`, `encerrar`, `reativacao`, `renovacao`,
`edit`, `update`, `cancelar`, `pagar`, `prorrogar`, `marcarEnviada`, lotes, etc.). Isso é o que protege quem já usa
o sistema hoje: dados antigos continuam 100% acessíveis mesmo que o bloco correspondente esteja "incompleto" pela
nova régua.

**Cadastro rápido em modal** (`CadastroRapidoCliente.vue`, `CadastroRapidoProduto` equivalente) continua existindo
sem mudança — só fica inalcançável enquanto quem ainda está na sequência não chega ao formulário de contrato/
cobrança (que está travado antes dele).

## 3. Prop compartilhada e menu

`HandleInertiaRequests::share()` ganha:
```php
'progresso' => fn () => $request->user() ? ProgressoDeConfiguracao::de($request->user()) : null,
```

`CamadaApp.vue`: cada item de `itens` ganha `bloqueado`:
- Painel, Meu perfil, Relatórios, Auditoria, Usuários (admin) → nunca.
- Clientes, Produtos e serviços → `!progresso.perfil`.
- Contratos, Cobranças → `!progresso.liberado`.

Item bloqueado renderiza como `<span>` (não `<Link>`, não navega por ali), com ícone de cadeado, texto em
`text-grafite/50`, e `title` com a mesma mensagem da seção 2. Não some do menu — só fica inerte, pra a pessoa ver
que existe e **por que** ainda não abre.

## 4. Guia interativo: coach flutuante + holofote

Dois componentes novos, só CSS/Vue (sem lib nova), montados a partir de `progresso.pendencias`:

**`CoachDeConfiguracao.vue`** — cartão fixo (`position: fixed`, canto inferior direito em telas largas; em 390 px
vira uma faixa recolhível no topo do conteúdo, abaixo do cabeçalho, pra não cobrir a tela) mostrado em
`/perfil`, `/produtos-servicos`, `/clientes` e no Painel, sempre que `!progresso.liberado`. Mostra as 5 linhas de
`pendencias` na ordem, com ✓ verde nas `feito`, e a próxima pendência em destaque (negrito + cor `carbono`). Clicar
num item ainda não feito navega pro `link` dele. Nunca se fecha de vez (não precisa — só aparece quando falta algo).

**`HoloteDePendencia.vue`** — só na tela onde a pendência atual tem algo pra apontar nela mesma:
- Em `/perfil`, enquanto `dados_prestador` não está `feito`: anda pelos `campos_faltando` um a um — recorte
  iluminado sobre o campo (`seletor`), balão "Passo X de N — `rotulo`", botão "Próximo campo" (vai pro próximo
  `campo_faltando` que ainda está vazio) e "Pular". **Precisa checar o campo vazio no `form` reativo da tela, não
  só no valor que veio do servidor** — senão não acompanha o que a pessoa já digitou antes de salvar. Depois dos
  campos do prestador, se `chave_pix`/`modelo_mensagem` ainda não estão feitos, aponta pro botão
  (`#nova-chave-pix` / `#novo-modelo-mensagem`) com o mesmo balão, sem a numeração de passos (é uma ação só).
- Em `/produtos-servicos` e `/clientes`: aponta pro botão "+ Novo..." (`#novo-produto-servico` / `#novo-cliente`)
  com um balão simples, sem numeração.
- "Pular" esconde o holofote só pelo resto da visita à página (nenhum estado novo gravado) — o coach (acima)
  continua visível, então a pessoa não fica sem norte.
- Em telas ≤ 640 px: nunca o recorte escurecido (risco de tampar o campo numa tela pequena) — só o balão de texto
  acima do campo/botão, sem o efeito de spotlight.

## 5. Modal de boas-vindas

Migração: `users.apresentacao_vista` (`boolean`, padrão `false`). Rota nova: `POST /perfil/apresentacao` →
marca `apresentacao_vista = true` e retorna `back()` (sem flash — é só um dismiss).

Prop compartilhada: `'mostrarApresentacao' => fn () => $request->user() && ! $request->user()->apresentacao_vista
&& ! ProgressoDeConfiguracao::de($request->user())['liberado']`.

`ModalBoasVindas.vue`, montado em `CamadaApp.vue` (não só no Painel — garante que apareça seja qual for a
primeira tela depois do login), `v-if="page.props.mostrarApresentacao"`, 3 telas com "Avançar"/"Pular":
1. "Bem-vindo! Pra organizar seus recibos, hoje falta completar alguns cadastros."
2. "Primeiro: seu perfil, uma chave PIX e um modelo de mensagem." (lista os itens do bloco `perfil` ainda
   pendentes, lidos de `progresso.pendencias`)
3. "Depois: produtos/serviços e clientes. Só então Contratos e Cobranças ficam liberados." (idem, bloco `catalogo`)

Botão final "Entendi, vamos lá" (e "Pular" em qualquer tela) chamam `POST /perfil/apresentacao`; o modal não volta
a aparecer depois disso, pra ninguém.

## 6. Banco (PORTÃO-BANCO — migração só com o "sim" do usuário, com conexão, contagem e comando literal antes)

- `users.apresentacao_vista`: `boolean`, padrão `false`, `after('dias_aviso_contrato')`.
- Nenhuma outra coluna nova — `perfil`/`catalogo`/`liberado` são sempre calculados, nunca gravados.

## 7. Fora do escopo

- Qualquer trava sobre **ver/editar/gerenciar** o que já existe (produto, cliente, contrato, cobrança) — só
  criação nova é afetada, em qualquer um dos quatro cadastros.
- Backfill de `apresentacao_vista = true` para contas antigas — decisão do usuário: todo mundo vê o modal uma vez
  no próximo login, sem exceção por data de cadastro.
- Holofote genérico/reaproveitável para qualquer tela futura — só as 5 pendências listadas na seção 1 (seletores
  fixos, não um motor de tour configurável).
- Re-mostrar o modal de boas-vindas ao avançar de um bloco pro outro — é um modal só, uma vez na vida da conta.
- Gate em `/relatorios`, `/auditoria`, `/admin/usuarios` — continuam sempre abertos (nada ali exige dado prévio
  pra fazer sentido).

## 8. Testes (resumo)

- `ProgressoDeConfiguracao::de()`: casos nada feito / só perfil completo / perfil + catálogo completos; cada
  pendência marca `feito` certo; `campos_faltando` lista exatamente os campos em branco, na ordem certa.
- Middleware `progresso:perfil` e `progresso:liberado`: bloqueia `create`/`store` dos quatro cadastros quando
  incompleto (redirect com a mensagem certa); libera quando completo; **nunca** bloqueia `index`/`edit`/`update`/
  ações sobre registro existente, mesmo com o bloco incompleto (teste que protege o usuário retroativo).
- `renovar`/`recomecar`/`renovar-vencido` de contrato também exigem `liberado` (criam contrato novo por baixo).
- Menu: item bloqueado não é `<Link>` (não navega); item liberado continua `<Link>` normal.
- `POST /perfil/apresentacao`: marca `apresentacao_vista`; `mostrarApresentacao` fica `false` depois; nunca
  aparece pra quem já está `liberado` mesmo sem nunca ter visto o modal.
- Smoke no navegador (usuário novo, zerado): sequência completa do zero até liberar Contratos/Cobranças, holofote
  andando pelos campos do perfil, coach atualizando conforme completa cada item, modal aparecendo uma vez só.
- Smoke com um usuário "antigo" (dados de antes desta fase, com contratos/cobranças já existentes mas sem chave
  PIX, por exemplo): lista de contratos/cobranças continua acessível e editável; só criar novo fica bloqueado até
  completar.
