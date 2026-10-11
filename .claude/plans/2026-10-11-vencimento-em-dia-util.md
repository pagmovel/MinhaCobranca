# Fase 23 — Vencimento em dia útil — plano de implementação

> **Para quem executa:** SUB-SKILL OBRIGATÓRIA: superpowers:executing-plans (nativa, recomendada) ou
> superpowers:subagent-driven-development. Passos com checkbox (`- [ ]`). Módulo: **Cobrança**.

**Objetivo:** vencimento de contrato que cair em sábado, domingo ou feriado (nacional, Carnaval, Sexta-feira da Paixão ou
feriado local do prestador) vai para o primeiro dia útil seguinte. Feriados locais cadastrados no perfil, ajuste com
prévia das cobranças já geradas e aviso na cobrança avulsa.

**Arquitetura:** `App\Support\DiasUteis` (puro) sabe o calendário. `CalculadoraDePeriodos::comDiasUteis()` devolve uma
cópia da calculadora que passa o vencimento de `periodo()` pelo calendário; os três serviços que calculam vencimento
(gerador, previsão, encerrador) pedem essa cópia com os feriados do usuário (`User::diasUteis()`). Sem calendário, a
calculadora continua como antes (testes unitários antigos intactos). Ajuste em lote em `App\Services\AjusteDeVencimentos`
(prévia + execução pela mesma consulta), seção "Feriados" no perfil e aviso no formulário da cobrança.

**Stack:** Laravel 13, PHP 8.4, Inertia v3, Vue 3, PHPUnit (SQLite em memória), Tailwind.

**Spec:** `docs/superpowers/specs/2026-10-11-vencimento-em-dia-util-design.md` (aprovada, D1–D3 "CONCORDO").
Skill: `.claude/skills/recibos/SKILL.md` (ler inteira antes de executar).

## Restrições globais
- Tudo em pt-BR (código, mensagens, comentários, commits).
- Isolamento por relação do usuário + `findOrFail` (404). FormRequest com id na rota dá 404 no `authorize()` (padrão
  `ChavePixRequest`).
- Data vinda de formulário ou URL: `date_format:Y-m-d` (regra `date` aceita ano de 5 dígitos — pegadinha da Fase 20).
- O período (`periodo_inicio`/`periodo_fim`), o valor e o número do recibo **nunca** mudam; só o vencimento.
- Enviadas, pagas e canceladas **nunca** mudam no ajuste; avulsas ficam fora do ajuste em lote (D2).
- Boleto: ajusta mesmo assim e avisa "Tem boleto: confira a data no banco." (D3).
- Sem dependência nova; sem serviço externo (Páscoa calculada no código).
- Não mexer em `webiss-v1`/`danfse-v1` (o recibo só recebe o vencimento novo pelo snapshot).
- Teste-guarda `test_previsao_bate_com_o_que_o_gerador_cria` não pode ser apagado nem afrouxado.
- Escrita no banco de dev (migração, smoke que grava): **PORTÃO-BANCO**, um "sim" por comando.
- Antes de commitar PHP: `vendor/bin/pint --dirty --format agent`. Antes de commitar tela: `npm run build`.
- Plano + skill atualizados no commit de cada tarefa (memória "Documentação por tarefa").

## Foco da revisão (o que a spec implica e pode morder quem usa)
1. **Feriado emendado com fim de semana** (sexta 20/11/2026 + sábado + domingo) → segunda 23/11, não sábado nem 21/11.
   Testes: `DiasUteisTest::test_consciencia_negra_so_a_partir_de_2024` e `VencimentoEmDiaUtilTest` (T23.1, T23.2).
2. **29/02 "todo ano" num ano que não é bissexto** não pode escorregar para 01/03; **31/02** é recusado no cadastro.
   Testes: `DiasUteisTest::test_29_de_fevereiro_anual_so_nos_anos_bissextos`, `FeriadosTest` (T23.1, T23.3).
3. **Id mandado à mão no "Ajustar"** (enviada, paga, avulsa, de outro usuário) não muda nada. Teste:
   `AjusteDeVencimentosTest::test_ajusta_so_as_escolhidas_que_ainda_precisam_com_o_mesmo_numero_de_recibo` (T23.4).
4. **Prévia velha e clique duplo:** só ajusta o que ainda precisa; o segundo clique não move a data de novo. Teste:
   `AjusteDeVencimentosTest::test_feriado_local_novo_reaparece_na_previa_e_ajustar_de_novo_nao_muda_nada` (T23.4).
5. **Ano com 5 dígitos** no feriado de data única e na consulta do próximo dia útil → erro, nunca data errada. Testes:
   `FeriadosTest::test_recusa_dia_que_nao_existe_e_data_com_ano_de_cinco_digitos`,
   `VencimentoEmDiaUtilTest::test_proximo_dia_util_para_o_aviso_da_avulsa` (T23.3, T23.5).

## Perguntas (respondidas pelo usuário em 2026-10-11: **P1 e P2 = SIM** → T23.10 e T23.11)
- **P1.** Registrar o ajuste em lote na auditoria (`cobranca.vencimento_ajustado`, como `cobranca.editada`)? (Padrão: não;
  a spec não pede.)
- **P2.** Avisar no Painel quando houver cobranças em aberto vencendo em dia não útil, com link para o perfil? (Padrão:
  não; o bloco fica só na seção Feriados do perfil, como diz a spec.)

## Acompanhamento

Estados: `pendente` → `concluída` (código pronto) → `testada` (verificação rodou) → `validada` (usuário confirmou).

### Fase A — Regra no cálculo
| Tarefa | O quê | Estado |
|---|---|---|
| T23.1 | `DiasUteis` (calendário puro) + `CalculadoraDePeriodos::comDiasUteis` | **testada**: `DiasUteisTest` (8) e 2 da calculadora RED → GREEN; unitários 91/91 |
| T23.2 | Tabela `feriados` + `Feriado` + `User::diasUteis()`; gerador, previsão e encerrador com o calendário; 7 testes antigos com a data nova | **testada**: `VencimentoEmDiaUtilTest` (2) RED → GREEN; PHP 411/411. Migração **não** aplicada no dev (T23.7) |

### Fase B — Feriados locais e cobranças já geradas
| Tarefa | O quê | Estado |
|---|---|---|
| T23.3 | Cadastro de feriados locais (rotas, request, controller, props do perfil) | **testada**: `FeriadosTest` (3) RED → GREEN; com `PerfilTest`, 9/9 |
| T23.4 | Ajuste em lote com prévia (`AjusteDeVencimentos`) | **testada**: `AjusteDeVencimentosTest` (4) RED → GREEN; com feriados e perfil, 13/13 |
| T23.5 | `GET /dias-uteis/proximo` (aviso da avulsa) | **testada**: `test_proximo_dia_util_para_o_aviso_da_avulsa` RED (404) → GREEN; PHP 419/419 |

### Fase C — Telas, banco de dev e validação
| Tarefa | O quê | Estado |
|---|---|---|
| T23.6 | Seção "Feriados" no perfil + aviso no formulário da cobrança | **concluída**: build ok, JS 50/50, PHP 419/419; conferência visual na T23.7 |
| T23.7 | PORTÃO-BANCO da migração, conferência no navegador, validação do usuário | **migração aplicada** no dev (2026-10-11, "sim" do usuário; `207.180.205.211:1`, `recibos`, `recibos_app`, não é produção; 18 → 19 tabelas, 0 pendentes). **Conferência no navegador feita** (2026-10-11, "sim" do usuário, SMOKE id 2, browser-use no Chrome 9333; nada novo gravado: sessão reaproveitada, cobranças 9 → 9) — resultado no registro. **Falta: validação do usuário** |

### Fase D — pedidos do usuário depois da revisão final (2026-10-11)
Palavras do usuário: "Aplicar revisão final (revisor independente). Corrigir agora os problemas menores que foram deixados
para depois. As perguntas P1 (registrar o ajuste na auditoria) e P2 (aviso no Painel) passam para SIM." E, no meio da
execução: "Eu quero que toda requisição ajax seja sinalizada no frontend como um spinner bem visível para que o cliente
não pense que está tudo parado. Agora mesmo tive algumas interações que me deixaram achando que algo tinha quebrado."

| Tarefa | O quê | Estado |
|---|---|---|
| T23.8 | Indicador global de carregamento: todo `fetch` (embrulho único do `fetch` global — 6 componentes chamam `fetch` direto) e toda visita do Inertia (`router.on('start'/'finish')`) acendem um aviso fixo "Carregando…" com spinner | **testada**: `carregando.test.js` (3) RED → GREEN; JS 53/53, build ok. Conferência no navegador na T23.7 |
| T23.9 | Os 6 menores da revisão final (ano 0000; laço sem limite; aviso velho na avulsa; falha do "Ajustar" sem mensagem; acessibilidade; consultas por cobrança no "Ajustar") | **testada**: (1) `AnoValido` em todas as 18 regras de data/mês — `DatasComAnoInvalidoTest` com o conjunto "ano 0000" RED (5/5) → GREEN; (2) laço com limite de 366 passos (RED = memória esgotada) + limite de 100 feriados no cadastro; (3) `avisoDiaUtil.js` (3 testes node RED → GREEN); (4) erro do "Ajustar" na tela e lotes de 1000; (5) regiões `aria-live` fixas e "→" lido como "passa para"; (6) `refazerEmLote` — de 6 para no máximo 4 consultas por cobrança (RED 12 > 8). PHP 431/431, JS 56/56, build ok |
| T23.10 | P1: o ajuste em lote grava `cobranca.vencimento_ajustado` na auditoria (uma linha por cobrança, mesma transação) | **testada**: `test_ajuste_registra_cada_cobranca_na_auditoria` RED → GREEN |
| T23.11 | P2: aviso no Painel "N cobranças em aberto vencem em dia não útil" com o link que resolve (`/perfil#feriados`) | **testada**: `test_painel_avisa_quantas_cobrancas_em_aberto_vencem_em_dia_nao_util` RED → GREEN; PHP 432/432, JS 56/56, build ok. Rolagem até `#feriados` pelo link do Inertia: **[A VERIFICAR]** no navegador (T23.7) |

