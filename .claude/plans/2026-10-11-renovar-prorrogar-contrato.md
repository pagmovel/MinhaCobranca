# Renovar contrato e prorrogar cobrança — Plano de implementação (T17.11)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permitir "renovar" um contrato com encerramento agendado (estender/limpar a data de fim e desfazer o que o
agendamento tinha fechado) e "prorrogar" o vencimento de qualquer cobrança em aberto, com aviso no Painel para
contratos perto do fim.

**Architecture:** Novo service `RenovadorDeContratos` (espelha `EncerradorDeContratos`: `plano()`/`renovar()`) mais um
método novo em `GeradorDeCobrancas` (`desfazerProporcional()`, contraparte de `ajustarProporcional()`). Duas rotas
novas de contrato (`GET /contratos/{id}/renovacao`, `POST /contratos/{id}/renovar`) e um componente Vue
(`RenovarContrato.vue`) espelhando `EncerramentoContrato.vue`. "Prorrogar" é um método novo em `CobrancaController`
(sem service dedicado, do tamanho de `cancelar()`). O aviso no Painel é um contador simples, igual ao de
"vencimentosEmDiaNaoUtil", com um campo novo e separado em `users`.

**Tech Stack:** Laravel 13 / PHP 8.4, PHPUnit, Inertia v3 + Vue 3, `node --test` para os módulos `.js` puros.

**Spec:** [docs/superpowers/specs/2026-10-11-renovar-prorrogar-contrato-design.md](../../docs/superpowers/specs/2026-10-11-renovar-prorrogar-contrato-design.md)

**Estado geral: testado, aguardando validação do usuário.** Implementado em `feat/renovar-prorrogar-contrato`
(5 tarefas, TDD) + revisão final (reviewer com contexto novo) que achou 1 Critical e 4 Important, todos
corrigidos num fix pass com teste próprio (commit `5a6d5e7`) — detalhes e rulings no ledger (apagado após a
revisão limpa, histórico fica no git). Mesclado em `main`.

**Extensões pós-aprovação (2026-10-12, decisões do usuário em conversa, fora das 5 tarefas originais):**
- Renovar avisa **e resolve sozinho** a cobrança "proporcional já enviada" (vira avulsa), exceto se a avulsa
  já foi paga — commit de código + doc na skill.
- Prorrogar limpa a `linha_digitavel` do boleto (mesmo motivo do ajuste proporcional) — commit de código + doc.
- "Reativar contrato" (formulário normal, para contrato já encerrado de fato) passou a também desfazer
  cancelamentos/truncamentos, com prévia própria (`GET /contratos/{contrato}/reativacao` +
  `ReativarContrato.vue`), igual ao "Renovar" dedicado — brainstorming bounded aprovado pelo usuário, TDD,
  commit `8a27262`.

Suíte depois de tudo: `php artisan test` 520/520, `node --test` 19/19, `npm run build` ok.

## Global Constraints

- pt-BR em todo código visível: comentários, mensagens de erro/sucesso, nomes de teste, auditoria.
- Rodar `vendor/bin/pint --dirty --format agent` depois de qualquer alteração em arquivo `.php`, antes do commit da tarefa.
- TDD: escrever o teste, rodar e ver falhar, implementar o mínimo, rodar e ver passar — nessa ordem, em cada tarefa.
- **PORTÃO-BANCO**: nenhuma migration é aplicada no banco de dev sem mostrar a conexão e o comando literal e esperar
  o "sim" explícito do usuário antes de rodar `php artisan migrate`. Isso NÃO se aplica a `php artisan test`/`vendor/bin/phpunit`,
  que usam a base de teste já declarada no projeto (`phpunit.xml`), refeita a cada execução — rodar a suíte não pede autorização.
- Smoke test de UI (quando o passo pedir checagem visual): usar o `browser-agent` instalado, nunca Playwright.
- Nenhuma dependência nova (composer/npm) sem aprovação explícita do usuário.
- Seguir os nomes/padrões já estabelecidos: `StatusCobranca::Pendente/Cancelada`, `Auditoria::registrar()`,
  `AnoValido` rule, `Dinheiro::formatar()`, `ItensDeVenda`, `LazilyRefreshDatabase`.

## Review Focus

- Contrato de outro usuário nas rotas `/contratos/{id}/renovacao` e `/renovar` → 404, nunca vaza dado nem altera nada
  (coberto na Tarefa 1).
- Renovar duas vezes em seguida: a segunda chamada já não tem encerramento agendado (`ultimo_periodo === 'inteiro'`)
  → mensagem de erro clara, nada é alterado de novo (coberto na Tarefa 1).
- Prorrogar com a mesma data do vencimento atual (não estritamente posterior) → recusado com 422/erro de validação,
  nunca silenciosamente aceito como "sem mudança" (coberto na Tarefa 3).
- Cobrança de outro usuário em `/cobrancas/{id}/prorrogar` → 404 (coberto na Tarefa 3).
- `dias_aviso_contrato` fora de 1–365 (`0`, `366`, vazio) → recusado na validação do perfil, nunca salvo (coberto na
  Tarefa 5).

---

## Task 1: `RenovadorDeContratos` + rotas de contrato

**Estado: testada** — 12/12 testes (8 do plano + 4 da revisão final: descrição/principal preservados ao
desfazer proporcional, fronteira cheia não mexida, fronteira `nao_cobrar` intacta não listada, nova
`data_fim` não reabre cobrança fora do novo intervalo). Commits `34217a1` (tarefa) e `5a6d5e7` (correções
da revisão). Falta validação do usuário.

**Files:**
- Modify: `app/Services/GeradorDeCobrancas.php` (novo método público `desfazerProporcional()`)
- Create: `app/Services/RenovadorDeContratos.php`
- Modify: `app/Http/Controllers/ContratoController.php` (métodos `renovacao()`, `renovar()`, `registrarRenovacao()`)
- Modify: `routes/web.php:59` (duas rotas novas)
- Test: `tests/Feature/RenovarContratoTest.php`

**Interfaces:**
- Consumes: `EncerradorDeContratos`/`ContratoController::encerrar()` já existentes (os testes reaproveitam o fluxo de
  `/contratos/{id}/encerrar` para colocar o contrato em estado de "encerramento agendado" antes de renovar).
