<?php

namespace Tests\Feature\Cobrancas;

use App\Enums\StatusCobranca;
use App\Models\Cobranca;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SituacaoCobrancaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_situacao_depende_do_status_e_do_vencimento(): void
    {
        $hoje = CarbonImmutable::parse('2026-10-10');
        $cobranca = Cobranca::factory()->create(['vencimento' => '2026-10-10']);

        $this->assertSame('a_vencer', $cobranca->situacao($hoje->subDay()));
        $this->assertSame('vence_hoje', $cobranca->situacao($hoje), 'no dia do vencimento');
        $this->assertSame('vencida', $cobranca->situacao($hoje->addDay()));

        $cobranca->status = StatusCobranca::Paga;
        $this->assertSame('paga', $cobranca->situacao($hoje->addMonth()));

        $cobranca->status = StatusCobranca::Cancelada;
        $this->assertSame('cancelada', $cobranca->situacao($hoje));
    }

    public function test_editavel_so_pendente_e_nao_enviada(): void
    {
        $cobranca = Cobranca::factory()->create();
        $this->assertTrue($cobranca->editavel());

        $cobranca->enviado_em = now();
        $this->assertFalse($cobranca->editavel());
    }

    public function test_cobranca_nova_nasce_pendente(): void
    {
        $this->assertSame(StatusCobranca::Pendente, Cobranca::factory()->create()->status);
    }
}
