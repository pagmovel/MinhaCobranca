# Previsão de recebimentos — desenho

Data: 2026-10-11 · Situação: aprovado em conversa pelo usuário (caminho A, filtro "até", relatórios) — aguardando
revisão deste documento.

## Problema (palavras do usuário)
"Não estamos provendo previsibilidade de recebimentos futuros, pois a cobrança só é gerada próximo ao vencimento.
[…] Assim o usuário terá previsibilidade de recebimentos futuros e poderá utilizar isto para tomada de decisões."

Hoje a cobrança (com o recibo numerado) nasce `users.dias_antecedencia` dias antes do vencimento
(`CalculadoraDePeriodos::devidos`). Antes disso, o mês futuro aparece vazio na lista e zerado nos relatórios.

## Decisão: previsão calculada, nada gravado (caminho A)
As cobranças futuras são **calculadas** a partir dos contratos ativos, na hora de mostrar, e **não** vão para o banco.
A cobrança real (com recibo) continua nascendo perto do vencimento, como hoje.

Rejeitados (com o usuário):
- **Gerar tudo com recibo**: o recibo usa o ano de emissão (`EmissorDeRecibos.php:26-32`) — o de março/2027 sairia
  numerado em 2026; número cancelado não volta, então cada reajuste/encerramento abriria buracos; contrato sem
  `data_fim` (coluna `nullable`) não tem fim.
- **Gravar cobranças sem recibo**: quebra a regra "cobrança nasce com o recibo" e obriga a atualizar/cancelar as
  futuras a cada reajuste, encerramento ou correção de contrato.

Consequência desejada: reajuste, encerramento agendado, "não cobrar"/"proporcional" e correção de contrato aparecem
na previsão na hora, sem efeito colateral.

## 1. Cálculo — `App\Services\PrevisaoDeCobrancas`
`entre(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): list<array>` — previstas com vencimento entre
`$de` (início do dia) e `$ate` (fim do dia), em ordem de vencimento (desempate: id do contrato).

Para cada contrato **ativo** do usuário (com itens e produtos carregados), percorre os períodos pelo índice
(`CalculadoraDePeriodos::periodo`, com a modalidade do contrato) até o vencimento passar de `$ate`, e inclui o período
quando **todas** valem:
1. vencimento dentro de `[$de, $ate]`;
2. não existe cobrança do contrato com o mesmo `periodo_inicio` (qualquer situação, inclusive cancelada — o gerador
   também não refaz);
3. não está antes de `cobrar_a_partir_de` (`GeradorDeCobrancas::antesDeCobrar`);
4. não começa depois de `data_fim`;
5. não é o período que contém o fim com `ultimo_periodo = nao_cobrar`.

Valor: **preço atual do contrato**. Com itens: um item previsto por item do contrato (`ContratoItem::total()`); valor =
soma. Sem itens (contrato antigo): `valor_centavos`, sem itens. Período que contém o fim com `ultimo_periodo =
proporcional`: mesma conta de `ajustarProporcional` (quantidade × usados ÷ dias por item com `ProRata::quantidade`,
total com `ItensDeVenda::total`; sem itens, `ProRata::valor`) e período terminando em `data_fim`.

As condições 3–5 e a conta do proporcional são **as mesmas** do gerador: extrair para um ponto comum usado pelos dois
(o gerador não pode divergir da previsão). Perfil incompleto não impede a previsão (o gerador não gera, mas o
contrato existe).

Formato de cada prevista (o mesmo de `Cobranca::paraLista` onde faz sentido): `id` = `"prevista-{contrato}-{AAAA-MM-DD do início}"`
(texto, só para chave de lista), `contrato_id`, `cliente_id`, `cliente`, `periodo`, `vencimento` (d/m/Y), `valor`
(formatado), `valor_centavos`, `situacao = 'prevista'`, `itens` (`produto_servico_id`, `total_centavos`), `recibo =
null`, `historico = []`.

