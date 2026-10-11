<?php

namespace App\Models;

use App\Support\ItensDeVenda;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produto_servico_id', 'quantidade', 'preco_unitario_centavos', 'principal', 'ordem'])]
class ContratoItem extends Model
{
    protected $table = 'contrato_itens';

    /** Mesmos defaults da migração. */
    protected $attributes = ['principal' => false, 'ordem' => 0];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario_centavos' => 'integer',
            'principal' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function produtoServico(): BelongsTo
    {
        return $this->belongsTo(ProdutoServico::class);
    }

    public function total(): int
    {
        return ItensDeVenda::total(ItensDeVenda::milesimos($this->quantidade), $this->preco_unitario_centavos);
    }
}