- Produces: `RenovadorDeContratos::plano(Contrato $contrato): array` e
  `RenovadorDeContratos::renovar(Contrato $contrato, ?CarbonImmutable $novaData): void` — consumidos pela Tarefa 2
  (via `ContratoController`, que a Tarefa 2 não toca de novo) e pelas rotas `GET /contratos/{contrato}/renovacao`
  (JSON `{acoes: [{acao, periodo, valor}]}`) e `POST /contratos/{contrato}/renovar` (body `{data_fim: string|null}`).

- [x] **Step 1: Escrever o teste principal (RED)**

Criar `tests/Feature/RenovarContratoTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovarContratoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Cliente $cliente;

    private ProdutoServico $hospedagem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create(['dias_antecedencia' => 40]);
        $this->cliente = Cliente::factory()->for($this->usuario)->create();
        $this->hospedagem = ProdutoServico::factory()->for($this->usuario)->create(['nome' => 'Hospedagem de site']);
    }

    private function dadosDoContrato(array $extra = []): array
    {
        return array_merge([
            'cliente_id' => $this->cliente->id, 'descricao' => 'Domínio exemplo.com.br', 'periodicidade' => 'mensal',
            'modalidade' => 'prepago', 'dia_vencimento' => 20, 'data_inicio' => '2026-08-01', 'ativo' => true,
            'itens' => [['produto_servico_id' => $this->hospedagem->id, 'quantidade' => '1', 'preco' => '247,57', 'principal' => true]],
        ], $extra);
    }

    /** Contrato mensal pré-pago desde 01/08 com as cobranças geradas até hoje (ago, set, out e nov). */
    private function contratoComCobrancas(array $extra = []): Contrato
    {
        $this->actingAs($this->usuario)->post('/contratos', $this->dadosDoContrato($extra))->assertRedirect('/contratos');
        app(GeradorDeCobrancas::class)->gerarPara($this->usuario->fresh(), CarbonImmutable::today());

        return $this->usuario->contratos()->sole();
    }

    private function cobrancaDe(Contrato $contrato, string $inicio): Cobranca
    {
        return $contrato->cobrancas()->whereDate('periodo_inicio', $inicio)->with('recibo')->sole();
    }

    public function test_renovar_reabre_cancelamentos_e_devolve_a_cobranca_de_fronteira_ao_valor_cheio(): void
    {
        $contrato = $this->contratoComCobrancas();
        $numeroDeOutubro = $this->cobrancaDe($contrato, '2026-10-01')->recibo->numero;

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertRedirect();
        $outubro = $this->cobrancaDe($contrato, '2026-10-01');
        $this->assertSame(['2026-10-18', 14384], [$outubro->periodo_fim->toDateString(), $outubro->valor_centavos]);
        $this->assertSame(StatusCobranca::Cancelada, $this->cobrancaDe($contrato, '2026-11-01')->status);

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()
            ->assertJsonCount(2, 'acoes')
            ->assertJsonPath('acoes.0.acao', 'proporcional_cheio')
            ->assertJsonPath('acoes.1.acao', 'reabrir');

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertRedirect("/contratos/{$contrato->id}/edit")->assertSessionHas('sucesso', 'Contrato renovado.');

        $contrato->refresh();
        $this->assertSame([true, null, 'inteiro'], [$contrato->ativo, $contrato->data_fim, $contrato->ultimo_periodo]);

        $outubro->refresh();
        $this->assertSame(['2026-10-31', 24757, 'Domínio exemplo.com.br'], [
            $outubro->periodo_fim->toDateString(), $outubro->valor_centavos, $outubro->descricao,
        ]);
        $this->assertSame($numeroDeOutubro, $outubro->recibo->numero);
        $this->assertSame(24757, $outubro->recibo->snapshot['servico']['valor_centavos']);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-11-01')->status);
    }

    public function test_renovar_com_data_de_fim_nova(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'cancelar'])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-12-31'])->assertRedirect();

        $contrato->refresh();
        $this->assertSame(['2026-12-31', 'inteiro'], [$contrato->data_fim->toDateString(), $contrato->ultimo_periodo]);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-10-01')->status);
    }

    public function test_renovar_recusa_data_de_fim_hoje_ou_no_passado(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-10-15'])->assertSessionHasErrors('data_fim');
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => '2026-10-18'])->assertSessionHasErrors('data_fim');

        $this->assertSame('2026-10-18', $contrato->fresh()->data_fim->toDateString());
    }

    public function test_renovar_so_vale_para_contrato_com_encerramento_agendado(): void
    {
        $contrato = $this->contratoComCobrancas();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');

        // Fim hoje: encerra na hora (ativo passa a false), também não tem o que renovar.
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-15'])->assertRedirect();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');
    }

    public function test_renovar_duas_vezes_a_segunda_nao_tem_mais_encerramento_agendado(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])
            ->assertSessionHas('erro', 'Este contrato não tem encerramento agendado para renovar.');
    }

    public function test_renovacao_sem_cobranca_de_fronteira_materializada_nao_erra(): void
    {
        // Fim bem no futuro: com dias_antecedencia=40, a cobrança de fronteira ainda não foi gerada.
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2062-10-15'])->assertRedirect();

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()->assertJsonCount(0, 'acoes');
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();
        $contrato->refresh();
        $this->assertSame([true, null, 'inteiro'], [$contrato->ativo, $contrato->data_fim, $contrato->ultimo_periodo]);
    }

    public function test_contrato_de_outro_usuario_da_404(): void
    {
        $contrato = $this->contratoComCobrancas();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18'])->assertRedirect();

        $outro = User::factory()->create();
        $this->actingAs($outro);
        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertNotFound();
        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertNotFound();
        $this->assertSame('2026-10-18', $contrato->fresh()->data_fim->toDateString());
    }

    /** Caso difícil da spec (seção 1/5): proporcional já enviada vira avulsa — renovar não toca nela, só avisa. */
    public function test_renovar_nao_toca_automaticamente_a_cobranca_proporcional_ja_enviada(): void
    {
        $contrato = $this->contratoComCobrancas();
        $outubroOriginal = $this->cobrancaDe($contrato, '2026-10-01');
        $outubroOriginal->forceFill(['enviado_em' => now()])->save();

        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => '2026-10-18', 'em_curso' => 'proporcional'])->assertRedirect();
        $outubroOriginal->refresh();
        $this->assertSame(StatusCobranca::Cancelada, $outubroOriginal->status);
        $avulsa = $this->usuario->cobrancas()->whereNull('contrato_id')->sole();

        $this->getJson("/contratos/{$contrato->id}/renovacao")->assertOk()
            ->assertJsonCount(2, 'acoes')
            ->assertJsonPath('acoes.0.acao', 'aviso_avulsa')
            ->assertJsonPath('acoes.1.acao', 'reabrir');

        $this->post("/contratos/{$contrato->id}/renovar", ['data_fim' => ''])->assertRedirect();

        $this->assertSame(StatusCobranca::Cancelada, $outubroOriginal->fresh()->status);
        $this->assertSame(StatusCobranca::Pendente, $avulsa->fresh()->status);
        $this->assertSame(StatusCobranca::Pendente, $this->cobrancaDe($contrato, '2026-11-01')->status);
        $this->assertSame(['inteiro', null], [$contrato->fresh()->ultimo_periodo, $contrato->fresh()->data_fim]);
    }
}
```

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=RenovarContratoTest`
Expected: FAIL — rota `renovacao`/`renovar` não existe (404) ou classe `RenovadorDeContratos` não encontrada.

- [x] **Step 3: `GeradorDeCobrancas::desfazerProporcional()`**

Em `app/Services/GeradorDeCobrancas.php`, adicionar depois do método `ajustarProporcional()` (os imports
`App\Models\Contrato`, `App\Models\ContratoItem`, `App\Support\Periodo` já existem no arquivo):

```php
    /**
     * Desfaz `ajustarProporcional`: refaz os itens a partir do contrato (quantidade e preço cheios) e devolve o
     * período original. Usado ao renovar um contrato com encerramento agendado (T17.11). O recibo fica por conta
     * do chamador.
     */
    public function desfazerProporcional(Cobranca $cobranca, Contrato $contrato, Periodo $periodoCheio): void
    {
        $contrato->loadMissing('itens.produtoServico');

        $cobranca->itens()->delete();
        $cobranca->itens()->createMany($contrato->itens->map(fn (ContratoItem $item) => [
            'produto_servico_id' => $item->produto_servico_id,
            'nome' => $item->produtoServico->nome,
            'unidade' => $item->produtoServico->unidade,
            'quantidade' => $item->quantidade,
            'preco_unitario_centavos' => $item->preco_unitario_centavos,
            'total_centavos' => $item->total(),
        ])->all());

        $cobranca->update([
            'periodo_fim' => $periodoCheio->fim,
            'valor_centavos' => $contrato->itens->isEmpty() ? $contrato->valor_centavos : $cobranca->itens()->sum('total_centavos'),
            'descricao' => $contrato->descricao,
        ]);
    }
