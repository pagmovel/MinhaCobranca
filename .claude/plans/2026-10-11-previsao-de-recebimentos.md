# Fase 18 — Previsão de recebimentos — plano de implementação

> **Para quem executar:** use superpowers:executing-plans (inline) tarefa por tarefa, com TDD
> (superpowers:test-driven-development). Os passos usam checkbox (`- [ ]`). Os scripts `task-start`/`task-done` procuram
> "Task N" e este plano usa "Tarefa": o registro do andamento fica **na tabela de estado abaixo** e nos commits.

**Objetivo:** mostrar, na lista de cobranças e nos relatórios, as cobranças que os contratos ativos ainda vão emitir.
Elas são calculadas na hora e nada é gravado.

**Arquitetura:** a calculadora de períodos ganha `entre()` (períodos com vencimento num intervalo). O gerador expõe a
regra "este período vira cobrança?" (`cobravel`), usada também pela previsão. Um serviço novo,
`PrevisaoDeCobrancas`, monta as linhas previstas no formato da lista. O `CobrancaController@index` mescla essas linhas
às cobranças reais. O `Relatorios` soma as previstas no "Previsto".

**Tecnologias:** Laravel 13, PHPUnit 12, Inertia v3, Vue 3, Tailwind 4, `node --test`.

**Especificação:** [docs/superpowers/specs/2026-10-11-previsao-de-recebimentos-design.md](../../docs/superpowers/specs/2026-10-11-previsao-de-recebimentos-design.md)
(aprovada pelo usuário em 2026-10-11: "pode escrever o plano de implementação com fases, tarefas e estados. e iniciar").

Branch: `feat/modulo-cobranca-catalogo` (continua). **Sem migração, sem escrita em banco:** os testes rodam em SQLite
(`phpunit.xml`).

## Restrições gerais
- Nada é gravado pela previsão. Nem cobrança, nem recibo, nem coluna nova.
- Os períodos, a regra de inclusão e a conta do proporcional são **as mesmas do gerador**. Um teste garante isso.
- As previstas entram só com vencimento **depois de hoje**, na tela e nos relatórios.
- Mensagens do "até" iguais às dos relatórios: "O fim do período deve ser igual ou depois do início." e "Escolha um
  período de até 36 meses."
- Na lista, as previstas aparecem só em Todas (`''`), A vencer (`a_vencer`) e Pendentes (`pendente`).
- Textos da tela: "Até", carimbo "Prevista" e a legenda "Previstas: calculadas pelos contratos; viram cobrança com
  recibo perto do vencimento."