Segurança: a consulta parte sempre de `$usuario->contratos()`. Desempenho: a trava `LIMITE` da calculadora (1200
períodos) e o limite de 36 meses do filtro seguram o laço.
`// ponytail:` uma consulta por contrato para os `periodo_inicio` existentes (como o gerador); juntar numa consulta só
se a lista de contratos crescer para centenas.

## 2. Tela de Cobranças
- Filtro: "Mês do vencimento" (`mes`, como hoje) + **"até"** (`ate`, `type="month"`). Sem `ate`, vale o próprio mês
  (a tela abre igual a hoje). `ate` antes de `mes` → erro de validação no campo "até" com a explicação; mais de 36
  meses → erro "Escolha um período de até 36 meses." (mesmas mensagens dos relatórios). Trocar "até" aplica o filtro
  na hora (padrão de usabilidade 7). "Vencendo hoje" e "Vencidas" continuam ignorando os meses.
- A consulta das cobranças reais passa a usar `vencimento` entre o 1º dia de `mes` e o último dia de `ate`.
- Previstas entram na mesma lista, **mescladas por vencimento**, quando a situação é "Todas" (`''`), "A vencer" ou
  "Pendentes"; nunca em "Vencendo hoje", "Vencidas", "Pagas" ou "Canceladas". O filtro de cliente vale para elas.
  Só entram previstas com vencimento **depois de hoje** (as de hoje para trás já foram geradas ao abrir a tela; se
  não foram — perfil incompleto —, mostrá-las como "a vencer" seria enganoso).
- Visual: `Carimbo` com situação `prevista` — "Prevista", cinza (`grafite`) com **borda tracejada**; sem recibo, sem
  botões (`acoesDaCobranca` devolve `{ principal: null, menu: [] }` para `prevista`); a linha não tem faixa de ação.
  Legenda curta acima da tabela quando houver previstas: "Previstas: calculadas pelos contratos; viram cobrança com
  recibo perto do vencimento."
- Mensagem de lista vazia continua; com previstas, a lista não fica vazia.

## 3. Relatórios
- **Faturamento por período**: "Previsto" de cada mês = emitidas não canceladas com vencimento no mês **+ previstas**
  do mês (previstas só para vencimentos depois de hoje, pela mesma razão da tela). Coluna nova **"Ainda não emitido"**
  (a parte prevista; soma no total). Descrição da tabela atualizada.
- **Receita por produto ou serviço**: "Previsto" soma os itens das previstas por `produto_servico_id`; previstas de
  contrato sem itens entram na linha "Sem item". Descrição atualizada.
- **Receita por cliente** e **Contas a receber**: não mudam (o segundo é a situação de hoje, sem período; com contrato
  sem fim, "depois de 90 dias" não teria limite).
- CSV acompanha (a tabela é a mesma).

## Fora do escopo
Painel; emitir uma prevista antes da hora ("emitir agora"); previsão de cobranças avulsas (não têm contrato);
abas de situação com contagem e soma (T17.16, depois desta).

## Testes
- Unidade/feature do serviço (TDD): contrato mensal sem fim → previstas até `$ate`; período já emitido (inclusive
  cancelado) fica de fora; `cobrar_a_partir_de`; `data_fim` com `inteiro`, `nao_cobrar` e `proporcional` (exemplo
  aprovado: 18/31 × R$ 247,57 = **R$ 143,84**); pós-pago; contrato inativo não prevê; contrato de outro usuário não
  aparece; reajuste muda o valor previsto; contrato sem itens.
- Garantia de igualdade com o gerador: para um contrato, as previstas de um intervalo, depois de "andar o relógio" e
  gerar, viram cobranças com os mesmos períodos, vencimentos e valores.
- Tela: `ate` padrão = `mes`; `ate` < `mes` e > 36 meses recusados; previstas mescladas por vencimento só nas situações
  certas; filtro de cliente; nada é gravado (contagem de `cobrancas` e `recibos` igual antes e depois).
- Relatórios: "Previsto" e "Ainda não emitido" do faturamento; previsto por produto com previstas e "Sem item".
- JS: `acoesDaCobranca` para `prevista`.
