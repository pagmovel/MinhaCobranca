<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['produto_servico_id', 'nome', 'unidade', 'quantidade', 'preco_unitario_centavos', 'total_centavos', 'principal'])]
class CobrancaItem extends Model
{
    protected $table = 'cobranca_itens';

    /** Mesmo default da migração. */
    protected $attributes = ['principal' => false];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario_centavos' => 'integer',
            'total_centavos' => 'integer',
            'principal' => 'boolean',
        ];
    }

    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