- pt-BR em código, comentários e commits. Os commits terminam com
  `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Depois de mexer em PHP, rodar `vendor/bin/pint --dirty --format agent`. Antes de commitar front-end, rodar
  `npm run build`.

## Pontos para a revisão final (casos que os testes não cobrem)
1. **Trocar o mês com o "até" diferente dele.** A regra está no `.vue` e não tem teste automático. O "até" acompanha o
   mês enquanto os dois forem iguais e nunca fica antes dele. Conferir no navegador.
2. **Contrato antigo, com centenas de períodos.** O laço de `entre()` para no `LIMITE` (1200) e no vencimento depois de
   `$ate`. A tela não pode travar.
3. **Prevista e cobrança real no mesmo dia.** A ordem tem que ser estável (a real primeiro) e a chave de lista não pode
   se repetir: o `id` da prevista é texto (`prevista-…`), o da real é número.
4. **`cliente_id` chega como texto na URL.** A comparação com o `cliente_id` inteiro da prevista precisa converter.
   Coberto no teste do filtro de cliente.
5. **Erro do "até" num GET do Inertia.** `back()->withErrors` precisa voltar à lista com a mensagem embaixo do campo,
   como nos relatórios. Conferir no navegador.

## Estado das tarefas

| Tarefa | O quê | Estado |
|---|---|---|
| T18.1 | `CalculadoraDePeriodos::entre()` + regra comum do gerador (`existentes`, `cobravel`, `contemOFim` público) | testada (RED: método inexistente → GREEN; gerador, encerramento, correção do início e Unit 117/117) |
| T18.2 | Serviço `PrevisaoDeCobrancas::entre()` + teste de igualdade com o gerador | testada (RED: classe inexistente nos 8 → GREEN 8/8, igualdade com o gerador inclusa) |
| T18.3 | Lista de cobranças, servidor: filtro "até", validação, previstas mescladas por vencimento | testada (RED 0/7 pelos motivos esperados → GREEN; Cobrancas + Auditoria 67/67) |
| T18.4 | Lista de cobranças, tela: campo "Até", carimbo "Prevista", sem ações, legenda | testada (JS RED: principal 'baixa' → GREEN 23/23; build ok); conferência no navegador pendente (T18.6) |
| T18.5 | Relatórios: "Previsto" e "Ainda não emitido" no faturamento; previstas por produto | testada (RED 4 falhas esperadas → GREEN; suíte inteira 332/332, JS 23/23) |
| T18.6 | Skill, revisão final, conferência no navegador (com autorização) e validação do usuário | concluída: skill atualizada; revisão final (Opus) com 0 Critical e 0 Important; a correção do "Até" foi testada (JS RED: módulo inexistente → GREEN 28/28, PHP 332/332, build ok). Pendentes: conferência no navegador (pede autorização) e validação do usuário |

## Registro de execução (decisões tomadas durante a execução)
- Execução **inline** (superpowers:executing-plans). O usuário pediu "e iniciar" sem escolher o método. Se fosse
  preferível subagente por tarefa, a única perda é a revisão por tarefa; a revisão final continua.
- Pré-checagem das interfaces:
  - T18.1 → T18.2: os nomes batem.
  - T18.2 → T18.3: `cliente_id` é `int` e `situacao` é `'prevista'`.
  - T18.2 → T18.5: `vencimento` em `d/m/Y`; o mês sai de `substr(…, 3)`.

  Sem conflito.
- **Decisão (T18.5):** o plano esqueceu o `test_csv_para_o_excel_em_portugues`, que compara o CSV do faturamento byte
  a byte. A expectativa foi atualizada com a coluna `"Ainda não emitido"`, vindo depois de "Previsto". A especificação
  diz "CSV acompanha". Se essa posição da coluna estiver errada, basta mudar a ordem das colunas.
- **Revisão final** (subagente Opus, só leitura; não rodou a suíte): 0 Critical, 0 Important, 5 Minor.
  - **Corrigido:** "Até" fora de contexto e campo apagado. Pela prioridade de usabilidade do produto, a gravidade subiu
    para Important. A regra foi para `filtroPeriodo.js` com 5 testes.
  - **Decisão do usuário:** os relatórios não geram cobranças ao abrir. Fica a limitação, registrada na skill.
  - **Adiados (Minor):**
    1. A previsão roda à toa quando o intervalo já passou (`de` > `ate`). Um `return []` no início de `entre()`
       resolve.
    2. Com "Vencendo hoje" ou "Vencidas", um "Até" inválido vindo da URL ainda é validado e bloqueia a tela. Pela tela
       não acontece mais.
    3. `cliente_id` e `contrato_id` da prevista sem `(int)`. Hoje funciona em SQLite e PostgreSQL.
- **Decisão:** ao mudar o próprio "Até", ele não é corrigido sem aviso. Um valor fora do limite mostra o erro do
  servidor embaixo dele, que é o campo mexido. Se o usuário preferir a correção automática também aí, basta trocar o
  `escolheuAte` para usar `ajustarAte`.

---

### Tarefa T18.1: períodos de um intervalo e regra comum do gerador

**Arquivos:**
- Modificar: `app/Services/CalculadoraDePeriodos.php` (método novo `entre`, depois de `devidos`)
- Modificar: `app/Services/GeradorDeCobrancas.php:32-54` (usar `existentes` e `cobravel`) e `:146` (`contemOFim` público)
- Teste: `tests/Unit/CalculadoraDePeriodosTest.php`

**Interfaces:**
- Produz:
  - `CalculadoraDePeriodos::entre(CarbonImmutable $dataInicio, ?CarbonImmutable $dataFim, Periodicidade $periodicidade, int $diaVencimento, Modalidade $modalidade, CarbonImmutable $de, CarbonImmutable $ate): list<Periodo>`
  - `GeradorDeCobrancas::existentes(Contrato $contrato): list<string>` (datas `Y-m-d`)
  - `GeradorDeCobrancas::cobravel(Contrato $contrato, Periodo $periodo, array $existentes): bool`
  - `GeradorDeCobrancas::contemOFim(Contrato $contrato, Periodo $periodo): bool` (passa a ser público)

- [ ] **Passo 1: teste que falha** (no fim de `tests/Unit/CalculadoraDePeriodosTest.php`)

```php
    public function test_entre_lista_os_vencimentos_do_intervalo_ate_o_fim_do_contrato(): void
    {
        $calc = new CalculadoraDePeriodos;
        $vencimentos = fn (?string $fim) => array_map(
            fn ($periodo) => $periodo->vencimento->toDateString(),
            $calc->entre($this->data('2026-09-21'), $fim === null ? null : $this->data($fim), Periodicidade::Mensal, 21, Modalidade::Prepago, $this->data('2026-10-01'), $this->data('2027-01-21')),
        );

        $this->assertSame(['2026-10-21', '2026-11-21', '2026-12-21', '2027-01-21'], $vencimentos(null));
        // Fim em 05/12: o período 21/11–20/12 contém o fim e entra; o de 21/12 começa depois e fica de fora.
        $this->assertSame(['2026-10-21', '2026-11-21'], $vencimentos('2026-12-05'));
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact --filter=test_entre_lista_os_vencimentos tests/Unit/CalculadoraDePeriodosTest.php`
Esperado: FAIL com `Call to undefined method App\Services\CalculadoraDePeriodos::entre()`.

- [ ] **Passo 3: implementar `entre`** (em `CalculadoraDePeriodos`, depois de `devidos`)

```php
    /**
     * Períodos com vencimento entre `$de` e `$ate` (dias inteiros), sem passar do fim do contrato: o período que contém
     * o fim entra; o que começa depois dele, não. Usado pela previsão de cobranças.
     *
     * @return list<Periodo>
     */
    public function entre(
        CarbonImmutable $dataInicio,
        ?CarbonImmutable $dataFim,
        Periodicidade $periodicidade,
        int $diaVencimento,
        Modalidade $modalidade,
        CarbonImmutable $de,
        CarbonImmutable $ate,
    ): array {
        $periodos = [];

        for ($indice = 0; $indice < self::LIMITE; $indice++) {
            $periodo = $this->periodo($dataInicio, $periodicidade, $diaVencimento, $indice, $modalidade);

            if (($dataFim !== null && $periodo->inicio->gt($dataFim)) || $periodo->vencimento->gt($ate->startOfDay())) {
                break;
            }

            if ($periodo->vencimento->gte($de->startOfDay())) {
                $periodos[] = $periodo;
            }
        }

        return $periodos;
    }
```

- [ ] **Passo 4: rodar e ver passar**

Rodar: `php artisan test --compact tests/Unit/CalculadoraDePeriodosTest.php`
Esperado: todos passam.

- [ ] **Passo 5: extrair a regra comum no gerador** (refatoração; a suíte do gerador continua verde)

Em `gerarPara`, trocar o bloco das linhas 33-53 por:

```php
            $existentes = $this->existentes($contrato);

            $devidos = $this->calculadora->devidos(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $usuario->dias_antecedencia,
                $hoje,
                $contrato->modalidade,
            );

            foreach ($devidos as $periodo) {
                if ($this->cobravel($contrato, $periodo, $existentes)) {
                    $aGerar[] = [$contrato, $periodo];
                }
            }
```

E acrescentar, antes de `antesDeCobrar`:

```php
    /** @return list<string> início (Y-m-d) dos períodos do contrato que já têm cobrança, em qualquer situação. */
    public function existentes(Contrato $contrato): array
    {
        return $contrato->cobrancas()->pluck('periodo_inicio')
            ->map(fn ($data) => CarbonImmutable::parse($data)->toDateString())
            ->all();
    }

    /**
     * O período vira cobrança? Não, se já existe (inclusive cancelada), se é anterior ao "cobrar a partir de" ou se é o
     * que contém o fim com "não cobrar". A geração e a previsão usam esta mesma regra.
     *
     * @param  list<string>  $existentes
     */
    public function cobravel(Contrato $contrato, Periodo $periodo, array $existentes): bool
    {
        return ! in_array($periodo->inicio->toDateString(), $existentes, true)
            && ! $this->antesDeCobrar($contrato, $periodo)
            && ! ($this->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'nao_cobrar');
    }
```

Trocar `private function contemOFim` por `public function contemOFim`.

- [ ] **Passo 6: rodar o gerador, o encerramento e a correção do início**

Rodar: `php artisan test --compact tests/Feature/Cobrancas/GeradorDeCobrancasTest.php tests/Feature/EncerramentoContratoTest.php tests/Feature/CorrecaoDoInicioTest.php tests/Unit`
Esperado: todos passam (refatoração sem mudança de comportamento).

- [ ] **Passo 7: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/CalculadoraDePeriodos.php app/Services/GeradorDeCobrancas.php tests/Unit/CalculadoraDePeriodosTest.php
git commit -m "feat: períodos de um intervalo e regra comum de geração (T18.1)"
```

---

### Tarefa T18.2: serviço `PrevisaoDeCobrancas`

**Arquivos:**
- Criar: `app/Services/PrevisaoDeCobrancas.php`
- Teste: `tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php`

**Interfaces:**
- Consome (T18.1): `CalculadoraDePeriodos::entre`, `GeradorDeCobrancas::existentes`, `cobravel`, `contemOFim`.
- Produz: `PrevisaoDeCobrancas::entre(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): list<array>`, em ordem
  de vencimento (desempate pelo id do contrato). Cada linha traz:

  | Chave | Valor |
  |---|---|
  | `id` | `"prevista-{contrato}-{Y-m-d do início}"` |
  | `contrato_id` | `int` |
  | `cliente_id` | `int` |
  | `cliente` | `string` |
  | `periodo` | `"d/m/Y a d/m/Y"` |
  | `vencimento` | `"d/m/Y"` |
  | `valor` | `"1.234,56"` |
  | `valor_centavos` | `int` |
  | `situacao` | `'prevista'` |
  | `itens` | `list<array{produto_servico_id: int, total_centavos: int}>` |
  | `recibo` | `null` |
  | `historico` | `[]` |
  | `pago_em` | `null` |
  | `enviado` | `false` |
  | `editavel` | `false` |

  As cinco últimas chaves existem para a linha caber no mesmo template da cobrança real (`Cobranca::paraLista`).

- [ ] **Passo 1: testes que falham** (`tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php`)

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\Modalidade;
use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Services\PrevisaoDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrevisaoDeCobrancasTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
    }

    /** @return list<array<string, mixed>> */
    private function prever(string $de, string $ate): array
    {
        return app(PrevisaoDeCobrancas::class)->entre($this->usuario, CarbonImmutable::parse($de), CarbonImmutable::parse($ate)->endOfDay());
    }

    /** Mensal pré-pago desde 01/08, vence dia 20, um item de R$ 247,57 (o do exemplo aprovado da T17.9). */
    private function contratoComItem(array $extra = []): Contrato
    {
        $contrato = Contrato::factory()->for($this->usuario)->create(array_merge([
            'data_inicio' => '2026-08-01', 'dia_vencimento' => 20, 'valor_centavos' => 24757,
        ], $extra));
        $produto = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem de site']);
        $contrato->itens()->create([
            'produto_servico_id' => $produto->id, 'quantidade' => '1.000', 'preco_unitario_centavos' => 24757, 'principal' => true, 'ordem' => 0,
        ]);

        return $contrato;
    }

    public function test_contrato_sem_fim_preve_cada_vencimento_do_intervalo(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->create(); // desde 21/09, dia 21, R$ 260,60, sem itens

        $previstas = $this->prever('2026-10-01', '2027-01-31');

        $this->assertSame(['21/10/2026', '21/11/2026', '21/12/2026', '21/01/2027'], array_column($previstas, 'vencimento'));
        $this->assertSame([
            'id' => "prevista-{$contrato->id}-2026-10-21",
            'contrato_id' => $contrato->id,
            'cliente_id' => $contrato->cliente_id,
            'cliente' => $contrato->cliente->nome,
            'periodo' => '21/10/2026 a 20/11/2026',
            'vencimento' => '21/10/2026',
            'valor' => '260,60',
            'valor_centavos' => 26060,
            'situacao' => 'prevista',
            'itens' => [],
            'recibo' => null,
            'historico' => [],
            'pago_em' => null,
            'enviado' => false,
            'editavel' => false,
        ], $previstas[0]);
    }

    public function test_periodo_ja_emitido_fica_de_fora_mesmo_cancelado(): void
    {
        $contrato = Contrato::factory()->for($this->usuario)->create();
        Cobranca::factory()->for($this->usuario)->for($contrato->cliente)->create([
            'contrato_id' => $contrato->id, 'periodo_inicio' => '2026-11-21', 'periodo_fim' => '2026-12-20',
            'vencimento' => '2026-11-21', 'status' => StatusCobranca::Cancelada,
        ]);

        $this->assertSame(['21/10/2026', '21/12/2026'], array_column($this->prever('2026-10-01', '2026-12-31'), 'vencimento'));
    }

    public function test_respeita_o_cobrar_a_partir_de(): void
    {
        Contrato::factory()->for($this->usuario)->create(['cobrar_a_partir_de' => '2026-12-01']);

        $this->assertSame(['21/12/2026', '21/01/2027'], array_column($this->prever('2026-10-01', '2027-01-31'), 'vencimento'));
    }

    public function test_ultimo_periodo_inteiro_nao_cobrar_ou_proporcional(): void
    {
        $esperados = [
            'inteiro' => [['01/10/2026 a 31/10/2026', 24757, [24757]]],
            'nao_cobrar' => [],
            // Exemplo aprovado (T17.9): 18 de 31 dias de 1 × R$ 247,57 = R$ 143,84 (quantidade 0,581).
            'proporcional' => [['01/10/2026 a 18/10/2026', 14384, [14384]]],
        ];

        foreach ($esperados as $ultimo => $esperado) {
            $this->usuario = User::factory()->create();
            $this->contratoComItem(['data_fim' => '2026-10-18', 'ultimo_periodo' => $ultimo]);

            $previstas = $this->prever('2026-10-01', '2026-12-31');

            $this->assertSame($esperado, array_map(
                fn (array $prevista) => [$prevista['periodo'], $prevista['valor_centavos'], array_column($prevista['itens'], 'total_centavos')],
                $previstas,
            ), $ultimo);
        }
    }

    public function test_pos_pago_vence_depois_do_periodo(): void
    {
        Contrato::factory()->for($this->usuario)->create(['modalidade' => Modalidade::Pospago, 'data_inicio' => '2026-09-01', 'dia_vencimento' => 10]);

        $this->assertSame(
            [['01/09/2026 a 30/09/2026', '10/10/2026'], ['01/10/2026 a 31/10/2026', '10/11/2026']],
            array_map(fn (array $prevista) => [$prevista['periodo'], $prevista['vencimento']], $this->prever('2026-10-01', '2026-11-30')),
        );
    }

    public function test_so_contratos_ativos_do_proprio_usuario(): void
    {
        Contrato::factory()->for($this->usuario)->create(['ativo' => false]);
        Contrato::factory()->create(); // de outro usuário

        $this->assertSame([], $this->prever('2026-10-01', '2026-12-31'));
    }

    public function test_reajuste_muda_o_valor_previsto(): void
    {
        $contrato = $this->contratoComItem();
        $this->assertSame([24757], array_column($this->prever('2026-11-01', '2026-11-30'), 'valor_centavos'));

        $contrato->itens()->update(['preco_unitario_centavos' => 26000]);
        $contrato->update(['valor_centavos' => 26000]);

        $this->assertSame([[26000, [26000]]], array_map(
            fn (array $prevista) => [$prevista['valor_centavos'], array_column($prevista['itens'], 'total_centavos')],
            $this->prever('2026-11-01', '2026-11-30'),
        ));
    }

    public function test_previsao_bate_com_o_que_o_gerador_cria(): void
    {
        $contrato = $this->contratoComItem(['data_inicio' => '2026-10-01', 'data_fim' => '2026-12-18', 'ultimo_periodo' => 'proporcional']);
        $previstas = array_map(
            fn (array $prevista) => [$prevista['periodo'], $prevista['vencimento'], $prevista['valor_centavos']],
            $this->prever('2026-10-01', '2026-12-31'),
        );

        // Anda o relógio até depois do último vencimento e deixa o gerador criar as cobranças de verdade.
        $this->travelTo(CarbonImmutable::parse('2026-12-31 10:00:00'));
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());
        $geradas = $contrato->cobrancas()->orderBy('vencimento')->get()->map(fn (Cobranca $cobranca) => [
            $cobranca->periodo_inicio->format('d/m/Y').' a '.$cobranca->periodo_fim->format('d/m/Y'),
            $cobranca->vencimento->format('d/m/Y'),
            $cobranca->valor_centavos,
        ])->all();

        $this->assertCount(3, $previstas);
        $this->assertSame($previstas, $geradas);
        $this->assertSame([], $this->prever('2026-10-01', '2026-12-31'), 'depois de gerar, nada fica previsto');
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php`
Esperado: FAIL em todos, com `Target class [App\Services\PrevisaoDeCobrancas] does not exist`.

- [ ] **Passo 3: implementar** (`app/Services/PrevisaoDeCobrancas.php`)

```php
<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\ContratoItem;
use App\Models\User;
use App\Support\Dinheiro;
use App\Support\ItensDeVenda;
use App\Support\Periodo;
use App\Support\ProRata;
use Carbon\CarbonImmutable;

