<?php

namespace Tests\Feature;

use App\Enums\StatusCobranca;
use App\Models\Cliente;
use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\ProdutoServico;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * O campo de data do Chrome deixa digitar até 6 dígitos no ano, e a regra "date" lia 72026-08-01 como 2006-08-01:
 * um erro de digitação virava outra data gravada, sem aviso. O ano 0000 passa no `date_format` e o PostgreSQL recusa
 * a data (erro 500; o SQLite dos testes aceita). Todo formulário e filtro que recebe data recusa os dois.
 */
class DatasComAnoInvalidoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 10:00:00'));
        $this->usuario = User::factory()->comPerfilPronto()->create();
        Cliente::factory()->for($this->usuario)->create();
        ProdutoServico::factory()->for($this->usuario)->create();
        $this->actingAs($this->usuario);
    }

    /** @return array<string, array{string, string}> data (Y-m-d) e mês (Y-m) inválidos */
    public static function anosInvalidos(): array
    {
        return [
            'ano com 5 dígitos' => ['72026-08-01', '72026-08'],
            'ano 0000' => ['0000-08-01', '0000-08'],
        ];
    }

    /** @return array<string, array{string}> data (Y-m-d) inválida — só o que os testes abaixo usam. */
    public static function datasInvalidas(): array
    {
        return [
            'ano com 5 dígitos' => ['72026-08-01'],
            'ano 0000' => ['0000-08-01'],
        ];
    }

    #[DataProvider('datasInvalidas')]
    public function test_baixa_e_correcao_do_pagamento(string $data): void
    {
        $pendente = Cobranca::factory()->for($this->usuario)->create();
        $this->post("/cobrancas/{$pendente->id}/pagar", ['pago_em' => $data])->assertSessionHasErrors('pago_em');
        $this->assertSame(StatusCobranca::Pendente, $pendente->fresh()->status);

        $paga = Cobranca::factory()->for($this->usuario)->create();
        $paga->forceFill(['status' => StatusCobranca::Paga, 'pago_em' => '2026-10-01'])->save();
        $this->post("/cobrancas/{$paga->id}/corrigir-pagamento", ['pago_em' => $data, 'senha' => 'password', 'motivo' => 'x'])
            ->assertSessionHasErrors('pago_em');
        $this->assertSame('2026-10-01', $paga->fresh()->pago_em->toDateString());
    }

    #[DataProvider('datasInvalidas')]
    public function test_cobranca_avulsa(string $data): void
    {
        $this->post('/cobrancas', ['periodo_inicio' => $data, 'periodo_fim' => $data, 'vencimento' => $data])
            ->assertSessionHasErrors(['periodo_inicio', 'vencimento']);
    }

    #[DataProvider('datasInvalidas')]
    public function test_contrato_inicio_retroativos_e_encerramento(string $data): void
    {
        $this->post('/contratos', ['data_inicio' => $data])->assertSessionHasErrors('data_inicio');
        $this->getJson('/contratos/retroativos?periodicidade=mensal&data_inicio='.$data)
            ->assertUnprocessable()->assertJsonValidationErrors('data_inicio');

        $contrato = Contrato::factory()->for($this->usuario)->create();
        $this->post("/contratos/{$contrato->id}/encerrar", ['fim' => $data])->assertSessionHasErrors('fim');
        $this->assertNull($contrato->fresh()->data_fim);
    }

    #[DataProvider('datasInvalidas')]
    public function test_feriado_de_data_unica_e_proximo_dia_util(string $data): void
    {
        $this->post('/perfil/feriados', ['descricao' => 'X', 'tipo' => 'unico', 'data' => $data])->assertSessionHasErrors('data');
        $this->assertSame(0, $this->usuario->feriados()->count());
        $this->getJson('/dias-uteis/proximo?data='.$data)->assertUnprocessable()->assertJsonValidationErrors('data');
    }

    public function test_data_mandada_como_lista_e_recusada_sem_erro_500(): void
    {
        $this->getJson('/cobrancas/dados?mes[]=x')->assertUnprocessable()->assertJsonValidationErrors('mes');
        $this->getJson('/auditoria/dados?de[]=x')->assertUnprocessable()->assertJsonValidationErrors('de');
        $this->getJson('/dias-uteis/proximo?data[]=x')->assertUnprocessable()->assertJsonValidationErrors('data');
    }

    #[DataProvider('anosInvalidos')]
    public function test_filtros_das_listas(string $data, string $mes): void
    {
        $this->getJson('/cobrancas/dados?mes='.$mes)->assertUnprocessable()->assertJsonValidationErrors('mes');
        $this->getJson('/auditoria/dados?de='.$data)->assertUnprocessable()->assertJsonValidationErrors('de');
        $this->getJson('/relatorios/faturamento/dados?de='.$mes)->assertUnprocessable()->assertJsonValidationErrors('de');
    }
}
