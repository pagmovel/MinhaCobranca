<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\ProdutoServico;

trait ComItens
{
    /** Um item novo no catálogo do dono do cliente, no formato que os formulários enviam. */
    protected function itensPara(Cliente $cliente, string $preco = '260,60', string $quantidade = '1'): array
    {
        $item = ProdutoServico::factory()->create(['user_id' => $cliente->user_id]);

        return [['produto_servico_id' => $item->id, 'quantidade' => $quantidade, 'preco' => $preco, 'principal' => true]];
    }
}