/** Cobranças que os contratos ativos ainda vão emitir: calculadas na hora, nada é gravado. */
final class PrevisaoDeCobrancas
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private GeradorDeCobrancas $gerador,
    ) {}

    /**
     * Previstas com vencimento entre `$de` e `$ate`, em ordem de vencimento (desempate: contrato). Mesmos períodos, regra
     * e valores do gerador: o preço é o atual do contrato.
     *
     * @return list<array<string, mixed>>
     */
    public function entre(User $usuario, CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $aPrever = [];

        foreach ($usuario->contratos()->where('ativo', true)->with(['itens', 'cliente:id,nome'])->get() as $contrato) {
            // ponytail: uma consulta por contrato para os períodos já emitidos (como o gerador); juntar numa só se a lista passar de centenas de contratos.
            $existentes = $this->gerador->existentes($contrato);
            $periodos = $this->calculadora->entre(
                $contrato->data_inicio,
                $contrato->data_fim,
                $contrato->periodicidade,
                $contrato->dia_vencimento,
                $contrato->modalidade,
                $de,
                $ate,
            );

            foreach ($periodos as $periodo) {
                if ($this->gerador->cobravel($contrato, $periodo, $existentes)) {
                    $aPrever[] = [$contrato, $periodo];
                }
            }
        }

        usort($aPrever, fn (array $a, array $b) => [$a[1]->vencimento, $a[0]->id] <=> [$b[1]->vencimento, $b[0]->id]);

        return array_map(fn (array $par) => $this->prevista(...$par), $aPrever);
    }

    /** @return array<string, mixed> */
    private function prevista(Contrato $contrato, Periodo $periodo): array
    {
        $fim = $periodo->fim;
        $valor = $contrato->valor_centavos;
        $itens = $contrato->itens->map(fn (ContratoItem $item) => [
            'produto_servico_id' => $item->produto_servico_id,
            'total_centavos' => $item->total(),
        ]);

        // Mesma conta de GeradorDeCobrancas::ajustarProporcional; o teste de igualdade com o gerador segura as duas.
        if ($this->gerador->contemOFim($contrato, $periodo) && $contrato->ultimo_periodo === 'proporcional') {
            [$usados, $dias] = ProRata::dias($periodo, $contrato->data_fim);
            $itens = $contrato->itens->map(fn (ContratoItem $item) => [
                'produto_servico_id' => $item->produto_servico_id,
                'total_centavos' => ItensDeVenda::total(
                    ProRata::quantidade(ItensDeVenda::milesimos($item->quantidade), $usados, $dias),
                    $item->preco_unitario_centavos,
                ),
            ]);
            $valor = $itens->isEmpty() ? ProRata::valor($valor, $usados, $dias) : $itens->sum('total_centavos');
            $fim = $contrato->data_fim;
        }

        return [
            'id' => "prevista-{$contrato->id}-{$periodo->inicio->toDateString()}",
            'contrato_id' => $contrato->id,
            'cliente_id' => $contrato->cliente_id,
            'cliente' => $contrato->cliente->nome,
            'periodo' => $periodo->inicio->format('d/m/Y').' a '.$fim->format('d/m/Y'),
            'vencimento' => $periodo->vencimento->format('d/m/Y'),
            'valor' => Dinheiro::formatar($valor),
            'valor_centavos' => $valor,
            'situacao' => 'prevista',
            'itens' => $itens->values()->all(),
            'recibo' => null,
            'historico' => [],
            'pago_em' => null,
            'enviado' => false,
            'editavel' => false,
        ];
    }
}
```

Fora do proporcional, o valor é o `valor_centavos` do contrato, como em `GeradorDeCobrancas::criar`, e não a soma dos
itens. A especificação diz "valor = soma". Segue-se o gerador porque a especificação também exige "a previsão não
diverge do gerador". Se essas duas exigências entrarem em conflito, o teste de igualdade acusa.

- [ ] **Passo 4: rodar e ver passar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php`
Esperado: 8 testes passam.

