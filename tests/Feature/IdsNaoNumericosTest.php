<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ComItens;
use Tests\TestCase;

/**
 * Id que não é número para na validação e nunca chega ao banco: no PostgreSQL, `where id = 'abc'` numa coluna
 * bigint é erro de SQL (500), não "não existe". O SQLite dos testes não reproduz o erro, então o teste confere
 * que nenhuma consulta recebe o texto.
 */
class IdsNaoNumericosTest extends TestCase
{
    use ComItens, LazilyRefreshDatabase;

    private const TEXTO = 'abc';

    /** @return list<string> */
    private function consultasComOTexto(callable $requisicao): array
    {
        $consultas = [];
        DB::listen(function (QueryExecuted $consulta) use (&$consultas) {
            if (in_array(self::TEXTO, $consulta->bindings, true)) {
                $consultas[] = $consulta->sql;
            }
        });

        $requisicao();

        return $consultas;
    }

    public function test_ids_nao_numericos_param_na_validacao(): void
    {
        $usuario = User::factory()->create();
        $cliente = Cliente::factory()->for($usuario)->create();
        $this->actingAs($usuario);

        $contrato = [
            'cliente_id' => $cliente->id, 'descricao' => 'Hospedagem', 'periodicidade' => 'mensal', 'modalidade' => 'prepago',
            'dia_vencimento' => 10, 'data_inicio' => '2026-10-01', 'ativo' => true, 'forma_pagamento' => 'nenhuma',
            'itens' => $this->itensPara($cliente),
        ];
        $avulsa = [
            'cliente_id' => $cliente->id, 'descricao' => 'Manutenção', 'periodo_inicio' => '2026-10-01',
            'periodo_fim' => '2026-10-31', 'vencimento' => '2026-10-20', 'forma_pagamento' => 'nenhuma',
            'itens' => $this->itensPara($cliente),
        ];

        $casos = [
            ['/contratos', ['itens' => [['produto_servico_id' => self::TEXTO, 'quantidade' => '1', 'preco' => '10,00']]] + $contrato, 'itens.0.produto_servico_id'],
            ['/contratos', ['cliente_id' => self::TEXTO] + $contrato, 'cliente_id'],
            ['/contratos', ['forma_pagamento' => 'pix', 'chave_pix_id' => self::TEXTO] + $contrato, 'chave_pix_id'],
            ['/cobrancas', ['cliente_id' => self::TEXTO] + $avulsa, 'cliente_id'],
            ['/cobrancas', ['itens' => [['produto_servico_id' => self::TEXTO, 'quantidade' => '1', 'preco' => '10,00']]] + $avulsa, 'itens.0.produto_servico_id'],
        ];

        foreach ($casos as [$url, $dados, $campo]) {
            $consultas = $this->consultasComOTexto(fn () => $this->post($url, $dados)->assertSessionHasErrors($campo));

            $this->assertSame([], $consultas, "{$url} {$campo}: o id não numérico chegou ao banco");
        }
    }
}
