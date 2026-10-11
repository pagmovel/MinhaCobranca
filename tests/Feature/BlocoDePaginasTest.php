<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use App\Support\BlocoDePaginas;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BlocoDePaginasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bloco_de_quatro_paginas_a_partir_da_pedida_numa_consulta(): void
    {
        $usuario = User::factory()->create();
        Cliente::factory()->for($usuario)->count(30)
            ->sequence(fn ($sequencia) => ['nome' => sprintf('Cliente %02d', $sequencia->index + 1)])->create();

        $bloco = BlocoDePaginas::de($usuario->clientes()->orderBy('nome'), 2, 5);

        $this->assertSame(['inicio' => 2, 'por_pagina' => 5, 'total' => 30], $bloco['pagina']);
        $this->assertCount(20, $bloco['itens']);
        $this->assertSame(['Cliente 06', 'Cliente 25'], [$bloco['itens']->first()->nome, $bloco['itens']->last()->nome]);
    }

    public function test_pagina_alem_da_ultima_devolve_a_ultima(): void
    {
        $bloco = BlocoDePaginas::de(collect(range(1, 12)), 9, 5);

        $this->assertSame(['inicio' => 3, 'por_pagina' => 5, 'total' => 12], $bloco['pagina']);
        $this->assertSame([11, 12], $bloco['itens']->all());
    }

    public function test_lista_vazia_fica_na_pagina_1(): void
    {
        $this->assertSame(['inicio' => 1, 'por_pagina' => 25, 'total' => 0], BlocoDePaginas::de(collect(), 4, 25)['pagina']);
    }

    /** No PostgreSQL, count(*) com ORDER BY de coluna é erro (o SQLite deixa passar): a contagem sai sem ORDER BY. */
    public function test_contagem_sem_order_by(): void
    {
        $usuario = User::factory()->create();
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas) {
            $consultas[] = strtolower($consulta->sql);
        });

        BlocoDePaginas::de($usuario->clientes()->orderBy('nome'), 1, 25);

        $contagem = collect($consultas)->first(fn (string $sql) => str_contains($sql, 'count('));
        $this->assertNotNull($contagem);
        $this->assertStringNotContainsString('order by', $contagem);
    }

    public function test_regras_de_pagina_e_por_pagina(): void
    {
        $this->assertTrue(Validator::make(['pagina' => 2, 'por_pagina' => 50], BlocoDePaginas::regras())->passes());
        $this->assertFalse(Validator::make(['pagina' => 0], BlocoDePaginas::regras())->passes());
        $this->assertFalse(Validator::make(['por_pagina' => 10], BlocoDePaginas::regras())->passes());
        $this->assertTrue(Validator::make(['por_pagina' => 10], BlocoDePaginas::regras([10, 25, 50]))->passes());
    }
}