- [ ] **Passo 5: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/PrevisaoDeCobrancas.php tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php
git commit -m "feat: previsão de cobranças calculada pelos contratos, sem gravar nada (T18.2)"
```

---

### Tarefa T18.3: lista de cobranças, lado do servidor

**Arquivos:**
- Modificar: `app/Http/Controllers/CobrancaController.php:27-75` (`index`)
- Teste: `tests/Feature/Cobrancas/PrevisaoNaListaTest.php`

**Interfaces:**
- Consome (T18.2): `PrevisaoDeCobrancas::entre`.
- Produz, para a T18.4: a prop `filtros.ate` (`Y-m`), o erro de validação `ate` e linhas com `situacao = 'prevista'` em
  `cobrancas`.

- [ ] **Passo 1: testes que falham** (`tests/Feature/Cobrancas/PrevisaoNaListaTest.php`)

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Recibo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PrevisaoNaListaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        // Antecedência 10: abrir a lista gera 21/09 e 21/10; de novembro em diante só há previstas.
        $this->usuario = User::factory()->create();
        $this->contrato = Contrato::factory()->for($this->usuario)->create(); // desde 21/09, dia 21, R$ 260,60
    }

    /** @return list<array{0: string, 1: string}> vencimento e situação de cada linha */
    private function linhas(string $consulta): array
    {
        return array_map(
            fn (array $linha) => [$linha['vencimento'], $linha['situacao']],
            $this->actingAs($this->usuario)->get('/cobrancas'.$consulta)->assertOk()->inertiaProps('cobrancas'),
        );
    }

    public function test_ate_comeca_igual_ao_mes(): void
    {
        $this->actingAs($this->usuario);

        $this->assertSame(['2026-10', '2026-10'], [
            $this->get('/cobrancas')->inertiaProps('filtros.mes'),
            $this->get('/cobrancas')->inertiaProps('filtros.ate'),
        ]);
        $this->assertSame('2026-11', $this->get('/cobrancas?mes=2026-11')->inertiaProps('filtros.ate'));
        // Um mês só: 21/10 já é cobrança de verdade e não sobra prevista em outubro.
        $this->assertSame([['21/10/2026', 'a_vencer']], $this->linhas('?mes=2026-10'));
    }

    public function test_previstas_entram_mescladas_por_vencimento(): void
    {
        Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(['vencimento' => '2026-11-10']);

        $this->assertSame([
            ['21/10/2026', 'a_vencer'],
            ['10/11/2026', 'a_vencer'],
            ['21/11/2026', 'prevista'],
            ['21/12/2026', 'prevista'],
        ], $this->linhas('?mes=2026-10&ate=2026-12'));
    }

    public function test_previstas_so_em_todas_a_vencer_e_pendentes(): void
    {
        $comPrevistas = [['21/10/2026', 'a_vencer'], ['21/11/2026', 'prevista']];

        $this->assertSame($comPrevistas, $this->linhas('?mes=2026-10&ate=2026-11&status=a_vencer'));
        $this->assertSame($comPrevistas, $this->linhas('?mes=2026-10&ate=2026-11&status=pendente'));
        foreach (['paga', 'cancelada', 'vencida', 'vence_hoje'] as $status) {
            $this->assertNotContains('prevista', array_column($this->linhas("?mes=2026-10&ate=2026-11&status={$status}"), 1), $status);
        }
    }

    public function test_filtro_de_cliente_vale_para_as_previstas(): void
    {
        $outro = Cliente::factory()->for($this->usuario)->create();
        Contrato::factory()->for($this->usuario)->for($outro)->create(['dia_vencimento' => 5, 'data_inicio' => '2026-09-05']);

        $this->assertSame([['21/11/2026', 'prevista']], $this->linhas("?mes=2026-11&cliente_id={$this->contrato->cliente_id}"));
        $this->assertSame([['05/11/2026', 'prevista']], $this->linhas("?mes=2026-11&cliente_id={$outro->id}"));
    }

    public function test_so_previstas_depois_de_hoje(): void
    {
        // Perfil incompleto: a geração não cria nada. Mesmo assim, 21/09 (já passou) não aparece como prevista.
        $this->usuario = User::factory()->create(['cep' => null]);
        Contrato::factory()->for($this->usuario)->create();

        $this->assertSame([['21/10/2026', 'prevista']], $this->linhas('?mes=2026-09&ate=2026-10'));
    }

    public function test_ate_antes_do_mes_ou_mais_de_36_meses_e_recusado(): void
    {
        $this->actingAs($this->usuario);

        $this->get('/cobrancas?mes=2026-10&ate=2026-09')->assertSessionHasErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        $this->get('/cobrancas?mes=2026-01&ate=2029-01')->assertSessionHasErrors(['ate' => 'Escolha um período de até 36 meses.']);
        $this->get('/cobrancas?ate=outubro')->assertSessionHasErrors('ate');
        $this->get('/cobrancas?mes=2026-01&ate=2028-12')->assertOk();
    }

    public function test_previsao_nao_grava_nada(): void
    {
        $this->actingAs($this->usuario)->get('/cobrancas')->assertOk(); // a geração normal acontece aqui
        $antes = [Cobranca::count(), Recibo::count()];

        $linhas = $this->linhas('?mes=2026-10&ate=2029-09');

        $this->assertCount(36, $linhas); // 21/10/2026 de verdade + 35 previstas
        $this->assertSame($antes, [Cobranca::count(), Recibo::count()]);
    }
}
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact tests/Feature/Cobrancas/PrevisaoNaListaTest.php`
Esperado: FAIL. `filtros.ate` não existe, não há linhas `prevista` e o `ate` não é validado.