```

- [x] **Step 4: `app/Services/RenovadorDeContratos.php`**

```php
<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Support\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Desfaz um encerramento agendado (T17.11): reabre as cobranças que o agendamento tinha cancelado e devolve a
 * cobrança de fronteira ao valor cheio, deixando o contrato de novo com `ultimo_periodo = 'inteiro'` e uma nova
 * data de fim (ou sem fim).
 */
final class RenovadorDeContratos
{
    public function __construct(
        private CalculadoraDePeriodos $calculadora,
        private GeradorDeCobrancas $gerador,
        private EmissorDeRecibos $emissor,
    ) {}

    /**
     * O que volta ao renovar — mesma lista para a prévia e para a execução.
     *
     * @return list<array{acao: 'reabrir'|'proporcional_cheio'|'aviso_avulsa', cobranca: Cobranca}>
     */
    public function plano(Contrato $contrato): array
    {
        $fimAntigo = $contrato->data_fim;
        $acoes = [];

        $canceladasDepois = $contrato->cobrancas()
            ->where('status', StatusCobranca::Cancelada)
            ->whereDate('periodo_inicio', '>', $fimAntigo)
            ->get();
        foreach ($canceladasDepois as $cobranca) {
            $acoes[] = ['acao' => 'reabrir', 'cobranca' => $cobranca];
        }

        $fronteira = $this->cobrancaDeFronteira($contrato, $fimAntigo);

        if ($fronteira !== null) {
            $acoes[] = match (true) {
                // Sem proporcional: o período de fronteira foi só cancelado (não truncado) ao encerrar.
                $contrato->ultimo_periodo === 'nao_cobrar' => ['acao' => 'reabrir', 'cobranca' => $fronteira],
                // Proporcional e já enviada: o encerramento criou uma avulsa e cancelou esta. Só avisa.
                $fronteira->status === StatusCobranca::Cancelada => ['acao' => 'aviso_avulsa', 'cobranca' => $fronteira],
                default => ['acao' => 'proporcional_cheio', 'cobranca' => $fronteira],
            };
        }

        usort($acoes, fn (array $a, array $b) => $a['cobranca']->periodo_inicio <=> $b['cobranca']->periodo_inicio);

        return $acoes;
    }

    /** Tudo ou nada: reabre o que o agendamento tinha fechado e volta a gerar no ritmo normal. */
    public function renovar(Contrato $contrato, ?CarbonImmutable $novaData): void
    {
        DB::transaction(function () use ($contrato, $novaData) {
            foreach ($this->plano($contrato) as $acao) {
                match ($acao['acao']) {
                    'reabrir' => $acao['cobranca']->update(['status' => StatusCobranca::Pendente]),
                    'proporcional_cheio' => $this->desfazerProporcional($contrato, $acao['cobranca']),
                    'aviso_avulsa' => null, // fora do automático: a prévia só avisa (decisão do usuário, spec seção 5).
                };
            }

            $contrato->update(['data_fim' => $novaData, 'ultimo_periodo' => 'inteiro']);
        });
    }

    /** A cobrança (pendente ou cancelada) cujo período contém a data de fim antiga. */
    private function cobrancaDeFronteira(Contrato $contrato, CarbonImmutable $fimAntigo): ?Cobranca
    {
        return $contrato->cobrancas()
            ->whereIn('status', [StatusCobranca::Pendente, StatusCobranca::Cancelada])
            ->whereDate('periodo_inicio', '<=', $fimAntigo)
            ->whereDate('periodo_fim', '>=', $fimAntigo)
            ->with('itens')
            ->first();
    }

    private function desfazerProporcional(Contrato $contrato, Cobranca $cobranca): void
    {
        $periodo = $this->periodoCompletoDe($contrato, $cobranca->periodo_inicio);
        $this->gerador->desfazerProporcional($cobranca, $contrato, $periodo);
        $this->emissor->refazer($cobranca->recibo()->firstOrFail());
    }