## Mapa de arquivos
| Arquivo | Responsabilidade |
|---|---|
| `app/Support/DiasUteis.php` (novo) | Calendário: fim de semana, nacionais, Carnaval, Paixão, Páscoa, lista local |
| `app/Services/CalculadoraDePeriodos.php` | `comDiasUteis()`; `periodo()` ajusta o vencimento |
| `database/migrations/*_create_feriados_table.php` (novo) | Tabela `feriados` |
| `app/Models/Feriado.php`, `database/factories/FeriadoFactory.php` (novos) | Feriado local; `quando()` e `paraCalendario()` |
| `app/Models/User.php` | `feriados()`, `diasUteis()` |
| `app/Services/GeradorDeCobrancas.php`, `PrevisaoDeCobrancas.php`, `EncerradorDeContratos.php` | Usam a calculadora com o calendário do usuário |
| `app/Http/Requests/FeriadoRequest.php`, `app/Http/Controllers/FeriadoController.php` (novos) | Cadastro, ajuste, próximo dia útil |
| `app/Services/AjusteDeVencimentos.php` (novo) | Prévia e execução do ajuste em lote |
| `app/Http/Controllers/PerfilController.php` | Props `feriados`, `feriadosNacionais`, `vencimentosParaAjustar` |
| `routes/web.php`, `app/Providers/AppServiceProvider.php` | Rotas; padrão numérico de `{feriado}` |
| `resources/js/Components/Feriados.vue` (novo), `Pages/Perfil/Editar.vue`, `Pages/Cobrancas/Form.vue` | Telas |
| Testes: `tests/Unit/DiasUteisTest.php`, `tests/Unit/CalculadoraDePeriodosTest.php`, `tests/Feature/VencimentoEmDiaUtilTest.php`, `tests/Feature/FeriadosTest.php`, `tests/Feature/AjusteDeVencimentosTest.php` | |

## Fato verificado ao escrever o plano (2026-10-11)
Experimento descartável (núcleo da T23.1 + ligação nos 3 serviços, sem feriados locais; desfeito com `git restore`):
suíte **392/399**. Os 7 que mudam são todos datas que agora caem em dia não útil — lista exata no passo 6 da T23.2. O
teste-guarda passou (o contrato dele vence em 21/11/2026, sábado: previsão e gerador ajustam igual).

---

### T23.1 — `DiasUteis` e calculadora com calendário

**Arquivos:** criar `app/Support/DiasUteis.php`, `tests/Unit/DiasUteisTest.php`; alterar
`app/Services/CalculadoraDePeriodos.php`, `tests/Unit/CalculadoraDePeriodosTest.php`.

**Interfaces produzidas:**
- `new DiasUteis(array $locais = [])` — `$locais`: `list<array{descricao: string, tipo: 'anual'|'corpus_christi'|'unico', dia: ?int, mes: ?int, data: ?string}>` (`data` em `Y-m-d`).
- `DiasUteis::ehDiaUtil(CarbonImmutable): bool`, `proximoDiaUtil(CarbonImmutable): CarbonImmutable` (a própria data se for útil),
  `motivo(CarbonImmutable): ?string`, `nacionais(int $ano): array<string, string>` (Y-m-d ⇒ nome, em ordem),
  `static pascoa(int $ano): CarbonImmutable`, `const DIAS_DA_SEMANA` (índice = `dayOfWeek`, 0 = domingo).
- `CalculadoraDePeriodos::comDiasUteis(DiasUteis): self` (cópia; a original não muda).

- [ ] **Passo 1: teste do calendário (falha)** — `tests/Unit/DiasUteisTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DiasUteisTest extends TestCase
{
    private function data(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data);
    }

    public function test_pascoa_de_anos_conhecidos(): void
    {
        $this->assertSame(
            ['2024-03-31', '2025-04-20', '2026-04-05', '2027-03-28'],
            array_map(fn (int $ano) => DiasUteis::pascoa($ano)->toDateString(), [2024, 2025, 2026, 2027]),
        );
    }

    public function test_fim_de_semana_e_feriado_nacional_em_sequencia(): void
    {
        $dias = new DiasUteis;

        $this->assertSame('sábado', $dias->motivo($this->data('2026-10-10')));
        $this->assertSame('domingo', $dias->motivo($this->data('2026-10-11')));
        $this->assertSame('Nossa Senhora Aparecida', $dias->motivo($this->data('2026-10-12')));
        // Sábado, domingo e o feriado da segunda: só na terça.
        $this->assertSame('2026-10-13', $dias->proximoDiaUtil($this->data('2026-10-10'))->toDateString());
        $this->assertSame('2026-10-14', $dias->proximoDiaUtil($this->data('2026-10-14'))->toDateString(), 'dia útil fica');
    }

    public function test_carnaval_e_sexta_feira_da_paixao_pela_pascoa(): void
    {
        $dias = new DiasUteis;

        $this->assertSame(['Carnaval', 'Carnaval'], [$dias->motivo($this->data('2026-02-16')), $dias->motivo($this->data('2026-02-17'))]);
        $this->assertSame(['Carnaval', 'Carnaval'], [$dias->motivo($this->data('2025-03-03')), $dias->motivo($this->data('2025-03-04'))]);
        $this->assertTrue($dias->ehDiaUtil($this->data('2026-02-18')), 'quarta-feira de cinzas é dia útil');
        $this->assertSame('Sexta-feira da Paixão', $dias->motivo($this->data('2026-04-03')));
        $this->assertSame('2026-04-06', $dias->proximoDiaUtil($this->data('2026-04-03'))->toDateString());
    }

    public function test_consciencia_negra_so_a_partir_de_2024(): void
    {
        $dias = new DiasUteis;

        $this->assertTrue($dias->ehDiaUtil($this->data('2023-11-20')));
        $this->assertSame('Dia Nacional de Zumbi e da Consciência Negra', $dias->motivo($this->data('2024-11-20')));
        // 2026: sexta feriado + fim de semana → segunda, 23/11.
        $this->assertSame('2026-11-23', $dias->proximoDiaUtil($this->data('2026-11-20'))->toDateString());
    }

    public function test_virada_de_ano(): void
    {
        $dias = new DiasUteis;

        $this->assertSame('Natal', $dias->motivo($this->data('2026-12-25')));
        // 01/01/2028 é sábado (e feriado): segunda, 03/01.
        $this->assertSame('2028-01-03', $dias->proximoDiaUtil($this->data('2028-01-01'))->toDateString());
    }

    public function test_lista_nacional_do_ano_para_a_tela(): void
    {
        $nacionais = (new DiasUteis)->nacionais(2026);

        $this->assertCount(12, $nacionais);
        $this->assertSame(['2026-01-01', 'Confraternização Universal'], [array_key_first($nacionais), $nacionais['2026-01-01']]);
        $this->assertSame('2026-12-25', array_key_last($nacionais));
    }

    public function test_feriados_locais_anual_corpus_christi_e_data_unica(): void
    {
        $dias = new DiasUteis([
            ['descricao' => 'Independência da Bahia', 'tipo' => 'anual', 'dia' => 2, 'mes' => 7, 'data' => null],
            ['descricao' => 'Corpus Christi', 'tipo' => 'corpus_christi', 'dia' => null, 'mes' => null, 'data' => null],
            ['descricao' => 'Ponto facultativo decretado', 'tipo' => 'unico', 'dia' => null, 'mes' => null, 'data' => '2026-08-13'],
            ['descricao' => 'Aniversário da cidade', 'tipo' => 'anual', 'dia' => 21, 'mes' => 4, 'data' => null],
        ]);

        $this->assertSame('Independência da Bahia', $dias->motivo($this->data('2026-07-02')));
        $this->assertSame('Independência da Bahia', $dias->motivo($this->data('2027-07-02')), 'todo ano');
        $this->assertSame('Corpus Christi', $dias->motivo($this->data('2026-06-04')));
        $this->assertSame('Corpus Christi', $dias->motivo($this->data('2025-06-19')));
        $this->assertSame('Ponto facultativo decretado', $dias->motivo($this->data('2026-08-13')));
        $this->assertTrue($dias->ehDiaUtil($this->data('2027-08-13')), 'data única não se repete');
        $this->assertSame('Tiradentes', $dias->motivo($this->data('2026-04-21')), 'no mesmo dia, vale o nome do nacional');
    }

    public function test_29_de_fevereiro_anual_so_nos_anos_bissextos(): void
    {
        $dias = new DiasUteis([['descricao' => 'Dia bissexto', 'tipo' => 'anual', 'dia' => 29, 'mes' => 2, 'data' => null]]);

        $this->assertSame('Dia bissexto', $dias->motivo($this->data('2028-02-29')));
        $this->assertTrue($dias->ehDiaUtil($this->data('2027-03-01')), 'não escorrega para 01/03');
    }
}
```

- [ ] **Passo 2:** `php artisan test --compact tests/Unit/DiasUteisTest.php` → FALHA ("Class App\Support\DiasUteis not found").

- [ ] **Passo 3: implementação** — `app/Support/DiasUteis.php`:

```php
<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Calendário de dias úteis do prestador (Fase 23): sábado, domingo, feriados nacionais, Carnaval, Sexta-feira da Paixão
 * e a lista local do usuário não são dias úteis. Puro: não lê banco nem relógio.
 */
final class DiasUteis
{
    public const DIAS_DA_SEMANA = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'];

    /** Lei 662/1949 (redação da Lei 10.607/2002) e Lei 6.802/1980. O 20/11 (Lei 14.759/2023) entra em `nacionais()`. */
    private const NACIONAIS = [
        '01-01' => 'Confraternização Universal',
        '04-21' => 'Tiradentes',
        '05-01' => 'Dia do Trabalho',
        '09-07' => 'Independência do Brasil',
        '10-12' => 'Nossa Senhora Aparecida',
        '11-02' => 'Finados',
        '11-15' => 'Proclamação da República',
        '12-25' => 'Natal',
    ];

    /** @var array<int, array<string, string>> por ano: data (Y-m-d) => nome do feriado */
    private array $calendario = [];

    /** @param list<array{descricao: string, tipo: string, dia: ?int, mes: ?int, data: ?string}> $locais */
    public function __construct(private readonly array $locais = []) {}

    public function ehDiaUtil(CarbonImmutable $data): bool
    {
        return $this->motivo($data) === null;
    }

    /** A própria data, se for dia útil; senão o primeiro dia útil depois dela. */
    public function proximoDiaUtil(CarbonImmutable $data): CarbonImmutable
    {
        $dia = $data->startOfDay();

        while (! $this->ehDiaUtil($dia)) {
            $dia = $dia->addDay();
        }

        return $dia;
    }

    /** Por que a data não é dia útil ("sábado", "Tiradentes", nome do feriado local); `null` se for. */
    public function motivo(CarbonImmutable $data): ?string
    {
        // Explícito (não `isWeekend()`): não depende da configuração global de fim de semana do Carbon.
        if (in_array($data->dayOfWeek, [0, 6], true)) {
            return self::DIAS_DA_SEMANA[$data->dayOfWeek];
        }

        // União: no mesmo dia, o nome do feriado nacional vence o da lista local.
        $this->calendario[$data->year] ??= $this->nacionais($data->year) + $this->locaisDoAno($data->year);

        return $this->calendario[$data->year][$data->toDateString()] ?? null;
    }

    /**
     * Nacionais, Carnaval e Sexta-feira da Paixão do ano, em ordem de data (a tela mostra só para leitura).
     *
     * @return array<string, string> data (Y-m-d) => nome
     */
    public function nacionais(int $ano): array
    {
        $pascoa = self::pascoa($ano);
        $feriados = [];

        foreach (self::NACIONAIS as $mesDia => $nome) {
            $feriados["{$ano}-{$mesDia}"] = $nome;
        }

        // Lei 14.759/2023, publicada em 22/12/2023: vale a partir de 2024.
        if ($ano >= 2024) {
            $feriados["{$ano}-11-20"] = 'Dia Nacional de Zumbi e da Consciência Negra';
        }

        // Não são feriados nacionais por lei; entram por decisão do usuário (bancos fechados). Spec da Fase 23, D1.
        $feriados[$pascoa->subDays(48)->toDateString()] = 'Carnaval';
        $feriados[$pascoa->subDays(47)->toDateString()] = 'Carnaval';
        $feriados[$pascoa->subDays(2)->toDateString()] = 'Sexta-feira da Paixão';

        ksort($feriados);

        return $feriados;
    }

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher, calendário gregoriano). */
    public static function pascoa(int $ano): CarbonImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = ($h + $l - 7 * $m + 114) % 31 + 1;

        return CarbonImmutable::create($ano, $mes, $dia)->startOfDay();
    }

    /** @return array<string, string> data (Y-m-d) => descrição */
    private function locaisDoAno(int $ano): array
    {
        $feriados = [];

        foreach ($this->locais as $local) {
            $data = match ($local['tipo']) {
                // 29/02 só existe em ano bissexto: nos outros, o feriado não acontece (não escorrega para 01/03).
                'anual' => checkdate((int) $local['mes'], (int) $local['dia'], $ano)
                    ? sprintf('%04d-%02d-%02d', $ano, $local['mes'], $local['dia'])
                    : null,
                'corpus_christi' => self::pascoa($ano)->addDays(60)->toDateString(),
                'unico' => str_starts_with((string) $local['data'], "{$ano}-") ? $local['data'] : null,
                default => null,
            };

            if ($data !== null) {
                $feriados[$data] ??= $local['descricao'];
            }
        }

        return $feriados;
    }
}
```

- [ ] **Passo 4:** `php artisan test --compact tests/Unit/DiasUteisTest.php` → PASSA (8 testes).

- [ ] **Passo 5: teste da calculadora (falha)** — acrescentar ao fim de `tests/Unit/CalculadoraDePeriodosTest.php` (e
  `use App\Support\DiasUteis;` no topo):

```php
    public function test_vencimento_em_dia_nao_util_vai_para_o_proximo_dia_util_e_o_periodo_nao_muda(): void
    {
        $calc = new CalculadoraDePeriodos;

        $p = $calc->comDiasUteis(new DiasUteis)->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 2);

        // 21/11/2026 é sábado (e 20/11, sexta, é feriado): segunda, 23/11.
        $this->assertSame(
            ['2026-11-21', '2026-12-20', '2026-11-23'],
            [$p->inicio->toDateString(), $p->fim->toDateString(), $p->vencimento->toDateString()],
        );
        $this->assertSame(
            '2026-11-21',
            $calc->periodo($this->data('2026-09-21'), Periodicidade::Mensal, 21, 2)->vencimento->toDateString(),
            'a calculadora original continua sem calendário',
        );
    }

    public function test_antecedencia_conta_do_vencimento_ajustado(): void
    {
        $devidos = (new CalculadoraDePeriodos)->comDiasUteis(new DiasUteis)->devidos(
            $this->data('2026-09-21'), null, Periodicidade::Mensal, 21, 0, $this->data('2026-11-21'),
        );

        $this->assertCount(2, $devidos, 'o de novembro vence em 23/11: em 21/11 ainda não é devido');
    }
```

- [ ] **Passo 6:** `php artisan test --compact tests/Unit/CalculadoraDePeriodosTest.php` → FALHA (`comDiasUteis` não existe).

- [ ] **Passo 7: implementação** — em `app/Services/CalculadoraDePeriodos.php`:
  - `use App\Support\DiasUteis;` junto dos outros `use`.
  - Logo depois de `private const LIMITE = 1200;`:

```php

    /** Sem calendário (padrão), o vencimento sai como sempre; com ele, vai para o próximo dia útil (Fase 23). */
    private ?DiasUteis $diasUteis = null;

    /** Cópia desta calculadora com o calendário do usuário; a original não muda. */
    public function comDiasUteis(DiasUteis $diasUteis): self
    {
        $copia = clone $this;
        $copia->diasUteis = $diasUteis;

        return $copia;
    }
```

  - Em `periodo()`, entre o cálculo de `$vencimento` e o `return`:

```php

        // Só o vencimento muda: início e fim do período (e o índice único do contrato) continuam.
        $vencimento = $this->diasUteis?->proximoDiaUtil($vencimento) ?? $vencimento;
```

  Não passar o calendário pelo construtor: o container do Laravel injetaria um `DiasUteis` vazio sozinho (ele é
  instanciável), e a calculadora passaria a ajustar sem os feriados locais.

- [ ] **Passo 8:** `php artisan test --compact tests/Unit` → PASSA (todos os unitários).
- [ ] **Passo 9:** `vendor/bin/pint --dirty --format agent`; atualizar este plano (T23.1 = testada + registro de
  execução) e a skill (Acertos, novo item "Fase 23": `DiasUteis` puro, motivo do fim de semana antes do feriado,
  `comDiasUteis` por cópia e o porquê de não ir no construtor). Commit:
  `feat: calendário de dias úteis e calculadora com vencimento no próximo dia útil (T23.1)`.

---

### T23.2 — Feriados do usuário no gerador, na previsão e no encerramento

**Arquivos:** criar a migração `create_feriados_table`, `app/Models/Feriado.php`, `database/factories/FeriadoFactory.php`,
`tests/Feature/VencimentoEmDiaUtilTest.php`; alterar `app/Models/User.php`, `app/Services/GeradorDeCobrancas.php`,
`app/Services/PrevisaoDeCobrancas.php`, `app/Services/EncerradorDeContratos.php` e os testes do passo 6.

**Interfaces:** consome `DiasUteis`, `comDiasUteis` (T23.1). Produz `User::feriados(): HasMany` (ordem por id),
`User::diasUteis(): DiasUteis`, `Feriado::quando(int $ano): string`, `Feriado::paraCalendario(): array`,
`Feriado::TIPOS`.

- [ ] **Passo 1: arquivos pelo Artisan** —
  `php artisan make:migration create_feriados_table --no-interaction` e
  `php artisan make:model Feriado --factory --no-interaction`. (Só cria arquivos; **não** rodar `migrate`: o banco de dev
  passa pelo PORTÃO-BANCO na T23.7. Os testes usam SQLite em memória.)

