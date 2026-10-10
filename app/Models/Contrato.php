<?php

namespace App\Models;

use App\Enums\Modalidade;
use App\Enums\Periodicidade;
use Database\Factories\ContratoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cliente_id', 'descricao', 'codigo_servico', 'codigo_nbs', 'codigo_tributacao_nacional',
    'valor_centavos', 'periodicidade', 'modalidade', 'dia_vencimento', 'data_inicio', 'data_fim', 'ativo',
])]
class Contrato extends Model
{
    /** @use HasFactory<ContratoFactory> */
    use HasFactory;

    /** Mesmo default da migração (objeto recém-criado precisa dele antes de ser relido). */
    protected $attributes = ['modalidade' => 'prepago'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'periodicidade' => Periodicidade::class,
            'modalidade' => Modalidade::class,
            'dia_vencimento' => 'integer',
            'data_inicio' => 'immutable_date',
            'data_fim' => 'immutable_date',
            'ativo' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(Cobranca::class);
    }
}