    /** Período cheio (sem truncar pelo fim antigo) que começa em `$periodoInicio`, pela periodicidade do contrato. */
    private function periodoCompletoDe(Contrato $contrato, CarbonImmutable $periodoInicio): Periodo
    {
        $periodos = $this->calculadora->devidos(
            $contrato->data_inicio, null, $contrato->periodicidade, $contrato->dia_vencimento, 0,
            $periodoInicio->addYears(1), $contrato->modalidade,
        );

        return collect($periodos)->first(fn (Periodo $periodo) => $periodo->inicio->isSameDay($periodoInicio));
    }
}
```

- [x] **Step 5: Rotas**

Em `routes/web.php`, depois da linha `Route::post('/contratos/{contrato}/recomecar', [ContratoController::class, 'recomecar']);`:

```php
    Route::get('/contratos/{contrato}/renovacao', [ContratoController::class, 'renovacao']);
    Route::post('/contratos/{contrato}/renovar', [ContratoController::class, 'renovar']);
```

- [x] **Step 6: `ContratoController`**

Adicionar `use App\Services\RenovadorDeContratos;` aos imports. Adicionar os métodos (ao lado de `encerramento()`/`encerrar()`):

```php
    /** Prévia da renovação (JSON): o que volta a pendente/cheio, sem gravar nada. */
    public function renovacao(Request $request, int $contrato, RenovadorDeContratos $renovador): JsonResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);
        $podeRenovar = $registro->ativo && $registro->ultimo_periodo !== 'inteiro';

        return response()->json([
            'acoes' => array_map(fn (array $acao) => [
                'acao' => $acao['acao'],
                'periodo' => $acao['cobranca']->periodo_inicio->format('d/m/Y').' a '.$acao['cobranca']->periodo_fim->format('d/m/Y'),
                'valor' => Dinheiro::formatar($acao['cobranca']->valor_centavos),
            ], $podeRenovar ? $renovador->plano($registro) : []),
        ]);
    }

    public function renovar(Request $request, int $contrato, RenovadorDeContratos $renovador): RedirectResponse
    {
        $registro = $request->user()->contratos()->findOrFail($contrato);

        if (! $registro->ativo || $registro->ultimo_periodo === 'inteiro') {
            return back()->with('erro', 'Este contrato não tem encerramento agendado para renovar.');
        }

        $dados = $request->validate([
            'data_fim' => ['nullable', 'date_format:Y-m-d', new AnoValido, 'after:today'],
        ], [
            'data_fim.after' => 'A nova data de fim deve ser depois de hoje.',
        ]);
        $novaData = filled($dados['data_fim'] ?? null) ? CarbonImmutable::parse($dados['data_fim'])->startOfDay() : null;

        DB::transaction(function () use ($renovador, $registro, $novaData, $request) {
            $fimAntigo = $registro->data_fim;
            $renovador->renovar($registro, $novaData);
            $this->registrarRenovacao($request, $registro, $fimAntigo, $novaData);
        });

        return redirect("/contratos/{$registro->id}/edit")->with('sucesso', 'Contrato renovado.');
    }

    private function registrarRenovacao(Request $request, Contrato $contrato, CarbonImmutable $fimAntigo, ?CarbonImmutable $novaData): void
    {
        Auditoria::registrar($request, 'contrato.renovado', $contrato,
            'Renovado (era '.$fimAntigo->format('d/m/Y').'): '.($novaData !== null ? 'novo fim em '.$novaData->format('d/m/Y') : 'sem data de fim'),
            ['data_fim' => $fimAntigo->toDateString()], ['data_fim' => $novaData?->toDateString()]);
    }
```

- [x] **Step 7: Rodar e ver passar**

Run: `php artisan test --filter=RenovarContratoTest`
Expected: PASS (8/8).

- [x] **Step 8: Formatar e rodar a suíte completa**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test`
Expected: suíte inteira verde (nenhuma regressão em `EncerramentoContratoTest`, `ContratosTest`, etc.).

- [x] **Step 9: Commit**

```bash
git add app/Services/GeradorDeCobrancas.php app/Services/RenovadorDeContratos.php app/Http/Controllers/ContratoController.php routes/web.php tests/Feature/RenovarContratoTest.php
git commit -m "feat: renovar contrato com encerramento agendado (T17.11)"
```

---

## Task 2: `RenovarContrato.vue` + `Form.vue`

**Estado: testada** — smoke test com browser-agent (usuário smoke@recibos.test, dev); revisão final
corrigiu erro de validação invisível e falta de aviso de alterações não salvas (commit `5a6d5e7`).
Commit da tarefa: `24e5a90`. Falta validação do usuário.

**Files:**
- Create: `resources/js/Components/RenovarContrato.vue`
- Modify: `resources/js/Pages/Contratos/Form.vue:246-272`

**Interfaces:**
- Consumes: `GET /contratos/{contrato}/renovacao` e `POST /contratos/{contrato}/renovar` (Tarefa 1); a prop
  `contrato.encerramento_agendado` já existe (`ContratoController::formulario()` já envia esse campo, sem mudança).
- Produces: nenhuma interface nova para outras tarefas (ponta de UI, sem harness de teste `.vue` no projeto — ver
  `.claude/skills/recibos/SKILL.md`).

Esta tarefa não tem teste automatizado próprio (o projeto não tem harness para `.vue`); a verificação é o smoke test
do Step 3.

- [x] **Step 1: Criar `resources/js/Components/RenovarContrato.vue`**