- [ ] **Passo 2: conteúdo** — migração:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('descricao', 60);
            $table->string('tipo', 20);
            $table->unsignedTinyInteger('dia')->nullable();
            $table->unsignedTinyInteger('mes')->nullable();
            $table->date('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
```

  `app/Models/Feriado.php`:

```php
<?php

namespace App\Models;

use App\Support\DiasUteis;
use Database\Factories\FeriadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feriado local do prestador (estadual ou municipal), que a regra do dia útil soma aos nacionais (Fase 23). */
#[Fillable(['descricao', 'tipo', 'dia', 'mes', 'data'])]
class Feriado extends Model
{
    /** @use HasFactory<FeriadoFactory> */
    use HasFactory;

    /** `anual`: dia/mês todo ano; `corpus_christi`: Páscoa + 60 dias; `unico`: uma data só. */
    public const TIPOS = ['anual', 'corpus_christi', 'unico'];

    private const MESES = [
        'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    protected function casts(): array
    {
        return ['dia' => 'integer', 'mes' => 'integer', 'data' => 'immutable_date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Como a tela mostra: "2 de julho, todo ano", "Corpus Christi (data móvel): 04/06 em 2026", "13/08/2026, só nessa data". */
    public function quando(int $ano): string
    {
        return match ($this->tipo) {
            'anual' => "{$this->dia} de ".self::MESES[$this->mes - 1].', todo ano',
            'corpus_christi' => 'Corpus Christi (data móvel): '.DiasUteis::pascoa($ano)->addDays(60)->format('d/m')." em {$ano}",
            default => $this->data->format('d/m/Y').', só nessa data',
        };
    }

    /** @return array{descricao: string, tipo: string, dia: ?int, mes: ?int, data: ?string} formato de `DiasUteis` */
    public function paraCalendario(): array
    {
        return [
            'descricao' => $this->descricao,
            'tipo' => $this->tipo,
            'dia' => $this->dia,
            'mes' => $this->mes,
            'data' => $this->data?->toDateString(),
        ];
    }
}
```

  `database/factories/FeriadoFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Feriado;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feriado>
 */
class FeriadoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'descricao' => 'Independência da Bahia',
            'tipo' => 'anual',
            'dia' => 2,
            'mes' => 7,
            'data' => null,
        ];
    }
}
```

  `app/Models/User.php` — `use App\Support\DiasUteis;` e, depois de `produtosServicos()`:

```php

    public function feriados(): HasMany
    {
        return $this->hasMany(Feriado::class)->orderBy('id');
    }

    /** Calendário do prestador: nacionais + a lista local dele (uma consulta). */
    public function diasUteis(): DiasUteis
    {
        return new DiasUteis($this->feriados()->get()->map(fn (Feriado $feriado) => $feriado->paraCalendario())->all());
    }
```

- [ ] **Passo 3: teste (falha)** — `tests/Feature/VencimentoEmDiaUtilTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Feriado;
use App\Models\User;
use App\Services\GeradorDeCobrancas;
use App\Services\PrevisaoDeCobrancas;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VencimentoEmDiaUtilTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_gerador_leva_o_vencimento_ao_proximo_dia_util_e_mantem_o_periodo(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-21 10:00:00'));
        $usuario = User::factory()->create(); // antecedência 10
        $contrato = Contrato::factory()->for($usuario)->create(); // desde 21/09, dia 21

        app(GeradorDeCobrancas::class)->gerarPara($usuario, CarbonImmutable::today());

        $novembro = $contrato->cobrancas()->whereDate('periodo_inicio', '2026-11-21')->with('recibo')->sole();
        $this->assertSame(
            ['2026-11-21', '2026-12-20', '2026-11-23'],
            [$novembro->periodo_inicio->toDateString(), $novembro->periodo_fim->toDateString(), $novembro->vencimento->toDateString()],
        );
        $this->assertSame('2026-11-23', $novembro->recibo->snapshot['servico']['vencimento']);
    }

    public function test_feriado_local_do_usuario_vale_para_a_previsao_e_o_de_outro_usuario_nao(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        Feriado::factory()->for($usuario)->create(['descricao' => 'Padroeira', 'dia' => 21, 'mes' => 12]);
        Feriado::factory()->create(['dia' => 21, 'mes' => 1]); // de outro usuário
        Contrato::factory()->for($usuario)->create();

        $previstas = app(PrevisaoDeCobrancas::class)->entre($usuario, CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2027-01-31'));

        // 21/12/2026 (segunda) é feriado local → terça, 22/12; 21/01/2027 (quinta) continua.
        $this->assertSame(['22/12/2026', '21/01/2027'], array_column($previstas, 'vencimento'));
    }
}
```

- [ ] **Passo 4:** `php artisan test --compact tests/Feature/VencimentoEmDiaUtilTest.php` → FALHA (vencimento `2026-11-21` e
  `21/12/2026`: os serviços ainda não usam o calendário).

- [ ] **Passo 5: ligar o calendário nos três serviços.**
  - `app/Services/GeradorDeCobrancas.php`, em `gerarPara()`, antes do `foreach ($contratos ...)`:
    `$calculadora = $this->calculadora->comDiasUteis($usuario->diasUteis());` e trocar
    `$devidos = $this->calculadora->devidos(` por `$devidos = $calculadora->devidos(`.
  - `app/Services/PrevisaoDeCobrancas.php`, em `entre()`, antes do `foreach ($contratos ...)`:
    `$calculadora = $this->calculadora->comDiasUteis($usuario->diasUteis());` e trocar
    `$periodos = $this->calculadora->entre(` por `$periodos = $calculadora->entre(`.
  - `app/Services/EncerradorDeContratos.php`, em `plano()`: trocar
    `$periodos = $this->calculadora->devidos(` por
    `$periodos = $this->calculadora->comDiasUteis($contrato->user->diasUteis())->devidos(`.
  - `ContratoController::retroativos` **não muda**: `anteriores()` só usa início e fim dos períodos.

- [ ] **Passo 6: testes antigos com a data nova** (lista exata do experimento; mudar só a data esperada e o comentário,
  nunca a regra):
  1. `tests/Feature/Cobrancas/PrevisaoDeCobrancasTest.php::test_contrato_sem_fim_preve_cada_vencimento_do_intervalo`:
     `'21/11/2026'` → `'23/11/2026'` (sábado; sexta 20/11 é feriado). Conferir as asserções seguintes do mesmo teste: o
     experimento parou na primeira falha.
  2. Mesmo arquivo, `test_pos_pago_vence_depois_do_periodo`: `'10/10/2026'` → `'13/10/2026'` (sábado, domingo e 12/10).
  3. `tests/Feature/Cobrancas/PrevisaoNaListaTest.php` — `test_previstas_entram_mescladas_por_vencimento`,
     `test_previstas_so_em_todas_a_vencer_e_pendentes` e `test_filtro_de_cliente_vale_para_as_previstas`:
     `'21/11/2026'` → `'23/11/2026'` (a ordem das linhas não muda).
  4. `tests/Feature/EncerramentoContratoTest.php::test_pos_pago_encerrado_hoje_emite_os_periodos_ate_o_fim_que_ainda_nao_sairam`:
     `'2026-10-25'` → `'2026-10-26'` (domingo) e o comentário "setembro (vence 25/10)" → "(vence 26/10; 25/10 é domingo)".
  5. Mesmo arquivo, `test_fim_no_futuro_agenda_o_encerramento_sem_emitir_nada_adiantado`: o `travelTo('2026-11-10 10:00:00')`
     antes de gerar dezembro → `'2026-11-11 10:00:00'`, e o comentário → "vence 20/12, domingo → 21/12; antecedência 40 →
     11/11". (Era erro "No query results": em 10/11 dezembro ainda não é devido.)

- [ ] **Passo 7:** `php artisan test --compact` (suíte inteira) → PASSA. Se aparecer falha fora da lista do passo 6, só é
  aceitável corrigir o teste quando a data esperada cai em dia não útil (conferir o dia da semana/feriado) — registrar no
  "Registro de execução"; qualquer outra falha é defeito: parar e investigar (superpowers:systematic-debugging).
  Atenção: `User::diasUteis()` acrescenta uma consulta em gerar/prever/encerrar — teste que conta consultas pode mudar
  (o de `periodo_inicio` da previsão não conta a de feriados).
- [ ] **Passo 8:** Pint; plano (T23.2 = testada; migração **não** aplicada no dev) e skill (Fase 23: os três pontos de
  ligação; `retroativos` não precisa; consulta a mais por geração). Commit:
  `feat: vencimento em dia útil no gerador, na previsão e no encerramento, com feriados do usuário (T23.2)`.

---

### T23.3 — Cadastro de feriados locais

**Arquivos:** criar `app/Http/Requests/FeriadoRequest.php`, `app/Http/Controllers/FeriadoController.php`,
`tests/Feature/FeriadosTest.php`; alterar `routes/web.php`, `app/Providers/AppServiceProvider.php`,
`app/Http/Controllers/PerfilController.php`.

**Interfaces:** consome `Feriado`, `User::feriados()`, `DiasUteis::nacionais()`. Produz rotas
`POST /perfil/feriados`, `PUT|DELETE /perfil/feriados/{feriado}`; props do perfil
`feriados: list<{id, descricao, tipo, dia: int|'', mes: int|'', data: string|'', quando}>` e
`feriadosNacionais: list<{data: 'd/m', descricao}>`.

- [ ] **Passo 1: teste (falha)** — `tests/Feature/FeriadosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Feriado;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeriadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cadastra_os_tres_tipos_e_lista_no_perfil(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/perfil/feriados', ['descricao' => 'Independência da Bahia', 'tipo' => 'anual', 'dia' => '2', 'mes' => '7'])
            ->assertSessionHas('sucesso', 'Feriado cadastrado.');
        // Campos de outro tipo enviados juntos não ficam gravados.
        $this->post('/perfil/feriados', ['descricao' => 'Corpus Christi', 'tipo' => 'corpus_christi', 'dia' => '9', 'data' => '2026-01-01'])
            ->assertSessionHas('sucesso');
        $this->post('/perfil/feriados', ['descricao' => 'Decreto', 'tipo' => 'unico', 'data' => '2026-08-13'])
            ->assertSessionHas('sucesso');

        $corpus = $usuario->feriados()->where('tipo', 'corpus_christi')->sole();
        $this->assertSame([null, null], [$corpus->dia, $corpus->data]);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('feriados', 3)
            ->where('feriados.0.quando', '2 de julho, todo ano')
            ->where('feriados.1.quando', 'Corpus Christi (data móvel): 04/06 em 2026')
            ->where('feriados.2.quando', '13/08/2026, só nessa data')
            ->where('feriados.2.data', '2026-08-13')
            ->has('feriadosNacionais', 12)
            ->where('feriadosNacionais.0', ['data' => '01/01', 'descricao' => 'Confraternização Universal']));
    }

    public function test_recusa_dia_que_nao_existe_e_data_com_ano_de_cinco_digitos(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'anual', 'dia' => '31', 'mes' => '2'])
            ->assertSessionHasErrors(['dia' => 'Este dia não existe no mês escolhido.']);
        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'unico', 'data' => '72026-08-01'])
            ->assertSessionHasErrors('data');
        $this->post('/perfil/feriados', ['descricao' => '', 'tipo' => 'anual'])
            ->assertSessionHasErrors(['descricao' => 'Informe o nome do feriado.', 'dia' => 'Informe o dia.', 'mes' => 'Escolha o mês.']);
        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'outro'])->assertSessionHasErrors('tipo');
        $this->post('/perfil/feriados', ['descricao' => 'Bissexto', 'tipo' => 'anual', 'dia' => '29', 'mes' => '2'])
            ->assertSessionHasNoErrors()->assertSessionHas('sucesso', 'Feriado cadastrado.');

        $this->assertSame(1, $usuario->feriados()->count());
    }

    public function test_edita_e_exclui_so_o_proprio_feriado(): void
    {
        $usuario = User::factory()->create();
        $meu = Feriado::factory()->for($usuario)->create();
        $alheio = Feriado::factory()->create();
        $this->actingAs($usuario);

        $this->put("/perfil/feriados/{$meu->id}", ['descricao' => 'Aniversário da cidade', 'tipo' => 'anual', 'dia' => '15', 'mes' => '9'])
            ->assertSessionHas('sucesso', 'Feriado atualizado.');
        $this->assertSame([15, 9], [$meu->fresh()->dia, $meu->fresh()->mes]);

        $this->put("/perfil/feriados/{$alheio->id}", ['descricao' => 'X', 'tipo' => 'anual', 'dia' => '1', 'mes' => '1'])->assertNotFound();
        $this->delete("/perfil/feriados/{$alheio->id}")->assertNotFound();
        $this->assertNotNull($alheio->fresh());

        $this->delete("/perfil/feriados/{$meu->id}")->assertSessionHas('sucesso', 'Feriado excluído.');
        $this->assertNull($meu->fresh());
    }
}
```

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/FeriadosTest.php` → FALHA (rota inexistente: 404/405).