- [ ] **Passo 3: implementar no `index`**

Assinatura e imports:

```php
use App\Services\PrevisaoDeCobrancas;
use Illuminate\Http\RedirectResponse; // já importado (usado em edit)

    public function index(Request $request, GeradorDeCobrancas $gerador, PrevisaoDeCobrancas $previsao): Response|RedirectResponse
```

Validação: acrescentar `'ate' => ['nullable', 'date_format:Y-m'],` depois de `mes`. Depois da linha do `$mes`:

```php
        $ate = isset($filtros['ate']) ? CarbonImmutable::createFromFormat('Y-m-d', $filtros['ate'].'-01')->startOfDay() : $mes;

        // Mesmas mensagens dos relatórios.
        if ($mes->gt($ate)) {
            return back()->withErrors(['ate' => 'O fim do período deve ser igual ou depois do início.']);
        }
        if ($mes->diffInMonths($ate) >= 36) {
            return back()->withErrors(['ate' => 'Escolha um período de até 36 meses.']);
        }
```

Trocar `[$mes, $mes->endOfMonth()]` por `[$mes, $ate->endOfMonth()]`. Trocar o bloco do cliente e o `'cobrancas' =>` por:

```php
        // Previstas (calculadas pelos contratos, nada gravado) só onde entram as em aberto e só depois de hoje: até hoje a
        // geração ao abrir a tela já criou as cobranças de verdade.
        $previstas = in_array($status, [null, 'a_vencer', 'pendente'], true)
            ? $previsao->entre($usuario, $mes->max($hoje->addDay()), $ate->endOfMonth())
            : [];
        if (! empty($filtros['cliente_id'])) {
            $consulta->where('cliente_id', $filtros['cliente_id']);
            $previstas = array_filter($previstas, fn (array $prevista) => $prevista['cliente_id'] === (int) $filtros['cliente_id']);
        }

        $lista = $consulta->get()->map(fn (Cobranca $cobranca) => $cobranca->paraLista($hoje))
            ->concat($previstas)
            ->sortBy(fn (array $linha) => CarbonImmutable::createFromFormat('!d/m/Y', $linha['vencimento'])->toDateString())
            ->values();
```