```vue
<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    contratoId: { type: Number, required: true },
    processando: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'voltar']);

const novaData = ref('');
const previa = ref(null);
const carregando = ref(true);
const erro = ref('');

const minimo = (() => {
    const amanha = new Date();
    amanha.setDate(amanha.getDate() + 1);
    return amanha.toLocaleDateString('sv-SE');
})();

onMounted(async () => {
    try {
        const resposta = await fetch(`/contratos/${props.contratoId}/renovacao`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!resposta.ok) {
            erro.value = 'Não foi possível montar a prévia.';
            return;
        }
        previa.value = await resposta.json();
    } catch {
        erro.value = 'Não foi possível montar a prévia.';
    } finally {
        carregando.value = false;
    }
});

const TEXTOS = {
    reabrir: 'Volta a cobrar',
    proporcional_cheio: 'Volta ao valor cheio',
};

function oQueAcontece(acao) {
    return acao.acao === 'aviso_avulsa'
        ? 'Já foi enviada como proporcional: revise manualmente, isto não muda sozinho.'
        : TEXTOS[acao.acao];
}

const confirmar = () => emit('confirmar', { data_fim: novaData.value || null });
</script>

<template>
    <div class="aparecer grid gap-3 border border-carbono/40 bg-white p-4">
        <label class="grid max-w-xs gap-1 text-sm">
            <span class="font-estreita text-xs font-medium text-grafite/75">Nova data de fim (vazio = sem fim)</span>
            <input v-model="novaData" type="date" :min="minimo" class="entrada">
        </label>
        <p v-if="carregando" class="text-sm text-grafite/70">Montando a prévia…</p>
        <Transition name="surgir">
            <p v-if="erro" class="text-sm text-tinta-vermelha">{{ erro }}</p>
        </Transition>
        <template v-if="previa && !erro">
            <p v-if="!previa.acoes.length" class="text-sm text-grafite/80">Nenhuma cobrança para ajustar.</p>
            <ul v-else class="grid gap-1 text-sm">
                <li v-for="(acao, indice) in previa.acoes" :key="indice" :class="{ 'text-tinta-vermelha': acao.acao === 'aviso_avulsa' }">
                    {{ acao.periodo }} (R$ {{ acao.valor }}): {{ oQueAcontece(acao) }}
                </li>
            </ul>
        </template>
        <div class="mt-2 flex gap-3">
            <button type="button" class="botao" :disabled="processando || carregando" @click="confirmar">
                Confirmar renovação
            </button>
            <button type="button" class="botao-secundario" @click="emit('voltar')">Voltar</button>
        </div>
    </div>
</template>
```

- [x] **Step 2: Editar `resources/js/Pages/Contratos/Form.vue`**

Adicionar o import e os dois `ref`/função novos, perto de `encerrando`/`encerrar` (mesmo bloco de `<script setup>`):

```js
import RenovarContrato from '../../Components/RenovarContrato.vue';
```

```js
const renovando = ref(false);
const enviandoRenovacao = ref(false);
const renovar = (dados) => router.post(`/contratos/${props.contrato.id}/renovar`, dados, {
    preserveScroll: true,
    onStart: () => { enviandoRenovacao.value = true; },
    onFinish: () => { enviandoRenovacao.value = false; },
    onSuccess: () => { renovando.value = false; },
});
```

Substituir o bloco (linhas 246-272 no arquivo atual):

```vue
        <section v-if="contrato?.ativo && !mudaPeriodos" class="mt-10 grid max-w-3xl gap-3 border-t border-carbono/15 pt-6">
            <h2 class="text-lg font-semibold">Encerrar contrato</h2>
            <p v-if="contrato.encerramento_agendado" class="text-sm">
                Encerramento agendado para {{ dataBr(contrato.data_fim) }}: o contrato gera cobranças até lá.
            </p>
            <p v-else class="text-sm text-grafite/80">
                Para de gerar cobranças a partir da data de fim. Antes de confirmar, você vê o que acontece com cada cobrança.
            </p>
            <template v-if="contrato.encerramento_agendado" />
            <p v-else-if="form.isDirty" class="text-sm">
                Você mudou campos acima: salve o contrato antes de encerrar, para não perder as mudanças.
            </p>
            <div v-else-if="!encerrando">
                <button type="button" class="botao-perigo" @click="encerrando = true">Encerrar contrato…</button>
            </div>
            <EncerramentoContrato
                v-else
                :contrato-id="contrato.id"
                :fim="hoje < contrato.data_inicio ? contrato.data_inicio : hoje"
                :minimo="contrato.data_inicio"
                editavel
                rotulo-confirmar="Confirmar encerramento"
                :processando="enviandoEncerramento"
                @confirmar="encerrar"
                @voltar="encerrando = false"
            />
        </section>
```

por:

```vue
        <section v-if="contrato?.ativo && !mudaPeriodos && contrato.encerramento_agendado" class="mt-10 grid max-w-3xl gap-3 border-t border-carbono/15 pt-6">
            <h2 class="text-lg font-semibold">Renovar contrato</h2>
            <p class="text-sm">
                Encerramento agendado para {{ dataBr(contrato.data_fim) }}: o contrato gera cobranças até lá.
            </p>
            <div v-if="!renovando">
                <button type="button" class="botao-secundario" @click="renovando = true">Renovar contrato…</button>
            </div>
            <RenovarContrato
                v-else
                :contrato-id="contrato.id"
                :processando="enviandoRenovacao"
                @confirmar="renovar"
                @voltar="renovando = false"
            />
        </section>
        <section v-else-if="contrato?.ativo && !mudaPeriodos" class="mt-10 grid max-w-3xl gap-3 border-t border-carbono/15 pt-6">
            <h2 class="text-lg font-semibold">Encerrar contrato</h2>
            <p class="text-sm text-grafite/80">
                Para de gerar cobranças a partir da data de fim. Antes de confirmar, você vê o que acontece com cada cobrança.
            </p>
            <p v-if="form.isDirty" class="text-sm">
                Você mudou campos acima: salve o contrato antes de encerrar, para não perder as mudanças.
            </p>
            <div v-else-if="!encerrando">
                <button type="button" class="botao-perigo" @click="encerrando = true">Encerrar contrato…</button>
            </div>
            <EncerramentoContrato
                v-else
                :contrato-id="contrato.id"
                :fim="hoje < contrato.data_inicio ? contrato.data_inicio : hoje"
                :minimo="contrato.data_inicio"
                editavel
                rotulo-confirmar="Confirmar encerramento"
                :processando="enviandoEncerramento"
                @confirmar="encerrar"
                @voltar="encerrando = false"
            />
        </section>
```

- [x] **Step 3: Build e smoke test com o browser-agent**

Run: `npm run build`

Com o servidor local rodando, usar o `browser-agent` (nunca Playwright, regra do projeto) para: abrir um contrato que
já tenha `encerramento_agendado = true` (ou criar um e encerrar com data futura antes), confirmar que a seção
"Renovar contrato" aparece no lugar de "Encerrar contrato", que a prévia carrega, e que "Confirmar renovação" com o
campo de data vazio redireciona com a mensagem "Contrato renovado." Checar também o caso sem agendamento: a seção
"Encerrar contrato" continua aparecendo como antes.
Expected: os dois fluxos renderizam sem erro de console e sem quebrar o fluxo de "Encerrar" já existente.

- [x] **Step 4: Commit**

```bash
git add resources/js/Components/RenovarContrato.vue resources/js/Pages/Contratos/Form.vue
git commit -m "feat: tela de renovar contrato (T17.11)"
```