- [ ] **Passo 3: request** — `app/Http/Requests/FeriadoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Feriado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FeriadoRequest extends FormRequest
{
    /** Feriado de outro usuário vira 404 antes da validação (padrão de `ChavePixRequest`). */
    public function authorize(): bool
    {
        $feriado = $this->route('feriado');

        abort_if($feriado !== null && ! $this->user()->feriados()->whereKey($feriado)->exists(), 404);

        return true;
    }

    /** Só ficam os campos do tipo escolhido: trocar de tipo não deixa resto gravado. */
    protected function prepareForValidation(): void
    {
        $tipo = $this->input('tipo');

        $this->merge([
            'dia' => $tipo === 'anual' ? $this->input('dia') : null,
            'mes' => $tipo === 'anual' ? $this->input('mes') : null,
            'data' => $tipo === 'unico' ? $this->input('data') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'max:60'],
            'tipo' => ['required', Rule::in(Feriado::TIPOS)],
            'dia' => ['nullable', 'required_if:tipo,anual', 'integer', 'between:1,31'],
            'mes' => ['nullable', 'required_if:tipo,anual', 'integer', 'between:1,12'],
            'data' => ['nullable', 'required_if:tipo,unico', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            // 2024 é bissexto: 29/02 passa (acontece nos anos bissextos); 31/02 e 31/04 não existem.
            if ($this->input('tipo') === 'anual' && ! $validator->errors()->hasAny(['dia', 'mes'])
                && ! checkdate((int) $this->input('mes'), (int) $this->input('dia'), 2024)) {
                $validator->errors()->add('dia', 'Este dia não existe no mês escolhido.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'descricao.required' => 'Informe o nome do feriado.',
            'dia.required_if' => 'Informe o dia.',
            'mes.required_if' => 'Escolha o mês.',
            'data.required_if' => 'Informe a data.',
        ];
    }
}
```

- [ ] **Passo 4: controller** — `app/Http/Controllers/FeriadoController.php` (a T23.4 e a T23.5 acrescentam métodos):

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeriadoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FeriadoController extends Controller
{
    public function store(FeriadoRequest $request): RedirectResponse
    {
        $request->user()->feriados()->create($request->validated());

        return back()->with('sucesso', 'Feriado cadastrado.');
    }

    public function update(FeriadoRequest $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->update($request->validated());

        return back()->with('sucesso', 'Feriado atualizado.');
    }

    public function destroy(Request $request, int $feriado): RedirectResponse
    {
        $request->user()->feriados()->findOrFail($feriado)->delete();

        return back()->with('sucesso', 'Feriado excluído.');
    }
}
```

- [ ] **Passo 5: rotas e padrão** — `routes/web.php`: `use App\Http\Controllers\FeriadoController;` e, depois da linha
  `Route::delete('/perfil/chaves-pix/{chave}', ...)`:

```php
    Route::post('/perfil/feriados', [FeriadoController::class, 'store']);
    Route::put('/perfil/feriados/{feriado}', [FeriadoController::class, 'update']);
    Route::delete('/perfil/feriados/{feriado}', [FeriadoController::class, 'destroy']);
```

  `app/Providers/AppServiceProvider.php`: acrescentar `'feriado'` à lista do `Route::pattern` (`[..., 'chave', 'produto', 'feriado']`).

- [ ] **Passo 6: props do perfil** — `app/Http/Controllers/PerfilController.php`: `use App\Models\Feriado;`,
  `use App\Support\DiasUteis;`, `use Carbon\CarbonImmutable;`; em `edit()`, `$ano = CarbonImmutable::today()->year;` e no
  array do `Inertia::render`, depois de `'tiposChavePix'`:

```php
            'feriados' => $usuario->feriados()->get()->map(fn (Feriado $feriado) => [
                'id' => $feriado->id,
                'descricao' => $feriado->descricao,
                'tipo' => $feriado->tipo,
                'dia' => $feriado->dia ?? '',
                'mes' => $feriado->mes ?? '',
                'data' => $feriado->data?->toDateString() ?? '',
                'quando' => $feriado->quando($ano),
            ]),
            'feriadosNacionais' => collect((new DiasUteis)->nacionais($ano))
                ->map(fn (string $nome, string $data) => ['data' => CarbonImmutable::parse($data)->format('d/m'), 'descricao' => $nome])
                ->values(),
```

- [ ] **Passo 7:** `php artisan test --compact tests/Feature/FeriadosTest.php tests/Feature/PerfilTest.php` → PASSA.
- [ ] **Passo 8:** Pint; plano e skill (Fase 23: `FeriadoRequest` limpa campos do outro tipo; 29/02 aceito, 31/02 recusado
  com ano bissexto de referência). Commit: `feat: cadastro de feriados locais no perfil (T23.3)`.

---

### T23.4 — Ajuste em lote das cobranças já geradas (prévia → confirmar)

**Arquivos:** criar `app/Services/AjusteDeVencimentos.php`, `tests/Feature/AjusteDeVencimentosTest.php`; alterar
`FeriadoController`, `routes/web.php`, `PerfilController`.

**Interfaces:** consome `User::diasUteis()`, `EmissorDeRecibos::refazer(Recibo)`. Produz
`AjusteDeVencimentos::previa(User): list<array{id: int, cliente: string, vencimento: string, motivo: string, novo_vencimento: string, tem_boleto: bool}>`,
`AjusteDeVencimentos::ajustar(User, list<int> $ids): int`, rota `POST /perfil/feriados/ajustar` (`ids[]`), prop do perfil
`vencimentosParaAjustar`.

- [ ] **Passo 1: teste (falha)** — `tests/Feature/AjusteDeVencimentosTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\User;
use App\Services\EmissorDeRecibos;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AjusteDeVencimentosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    private Contrato $contrato;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $this->usuario = User::factory()->create();
        $this->contrato = Contrato::factory()->for($this->usuario)->create();
    }

    /** Cobrança do contrato com recibo, gravada com o vencimento dado (como as geradas antes da regra nova). */
    private function cobranca(string $vencimento, array $extra = []): Cobranca
    {
        $cobranca = Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(array_merge([
            'contrato_id' => $this->contrato->id,
            'vencimento' => $vencimento,
            'periodo_inicio' => $vencimento,
            'periodo_fim' => CarbonImmutable::parse($vencimento)->addMonthNoOverflow()->subDay()->toDateString(),
        ], $extra));
        app(EmissorDeRecibos::class)->emitir($cobranca);

        return $cobranca;
    }

    public function test_previa_lista_so_as_de_contrato_pendentes_e_nao_enviadas_em_dia_nao_util(): void
    {
        $sabado = $this->cobranca('2026-10-24', ['forma_pagamento' => 'boleto', 'linha_digitavel' => str_repeat('1', 47)]);
        $this->cobranca('2026-10-26'); // segunda: dia útil
        $this->cobranca('2026-10-31')->forceFill(['enviado_em' => now()])->save(); // sábado, enviada
        $this->cobranca('2026-11-07')->forceFill(['status' => 'paga', 'pago_em' => '2026-10-15'])->save(); // sábado, paga
        $this->cobranca('2026-11-14')->forceFill(['status' => 'cancelada'])->save(); // sábado, cancelada
        Cobranca::factory()->for($this->usuario)->for($this->contrato->cliente)->create(['vencimento' => '2026-11-21']); // avulsa (D2)

        $this->actingAs($this->usuario)->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->has('vencimentosParaAjustar', 1)
            ->where('vencimentosParaAjustar.0', [
                'id' => $sabado->id,
                'cliente' => $this->contrato->cliente->exibicao(),
                'vencimento' => '24/10/2026',
                'motivo' => 'sábado',
                'novo_vencimento' => 'segunda, 26/10/2026',
                'tem_boleto' => true,
            ]));
    }

    public function test_ajusta_so_as_escolhidas_que_ainda_precisam_com_o_mesmo_numero_de_recibo(): void
    {
        $sabado = $this->cobranca('2026-10-24');
        $domingo = $this->cobranca('2026-11-01');
        $enviada = $this->cobranca('2026-10-31');
        $enviada->forceFill(['enviado_em' => now()])->save();
        $alheia = Cobranca::factory()->create(['vencimento' => '2026-10-24']);
        $numero = $sabado->recibo->numero;

        $this->actingAs($this->usuario)
            ->post('/perfil/feriados/ajustar', ['ids' => [$sabado->id, $enviada->id, $alheia->id]])
            ->assertSessionHas('sucesso', '1 cobrança ajustada.');

        $sabado = $sabado->fresh()->load('recibo');
        $this->assertSame(
            ['2026-10-26', '2026-10-26', $numero, '2026-10-24'],
            [$sabado->vencimento->toDateString(), $sabado->recibo->snapshot['servico']['vencimento'], $sabado->recibo->numero, $sabado->periodo_inicio->toDateString()],
        );
        $this->assertSame('2026-11-01', $domingo->fresh()->vencimento->toDateString(), 'fora da escolha: não muda');
        $this->assertSame('2026-10-31', $enviada->fresh()->vencimento->toDateString());
        $this->assertSame('2026-10-24', $alheia->fresh()->vencimento->toDateString());
    }

    public function test_feriado_local_novo_reaparece_na_previa_e_ajustar_de_novo_nao_muda_nada(): void
    {
        $quarta = $this->cobranca('2026-10-21');
        $this->actingAs($this->usuario)->get('/perfil')->assertInertia(fn (Assert $page) => $page->has('vencimentosParaAjustar', 0));

        $this->post('/perfil/feriados', ['descricao' => 'Padroeira', 'tipo' => 'anual', 'dia' => '21', 'mes' => '10']);
        $this->get('/perfil')->assertInertia(fn (Assert $page) => $page
            ->where('vencimentosParaAjustar.0.motivo', 'Padroeira')
            ->where('vencimentosParaAjustar.0.novo_vencimento', 'quinta, 22/10/2026'));

        $this->post('/perfil/feriados/ajustar', ['ids' => [$quarta->id]])->assertSessionHas('sucesso', '1 cobrança ajustada.');
        $this->post('/perfil/feriados/ajustar', ['ids' => [$quarta->id]])->assertSessionHas('sucesso', 'Nenhuma cobrança precisava de ajuste.');
        $this->assertSame('2026-10-22', $quarta->fresh()->vencimento->toDateString());
    }

    public function test_pedido_sem_ids_ou_com_id_nao_numerico_e_recusado(): void
    {
        $this->actingAs($this->usuario)->post('/perfil/feriados/ajustar', [])->assertSessionHasErrors('ids');
        $this->post('/perfil/feriados/ajustar', ['ids' => ['abc']])->assertSessionHasErrors('ids.0');
    }
}
```

- [ ] **Passo 2:** `php artisan test --compact tests/Feature/AjusteDeVencimentosTest.php` → FALHA (prop/rota inexistentes).

- [ ] **Passo 3: serviço** — `app/Services/AjusteDeVencimentos.php`:

```php
<?php