No `Inertia::render`, usar `'cobrancas' => $lista,` e acrescentar `'ate' => $ate->format('Y-m'),` em `filtros`, depois
de `mes`.

- [ ] **Passo 4: rodar e ver passar, com as listas antigas**

Rodar: `php artisan test --compact tests/Feature/Cobrancas tests/Feature/AuditoriaTest.php`
Esperado: todos passam. Se um teste antigo da lista ganhar uma linha `prevista` inesperada, reler o cenário antes de
mexer: a regra "só depois de hoje" deveria segurar.

- [ ] **Passo 5: Pint e commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/CobrancaController.php tests/Feature/Cobrancas/PrevisaoNaListaTest.php
git commit -m "feat: filtro até e cobranças previstas na lista (T18.3)"
```

---

### Tarefa T18.4: lista de cobranças, tela

**Arquivos:**
- Modificar: `resources/js/acoesCobranca.js` (`emOrdem`)
- Modificar: `resources/js/acoesCobranca.test.js`
- Modificar: `resources/js/Components/Carimbo.vue` (`estilos`)
- Modificar: `resources/js/Pages/Cobrancas/Index.vue` (script: `vazio`, `escolheuMes`; template: formulário e legenda)

**Interfaces:**
- Consome (T18.3): `filtros.ate`, `$page.props.errors.ate` e as linhas com `situacao === 'prevista'`.

- [ ] **Passo 1: teste JS que falha** (no fim de `resources/js/acoesCobranca.test.js`)

```js
test('prevista: sem ações (a cobrança ainda não existe)', () => {
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'prevista', recibo: null, editavel: false })), { principal: null, menu: [] });
});
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `node --test resources/js/acoesCobranca.test.js`
Esperado: FAIL. Hoje volta `principal: 'baixa'`.