---

## Task 3: `CobrancaController::prorrogar()`

**Estado: testada** — 7/7 testes passando. Commit `92a7135`. Falta validação do usuário.

**Files:**
- Modify: `app/Http/Controllers/CobrancaController.php` (novo método `prorrogar()`)
- Modify: `routes/web.php:82` (nova rota)
- Test: `tests/Feature/Cobrancas/ProrrogarCobrancaTest.php`

**Interfaces:**
- Consumes: nada das tarefas anteriores (independente).
- Produces: `POST /cobrancas/{cobranca}/prorrogar` (body `{vencimento: 'Y-m-d'}`) — consumido pela Tarefa 4
  (`acoesCobranca.js`, que só decide se o botão aparece; o POST em si é feito por `resources/js/Pages/Cobrancas/Index.vue`
  ou equivalente, fora do escopo desta tarefa — ver nota no Step 4).

- [x] **Step 1: Escrever o teste (RED)**

Criar `tests/Feature/Cobrancas/ProrrogarCobrancaTest.php`:

```php
<?php

namespace Tests\Feature\Cobrancas;

use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProrrogarCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: User, 1: Cliente} */
    private function prestadorComCliente(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00:00'));
        $usuario = User::factory()->create();

        return [$usuario, Cliente::factory()->for($usuario)->create()];
    }

    private function cobrancaComRecibo(User $usuario, Cliente $cliente, array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create($extra);
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca->fresh('recibo');
    }

    public function test_prorroga_o_vencimento_e_refaz_o_recibo(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $numero = $cobranca->recibo->numero;

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');

        $cobranca->refresh();
        $this->assertSame('2026-10-20', $cobranca->vencimento->toDateString());
        $this->assertSame($numero, $cobranca->recibo->numero);
        $this->assertSame('2026-10-20', $cobranca->recibo->snapshot['servico']['vencimento']);
    }

    public function test_recusa_data_igual_ou_anterior_ao_vencimento_atual(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $this->actingAs($usuario);

        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-10'])->assertSessionHasErrors('vencimento');
        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-09'])->assertSessionHasErrors('vencimento');

        $this->assertSame('2026-10-10', $cobranca->fresh()->vencimento->toDateString());
    }

    public function test_so_pendente_pode_ser_prorrogada(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);
        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/pagar", ['pago_em' => '2026-10-09']);

        $this->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('erro', 'Só cobranças pendentes podem ter o vencimento prorrogado.');
    }

    public function test_vale_para_cobranca_de_contrato_enviada_ou_nao(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $contrato = Contrato::factory()->for($usuario)->for($cliente)->create();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, [
            'contrato_id' => $contrato->id, 'vencimento' => '2026-10-10', 'enviado_em' => now(),
        ]);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');
        $this->assertSame('2026-10-20', $cobranca->fresh()->vencimento->toDateString());
    }

    public function test_grava_auditoria_com_data_antes_e_depois(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = $this->cobrancaComRecibo($usuario, $cliente, ['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20']);

        $registro = $usuario->auditorias()->sole();
        $this->assertSame(
            ['cobranca.vencimento_prorrogado', 'Vencimento prorrogado de 10/10/2026 para 20/10/2026', ['vencimento' => '2026-10-10'], ['vencimento' => '2026-10-20']],
            [$registro->acao, $registro->resumo, $registro->antes, $registro->depois],
        );
    }

    public function test_cobranca_de_outro_usuario_da_404(): void
    {
        [$usuario] = $this->prestadorComCliente();
        $alheia = Cobranca::factory()->create(['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$alheia->id}/prorrogar", ['vencimento' => '2026-10-20'])->assertNotFound();
    }

    /** `Cobranca::factory()->create()` sem passar por `EmissorDeRecibos` não gera recibo — o caminho raro, mas previsto na spec. */
    public function test_prorroga_sem_recibo_nao_erra(): void
    {
        [$usuario, $cliente] = $this->prestadorComCliente();
        $cobranca = Cobranca::factory()->for($usuario)->for($cliente)->create(['vencimento' => '2026-10-10']);

        $this->actingAs($usuario)->post("/cobrancas/{$cobranca->id}/prorrogar", ['vencimento' => '2026-10-20'])
            ->assertSessionHas('sucesso', 'Vencimento prorrogado.');

        $this->assertSame('2026-10-20', $cobranca->fresh()->vencimento->toDateString());
    }
}
```

- [x] **Step 2: Rodar e ver falhar**

Run: `php artisan test --filter=ProrrogarCobrancaTest`
Expected: FAIL — rota `/cobrancas/{cobranca}/prorrogar` não existe (404 em todos os métodos).

- [x] **Step 3: Rota**

Em `routes/web.php`, depois de `Route::post('/cobrancas/{cobranca}/cancelar', [CobrancaController::class, 'cancelar']);`:

```php
    Route::post('/cobrancas/{cobranca}/prorrogar', [CobrancaController::class, 'prorrogar']);
```

- [x] **Step 4: `CobrancaController::prorrogar()`**

Adicionar ao lado de `cancelar()` (`EmissorDeRecibos` e `AnoValido` já estão importados no arquivo):

```php
    public function prorrogar(Request $request, int $cobranca, EmissorDeRecibos $emissor): RedirectResponse
    {
        $registro = $request->user()->cobrancas()->with('recibo')->findOrFail($cobranca);

        if ($registro->status !== StatusCobranca::Pendente) {
            return back()->with('erro', 'Só cobranças pendentes podem ter o vencimento prorrogado.');
        }

        $dados = $request->validate([
            'vencimento' => ['required', 'date_format:Y-m-d', new AnoValido, 'after:'.$registro->vencimento->toDateString()],
        ], [
            'vencimento.after' => 'A nova data deve ser depois do vencimento atual ('.$registro->vencimento->format('d/m/Y').').',
        ]);

        $antes = $registro->vencimento;

        DB::transaction(function () use ($registro, $dados, $antes, $emissor, $request) {
            $registro->update(['vencimento' => $dados['vencimento']]);
            Auditoria::registrar($request, 'cobranca.vencimento_prorrogado', $registro,
                'Vencimento prorrogado de '.$antes->format('d/m/Y').' para '.$registro->vencimento->format('d/m/Y'),
                ['vencimento' => $antes->toDateString()], ['vencimento' => $registro->vencimento->toDateString()]);

            if ($registro->recibo !== null) {
                $emissor->refazer($registro->recibo);
            }
        });

        return back()->with('sucesso', 'Vencimento prorrogado.');
    }
```