namespace App\Services;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use App\Models\User;
use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cobranças já geradas que vencem em dia não útil (Fase 23, spec seção 4): só as de contrato, pendentes e não enviadas
 * (avulsas ficam fora — D2; enviadas, pagas e canceladas nunca mudam). A prévia e a execução usam a mesma consulta.
 */
final class AjusteDeVencimentos
{
    public function __construct(private EmissorDeRecibos $emissor) {}

    /** @return list<array{id: int, cliente: string, vencimento: string, motivo: string, novo_vencimento: string, tem_boleto: bool}> */
    public function previa(User $usuario): array
    {
        return $this->aAjustar($usuario)->map(fn (array $linha) => [
            'id' => $linha['cobranca']->id,
            'cliente' => $linha['cobranca']->cliente->exibicao(),
            'vencimento' => $linha['cobranca']->vencimento->format('d/m/Y'),
            'motivo' => $linha['motivo'],
            'novo_vencimento' => DiasUteis::DIAS_DA_SEMANA[$linha['novo']->dayOfWeek].', '.$linha['novo']->format('d/m/Y'),
            // D3: o vencimento do boleto está dentro da linha digitável; o recibo muda, o boleto não.
            'tem_boleto' => filled($linha['cobranca']->linha_digitavel),
        ])->all();
    }

    /**
     * Ajusta, numa transação, as escolhidas na prévia que ainda precisam (a prévia pode ter ficado velha): vencimento novo
     * e recibo refeito com o mesmo número. Id alheio, enviado ou já ajustado é ignorado. Devolve quantas mudaram.
     *
     * @param  list<int>  $ids
     */
    public function ajustar(User $usuario, array $ids): int
    {
        return DB::transaction(function () use ($usuario, $ids) {
            $aAjustar = $this->aAjustar($usuario)->filter(fn (array $linha) => in_array($linha['cobranca']->id, $ids, true));

            foreach ($aAjustar as $linha) {
                $linha['cobranca']->update(['vencimento' => $linha['novo']]);

                if ($linha['cobranca']->recibo) {
                    $this->emissor->refazer($linha['cobranca']->recibo);
                }
            }

            return $aAjustar->count();
        });
    }

    /** @return Collection<int, array{cobranca: Cobranca, novo: CarbonImmutable, motivo: string}> */
    private function aAjustar(User $usuario): Collection
    {
        $diasUteis = $usuario->diasUteis();

        return $usuario->cobrancas()
            ->where('status', StatusCobranca::Pendente)
            ->whereNull('enviado_em')
            ->whereNotNull('contrato_id')
            ->with(['cliente:id,nome,apelido', 'recibo'])
            ->orderBy('vencimento')->orderBy('id')
            ->get()
            ->reject(fn (Cobranca $cobranca) => $diasUteis->ehDiaUtil($cobranca->vencimento))
            ->map(fn (Cobranca $cobranca) => [
                'cobranca' => $cobranca,
                'novo' => $diasUteis->proximoDiaUtil($cobranca->vencimento),
                'motivo' => $diasUteis->motivo($cobranca->vencimento),
            ])
            ->values();
    }
}
```

- [ ] **Passo 4: controller e rota** — em `FeriadoController`: `use App\Services\AjusteDeVencimentos;` e

```php
    public function ajustar(Request $request, AjusteDeVencimentos $ajuste): RedirectResponse
    {
        $dados = $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['integer']]);
        $quantas = $ajuste->ajustar($request->user(), array_map('intval', $dados['ids']));

        return back()->with('sucesso', match ($quantas) {
            0 => 'Nenhuma cobrança precisava de ajuste.',
            1 => '1 cobrança ajustada.',
            default => "{$quantas} cobranças ajustadas.",
        });
    }
```

  `routes/web.php`, **antes** das rotas `{feriado}` (para `ajustar` não ser lido como id):
  `Route::post('/perfil/feriados/ajustar', [FeriadoController::class, 'ajustar']);`
  (o padrão numérico de `{feriado}` já evita a colisão; a ordem só deixa isso explícito).

- [ ] **Passo 5: prop do perfil** — `PerfilController::edit(Request $request, AjusteDeVencimentos $ajuste)` (com
  `use App\Services\AjusteDeVencimentos;`) e, no array, `'vencimentosParaAjustar' => $ajuste->previa($usuario),`.
  Depois de cadastrar/excluir feriado, o `back()` recarrega o perfil e a prévia já vem atualizada (spec: "reaparece
  quando a pessoa cadastra um feriado local").

- [ ] **Passo 6:** `php artisan test --compact tests/Feature/AjusteDeVencimentosTest.php tests/Feature/FeriadosTest.php tests/Feature/PerfilTest.php` → PASSA.
- [ ] **Passo 7:** Pint; plano e skill (Fase 23: prévia e execução pela mesma consulta; ids da prévia filtrados pelo que
  ainda precisa = prévia velha e clique duplo seguros; boleto com aviso). Commit:
  `feat: ajuste com prévia das cobranças em aberto que vencem em dia não útil (T23.4)`.

---

### T23.5 — Próximo dia útil para o aviso da cobrança avulsa

**Arquivos:** alterar `FeriadoController`, `routes/web.php`, `tests/Feature/VencimentoEmDiaUtilTest.php`.

**Interfaces:** produz `GET /dias-uteis/proximo?data=Y-m-d` → `{util: bool, aviso: ?string, proximo: 'Y-m-d', proximo_rotulo: 'terça, 13/10'}`;
422 com ano inválido; 401 sem sessão. Só leitura.

- [ ] **Passo 1: teste (falha)** — acrescentar a `tests/Feature/VencimentoEmDiaUtilTest.php`:

```php
    public function test_proximo_dia_util_para_o_aviso_da_avulsa(): void
    {
        $usuario = User::factory()->create();
        Feriado::factory()->for($usuario)->create(['descricao' => 'Padroeira', 'dia' => 14, 'mes' => 10]);

        $this->getJson('/dias-uteis/proximo?data=2026-10-10')->assertUnauthorized();

        $this->actingAs($usuario);
        $this->getJson('/dias-uteis/proximo?data=2026-10-10')
            ->assertExactJson(['util' => false, 'aviso' => 'Cai num sábado.', 'proximo' => '2026-10-13', 'proximo_rotulo' => 'terça, 13/10']);
        $this->getJson('/dias-uteis/proximo?data=2026-10-14')
            ->assertJson(['util' => false, 'aviso' => 'É feriado: Padroeira.', 'proximo' => '2026-10-15']);
        $this->getJson('/dias-uteis/proximo?data=2026-10-15')
            ->assertJson(['util' => true, 'aviso' => null, 'proximo' => '2026-10-15']);
        $this->getJson('/dias-uteis/proximo?data=72026-10-15')->assertUnprocessable()->assertJsonValidationErrors('data');
    }
```

- [ ] **Passo 2:** `php artisan test --compact --filter=test_proximo_dia_util_para_o_aviso_da_avulsa` → FALHA (404).

- [ ] **Passo 3: implementação** — em `FeriadoController`: `use App\Support\DiasUteis;`,
  `use Carbon\CarbonImmutable;`, `use Illuminate\Http\JsonResponse;` e

```php
    /** Aviso da cobrança avulsa (spec seção 5): só leitura; a data digitada continua valendo. */
    public function proximoDiaUtil(Request $request): JsonResponse
    {
        $dados = $request->validate(['data' => ['required', 'date_format:Y-m-d']]);
        $data = CarbonImmutable::createFromFormat('Y-m-d', $dados['data'])->startOfDay();
        $diasUteis = $request->user()->diasUteis();
        $motivo = $diasUteis->motivo($data);
        $proximo = $diasUteis->proximoDiaUtil($data);

        return response()->json([
            'util' => $motivo === null,
            'aviso' => match (true) {
                $motivo === null => null,
                in_array($motivo, ['sábado', 'domingo'], true) => "Cai num {$motivo}.",
                default => "É feriado: {$motivo}.",
            },
            'proximo' => $proximo->toDateString(),
            'proximo_rotulo' => DiasUteis::DIAS_DA_SEMANA[$proximo->dayOfWeek].', '.$proximo->format('d/m'),
        ]);
    }