- [ ] **Passo 3: implementar** (primeira linha de `emOrdem`)

```js
    if (cobranca.situacao === 'prevista') return [];
```

- [ ] **Passo 4: rodar e ver passar**

Rodar: `node --test resources/js/*.test.js`
Esperado: 23/23 passam.

- [ ] **Passo 5: carimbo "Prevista"** (`Carimbo.vue`, acrescentar ao fim de `estilos`)

```js
    // Ainda não é cobrança: cinza e tracejado, para não se confundir com "A vencer".
    prevista: ['Prevista', 'border-dashed border-grafite/50 text-grafite/70'],
```

- [ ] **Passo 6: campo "Até", legenda e mensagem de vazio** (`Index.vue`)

No script, trocar `vazio` e `escolheuMes` por:

```js
const vazio = computed(() => ({
    vence_hoje: 'Nenhuma cobrança vence hoje.',
    vencida: 'Nenhuma cobrança vencida em aberto.',
}[props.filtros.status] ?? 'Nenhuma cobrança com vencimento neste período e filtros.'));
const temPrevistas = computed(() => props.cobrancas.some((cobranca) => cobranca.situacao === 'prevista'));
```

```js
// Escolher um mês é pedir para ver aquele período: sai de "Vencendo hoje"/"Vencidas", que buscam em todos os meses.
function escolheuPeriodo() {
    if (todosOsMeses.value) {
        filtro.status = '';
    }
    filtrar();
}
// "Até" acompanha o mês enquanto os dois forem iguais (um mês só) e nunca fica antes dele.
let mesAnterior = filtro.mes;
function escolheuMes() {
    if (filtro.ate === mesAnterior || filtro.ate < filtro.mes) {
        filtro.ate = filtro.mes;
    }
    mesAnterior = filtro.mes;
    escolheuPeriodo();
}
```

No template, trocar `sm:grid-cols-4` por `sm:grid-cols-2 lg:grid-cols-5` no `<form>` e acrescentar, logo depois do
`Campo` do mês:

```html
            <Campo id="ate" rotulo="Até" :erro="$page.props.errors?.ate">
                <input id="ate" v-model="filtro.ate" type="month" :min="filtro.mes" class="entrada" @change="escolheuPeriodo">
            </Campo>
```

Antes do `<p v-if="!cobrancas.length"…>`:

```html
        <Transition name="surgir">
            <p v-if="temPrevistas" class="mb-3 font-estreita text-xs text-grafite/75">
                Previstas: calculadas pelos contratos; viram cobrança com recibo perto do vencimento.
            </p>
        </Transition>
```

A linha prevista não tem ação principal nem menu, porque `acoes` é `{ principal: null, menu: [] }`. Também não tem
recibo nem faixa. O template atual já trata esses casos, então ele não muda.

- [ ] **Passo 7: build**

Rodar: `npm run build`
Esperado: termina sem erro.

- [ ] **Passo 8: commit**

```bash
git add resources/js/acoesCobranca.js resources/js/acoesCobranca.test.js resources/js/Components/Carimbo.vue resources/js/Pages/Cobrancas/Index.vue
git commit -m "feat: campo Até e cobranças previstas na tela de cobranças (T18.4)"
```

---

### Tarefa T18.5: relatórios

**Arquivos:**
- Modificar: `app/Services/Relatorios.php`. Acrescentar um construtor; mexer em `faturamento` e em `porProduto`, que
  ganha o parâmetro `$hoje`.
- Modificar: `app/Http/Controllers/RelatorioController.php:47`, que passa `$hoje` ao `porProduto`.
- Testes:
  - `tests/Feature/Relatorios/FaturamentoTest.php`: um teste novo e o acréscimo de `nao_emitido` às linhas exatas
    que já existem;
  - `tests/Feature/Relatorios/ReceitaTest.php`: um teste novo e a expectativa do teste antigo de produto.

**Interfaces:**
- Consome (T18.2): `PrevisaoDeCobrancas::entre`.
- Produz:
  - a coluna `nao_emitido` ("Ainda não emitido") no faturamento;
  - `Relatorios::porProduto(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje)`.

- [ ] **Passo 1: testes que falham**

No `FaturamentoTest`:
- acrescentar `use App\Models\Contrato;`;
- incluir `'nao_emitido' => 0` nas cinco asserções exatas das linhas 60-64. Os totais ficam com `'nao_emitido' => 0`;
- acrescentar o teste:

```php
    public function test_previsto_inclui_o_que_os_contratos_ainda_vao_emitir(): void
    {
        // Desde 21/09, dia 21, R$ 260,60, nada gerado (o relatório não gera): 21/09 já passou e fica de fora.
        Contrato::factory()->for($this->usuario)->for($this->cliente)->create();
        $this->cobranca('2026-11-10', 10000);

        $this->actingAs($this->usuario)->get('/relatorios/faturamento?de=2026-09&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->where('tabela.linhas.0', ['mes' => '09/2026', 'previsto' => 0, 'nao_emitido' => 0, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.linhas.1', ['mes' => '10/2026', 'previsto' => 26060, 'nao_emitido' => 26060, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.linhas.2', ['mes' => '11/2026', 'previsto' => 36060, 'nao_emitido' => 26060, 'recebido' => 0, 'inadimplencia' => 0])
                ->where('tabela.totais.nao_emitido', 52120)
                ->where('tabela.colunas.2.rotulo', 'Ainda não emitido'));
    }
```

No `ReceitaTest`, no `test_receita_por_produto_bate_com_o_total_pago`, trocar o `previsto` da Hospedagem de `30000`
para `50000` e comentar: `// + 2 previstas de 21/10 (contratos ativos, ainda não emitidas)`. Acrescentar:

```php
    public function test_previsto_por_produto_inclui_as_previstas(): void
    {
        $hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem']);
        Contrato::factory()->for($this->usuario)->create()->itens()->create([
            'produto_servico_id' => $hospedagem->id, 'quantidade' => '2.000', 'preco_unitario_centavos' => 5000, 'principal' => true, 'ordem' => 0,
        ]);
        Contrato::factory()->for($this->usuario)->create(); // sem itens, R$ 260,60

        $this->actingAs($this->usuario)->get('/relatorios/produtos?de=2026-11&ate=2026-11')
            ->assertInertia(fn (Assert $page) => $page
                ->has('tabela.linhas', 2)
                ->where('tabela.linhas.0', ['item' => 'Hospedagem', 'recebido' => 0, 'previsto' => 10000, 'contratos' => 1])
                ->where('tabela.linhas.1', ['item' => 'Sem item (cobranças anteriores ao catálogo)', 'recebido' => 0, 'previsto' => 26060, 'contratos' => 0]));
    }
```

- [ ] **Passo 2: rodar e ver falhar**

Rodar: `php artisan test --compact tests/Feature/Relatorios`
Esperado: FAIL. Falta `nao_emitido` e o previsto não inclui as previstas.

- [ ] **Passo 3: implementar**

Em `Relatorios`, logo depois da abertura da classe:

```php
    public function __construct(private PrevisaoDeCobrancas $previsao) {}
```

Em `faturamento`, depois do `->get([...])` das cobranças:

```php
        // O que os contratos ainda vão emitir, por mês (m/Y). Só depois de hoje: até hoje a geração já criou as de verdade.
        $naoEmitido = collect($this->previsao->entre($usuario, $de->max($hoje->addDay()), $fim))
            ->groupBy(fn (array $prevista) => substr($prevista['vencimento'], 3))
            ->map(fn (Collection $previstas) => $previstas->sum('valor_centavos'));
```

E a linha do mês passa a ser:

```php
            $previstoNaoEmitido = (int) ($naoEmitido[$mes->format('m/Y')] ?? 0);
            $linha = ['mes' => $mes->format('m/Y'), 'previsto' => $previstoNaoEmitido, 'nao_emitido' => $previstoNaoEmitido, 'recebido' => 0, 'inadimplencia' => 0];
```

Colunas e descrição:

```php
            'Mês a mês: previsto (vencimentos, incluindo o que os contratos ainda vão emitir), recebido (pagamentos do mês) e o que estava em atraso no fim do mês.',
            [
                self::coluna('mes', 'Mês', 'texto'),
                self::coluna('previsto', 'Previsto'),
                self::coluna('nao_emitido', 'Ainda não emitido'),
                self::coluna('recebido', 'Recebido'),
                self::coluna('inadimplencia', 'Em atraso no fim do mês', soma: false),
            ],
```

Em `porProduto`, a assinatura passa a ser
`porProduto(User $usuario, CarbonImmutable $de, CarbonImmutable $ate, CarbonImmutable $hoje): array`. Depois do
`$previsto = …->pluck('soma', 'produto');`:

```php
        // Previstas (só depois de hoje) somam no previsto pelos itens; contrato sem itens vai para "Sem item".
        $previstoSemItem = 0;
        foreach ($this->previsao->entre($usuario, $de->max($hoje->startOfDay()->addDay()), $fim) as $prevista) {
            if ($prevista['itens'] === []) {
                $previstoSemItem += $prevista['valor_centavos'];
            }
            foreach ($prevista['itens'] as $item) {
                $previsto[$item['produto_servico_id']] = (int) ($previsto[$item['produto_servico_id']] ?? 0) + $item['total_centavos'];
            }
        }
```

Somar `$previstoSemItem` ao `$semItemPrevisto`, assim:
`$semItemPrevisto = (int) $semItens()->…->sum('valor_centavos') + $previstoSemItem;`. Atualizar a descrição para:
`'Pelos itens das cobranças: recebido (pagas no período) e previsto (vencendo no período, incluindo o que os contratos ainda vão emitir), com os contratos ativos que usam cada item.'`.

No `RelatorioController`, trocar
`'produtos' => $relatorios->porProduto($usuario, $de, $ate),` por
`'produtos' => $relatorios->porProduto($usuario, $de, $ate, $hoje),`.

- [ ] **Passo 4: rodar e ver passar**

Rodar: `php artisan test --compact tests/Feature/Relatorios tests/Feature/PainelTest.php`
Esperado: todos passam.

- [ ] **Passo 5: suíte inteira, Pint e commit**

Rodar: `php artisan test --compact` e `node --test resources/js/*.test.js`
Esperado: tudo verde (314 + os testes novos; JS 23/23).

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Relatorios.php app/Http/Controllers/RelatorioController.php tests/Feature/Relatorios
git commit -m "feat: relatórios somam as cobranças previstas no previsto (T18.5)"
```

---

### Tarefa T18.6: documentação, revisão e validação

- [ ] **Passo 1: skill.** Em `.claude/skills/recibos/SKILL.md`, acrescentar um "Acerto" sobre a previsão de
  recebimentos. Ele deve registrar:
  - o que é calculado e o que não é gravado;
  - a regra comum `cobravel`;
  - a regra "só depois de hoje";
  - onde aparece: lista, faturamento e produto;
  - o teste de igualdade com o gerador;
  - as pegadinhas encontradas na execução.

  Atualizar este plano e a tabela da Fase 17 (a T17.16 deve considerar as previstas).
- [ ] **Passo 2: revisão final.** Um subagente revisor, no modelo mais capaz, lê o diff desde `319ea9c`. Ele recebe esta
  especificação, este plano e a seção "Pontos para a revisão final". Corrigir os achados Critical e Important com TDD.
- [ ] **Passo 3: conferência no navegador**, a 1280 e a 390 px:
  - o campo "Até";
  - a mensagem de erro;
  - o carimbo "Prevista";
  - a legenda;
  - o relatório de faturamento.

  **Pedir autorização antes:** entrar grava a sessão no banco e abrir `/cobrancas` roda a geração.
- [ ] **Passo 4: commit e push.** O push para `feat/modulo-cobranca-catalogo` tem autorização. Depois, pedir a
  validação do usuário.