Nota: a cobrança avulsa/sem recibo (caso raro — todo fluxo de criação já emite recibo) não quebra por causa do guard
`$registro->recibo !== null`.

- [x] **Step 5: Rodar e ver passar**

Run: `php artisan test --filter=ProrrogarCobrancaTest`
Expected: PASS (7/7).

- [x] **Step 6: Formatar e rodar a suíte completa**

Run: `vendor/bin/pint --dirty --format agent`
Run: `php artisan test`
Expected: suíte inteira verde.

- [x] **Step 7: Commit**

```bash
git add app/Http/Controllers/CobrancaController.php routes/web.php tests/Feature/Cobrancas/ProrrogarCobrancaTest.php
git commit -m "feat: prorrogar vencimento de cobrança em aberto (T17.11)"
```

---

## Task 4: `acoesCobranca.js` — ação "Prorrogar"

**Estado: testada** — 12/12 testes (3 do plano + 3 antigos ajustados para incluir 'prorrogar'). A revisão
final achou que o menu chamava uma ação sem tela — corrigido na Tarefa 2/revisão (`ProrrogarInline.vue`,
commit `5a6d5e7`). Commit da tarefa: `e02c45f`. Falta validação do usuário.

**Files:**
- Modify: `resources/js/acoesCobranca.js`
- Test: `resources/js/acoesCobranca.test.js`

**Interfaces:**
- Consumes: `POST /cobrancas/{cobranca}/prorrogar` da Tarefa 3 (a chamada HTTP de fato fica a cargo da tela que
  renderiza o menu "⋯" — este módulo só decide ordem/rótulo; não há tela de formulário dedicada a abrir/fechar por
  este plano, igual às outras ações simples do menu como "cancelar").
- Produces: `'prorrogar'` passa a aparecer em `acoesDaCobranca(cobranca).menu` para cobrança em aberto (não
  prevista/paga/cancelada); `rotuloDaAcao('prorrogar', cobranca)` retorna `'Prorrogar vencimento'`.

- [x] **Step 1: Escrever os testes (RED)**

Em `resources/js/acoesCobranca.test.js`, adicionar (depois do teste `'rótulo de avisar'`):

```js
test('prorrogar aparece no menu de uma cobrança em aberto, enviada ou não', () => {
    assert.ok(acoesDaCobranca(cobranca()).menu.includes('prorrogar'));
    assert.ok(acoesDaCobranca(cobranca({ situacao: 'vencida', enviado: true, editavel: false })).menu.includes('prorrogar'));
});

test('prorrogar não aparece para paga, cancelada ou prevista', () => {
    assert.ok(!acoesDaCobranca(cobranca({ situacao: 'paga', enviado: true })).menu.includes('prorrogar'));
    assert.ok(!acoesDaCobranca(cobranca({ situacao: 'cancelada' })).menu.includes('prorrogar'));
    assert.deepEqual(acoesDaCobranca(cobranca({ situacao: 'prevista', recibo: null, editavel: false })), { principal: null, menu: [] });
});

test('rótulo de prorrogar', () => {
    assert.equal(rotuloDaAcao('prorrogar', cobranca()), 'Prorrogar vencimento');
});
```

- [x] **Step 2: Rodar e ver falhar**

Run: `node --test resources/js/acoesCobranca.test.js`
Expected: FAIL — `menu` não contém `'prorrogar'`; `rotuloDaAcao('prorrogar', ...)` retorna `undefined`.

- [x] **Step 3: Implementar**

Em `resources/js/acoesCobranca.js`, trocar a linha do `return` em `emOrdem()`:

```js
    return [...inicio, ...pdf, 'avisar', 'pagamento', ...(cobranca.editavel ? ['editar'] : []), 'cancelar'];
```

por:

```js
    return [...inicio, ...pdf, 'avisar', 'prorrogar', 'pagamento', ...(cobranca.editavel ? ['editar'] : []), 'cancelar'];
```

E adicionar ao objeto `ROTULOS`:

```js
    prorrogar: 'Prorrogar vencimento',
```

- [x] **Step 4: Rodar e ver passar**

Run: `node --test resources/js/acoesCobranca.test.js`
Expected: PASS, todos os testes do arquivo (os novos e os já existentes).

- [x] **Step 5: Commit**

```bash
git add resources/js/acoesCobranca.js resources/js/acoesCobranca.test.js
git commit -m "feat: ação Prorrogar vencimento no menu da cobrança (T17.11)"
```

---

## Task 5: Aviso de contrato perto do fim no Painel

**Estado: testada** — 9/9 PerfilTest + 7/7 PainelTest. Migration aplicada no banco de dev (PostgreSQL
207.180.205.211:1/recibos) com autorização do usuário. Commit `72fe0f3`. Falta validação do usuário.

**Files:**
- Create: migration `add_dias_aviso_contrato_to_users_table` (via `php artisan make:migration`)
- Modify: `app/Models/User.php` (fillable, cast, default)
- Modify: `app/Http/Requests/PerfilRequest.php` (regra de validação)
- Modify: `app/Http/Controllers/PerfilController.php` (`CAMPOS`)
- Modify: `resources/js/Pages/Perfil/Editar.vue` (campo novo)
- Modify: `app/Http/Controllers/PainelController.php` (`__invoke()`)
- Modify: `resources/js/Pages/Painel.vue` (prop + banner)
- Test: `tests/Feature/PerfilTest.php`, `tests/Feature/PainelTest.php`

**Interfaces:**
- Consumes: nada das tarefas anteriores (independente).
- Produces: `users.dias_aviso_contrato` (int, 1–365, padrão 30); `PainelController` expõe `contratosParaRenovar: int`.

- [x] **Step 1: Criar a migration**

Run: `php artisan make:migration add_dias_aviso_contrato_to_users_table --no-interaction`