```

  `routes/web.php`, junto das rotas de feriados: `Route::get('/dias-uteis/proximo', [FeriadoController::class, 'proximoDiaUtil']);`

- [ ] **Passo 4:** `php artisan test --compact tests/Feature/VencimentoEmDiaUtilTest.php` → PASSA.
- [ ] **Passo 5:** suíte inteira `php artisan test --compact` → PASSA. Pint; plano e skill. Commit:
  `feat: consulta do próximo dia útil para o aviso da cobrança avulsa (T23.5)`.

---

### T23.6 — Telas: seção "Feriados" no perfil e aviso no formulário da cobrança

**Arquivos:** criar `resources/js/Components/Feriados.vue`; alterar `resources/js/Pages/Perfil/Editar.vue`,
`resources/js/Pages/Cobrancas/Form.vue`.

Padrões de usabilidade da skill aplicados: 2 (o aviso traz o botão que resolve), 5 (animações `surgir`/`lista`),
8 (edição na própria linha; excluir com `botao-perigo` e confirmação no segundo clique), 9 (prévia antes do lote),
10 (390 px: lista com `flex-wrap`, nada de tabela larga).

- [ ] **Passo 1:** `resources/js/Components/Feriados.vue`:

```vue
<script setup>
import { computed, nextTick, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Campo from './Campo.vue';

const props = defineProps({
    feriados: { type: Array, required: true },
    nacionais: { type: Array, required: true },
    vencimentos: { type: Array, required: true },
});

const tipos = [
    { valor: 'anual', rotulo: 'Todo ano no mesmo dia' },
    { valor: 'corpus_christi', rotulo: 'Corpus Christi (data móvel)' },
    { valor: 'unico', rotulo: 'Uma data só' },
];
const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

const editando = ref(null); // id do feriado em edição, 'novo' para inclusão
const confirmando = ref(null);
const ajustando = ref(false);
const form = useForm({ descricao: '', tipo: 'anual', dia: '', mes: '', data: '' });

/** O feriado novo entra como uma linha a mais no fim da lista: o formulário aparece onde a pessoa clicou. */
const linhas = computed(() => (editando.value === 'novo' ? [...props.feriados, { id: 'novo' }] : props.feriados));
const rotuloAjuste = computed(() => (props.vencimentos.length === 1 ? 'Ajustar 1 cobrança' : `Ajustar ${props.vencimentos.length} cobranças`));

async function abrir(feriado = null) {
    form.clearErrors();
    form.descricao = feriado?.descricao ?? '';
    form.tipo = feriado?.tipo ?? 'anual';
    form.dia = feriado?.dia ?? '';
    form.mes = feriado?.mes ?? '';
    form.data = feriado?.data ?? '';
    confirmando.value = null;
    editando.value = feriado ? feriado.id : 'novo';
    await nextTick();
    const campo = document.getElementById('feriado_descricao');
    campo?.scrollIntoView({ block: 'nearest' });
    campo?.focus();
}

function salvar() {
    const opcoes = { preserveScroll: true, onSuccess: () => { editando.value = null; } };
    if (editando.value === 'novo') {
        form.post('/perfil/feriados', opcoes);
    } else {
        form.put(`/perfil/feriados/${editando.value}`, opcoes);
    }
}

const excluir = (id) => router.delete(`/perfil/feriados/${id}`, {
    preserveScroll: true,
    onFinish: () => { confirmando.value = null; },
});

const ajustar = () => router.post('/perfil/feriados/ajustar', { ids: props.vencimentos.map((item) => item.id) }, {
    preserveScroll: true,
    onStart: () => { ajustando.value = true; },
    onFinish: () => { ajustando.value = false; },
});
</script>

<template>
    <section id="feriados" class="flex max-w-3xl flex-col gap-3" aria-labelledby="titulo-feriados">
        <h2 id="titulo-feriados" class="text-sm font-semibold">Feriados</h2>
        <p class="text-sm text-grafite/80">
            Vencimento de contrato que cair em sábado, domingo ou feriado vai para o primeiro dia útil seguinte. Os feriados
            nacionais, o Carnaval e a Sexta-feira da Paixão já contam; cadastre aqui os do seu estado e da sua cidade.
        </p>

        <Transition name="surgir">
            <div v-if="vencimentos.length" role="status" class="flex flex-col gap-3 border border-carbono/40 bg-white px-4 py-3">
                <p class="text-sm font-semibold">
                    {{ vencimentos.length === 1 ? '1 cobrança em aberto vence' : `${vencimentos.length} cobranças em aberto vencem` }} em dia não útil
                </p>
                <p class="text-sm text-grafite/80">
                    Ainda não foram enviadas. Ajustar muda o vencimento para o próximo dia útil e refaz o recibo com o mesmo número.
                </p>
                <TransitionGroup tag="ul" name="lista" class="relative divide-y divide-linha border border-linha">
                    <li v-for="item in vencimentos" :key="item.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-3 py-2 text-sm">
                        <span class="min-w-0 font-semibold break-words">{{ item.cliente }}</span>
                        <span class="tabular-nums">{{ item.vencimento }} ({{ item.motivo }}) → {{ item.novo_vencimento }}</span>
                        <span v-if="item.tem_boleto" class="w-full font-estreita text-xs font-semibold">Tem boleto: confira a data no banco.</span>
                    </li>
                </TransitionGroup>
                <div>
                    <button type="button" class="botao" :disabled="ajustando" @click="ajustar">{{ rotuloAjuste }}</button>
                </div>
            </div>
        </Transition>

        <TransitionGroup v-if="linhas.length" tag="ul" name="lista" class="relative divide-y divide-linha border border-linha bg-white">
            <li v-for="feriado in linhas" :key="feriado.id" :class="editando === feriado.id ? 'bg-papel' : ''">
                <form
                    v-if="editando === feriado.id"
                    class="grid gap-4 px-4 py-4 sm:grid-cols-6"
                    :aria-label="feriado.id === 'novo' ? 'Novo feriado' : `Editar feriado ${feriado.descricao}`"
                    @submit.prevent="salvar"
                    @keydown.esc="editando = null"
                >
                    <p class="text-sm font-semibold sm:col-span-6">{{ feriado.id === 'novo' ? 'Novo feriado' : 'Editando este feriado' }}</p>
                    <Campo id="feriado_descricao" rotulo="Nome do feriado" :erro="form.errors.descricao" class="sm:col-span-3">
                        <input id="feriado_descricao" v-model="form.descricao" maxlength="60" class="entrada" required>
                    </Campo>
                    <Campo id="feriado_tipo" rotulo="Quando" :erro="form.errors.tipo" class="sm:col-span-3">
                        <select id="feriado_tipo" v-model="form.tipo" class="entrada">
                            <option v-for="tipo in tipos" :key="tipo.valor" :value="tipo.valor">{{ tipo.rotulo }}</option>
                        </select>
                    </Campo>
                    <template v-if="form.tipo === 'anual'">
                        <Campo id="feriado_dia" rotulo="Dia" :erro="form.errors.dia" class="sm:col-span-2">
                            <input id="feriado_dia" v-model="form.dia" type="number" min="1" max="31" class="entrada" required>
                        </Campo>
                        <Campo id="feriado_mes" rotulo="Mês" :erro="form.errors.mes" class="sm:col-span-4">
                            <select id="feriado_mes" v-model="form.mes" class="entrada" required>
                                <option value="" disabled>Escolha o mês</option>
                                <option v-for="(nome, indice) in meses" :key="nome" :value="indice + 1">{{ nome }}</option>
                            </select>
                        </Campo>
                    </template>
                    <Campo v-else-if="form.tipo === 'unico'" id="feriado_data" rotulo="Data" :erro="form.errors.data" class="sm:col-span-3">
                        <input id="feriado_data" v-model="form.data" type="date" class="entrada" required>
                    </Campo>
                    <p v-else class="font-estreita text-xs text-grafite/70 sm:col-span-6">
                        A data muda todo ano (60 dias depois da Páscoa) e é calculada sozinha.
                    </p>
                    <div class="flex flex-wrap gap-2 sm:col-span-6">
                        <button class="botao" :disabled="form.processing">{{ feriado.id === 'novo' ? 'Cadastrar feriado' : 'Salvar feriado' }}</button>
                        <button type="button" class="botao-secundario" @click="editando = null">Cancelar</button>
                    </div>
                </form>

                <div v-else class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ feriado.descricao }}</p>
                        <p class="font-estreita text-sm text-grafite/80">{{ feriado.quando }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="botao-secundario" @click="abrir(feriado)">Editar</button>
                        <button v-if="confirmando !== feriado.id" type="button" class="botao-perigo" @click="confirmando = feriado.id">Excluir</button>
                        <template v-else>
                            <button type="button" class="botao-perigo-confirmar" @click="excluir(feriado.id)">Confirmar exclusão</button>
                            <button type="button" class="botao-secundario" @click="confirmando = null">Manter feriado</button>
                        </template>
                    </div>
                </div>
            </li>
        </TransitionGroup>
        <p v-else class="text-sm text-grafite/80">Nenhum feriado local cadastrado.</p>

        <div v-if="editando !== 'novo'">
            <button type="button" class="botao-secundario" @click="abrir()">Novo feriado</button>
        </div>

        <details class="text-sm">
            <summary class="cursor-pointer font-estreita text-grafite/80">Feriados nacionais deste ano (já contam, não precisa cadastrar)</summary>
            <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                <li v-for="item in nacionais" :key="item.data" class="tabular-nums">{{ item.data }} · {{ item.descricao }}</li>
            </ul>
        </details>
    </section>
</template>
```

- [ ] **Passo 2:** `resources/js/Pages/Perfil/Editar.vue`: `import Feriados from '../../Components/Feriados.vue';`; em
  `defineProps`, `feriados`, `feriadosNacionais`, `vencimentosParaAjustar` (`{ type: Array, required: true }`); depois de
  `<ChavesPix ... />`:
  `<Feriados class="mt-10" :feriados="feriados" :nacionais="feriadosNacionais" :vencimentos="vencimentosParaAjustar" />`.

- [ ] **Passo 3:** `resources/js/Pages/Cobrancas/Form.vue`:
  - imports: `import { ref, watch } from 'vue';` (no lugar de `import { ref } from 'vue';`),
    `import { buscarJson } from '../../http.js';`, `import { adiar } from '../../listaPaginada.js';`,
    `import { anoCompleto } from '../../filtroPeriodo.js';`
  - depois de `const form = useForm(...)`:

```js
// Vencimento em dia não útil (Fase 23): a data digitada vale; o aviso só sugere o próximo dia útil (regra no servidor).
const diaUtil = ref(null);
let ultimoPedido = 0;
const conferirVencimento = adiar(async () => {
    const pedido = ++ultimoPedido;
    const data = form.vencimento;
    if (!data || !anoCompleto(data)) {
        diaUtil.value = null;
        return;
    }
    const { dados } = await buscarJson(`/dias-uteis/proximo?data=${encodeURIComponent(data)}`);
    // Resposta atrasada de uma data já trocada é descartada; falha não mostra aviso (o formulário segue normal).
    if (pedido === ultimoPedido) {
        diaUtil.value = dados && !dados.util ? dados : null;
    }
});
watch(() => form.vencimento, conferirVencimento, { immediate: true });
```

  - no template, logo depois do `<Campo id="periodo_fim" ...>...</Campo>`:

```vue
            <Transition name="surgir">
                <p v-if="diaUtil" role="status" class="flex flex-wrap items-center gap-2 border border-carbono/40 bg-white px-4 py-2 text-sm sm:col-span-6">
                    <span>{{ diaUtil.aviso }} Próximo dia útil: {{ diaUtil.proximo_rotulo }}.</span>
                    <button type="button" class="botao-secundario" @click="form.vencimento = diaUtil.proximo">Usar {{ diaUtil.proximo_rotulo }}</button>
                </p>
            </Transition>
```

- [ ] **Passo 4:** `npm run build` → sem erro (parar se falhar). `node --test resources/js/*.test.js` → 50/50 (nada de JS
  puro mudou). `php artisan test --compact` → PASSA.
- [ ] **Passo 5:** plano (T23.6 = concluída; conferência visual na T23.7) e skill (Fase 23: onde ficam as telas; aviso
  com contador de pedido e `anoCompleto`). Commit: `feat: seção Feriados no perfil e aviso de dia não útil na cobrança (T23.6)`.

---

### T23.7 — PORTÃO-BANCO, conferência no navegador e validação

- [ ] **Passo 1: PORTÃO-BANCO da migração** (regra global + memória "Regras de banco do projeto"). Só leitura antes:
  `php artisan db:show` (mostrar host, porta, banco, usuário e dizer se é produção — o esperado é o dev
  `207.180.205.211:1`, banco `recibos`, mas **conferir na saída**, não supor) e `php artisan migrate:status` (a única
  pendente deve ser `create_feriados_table`; se houver outra, parar e avisar). Contagem: a tabela `feriados` ainda não
  existe (nada a apagar ou sobrescrever; migração só cria). Mostrar o comando literal
  `php artisan migrate --no-interaction` e **esperar o "sim"** (o "SIM" antecipado da spec não dispensa este portão).
  Recontar/reconferir o `migrate:status` em comando separado logo antes de rodar.
- [ ] **Passo 2: conferência no navegador (browser-use, Chrome de teste na porta 9333; nunca Playwright).** Sem gravar:
  seção Feriados no perfil (lista nacional, formulário na linha, os três tipos, 390 px sem rolagem horizontal) e o aviso
  na cobrança avulsa (digitar 10/10/2026 → "Cai num sábado. Próximo dia útil: terça, 13/10." → "Usar terça, 13/10" troca
  a data e o aviso some). **Cadastrar feriado e "Ajustar" gravam no banco: pedir autorização à parte, com conexão,
  contagem e o que muda**, e usar só o usuário SMOKE (id 2).
- [ ] **Passo 3:** registrar o resultado no "Registro de execução", atualizar o roteiro
  (`.claude/plans/2026-10-10-roteiro-modulos.md`, seção 0) e a skill; pedir a validação do usuário (T23.6/T23.7 →
  `validada` só com o "ok" dele).
- [ ] **Passo 4:** com todas as tarefas `testada`/`validada`: merge e push pela memória "Merge e push por fase" — **depois**
  da revisão final da branch inteira que o roteiro pede (item 1 do backlog).

## Registro de execução
- **T23.1:** código do plano sem mudança. **Correção no teste do plano:** `test_29_de_fevereiro_anual_so_nos_anos_bissextos`
  usava 29/02/2028, que é **terça de Carnaval** (Páscoa de 2028 em 16/04): o nome nacional vence o local (regra do próprio
  plano) e o teste falhou com "Carnaval". Trocado para 29/02/2036 (sexta; Páscoa em 13/04/2036). O código estava certo.
- **T23.2:** migração `2026_10_11_130417_create_feriados_table.php`. Os 7 testes antigos mudaram só a data (lista do passo
  6, exatamente a do experimento); nenhuma outra falha. As outras asserções do teste de previsão "sem fim" não mudaram
  (o 1º item é 21/10, quarta). O outro `travelTo('2026-11-10')` do `EncerramentoContratoTest` (linha ~215) é de outro
  teste e continua.
- **Revisão final (subagente opus, 2026-10-11, diff 0bca21d..2148a0f):** sem Critical. Os 5 itens do foco OK; nenhum
  chamador da calculadora ficou sem calendário.
  - **Important 1 — a tabela `feriados` não existe no dev** (o revisor conferiu por SELECT). Sem a migração, Painel,
    Cobranças, Perfil, relatórios e encerramento dão 500 no dev. É a T23.7: **não integrar antes do PORTÃO-BANCO.**
  - **Important 2 — corrigido (`5a0116c`):** o teste do "Ajustar" usava uma cobrança alheia **avulsa** (a factory grava
    `contrato_id` nulo), barrada pelo filtro de contrato e não pelo isolamento. Novo teste
    `test_cobranca_de_contrato_de_outro_usuario_nao_aparece_na_previa_nem_muda`: RED com a consulta trocada por
    `Cobranca::query()` (mutação temporária, desfeita), GREEN no código real; PHP 420/420.
  - **Minor adiados:** (1) ano 0000 no feriado de data única passa no `date_format` e dá 500 no PostgreSQL (o mesmo buraco
    já existe em `CobrancaRequest`/`ContratoRequest`); (2) `proximoDiaUtil` sem limite de laço se todos os dias virarem
    feriado local; (3) aviso da avulsa continua visível nos 400 ms depois de trocar a data — clicar "Usar…" nesse
    intervalo troca a data nova pela sugerida da antiga; (4) falha do "Ajustar" (mais de 1000 ids) não aparece na tela;
    (5) `role="status"` em bloco que nasce preenchido e a seta "→" lida pelo leitor de tela; (6) "Ajustar" faz 3–4
    consultas por cobrança (`refazer`), aceitável numa ação pontual. **Todos corrigidos na T23.9** (pedido do usuário).
- **Conferência no navegador (T23.7, 2026-10-11, SMOKE id 2, `http://recibos.test`, prints em scratchpad `smoke23/`):**
  - Spinner: pílula "Carregando…" fixa no topo durante um pedido real de 5,1 s; some no fim. `fetch` embrulhado.
  - Painel: "3 cobranças em aberto vencem em sábado, domingo ou feriado. Ver e ajustar" (avulsa de 10/10 corretamente
    fora). O link leva a `/perfil#feriados` e **a página rola até a seção** (era [A VERIFICAR]).
  - Perfil → Feriados: prévia com motivo e data nova (20/09 domingo → segunda 21/09; 10/10 sábado → terça 13/10, pulando
    o feriado de 12/10), "Ajustar 3 cobranças", 12 nacionais; formulário na linha com foco no nome, campos certos por
    tipo, Esc fecha; 390 px sem rolagem horizontal. Nada cadastrado nem ajustado.
  - Avulsa: 10/10 → "Cai num sábado. Próximo dia útil: terça, 13/10." + "Usar terça, 13/10" (troca para 13/10 e o
    aviso some); região viva com a frase. **Achado:** depois de trocar a data, o aviso velho ficava 180 ms na tela
    (animação de saída `surgir`) **ainda clicável**. Corrigido no CSS: `.surgir/.lista/.modal-leave-active { pointer-events:
    none }`; conferido no navegador (`pe=none` durante a saída).
- **Segunda revisão (subagente opus, 2148a0f..ae3acf5):** sem Critical.
  - **Important corrigido:** `AnoValido` com valor em lista (`?mes[]=x`) dava 500 ("Array to string conversion"; antes
    era 422). Agora só olha texto que começa com 4 dígitos; o formato fica com o `date_format`. Teste
    `test_data_mandada_como_lista_e_recusada_sem_erro_500` RED (500) → GREEN; PHP 433/433.
  - **Minor (aguardam decisão do usuário):** (1) erro de rede/419 no "Ajustar" não aparece ao lado do botão (`onError`
    só cobre 422; lacuna geral do projeto); (2) piso de zero só no lado do Inertia do contador do spinner; (3) leitor de
    tela anuncia "Carregando…" a cada pedido e a pílula pisca em pedido rápido (sugestão: atrasar ~250 ms); (4) o Painel
    monta a prévia inteira só para contar; (5) `refazerEmLote` não confere que o lote é de um usuário só; (6) limite de
    100 feriados sem trava contra dois cliques simultâneos (inofensivo).
