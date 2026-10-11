<?php

namespace App\Models;

use App\Enums\TipoItem;
use Database\Factories\ProdutoServicoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'nome', 'tipo', 'unidade', 'preco_centavos', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional', 'ativo',
])]
class ProdutoServico extends Model
{
    /** @use HasFactory<ProdutoServicoFactory> */
    use HasFactory;

    protected $table = 'produtos_servicos';

    /** Mesmos defaults da migração (objeto recém-criado precisa deles antes de ser relido). */
    protected $attributes = ['tipo' => 'servico', 'unidade' => 'un', 'preco_centavos' => 0, 'ativo' => true];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'preco_centavos' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contratoItens(): HasMany
    {
        return $this->hasMany(ContratoItem::class);
    }
}