Editar o arquivo gerado (`database/migrations/<timestamp>_add_dias_aviso_contrato_to_users_table.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_aviso_contrato')->default(30)->after('dias_aviso');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dias_aviso_contrato');
        });
    }
};
```

**⚠️ PORTÃO-BANCO:** `php artisan test`/`vendor/bin/phpunit` rodam essa migration automaticamente contra a base de
teste (não pede autorização — é a base de teste do projeto, recriada a cada execução). Mas para a coluna existir
também no banco de **desenvolvimento** (necessário para usar a tela manualmente), é preciso rodar
`php artisan migrate` contra esse banco — **antes disso**, mostrar ao usuário a conexão (`php artisan config:show
database.connections.<default>` ou equivalente), o comando literal (`php artisan migrate`), e esperar o "sim"
explícito. Não rodar por conta própria.

- [x] **Step 2: Escrever os testes (RED)**

Em `tests/Feature/PerfilTest.php`, adicionar `'dias_aviso_contrato' => 20,` dentro do `array_merge` de `dados()`
(ao lado de `'dias_aviso' => 8,`), e adicionar, depois de `test_dias_aviso_fora_de_1_a_30_e_recusado`:

```php
    public function test_dias_aviso_contrato_fora_de_1_a_365_e_recusado(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/perfil', $this->dados(['dias_aviso_contrato' => 0]))->assertSessionHasErrors('dias_aviso_contrato');
        $this->post('/perfil', $this->dados(['dias_aviso_contrato' => 366]))->assertSessionHasErrors('dias_aviso_contrato');

        $this->post('/perfil', $this->dados(['dias_aviso_contrato' => 45]))->assertSessionHas('sucesso');
        $this->assertSame(45, $usuario->fresh()->dias_aviso_contrato);
    }

    public function test_dias_aviso_contrato_tem_padrao_30_ao_criar_usuario(): void
    {
        $this->assertSame(30, User::factory()->create()->dias_aviso_contrato);
    }
```

Em `tests/Feature/PainelTest.php`, adicionar (precisa de `use App\Models\Cliente;` e `use App\Models\Contrato;`, já
importados no arquivo):

```php
    public function test_aviso_de_contrato_perto_do_fim(): void
    {
        $usuario = User::factory()->create(['dias_aviso_contrato' => 10]);
        $cliente = Cliente::factory()->for($usuario)->create();
        Contrato::factory()->for($usuario)->for($cliente)->create(['ativo' => true, 'data_fim' => '2026-10-15']);
        Contrato::factory()->for($usuario)->for($cliente)->create(['ativo' => true, 'data_fim' => '2026-12-01']); // fora do prazo
        Contrato::factory()->for($usuario)->for($cliente)->create(['ativo' => false, 'data_fim' => '2026-10-12']); // inativo
        Contrato::factory()->for($usuario)->for($cliente)->create(['ativo' => true, 'data_fim' => null]); // sem fim

        $this->actingAs($usuario)->get('/painel')
            ->assertInertia(fn (Assert $page) => $page->where('contratosParaRenovar', 1));
    }
```

- [x] **Step 3: Rodar e ver falhar**

Run: `php artisan test --filter=PerfilTest`
Expected: FAIL — coluna `dias_aviso_contrato` não existe / regra de validação ainda não aceita o campo.

Run: `php artisan test --filter=PainelTest`
Expected: FAIL — `contratosParaRenovar` ausente no Inertia response.

- [x] **Step 4: `User` model**

Em `app/Models/User.php`:
- Adicionar `'dias_aviso_contrato'` ao array do atributo `#[Fillable([...])]` (ao lado de `'dias_aviso'`).
- Adicionar `'dias_aviso_contrato' => 30` ao `protected $attributes`.
- Adicionar `'dias_aviso_contrato' => 'integer',` em `casts()`.

- [x] **Step 5: `PerfilRequest` e `PerfilController`**

Em `app/Http/Requests/PerfilRequest.php`, adicionar depois de `'dias_aviso' => ['required', 'integer', 'between:1,30'],`:

```php
            'dias_aviso_contrato' => ['required', 'integer', 'between:1,365'],
```

Em `app/Http/Controllers/PerfilController.php`, adicionar `'dias_aviso_contrato'` ao array `CAMPOS` (ao lado de
`'dias_aviso'`).

- [x] **Step 6: `Perfil/Editar.vue`**

Em `resources/js/Pages/Perfil/Editar.vue`, depois do `<Campo id="dias_aviso" ...>` existente:

```vue
                <Campo id="dias_aviso_contrato" rotulo="Avisar sobre fim de contrato com quantos dias de antecedência" :erro="form.errors.dias_aviso_contrato" class="sm:col-span-3">
                    <input id="dias_aviso_contrato" v-model="form.dias_aviso_contrato" type="number" min="1" max="365" class="entrada">
                </Campo>
```

- [x] **Step 7: `PainelController`**

Em `app/Http/Controllers/PainelController.php`, dentro de `__invoke()`, adicionar ao array passado para
`Inertia::render('Painel', [...])` (ao lado de `'vencimentosEmDiaNaoUtil' => ...`):

```php
            'contratosParaRenovar' => $usuario->contratos()->where('ativo', true)->whereNotNull('data_fim')
                ->whereBetween('data_fim', [$hoje, $hoje->addDays($usuario->dias_aviso_contrato)])->count(),
```

- [x] **Step 8: `Painel.vue`**

Em `resources/js/Pages/Painel.vue`, adicionar a prop (ao lado de `vencimentosEmDiaNaoUtil`):

```js
    contratosParaRenovar: { type: Number, required: true },
```

E o banner, depois do bloco `<p v-if="vencimentosEmDiaNaoUtil" ...>`:

```vue
        <p v-if="contratosParaRenovar" class="mt-4 border border-carbono bg-white px-4 py-3 text-sm">
            {{ contratosParaRenovar === 1 ? '1 contrato encerra' : `${contratosParaRenovar} contratos encerram` }}
            nos próximos dias.
            <Link href="/contratos" class="font-semibold text-carbono underline">Ver contratos</Link>
        </p>
```

- [x] **Step 9: Rodar e ver passar**

Run: `php artisan test --filter=PerfilTest`
Expected: PASS.

Run: `php artisan test --filter=PainelTest`
Expected: PASS.

- [x] **Step 10: Formatar, build e rodar a suíte completa**

Run: `vendor/bin/pint --dirty --format agent`
Run: `npm run build`
Run: `php artisan test`
Expected: suíte inteira verde.

- [x] **Step 11: Commit**

```bash
git add database/migrations app/Models/User.php app/Http/Requests/PerfilRequest.php app/Http/Controllers/PerfilController.php resources/js/Pages/Perfil/Editar.vue app/Http/Controllers/PainelController.php resources/js/Pages/Painel.vue tests/Feature/PerfilTest.php tests/Feature/PainelTest.php
git commit -m "feat: aviso no painel para contrato perto do fim (T17.11)"
```
